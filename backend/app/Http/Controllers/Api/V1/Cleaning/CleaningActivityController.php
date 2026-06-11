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

        // Get current shift (supports shifts crossing midnight)
        $currentTime = now()->format('H:i:s');
        $currentShift = \App\Models\Shift::active()
            ->where(function ($query) use ($currentTime) {
                $query->where(function ($q) use ($currentTime) {
                    $q->whereRaw('start_time <= end_time')
                        ->where('start_time', '<=', $currentTime)
                        ->where('end_time', '>', $currentTime);
                })->orWhere(function ($q) use ($currentTime) {
                    $q->whereRaw('start_time > end_time')
                        ->where(function ($sub) use ($currentTime) {
                            $sub->where('start_time', '<=', $currentTime)
                                ->orWhere('end_time', '>', $currentTime);
                        });
                });
            })
            ->first();

        // Check if already cleaned today for this shift
        $existingActivity = null;
        if ($currentShift) {
            $existingActivity = CleaningActivity::where('area_id', $area->id)
                ->where('shift_id', $currentShift->id)
                ->whereDate('date', today())
                ->where('status', ActivityStatus::COMPLETED)
                ->first();
        }

        return response()->json([
            'data' => [
                'area' => $area,
                'current_shift' => $currentShift,
                'already_cleaned' => $existingActivity !== null,
                'checklist' => $area->areaObjects->groupBy('room_name')->map(function ($items, $roomName) {
                    return [
                        'room_name' => $roomName ?: 'Umum',
                        'items' => $items->map(fn($ao) => [
                            'id' => $ao->id,
                            'name' => $ao->cleaningObject->name,
                            'icon' => $ao->cleaningObject->icon,
                            'is_required' => $ao->is_required,
                        ])->values(),
                    ];
                })->values(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'uuid' => 'sometimes|uuid',
            'area_id' => 'required|exists:areas,id',
            'shift_id' => 'required|exists:shifts,id',
            'date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.area_object_id' => 'required|exists:area_objects,id',
            'items.*.is_checked' => 'required|boolean',
            'device_id' => 'nullable|string',
            'status' => 'sometimes|string|in:in_progress,completed',
        ]);

        return DB::transaction(function () use ($request, $validated) {
            // Calculate lateness
            $isLate = false;
            $lateMinutes = 0;
            $scheduleId = null;

            $schedule = AreaSchedule::where('area_id', $validated['area_id'])
                ->where('shift_id', $validated['shift_id'])
                ->first();

            if ($schedule) {
                $scheduleId = $schedule->id;
                $scheduledTime = \Carbon\Carbon::parse($validated['date'] . ' ' . $schedule->scheduled_time->format('H:i'));
                $actualTime = \Carbon\Carbon::parse($validated['date'] . ' ' . $validated['start_time']);

                if ($actualTime->isAfter($scheduledTime->addMinutes($schedule->tolerance_minutes))) {
                    $isLate = true;
                    $lateMinutes = intval($actualTime->diffInMinutes($scheduledTime));
                }
            }

            $status = $request->input('status', 'completed') === 'in_progress'
                ? ActivityStatus::IN_PROGRESS
                : ActivityStatus::COMPLETED;

            // Check if there is an existing in_progress activity for this user, area, shift, date
            $activity = CleaningActivity::where('user_id', $request->user()->id)
                ->where('area_id', $validated['area_id'])
                ->where('shift_id', $validated['shift_id'])
                ->whereDate('date', $validated['date'])
                ->where('status', ActivityStatus::IN_PROGRESS)
                ->first();

            if ($activity) {
                $activity->update([
                    'end_time' => $validated['end_time'] ?? now()->format('H:i'),
                    'notes' => $validated['notes'],
                    'status' => $status,
                    'is_late' => $isLate,
                    'late_minutes' => $lateMinutes,
                    'submitted_at' => $status === ActivityStatus::COMPLETED ? now() : null,
                    'device_id' => $validated['device_id'],
                ]);
                // Recreate checklist items
                $activity->items()->delete();
            } else {
                $activity = CleaningActivity::create([
                    'uuid' => $validated['uuid'] ?? Str::uuid()->toString(),
                    'area_id' => $validated['area_id'],
                    'user_id' => $request->user()->id,
                    'shift_id' => $validated['shift_id'],
                    'schedule_id' => $scheduleId,
                    'date' => $validated['date'],
                    'start_time' => $validated['start_time'],
                    'end_time' => $validated['end_time'] ?? now()->format('H:i'),
                    'notes' => $validated['notes'],
                    'status' => $status,
                    'sync_status' => SyncStatus::SYNCED,
                    'is_late' => $isLate,
                    'late_minutes' => $lateMinutes,
                    'submitted_at' => $status === ActivityStatus::COMPLETED ? now() : null,
                    'device_id' => $validated['device_id'],
                ]);
            }

            // Create checklist items
            foreach ($validated['items'] as $item) {
                ActivityItem::create([
                    'cleaning_activity_id' => $activity->id,
                    'area_object_id' => $item['area_object_id'],
                    'is_checked' => $item['is_checked'],
                    'checked_at' => $item['is_checked'] ? now() : null,
                ]);
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
            'activities.*.shift_id' => 'required|exists:shifts,id',
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

                // Create activity
                $activity = CleaningActivity::create([
                    'uuid' => $actData['uuid'],
                    'area_id' => $actData['area_id'],
                    'user_id' => $request->user()->id,
                    'shift_id' => $actData['shift_id'],
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
}
