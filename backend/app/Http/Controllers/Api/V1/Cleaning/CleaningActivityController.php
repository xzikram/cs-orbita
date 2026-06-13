<?php

namespace App\Http\Controllers\Api\V1\Cleaning;

use App\Http\Controllers\Controller;
use App\Models\CleaningActivity;
use App\Models\ActivityItem;
use App\Models\ActivityPhoto;
use App\Models\Area;
use App\Models\AreaSchedule;
use App\Models\QrCode;
use App\Models\SyncLog;
use App\Models\Shift;
use App\Enums\ActivityStatus;
use App\Enums\SyncStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CleaningActivityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = CleaningActivity::with(['area.floor.building', 'shift', 'user', 'items.areaObject.cleaningObject', 'photos'])
            ->when($user->isCleaningService(), fn($q) => $q->where('user_id', $user->id))
            ->when($request->date, fn($q, $v) => $q->whereDate('date', $v))
            ->when($request->area_id, fn($q, $v) => $q->where('area_id', $v))
            ->when($request->shift_id, fn($q, $v) => $q->where('shift_id', $v))
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->when($request->approval_status, fn($q, $v) => $q->where('approval_status', $v))
            ->latest('date')
            ->latest('start_time');

        $activities = $request->has('per_page')
            ? $query->paginate($request->per_page)
            : $query->get();

        return response()->json(['data' => $activities]);
    }

    public function today(Request $request): JsonResponse
    {
        $user = $request->user();

        $activities = CleaningActivity::with(['area', 'shift', 'items.areaObject.cleaningObject', 'photos'])
            ->where('user_id', $user->id)
            ->whereDate('date', today())
            ->orderBy('start_time')
            ->get();

        return response()->json(['data' => $activities]);
    }

    public function show(CleaningActivity $activity): JsonResponse
    {
        $activity->load([
            'area.floor.building',
            'shift',
            'user',
            'items.areaObject.cleaningObject',
            'photos',
            'auditScore.auditor',
        ]);

        return response()->json(['data' => $activity]);
    }

    public function scanQr(string $uuid): JsonResponse
    {
        $qrCode = QrCode::where('uuid', $uuid)->active()->first();

        if (!$qrCode) {
            return response()->json([
                'message' => 'QR Code tidak valid atau sudah tidak aktif.',
            ], 404);
        }

        $area = $qrCode->area;
        $area->load(['areaObjects.cleaningObject', 'schedules.shift', 'floor.building']);

        $user = auth()->user();

        // Check if there's an existing activity for this user + area + today
        $existingActivity = null;
        if ($user) {
            $existingActivity = CleaningActivity::with('items')
                ->where('user_id', $user->id)
                ->where('area_id', $area->id)
                ->whereDate('date', today())
                ->first();
        }

        // Build checklist with previously checked items
        $checklist = $area->areaObjects->groupBy('room_name')->map(function ($items, $roomName) use ($existingActivity) {
            return [
                'room_name' => $roomName ?: 'Umum',
                'items' => $items->map(function ($ao) use ($existingActivity) {
                    $isChecked = false;
                    $checkedAt = null;
                    if ($existingActivity) {
                        $existingItem = $existingActivity->items
                            ->where('area_object_id', $ao->id)
                            ->first();
                        if ($existingItem && $existingItem->is_checked) {
                            $isChecked = true;
                            $checkedAt = $existingItem->checked_at?->toIso8601String();
                        }
                    }
                    return [
                        'id' => $ao->id,
                        'name' => $ao->cleaningObject->name,
                        'icon' => $ao->cleaningObject->icon,
                        'is_required' => $ao->is_required,
                        'is_checked' => $isChecked,
                        'checked_at' => $checkedAt,
                    ];
                })->values(),
            ];
        })->values();

        return response()->json([
            'data' => [
                'area' => $area,
                'existing_activity' => $existingActivity ? [
                    'id' => $existingActivity->id,
                    'start_time' => \Carbon\Carbon::parse($existingActivity->start_time)->format('H:i'),
                    'end_time' => $existingActivity->end_time ? \Carbon\Carbon::parse($existingActivity->end_time)->format('H:i') : null,
                    'notes' => $existingActivity->notes,
                ] : null,
                'can_continue' => $existingActivity !== null,
                'checklist' => $checklist,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'uuid' => 'sometimes|uuid',
            'area_id' => 'required|exists:areas,id',
            'shift_id' => 'nullable|exists:shifts,id',
            'date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.area_object_id' => 'required|exists:area_objects,id',
            'items.*.is_checked' => 'sometimes|boolean',
            'device_id' => 'nullable|string',
        ]);

        if (empty($validated['shift_id'])) {
            $validated['shift_id'] = $this->determineShiftId($validated['start_time']);
        }

        return DB::transaction(function () use ($request, $validated) {
            $scheduleId = null;
            if (!empty($validated['shift_id'])) {
                $schedule = AreaSchedule::where('area_id', $validated['area_id'])
                    ->where('shift_id', $validated['shift_id'])
                    ->first();
                if ($schedule) {
                    $scheduleId = $schedule->id;
                }
            }

            // Upsert: find existing activity by user + area + date, or by UUID
            $activity = CleaningActivity::where('user_id', $request->user()->id)
                ->where('area_id', $validated['area_id'])
                ->whereDate('date', $validated['date'])
                ->first();

            // Also check if UUID already exists (prevent duplicate key error)
            if (!$activity && !empty($validated['uuid'])) {
                $activity = CleaningActivity::where('uuid', $validated['uuid'])->first();
            }

            if ($activity) {
                // Update existing activity
                $activity->update([
                    'end_time' => $validated['end_time'] ?? now()->format('H:i'),
                    'notes' => $validated['notes'] ?? $activity->notes,
                    'status' => ActivityStatus::COMPLETED,
                    'is_late' => false,
                    'late_minutes' => 0,
                    'submitted_at' => now(),
                    'device_id' => $validated['device_id'] ?? $activity->device_id,
                ]);

                // Merge checklist items (preserve previously checked items)
                foreach ($validated['items'] as $item) {
                    $existingItem = $activity->items()
                        ->where('area_object_id', $item['area_object_id'])
                        ->first();

                    $isChecked = $item['is_checked'] ?? false;

                    if ($existingItem) {
                        // Update: if newly checked, set checked_at; if unchecked, clear it
                        $existingItem->update([
                            'is_checked' => $isChecked,
                            'checked_at' => $isChecked ? ($existingItem->is_checked ? $existingItem->checked_at : now()) : null,
                        ]);
                    } else {
                        // Create new item
                        ActivityItem::create([
                            'cleaning_activity_id' => $activity->id,
                            'area_object_id' => $item['area_object_id'],
                            'is_checked' => $isChecked,
                            'checked_at' => $isChecked ? now() : null,
                        ]);
                    }
                }
            } else {
                // Create new activity - always generate a fresh UUID
                $activity = CleaningActivity::create([
                    'uuid' => Str::uuid()->toString(),
                    'area_id' => $validated['area_id'],
                    'user_id' => $request->user()->id,
                    'shift_id' => $validated['shift_id'] ?? null,
                    'schedule_id' => $scheduleId,
                    'date' => $validated['date'],
                    'start_time' => $validated['start_time'],
                    'end_time' => $validated['end_time'] ?? now()->format('H:i'),
                    'notes' => $validated['notes'] ?? null,
                    'status' => ActivityStatus::COMPLETED,
                    'sync_status' => SyncStatus::SYNCED,
                    'is_late' => false,
                    'late_minutes' => 0,
                    'submitted_at' => now(),
                    'device_id' => $validated['device_id'] ?? null,
                ]);

                // Create checklist items
                foreach ($validated['items'] as $item) {
                    $isChecked = $item['is_checked'] ?? false;
                    ActivityItem::create([
                        'cleaning_activity_id' => $activity->id,
                        'area_object_id' => $item['area_object_id'],
                        'is_checked' => $isChecked,
                        'checked_at' => $isChecked ? now() : null,
                    ]);
                }
            }

            $activity->load(['area', 'shift', 'items.areaObject.cleaningObject', 'photos']);

            return response()->json([
                'message' => 'Aktivitas cleaning berhasil disimpan',
                'data' => $activity,
            ], 201);
        });
    }

    public function uploadPhotos(Request $request, CleaningActivity $activity): JsonResponse
    {
        $request->validate([
            'photos' => 'required|array|max:4',
            'photos.*.file' => 'required|image|max:5120',
            'photos.*.type' => 'required|in:before,after',
        ]);

        $uploaded = [];

        foreach ($request->photos as $photoData) {
            $file = $photoData['file'];
            $path = $file->store('cleaning-photos/' . $activity->date->format('Y/m'), 'public');

            $photo = ActivityPhoto::create([
                'cleaning_activity_id' => $activity->id,
                'type' => $photoData['type'],
                'file_path' => $path,
                'file_size' => $file->getSize(),
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'uploaded_at' => now(),
            ]);

            $uploaded[] = $photo;
        }

        return response()->json([
            'message' => count($uploaded) . ' foto berhasil diupload',
            'data' => $uploaded,
        ]);
    }

    // Batch sync endpoint for offline data
    public function batchSync(Request $request): JsonResponse
    {
        $request->validate([
            'activities' => 'required|array',
            'activities.*.uuid' => 'required|uuid',
            'activities.*.area_id' => 'required|exists:areas,id',
            'activities.*.shift_id' => 'nullable|exists:shifts,id',
            'activities.*.date' => 'required|date',
            'activities.*.start_time' => 'required',
            'activities.*.end_time' => 'nullable',
            'activities.*.notes' => 'nullable|string',
            'activities.*.items' => 'required|array',
            'device_id' => 'nullable|string',
        ]);

        $results = [];
        $syncedCount = 0;

        DB::beginTransaction();
        try {
            foreach ($request->activities as $actData) {
                // Skip if already synced (by UUID)
                $existing = CleaningActivity::where('uuid', $actData['uuid'])->first();
                if ($existing) {
                    $results[] = [
                        'uuid' => $actData['uuid'],
                        'status' => 'already_synced',
                        'id' => $existing->id,
                    ];
                    continue;
                }

                $shiftId = $actData['shift_id'] ?? null;
                if (empty($shiftId)) {
                    $shiftId = $this->determineShiftId($actData['start_time']);
                }

                // Create activity
                $activity = CleaningActivity::create([
                    'uuid' => $actData['uuid'],
                    'area_id' => $actData['area_id'],
                    'user_id' => $request->user()->id,
                    'shift_id' => $shiftId,
                    'date' => $actData['date'],
                    'start_time' => $actData['start_time'],
                    'end_time' => $actData['end_time'],
                    'notes' => $actData['notes'] ?? null,
                    'status' => ActivityStatus::COMPLETED,
                    'sync_status' => SyncStatus::SYNCED,
                    'submitted_at' => now(),
                    'device_id' => $request->device_id,
                ]);

                foreach ($actData['items'] as $item) {
                    ActivityItem::create([
                        'cleaning_activity_id' => $activity->id,
                        'area_object_id' => $item['area_object_id'],
                        'is_checked' => $item['is_checked'] ?? false,
                        'checked_at' => ($item['is_checked'] ?? false) ? now() : null,
                    ]);
                }

                $results[] = [
                    'uuid' => $actData['uuid'],
                    'status' => 'synced',
                    'id' => $activity->id,
                ];
                $syncedCount++;
            }

            DB::commit();

            // Log sync
            SyncLog::create([
                'user_id' => $request->user()->id,
                'device_id' => $request->device_id,
                'records_synced' => $syncedCount,
                'status' => 'success',
                'synced_at' => now(),
            ]);

            return response()->json([
                'message' => "{$syncedCount} aktivitas berhasil disinkronkan",
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            SyncLog::create([
                'user_id' => $request->user()->id,
                'device_id' => $request->device_id,
                'records_synced' => 0,
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'synced_at' => now(),
            ]);

            return response()->json([
                'message' => 'Sinkronisasi gagal',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function approve(Request $request, CleaningActivity $activity): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:approved,rejected',
        ]);

        $activity->update([
            'approval_status' => $request->status,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return response()->json([
            'message' => 'Status laporan berhasil diperbarui menjadi ' . $request->status,
            'data' => $activity
        ]);
    }

    private function determineShiftId(string $timeStr): int
    {
        if (strlen($timeStr) > 5) {
            $timeStr = substr($timeStr, 0, 5);
        }

        try {
            $shifts = \App\Models\Shift::active()->get();
            
            foreach ($shifts as $shift) {
                $start = $shift->start_time instanceof \Carbon\Carbon 
                    ? $shift->start_time->format('H:i') 
                    : substr((string)$shift->start_time, 0, 5);
                $end = $shift->end_time instanceof \Carbon\Carbon 
                    ? $shift->end_time->format('H:i') 
                    : substr((string)$shift->end_time, 0, 5);
                
                if (strlen($start) > 5) $start = substr($start, 0, 5);
                if (strlen($end) > 5) $end = substr($end, 0, 5);
                
                if ($start < $end) {
                    if ($timeStr >= $start && $timeStr < $end) {
                        return $shift->id;
                    }
                } else {
                    if ($timeStr >= $start || $timeStr < $end) {
                        return $shift->id;
                    }
                }
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("Error determining shift_id: " . $e->getMessage());
        }
        
        try {
            $firstShift = \App\Models\Shift::active()->ordered()->first();
            return $firstShift ? $firstShift->id : 1;
        } catch (\Exception $e) {
            return 1;
        }
    }
}
