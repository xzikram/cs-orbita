<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CleaningActivity;
use App\Models\AuditScore;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    /**
     * Generate Monthly Cleaning Activities CSV Report
     */
    public function exportMonthly(Request $request)
    {
        $month = $request->query('month', date('m'));
        $year = $request->query('year', date('Y'));

        $activities = CleaningActivity::with(['user', 'area', 'shift'])
            ->whereMonth('date', $month)
            ->whereYear('date', $year)
            ->orderBy('date', 'asc')
            ->orderBy('start_time', 'asc')
            ->get();

        $filename = "Laporan_Kebersihan_{$year}_{$month}.csv";

        $headers = [
            'Content-type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=$filename",
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        $callback = function() use ($activities) {
            $file = fopen('php://output', 'w');
            
            // Add BOM to fix UTF-8 in Excel
            fputs($file, "\xEF\xBB\xBF");

            // CSV Headers
            fputcsv($file, [
                'ID', 'Tanggal', 'Shift', 'Area', 'Petugas', 
                'Waktu Mulai', 'Waktu Selesai', 'Durasi (Menit)', 
                'Status', 'SLA (Terlambat)', 'Catatan'
            ]);

            foreach ($activities as $act) {
                // Calculate duration
                $duration = '-';
                if ($act->start_time && $act->end_time) {
                    $start = \Carbon\Carbon::parse($act->start_time);
                    $end = \Carbon\Carbon::parse($act->end_time);
                    $duration = $end->diffInMinutes($start);
                }

                fputcsv($file, [
                    $act->id,
                    $act->date->format('Y-m-d'),
                    $act->shift->name ?? '-',
                    $act->area->name ?? '-',
                    $act->user->name ?? '-',
                    $act->start_time ?? '-',
                    $act->end_time ?? '-',
                    $duration,
                    $act->status,
                    $act->is_late ? 'Ya' : 'Tidak',
                    $act->notes ?? ''
                ]);
            }

            fclose($file);
        };

        return new StreamedResponse($callback, 200, $headers);
    }

    /**
     * Generate Audit Report CSV
     */
    public function exportAudit(Request $request)
    {
        $month = $request->query('month', date('m'));
        $year = $request->query('year', date('Y'));

        $audits = AuditScore::with(['cleaningActivity.area', 'auditor', 'findings'])
            ->whereMonth('audited_at', $month)
            ->whereYear('audited_at', $year)
            ->orderBy('audited_at', 'asc')
            ->get();

        $filename = "Laporan_Audit_Kebersihan_{$year}_{$month}.csv";

        $headers = [
            'Content-type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=$filename",
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        $callback = function() use ($audits) {
            $file = fopen('php://output', 'w');
            fputs($file, "\xEF\xBB\xBF");

            fputcsv($file, [
                'ID Audit', 'Tanggal Audit', 'Area', 'Auditor', 
                'Skor', 'Status', 'Temuan / Catatan'
            ]);

            foreach ($audits as $audit) {
                $findingsCount = $audit->findings->count();
                $findingsText = $findingsCount > 0 ? "{$findingsCount} temuan" : "Tidak ada";
                
                fputcsv($file, [
                    $audit->id,
                    $audit->audited_at ? $audit->audited_at->format('Y-m-d') : '-',
                    $audit->cleaningActivity->area->name ?? '-',
                    $audit->auditor->name ?? '-',
                    $audit->total_score,
                    $audit->status,
                    $audit->notes ?? $findingsText
                ]);
            }

            fclose($file);
        };

        return new StreamedResponse($callback, 200, $headers);
    }
}
