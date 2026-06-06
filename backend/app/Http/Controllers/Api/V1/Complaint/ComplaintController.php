<?php

namespace App\Http\Controllers\Api\V1\Complaint;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\ComplaintPhoto;
use App\Models\ComplaintUpdate;
use App\Enums\ComplaintStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ComplaintController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Complaint::with(['area', 'reporter', 'assignee', 'photos'])
            ->when($user->isKepalaRuangan(), fn($q) => $q->where('reporter_id', $user->id))
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->when($request->area_id, fn($q, $v) => $q->where('area_id', $v))
            ->when($request->priority, fn($q, $v) => $q->where('priority', $v))
            ->latest();

        return response()->json($query->paginate($request->get('per_page', 20)));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'area_id' => 'required|exists:areas,id',
            'title' => 'required|string|max:255',
            'category' => 'required|string|max:50',
            'description' => 'required|string',
            'priority' => 'sometimes|in:low,medium,high,critical',
            'photos' => 'sometimes|array|max:4',
            'photos.*' => 'image|max:2048',
        ]);

        $slaHours = \App\Models\Setting::getValue('complaint_sla_hours', 24);

        $complaint = Complaint::create([
            'area_id' => $validated['area_id'],
            'reporter_id' => $request->user()->id,
            'title' => $validated['title'],
            'category' => $validated['category'],
            'description' => $validated['description'],
            'priority' => $validated['priority'] ?? 'medium',
            'status' => ComplaintStatus::OPEN,
            'sla_deadline' => now()->addHours($slaHours),
        ]);

        // Upload photos
        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $photo) {
                $path = $photo->store('complaint-photos/' . now()->format('Y/m'), 'public');
                ComplaintPhoto::create([
                    'complaint_id' => $complaint->id,
                    'file_path' => $path,
                ]);
            }
        }

        // Create initial update
        ComplaintUpdate::create([
            'complaint_id' => $complaint->id,
            'user_id' => $request->user()->id,
            'status_from' => null,
            'status_to' => ComplaintStatus::OPEN->value,
            'notes' => 'Komplain dibuat',
        ]);

        $complaint->load(['area', 'photos', 'updates']);

        return response()->json([
            'message' => 'Komplain berhasil dibuat',
            'data' => $complaint,
        ], 201);
    }

    public function show(Complaint $complaint): JsonResponse
    {
        $complaint->load(['area.floor.building', 'reporter', 'assignee', 'photos', 'updates.user']);

        return response()->json(['data' => $complaint]);
    }

    public function updateStatus(Request $request, Complaint $complaint): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:open,in_progress,resolved,closed',
            'notes' => 'nullable|string',
            'assignee_id' => 'sometimes|exists:users,id',
        ]);

        $oldStatus = $complaint->status->value;

        $complaint->update([
            'status' => $validated['status'],
            'assignee_id' => $validated['assignee_id'] ?? $complaint->assignee_id,
            'resolved_at' => in_array($validated['status'], ['resolved', 'closed']) ? now() : $complaint->resolved_at,
        ]);

        ComplaintUpdate::create([
            'complaint_id' => $complaint->id,
            'user_id' => $request->user()->id,
            'status_from' => $oldStatus,
            'status_to' => $validated['status'],
            'notes' => $validated['notes'],
        ]);

        return response()->json([
            'message' => 'Status komplain berhasil diperbarui',
            'data' => $complaint->load(['updates.user']),
        ]);
    }
}
