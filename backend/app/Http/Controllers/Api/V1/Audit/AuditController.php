<?php

namespace App\Http\Controllers\Api\V1\Audit;

use App\Http\Controllers\Controller;
use App\Models\AuditScore;
use App\Models\AuditFinding;
use App\Models\CleaningActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuditController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $audits = AuditScore::with(['cleaningActivity.area', 'cleaningActivity.user', 'auditor', 'findings'])
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->when($request->date_from, fn($q, $v) => $q->whereHas('cleaningActivity', fn($qa) => $qa->where('date', '>=', $v)))
            ->when($request->date_to, fn($q, $v) => $q->whereHas('cleaningActivity', fn($qa) => $qa->where('date', '<=', $v)))
            ->latest('audited_at')
            ->paginate($request->get('per_page', 20));

        return response()->json($audits);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cleaning_activity_id' => 'required|exists:cleaning_activities,id',
            'kebersihan_score' => 'required|integer|min:0|max:100',
            'kerapihan_score' => 'required|integer|min:0|max:100',
            'kepatuhan_sop_score' => 'required|integer|min:0|max:100',
            'notes' => 'nullable|string',
            'findings' => 'sometimes|array',
            'findings.*.category' => 'required_with:findings|string',
            'findings.*.description' => 'required_with:findings|string',
        ]);

        return DB::transaction(function () use ($request, $validated) {
            $activity = CleaningActivity::findOrFail($validated['cleaning_activity_id']);

            $audit = AuditScore::create([
                'cleaning_activity_id' => $validated['cleaning_activity_id'],
                'auditor_id' => $request->user()->id,
                'kebersihan_score' => $validated['kebersihan_score'],
                'kerapihan_score' => $validated['kerapihan_score'],
                'kepatuhan_sop_score' => $validated['kepatuhan_sop_score'],
                'notes' => $validated['notes'],
                'audited_at' => now(),
            ]);

            // Auto-create findings if score < 80
            if (!$audit->isPassed()) {
                $categories = ['kebersihan', 'kerapihan', 'kepatuhan_sop'];
                $scores = [
                    $validated['kebersihan_score'],
                    $validated['kerapihan_score'],
                    $validated['kepatuhan_sop_score'],
                ];

                foreach ($categories as $idx => $cat) {
                    if ($scores[$idx] < 80) {
                        AuditFinding::create([
                            'audit_score_id' => $audit->id,
                            'area_id' => $activity->area_id,
                            'category' => $cat,
                            'description' => "Skor {$cat} di bawah standar: {$scores[$idx]}/100",
                        ]);
                    }
                }
            }

            // Also add manual findings
            if ($request->has('findings')) {
                foreach ($request->findings as $finding) {
                    AuditFinding::create([
                        'audit_score_id' => $audit->id,
                        'area_id' => $activity->area_id,
                        'category' => $finding['category'],
                        'description' => $finding['description'],
                    ]);
                }
            }

            $audit->load(['cleaningActivity.area', 'findings']);

            return response()->json([
                'message' => 'Audit berhasil disimpan',
                'data' => $audit,
            ], 201);
        });
    }

    public function findings(Request $request): JsonResponse
    {
        $findings = AuditFinding::with(['auditScore.cleaningActivity.user', 'area'])
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->when($request->area_id, fn($q, $v) => $q->where('area_id', $v))
            ->latest()
            ->paginate($request->get('per_page', 20));

        return response()->json($findings);
    }

    public function resolveFinding(AuditFinding $finding): JsonResponse
    {
        $finding->update([
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);

        return response()->json([
            'message' => 'Temuan berhasil diselesaikan',
            'data' => $finding,
        ]);
    }
}
