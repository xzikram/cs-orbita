<?php

namespace App\Http\Controllers\Api\V1\Master;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\Floor;
use App\Models\Area;
use App\Models\AreaObject;
use App\Models\CleaningObject;
use App\Models\Shift;
use App\Models\AreaSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MasterDataController extends Controller
{
    // ========== BUILDINGS ==========
    public function buildings(Request $request): JsonResponse
    {
        $buildings = Building::with('floors.areas')->active()->get();

        return response()->json(['data' => $buildings]);
    }

    public function storeBuilding(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:buildings,code',
            'name' => 'required|string|max:255',
            'address' => 'nullable|string',
        ]);

        $building = Building::create($validated);

        return response()->json([
            'message' => 'Gedung berhasil ditambahkan',
            'data' => $building,
        ], 201);
    }

    public function updateBuilding(Request $request, Building $building): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'sometimes|string|max:20|unique:buildings,code,' . $building->id,
            'name' => 'sometimes|string|max:255',
            'address' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        $building->update($validated);

        return response()->json([
            'message' => 'Gedung berhasil diperbarui',
            'data' => $building,
        ]);
    }

    public function deleteBuilding(Building $building): JsonResponse
    {
        $building->delete();

        return response()->json(['message' => 'Gedung berhasil dihapus']);
    }

    // ========== FLOORS ==========
    public function floors(Request $request): JsonResponse
    {
        $floors = Floor::with('building', 'areas')
            ->when($request->building_id, fn($q, $v) => $q->where('building_id', $v))
            ->active()
            ->get();

        return response()->json(['data' => $floors]);
    }

    public function storeFloor(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'building_id' => 'required|exists:buildings,id',
            'name' => 'required|string|max:255',
            'level_number' => 'required|integer',
        ]);

        $floor = Floor::create($validated);

        return response()->json([
            'message' => 'Lantai berhasil ditambahkan',
            'data' => $floor->load('building'),
        ], 201);
    }

    public function updateFloor(Request $request, Floor $floor): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'level_number' => 'sometimes|integer',
            'is_active' => 'sometimes|boolean',
        ]);

        $floor->update($validated);

        return response()->json([
            'message' => 'Lantai berhasil diperbarui',
            'data' => $floor,
        ]);
    }

    // ========== AREAS ==========
    public function areas(Request $request): JsonResponse
    {
        $areas = Area::with(['floor.building', 'areaObjects.cleaningObject', 'qrCode'])
            ->when($request->floor_id, fn($q, $v) => $q->where('floor_id', $v))
            ->when($request->category, fn($q, $v) => $q->where('category', $v))
            ->when($request->search, fn($q, $v) => $q->where('name', 'like', "%{$v}%"))
            ->active()
            ->get();

        return response()->json(['data' => $areas]);
    }

    public function showArea(Area $area): JsonResponse
    {
        $area->load(['floor.building', 'areaObjects.cleaningObject', 'qrCode', 'schedules.shift']);

        return response()->json(['data' => $area]);
    }

    public function storeArea(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'floor_id' => 'required|exists:floors,id',
            'code' => 'required|string|max:30|unique:areas,code',
            'name' => 'required|string|max:255',
            'category' => 'required|string|max:30',
            'description' => 'nullable|string',
            'objects' => 'sometimes|array',
            'objects.*.cleaning_object_id' => 'required_with:objects|exists:cleaning_objects,id',
            'objects.*.sort_order' => 'sometimes|integer',
            'objects.*.is_required' => 'sometimes|boolean',
        ]);

        $area = Area::create($validated);

        if ($request->has('objects')) {
            foreach ($request->objects as $idx => $obj) {
                AreaObject::create([
                    'area_id' => $area->id,
                    'cleaning_object_id' => $obj['cleaning_object_id'],
                    'sort_order' => $obj['sort_order'] ?? $idx + 1,
                    'is_required' => $obj['is_required'] ?? true,
                ]);
            }
        }

        return response()->json([
            'message' => 'Area berhasil ditambahkan',
            'data' => $area->load('areaObjects.cleaningObject'),
        ], 201);
    }

    public function updateArea(Request $request, Area $area): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'sometimes|string|max:30|unique:areas,code,' . $area->id,
            'name' => 'sometimes|string|max:255',
            'category' => 'sometimes|string|max:30',
            'description' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        $area->update($validated);

        return response()->json([
            'message' => 'Area berhasil diperbarui',
            'data' => $area,
        ]);
    }

    public function areaChecklist(Area $area): JsonResponse
    {
        $area->load(['areaObjects.cleaningObject', 'schedules.shift']);

        return response()->json([
            'data' => [
                'area' => [
                    'id' => $area->id,
                    'code' => $area->code,
                    'name' => $area->name,
                    'category' => $area->category,
                ],
                'checklist' => $area->areaObjects->groupBy('room_name')->map(function ($items, $roomName) {
                    return [
                        'room_name' => $roomName ?: 'Umum',
                        'items' => $items->map(fn($ao) => [
                            'id' => $ao->id,
                            'object_id' => $ao->cleaning_object_id,
                            'name' => $ao->cleaningObject->name,
                            'icon' => $ao->cleaningObject->icon,
                            'is_required' => $ao->is_required,
                            'sort_order' => $ao->sort_order,
                        ])->values(),
                    ];
                })->values(),
                'schedules' => $area->schedules->map(fn($s) => [
                    'id' => $s->id,
                    'shift' => $s->shift->name,
                    'shift_id' => $s->shift_id,
                    'time' => $s->scheduled_time,
                    'tolerance' => $s->tolerance_minutes,
                ]),
            ],
        ]);
    }

    // ========== CLEANING OBJECTS ==========
    public function cleaningObjects(): JsonResponse
    {
        $objects = CleaningObject::active()->get();

        return response()->json(['data' => $objects]);
    }

    public function storeCleaningObject(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:50',
        ]);

        $object = CleaningObject::create($validated);

        return response()->json([
            'message' => 'Objek cleaning berhasil ditambahkan',
            'data' => $object,
        ], 201);
    }

    // ========== SHIFTS ==========
    public function shifts(): JsonResponse
    {
        $shifts = Shift::active()->ordered()->get();

        return response()->json(['data' => $shifts]);
    }

    public function storeShift(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'sort_order' => 'sometimes|integer',
        ]);

        $shift = Shift::create($validated);

        return response()->json([
            'message' => 'Shift berhasil ditambahkan',
            'data' => $shift,
        ], 201);
    }

    // ========== SCHEDULES ==========
    public function schedules(Request $request): JsonResponse
    {
        $schedules = AreaSchedule::with(['area', 'shift'])
            ->when($request->area_id, fn($q, $v) => $q->where('area_id', $v))
            ->active()
            ->get();

        return response()->json(['data' => $schedules]);
    }

    public function storeSchedule(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'area_id' => 'required|exists:areas,id',
            'shift_id' => 'required|exists:shifts,id',
            'scheduled_time' => 'required|date_format:H:i',
            'tolerance_minutes' => 'sometimes|integer|min:0',
        ]);

        $schedule = AreaSchedule::create($validated);

        return response()->json([
            'message' => 'Jadwal berhasil ditambahkan',
            'data' => $schedule->load(['area', 'shift']),
        ], 201);
    }

    public function deleteSchedule(AreaSchedule $schedule): JsonResponse
    {
        $schedule->delete();

        return response()->json(['message' => 'Jadwal berhasil dihapus']);
    }
}
