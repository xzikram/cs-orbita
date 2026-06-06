<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Area;
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
        $today = today();

        $todayActivities = CleaningActivity::where('user_id', $user->id)
            ->whereDate('date', $today)
            ->get();

        return response()->json([
            'data' => [
                'today_total' => $todayActivities->count(),
                'today_completed' => $todayActivities->where('status', ActivityStatus::COMPLETED)->count(),
                'today_pending' => $todayActivities->where('status', ActivityStatus::PENDING)->count(),
                'pending_sync' => CleaningActivity::where('user_id', $user->id)
                    ->where('sync_status', 'pending')->count(),
                'assigned_areas' => $user->areas()->count(),
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
                'areas_late' => $lateActivities->pluck('area_id')->unique()->count(),
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

        $areas = Area::with(['floor.building'])
            ->active()
            ->get()
            ->map(function ($area) use ($today, $shifts) {
                $statuses = [];

                foreach ($shifts as $shift) {
                    $activity = CleaningActivity::where('area_id', $area->id)
                        ->where('shift_id', $shift->id)
                        ->whereDate('date', $today)
                        ->first();

                    if ($activity) {
                        if ($activity->status === ActivityStatus::COMPLETED) {
                            $statuses[$shift->id] = $activity->sync_status->value === 'synced' ? 'clean' : 'pending';
                        } else {
                            $statuses[$shift->id] = 'pending';
                        }
                    } else {
                        // Check if schedule exists and is overdue
                        $schedule = $area->schedules()->where('shift_id', $shift->id)->first();
                        if ($schedule && now()->format('H:i') > $schedule->scheduled_time->format('H:i')) {
                            $statuses[$shift->id] = 'late';
                        } else {
                            $statuses[$shift->id] = 'none';
                        }
                    }
                }

                return [
                    'id' => $area->id,
                    'code' => $area->code,
                    'name' => $area->name,
                    'category' => $area->category->value,
                    'floor' => $area->floor->name,
                    'building' => $area->floor->building->name,
                    'statuses' => $statuses,
                ];
            });

        return response()->json([
            'data' => [
                'shifts' => $shifts,
                'areas' => $areas,
            ],
        ]);
    }

    // Audit grid (Excel-like view for supervisor)
    public function auditGrid(Request $request): JsonResponse
    {
        $date = $request->get('date', today()->toDateString());
        $shifts = Shift::active()->ordered()->get();

        $areas = Area::with(['areaObjects.cleaningObject'])
            ->active()
            ->get()
            ->map(function ($area) use ($date, $shifts) {
                $objects = $area->areaObjects->map(function ($ao) use ($area, $date, $shifts) {
                    $shiftStatuses = [];

                    foreach ($shifts as $shift) {
                        $activity = CleaningActivity::where('area_id', $area->id)
                            ->where('shift_id', $shift->id)
                            ->whereDate('date', $date)
                            ->where('status', ActivityStatus::COMPLETED)
                            ->first();

                        if ($activity) {
                            $item = $activity->items()
                                ->where('area_object_id', $ao->id)
                                ->first();

                            $shiftStatuses[$shift->id] = $item ? $item->is_checked : false;
                        } else {
                            $shiftStatuses[$shift->id] = null; // no activity
                        }
                    }

                    return [
                        'object_name' => $ao->cleaningObject->name,
                        'shifts' => $shiftStatuses,
                    ];
                });

                return [
                    'area_id' => $area->id,
                    'area_name' => $area->name,
                    'area_code' => $area->code,
                    'objects' => $objects,
                ];
            });

        return response()->json([
            'data' => [
                'date' => $date,
                'shifts' => $shifts,
                'areas' => $areas,
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
                'late' => $late,
                'completion_rate' => $totalAreas > 0
                    ? round(($completed / $totalAreas) * 100, 1) : 0,
                'problem_areas' => $problemAreas,
                'recent_activities' => $recentActivities,
                'last_updated' => now()->format('H:i:s'),
            ],
        ]);
    }
}
