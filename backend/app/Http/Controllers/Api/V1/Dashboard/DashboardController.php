<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\AreaSchedule;
use App\Models\CleaningActivity;
use App\Models\Complaint;
use App\Models\AuditScore;
use App\Models\Shift;
use App\Enums\ActivityStatus;
use App\Enums\ComplaintStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    // Mobile dashboard for cleaning service
    public function mobile(Request $request): JsonResponse
    {
        $user = $request->user();
        $todayStr = now()->toDateString();

        $assignedAreaIds = $user->areas()->pluck('areas.id');

        // Get all active schedules for the user's assigned areas
        $schedules = AreaSchedule::whereIn('area_id', $assignedAreaIds)
            ->where('is_active', true)
            ->get();

        $todayTotal = $schedules->count();

        // Count how many activities are completed today by this user
        $todayCompleted = CleaningActivity::where('user_id', $user->id)
            ->where('date', $todayStr)
            ->where('status', ActivityStatus::COMPLETED)
            ->count();

        if ($todayTotal === 0) {
            // Fallback: if no schedules exist, use assigned areas count
            $todayTotal = $assignedAreaIds->count();
        }

        // Ensure total tasks is at least the number of completed tasks today
        $todayTotal = max($todayTotal, $todayCompleted);

        // Count how many activities are still in progress today (started but not completed)
        $todayPending = CleaningActivity::where('user_id', $user->id)
            ->where('date', $todayStr)
            ->where('status', ActivityStatus::IN_PROGRESS)
            ->count();

        return response()->json([
            'data' => [
                'today_total' => $todayTotal,
                'today_completed' => $todayCompleted,
                'today_pending' => $todayPending,
                'pending_sync' => CleaningActivity::where('user_id', $user->id)
                    ->where('sync_status', 'pending')->count(),
                'assigned_areas' => $assignedAreaIds->count(),
            ],
        ]);
    }

    // Supervisor command center
    public function supervisor(Request $request): JsonResponse
    {
        $today = today();
        $totalAreas = Area::active()->count();

        $todayActivities = CleaningActivity::whereDate('date', $today)->get();

        $completedAreaIds = $todayActivities
            ->where('status', ActivityStatus::COMPLETED)
            ->pluck('area_id')
            ->unique();

        $lateActivities = $todayActivities->where('is_late', true);

        // Recent activities (last 20)
        $recentActivities = CleaningActivity::with(['user', 'area', 'shift'])
            ->whereDate('date', $today)
            ->latest('submitted_at')
            ->take(20)
            ->get()
            ->map(fn($a) => [
                'id' => $a->id,
                'time' => $a->submitted_at?->format('H:i'),
                'user' => $a->user->name,
                'area' => $a->area->name,
                'shift' => $a->shift->name,
                'status' => $a->status->value,
                'is_late' => $a->is_late,
            ]);

        return response()->json([
            'data' => [
                'total_areas' => $totalAreas,
                'total_activities' => $todayActivities->count(),
                'areas_completed' => $completedAreaIds->count(),
                'areas_pending' => $totalAreas - $completedAreaIds->count(),
                'areas_late' => 0,
                'completion_rate' => $totalAreas > 0
                    ? round(($completedAreaIds->count() / $totalAreas) * 100, 1) : 0,
                'recent_activities' => $recentActivities,
            ],
        ]);
    }

    // Area heatmap data
    public function heatmap(Request $request): JsonResponse
    {
        $today = today();
        $shifts = Shift::active()->ordered()->get();

        // Pre-load all today's activities to avoid N+1
        $todayActivities = CleaningActivity::whereDate('date', $today)
            ->get()
            ->groupBy(function ($activity) {
                return $activity->area_id . '-' . $activity->shift_id;
            });

        // Pre-load all schedules
        $allSchedules = \App\Models\AreaSchedule::all()
            ->groupBy(function ($schedule) {
                return $schedule->area_id . '-' . $schedule->shift_id;
            });

        $currentTime = now()->format('H:i');

        // Summary counters
        $summaryClean = 0;
        $summaryPending = 0;
        $summaryLate = 0;
        $summaryNone = 0;

        $areas = Area::with(['floor.building'])
            ->active()
            ->get()
            ->map(function ($area) use ($today, $shifts, $todayActivities, $allSchedules, $currentTime, &$summaryClean, &$summaryPending, &$summaryLate, &$summaryNone) {
                $statuses = [];

                foreach ($shifts as $shift) {
                    $key = $area->id . '-' . $shift->id;
                    $activity = $todayActivities->get($key)?->first();

                    if ($activity) {
                        if ($activity->status === ActivityStatus::COMPLETED) {
                            $statuses[$shift->id] = 'clean';
                            $summaryClean++;
                        } elseif ($activity->status === ActivityStatus::IN_PROGRESS) {
                            $statuses[$shift->id] = 'pending';
                            $summaryPending++;
                        } else {
                            $statuses[$shift->id] = 'pending';
                            $summaryPending++;
                        }
                    } else {
                        $statuses[$shift->id] = 'none';
                        $summaryNone++;
                    }
                }

                return [
                    'id' => $area->id,
                    'code' => $area->code,
                    'name' => $area->name,
                    'category' => $area->category?->value ?? '-',
                    'floor' => $area->floor?->name ?? '-',
                    'building' => $area->floor?->building?->name ?? '-',
                    'statuses' => $statuses,
                ];
            });

        return response()->json([
            'data' => [
                'shifts' => $shifts,
                'areas' => $areas,
                'summary' => [
                    'total_cells' => $summaryClean + $summaryPending + $summaryLate + $summaryNone,
                    'clean' => $summaryClean,
                    'pending' => $summaryPending,
                    'late' => $summaryLate,
                    'none' => $summaryNone,
                    'total_areas' => $areas->count(),
                ],
            ],
        ]);
    }

    // Audit grid (Excel-like view for supervisor) — Optimized
    public function auditGrid(Request $request): JsonResponse
    {
        $date = $request->get('date', today()->toDateString());
        $shifts = Shift::active()->ordered()->get();

        // Pre-load ALL completed activities for this date with their items
        $allActivities = CleaningActivity::with(['items'])
            ->whereDate('date', $date)
            ->where('status', ActivityStatus::COMPLETED)
            ->get()
            ->groupBy(function ($activity) {
                return $activity->area_id . '-' . $activity->shift_id;
            });

        $areas = Area::with(['areaObjects.cleaningObject'])
            ->active()
            ->get()
            ->map(function ($area) use ($date, $shifts, $allActivities) {
                $totalObjects = $area->areaObjects->count();
                $totalChecked = 0;
                $totalCells = 0;

                $objects = $area->areaObjects->map(function ($ao) use ($area, $date, $shifts, $allActivities, &$totalChecked, &$totalCells) {
                    $shiftStatuses = [];

                    foreach ($shifts as $shift) {
                        $key = $area->id . '-' . $shift->id;
                        $activity = $allActivities->get($key)?->first();

                        if ($activity) {
                            $item = $activity->items->where('area_object_id', $ao->id)->first();
                            $isChecked = $item ? $item->is_checked : false;
                            $shiftStatuses[$shift->id] = $isChecked;
                            $totalCells++;
                            if ($isChecked) $totalChecked++;
                        } else {
                            $shiftStatuses[$shift->id] = null; // no activity
                        }
                    }

                    return [
                        'object_name' => $ao->cleaningObject->name ?? '-',
                        'shifts' => $shiftStatuses,
                    ];
                });

                $completionRate = $totalCells > 0 ? round(($totalChecked / $totalCells) * 100, 1) : 0;

                return [
                    'area_id' => $area->id,
                    'area_name' => $area->name,
                    'area_code' => $area->code,
                    'total_objects' => $totalObjects,
                    'total_checked' => $totalChecked,
                    'total_cells' => $totalCells,
                    'completion_rate' => $completionRate,
                    'objects' => $objects,
                ];
            });

        // Global stats
        $globalTotalCells = $areas->sum('total_cells');
        $globalChecked = $areas->sum('total_checked');
        $globalRate = $globalTotalCells > 0 ? round(($globalChecked / $globalTotalCells) * 100, 1) : 0;

        return response()->json([
            'data' => [
                'date' => $date,
                'shifts' => $shifts,
                'areas' => $areas,
                'summary' => [
                    'total_areas' => $areas->count(),
                    'total_objects' => $areas->sum('total_objects'),
                    'total_checked' => $globalChecked,
                    'total_cells' => $globalTotalCells,
                    'completion_rate' => $globalRate,
                ],
            ],
        ]);
    }

    // Management KPI dashboard
    public function kpi(Request $request): JsonResponse
    {
        $startDate = $request->get('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->get('end_date', now()->toDateString());

        $totalActivities = CleaningActivity::whereBetween('date', [$startDate, $endDate])->count();
        $completedActivities = CleaningActivity::whereBetween('date', [$startDate, $endDate])
            ->where('status', ActivityStatus::COMPLETED)->count();
        $lateActivities = CleaningActivity::whereBetween('date', [$startDate, $endDate])
            ->where('is_late', true)->count();

        $avgAuditScore = AuditScore::whereHas('cleaningActivity', fn($q) =>
            $q->whereBetween('date', [$startDate, $endDate])
        )->avg('total_score');

        $complaints = Complaint::whereBetween('created_at', [$startDate, $endDate])->count();
        $resolvedComplaints = Complaint::whereBetween('created_at', [$startDate, $endDate])
            ->whereIn('status', [ComplaintStatus::RESOLVED, ComplaintStatus::CLOSED])->count();

        // Daily trend
        $dailyTrend = CleaningActivity::whereBetween('date', [$startDate, $endDate])
            ->select(
                DB::raw('DATE(date) as day'),
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed'),
                DB::raw('SUM(CASE WHEN is_late = 1 THEN 1 ELSE 0 END) as late')
            )
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        // Top Staff Leaderboard
        $topStaff = CleaningActivity::whereBetween('date', [$startDate, $endDate])
            ->where('status', ActivityStatus::COMPLETED)
            ->select('user_id', DB::raw('COUNT(*) as completed_count'))
            ->groupBy('user_id')
            ->orderByDesc('completed_count')
            ->with('user:id,name,employee_id')
            ->take(5)
            ->get()
            ->map(fn($item) => [
                'name' => $item->user->name ?? 'Staf Tidak Dikenal',
                'employee_id' => $item->user->employee_id ?? '-',
                'completed' => $item->completed_count
            ]);

        // Bottom Areas Leaderboard
        $areaStats = CleaningActivity::whereBetween('date', [$startDate, $endDate])
            ->select(
                'area_id',
                DB::raw('COUNT(*) as total_count'),
                DB::raw('SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed_count')
            )
            ->groupBy('area_id')
            ->get();

        $bottomAreas = $areaStats->map(function ($item) {
            $rate = $item->total_count > 0 ? round(($item->completed_count / $item->total_count) * 100, 1) : 0;
            return [
                'area_id' => $item->area_id,
                'total' => $item->total_count,
                'completed' => $item->completed_count,
                'rate' => $rate
            ];
        })
        ->sortBy('rate')
        ->take(5)
        ->values();

        $areaIds = $bottomAreas->pluck('area_id');
        $areas = Area::whereIn('id', $areaIds)->get(['id', 'name', 'code'])->keyBy('id');

        $bottomAreasFormatted = $bottomAreas->map(fn($item) => [
            'name' => $areas[$item['area_id']]->name ?? 'Area Tidak Dikenal',
            'code' => $areas[$item['area_id']]->code ?? '-',
            'rate' => $item['rate'],
            'completed' => $item['completed'],
            'total' => $item['total']
        ]);

        return response()->json([
            'data' => [
                'period' => ['start' => $startDate, 'end' => $endDate],
                'total_activities' => $totalActivities,
                'completed_activities' => $completedActivities,
                'completion_rate' => $totalActivities > 0
                    ? round(($completedActivities / $totalActivities) * 100, 1) : 0,
                'late_activities' => $lateActivities,
                'sla_compliance' => $totalActivities > 0
                    ? round((($totalActivities - $lateActivities) / $totalActivities) * 100, 1) : 0,
                'avg_audit_score' => round($avgAuditScore ?? 0, 1),
                'total_complaints' => $complaints,
                'resolved_complaints' => $resolvedComplaints,
                'complaint_resolution_rate' => $complaints > 0
                    ? round(($resolvedComplaints / $complaints) * 100, 1) : 0,
                'daily_trend' => $dailyTrend,
                'top_staff' => $topStaff,
                'bottom_areas' => $bottomAreasFormatted,
            ],
        ]);
    }

    // Smart TV dashboard
    public function tv(Request $request): JsonResponse
    {
        $today = today();
        $totalAreas = Area::active()->count();

        $todayActivities = CleaningActivity::with(['user', 'area'])
            ->whereDate('date', $today)
            ->get();

        $completed = $todayActivities->where('status', ActivityStatus::COMPLETED)
            ->pluck('area_id')->unique()->count();
        $late = $todayActivities->where('is_late', true)
            ->pluck('area_id')->unique()->count();

        // Problem areas (late or uncleaned)
        $problemAreas = Area::active()
            ->whereDoesntHave('cleaningActivities', fn($q) =>
                $q->whereDate('date', $today)->where('status', ActivityStatus::COMPLETED)
            )
            ->take(10)
            ->get(['id', 'code', 'name', 'category']);

        // Last 10 activities
        $recentActivities = CleaningActivity::with(['user', 'area'])
            ->whereDate('date', $today)
            ->latest('submitted_at')
            ->take(10)
            ->get()
            ->map(fn($a) => [
                'time' => $a->submitted_at?->format('H:i'),
                'user' => $a->user->name,
                'area' => $a->area->name,
                'status' => $a->status->value,
                'is_late' => $a->is_late,
            ]);

        return response()->json([
            'data' => [
                'total_areas' => $totalAreas,
                'completed' => $completed,
                'pending' => $totalAreas - $completed,
                'late' => 0,
                'total_activities' => $todayActivities->count(),
                'completion_rate' => $totalAreas > 0
                    ? round(($completed / $totalAreas) * 100, 1) : 0,
                'problem_areas' => $problemAreas,
                'recent_activities' => $recentActivities,
                'last_updated' => now()->format('H:i:s'),
            ],
        ]);
    }
}
