<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLink;
use App\Models\AuditSession;
use App\Models\CleaningActivity;
use App\Models\AuditScore;
use App\Models\Area;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditAccessController extends Controller
{
    /**
     * Validate the public session token from headers or query params
     */
    private function validateSession(Request $request)
    {
        $token = $request->header('X-Audit-Session-Token') ?? $request->query('session_token');
        if (!$token) {
            return null;
        }

        return AuditSession::where('uuid', $token)
            ->where('status', 'approved')
            ->where(function($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
            })
            ->first();
    }

    // ==========================================
    // PUBLIC ENDPOINTS (AUDITOR GUESTS)
    // ==========================================

    /**
     * Get all active areas for the public drop-down
     */
    public function getAreas(Request $request)
    {
        $session = $this->validateSession($request);
        if (!$session) {
            return response()->json(['message' => 'Akses ditolak atau sesi kedaluwarsa.'], 403);
        }

        $areas = Area::active()->orderBy('name', 'asc')->get(['id', 'code', 'name']);
        return response()->json(['data' => $areas]);
    }

    /**
     * Get cleaning activities for the daily checklist preview
     */
    public function getActivities(Request $request)
    {
        $session = $this->validateSession($request);
        if (!$session) {
            return response()->json(['message' => 'Akses ditolak atau sesi kedaluwarsa.'], 403);
        }

        $request->validate([
            'area_id' => 'required|exists:areas,id',
        ]);

        $query = CleaningActivity::with(['user', 'items.areaObject.cleaningObject', 'photos', 'shift'])
            ->where('area_id', $request->area_id);

        if ($request->filled('date')) {
            $query->whereDate('date', $request->date);
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('date', [$request->start_date, $request->end_date]);
        }

        $activities = $query->orderBy('date', 'desc')
            ->orderBy('start_time', 'asc')
            ->get();

        $area = Area::find($request->area_id);
        $areaName = $area ? $area->name : 'Unknown';

        // Log the activity preview
        \App\Models\AuditAccessLog::create([
            'audit_session_id' => $session->id,
            'report_type' => 'Pratinjau Ceklist Harian',
            'details' => [
                'area_id' => $request->area_id,
                'area_name' => $areaName,
                'date' => $request->date,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
            ],
            'accessed_at' => now(),
        ]);

        return response()->json(['data' => $activities]);
    }

    /**
     * Verify if the link is valid and active
     */
    public function verifyLink($uuid)
    {
        $link = AuditLink::where('uuid', $uuid)->first();

        if (!$link) {
            return response()->json([
                'valid' => false,
                'message' => 'Tautan tidak ditemukan.'
            ], 404);
        }

        if (!$link->is_active) {
            return response()->json([
                'valid' => false,
                'message' => 'Tautan ini telah dinonaktifkan.'
            ], 400);
        }

        if ($link->expires_at && $link->expires_at->isPast()) {
            return response()->json([
                'valid' => false,
                'message' => 'Tautan ini sudah kedaluwarsa.'
            ], 400);
        }

        return response()->json([
            'valid' => true,
            'message' => 'Tautan aktif.',
            'link' => [
                'uuid' => $link->uuid,
                'expires_at' => $link->expires_at?->format('Y-m-d')
            ]
        ]);
    }

    /**
     * Request a temporary session
     */
    public function requestSession(Request $request, $uuid)
    {
        $link = AuditLink::where('uuid', $uuid)
            ->where('is_active', true)
            ->where(function($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>=', today());
            })
            ->first();

        if (!$link) {
            return response()->json([
                'message' => 'Tautan tidak valid atau sudah kedaluwarsa.'
            ], 400);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'unit' => 'required|string|max:255',
        ]);

        $session = AuditSession::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'audit_link_id' => $link->id,
            'name' => $request->name,
            'unit' => $request->unit,
            'status' => 'pending',
        ]);

        // Broadcast to admin panel for real-time notification
        broadcast(new \App\Events\AuditSessionRequested($session))->toOthers();

        return response()->json([
            'message' => 'Permintaan akses diajukan. Silakan tunggu persetujuan admin.',
            'session_uuid' => $session->uuid,
            'status' => $session->status
        ], 201);
    }

    /**
     * Check if session has been approved
     */
    public function checkSessionStatus($sessionUuid)
    {
        $session = AuditSession::where('uuid', $sessionUuid)->first();

        if (!$session) {
            return response()->json([
                'message' => 'Sesi tidak ditemukan.'
            ], 404);
        }

        return response()->json([
            'uuid' => $session->uuid,
            'status' => $session->status,
            'expires_at' => $session->expires_at?->toIso8601String(),
        ]);
    }

    /**
     * Log public report view
     */
    public function logAccess(Request $request)
    {
        $session = $this->validateSession($request);
        if (!$session) {
            return response()->json(['message' => 'Akses ditolak atau sesi kedaluwarsa.'], 403);
        }

        $request->validate([
            'report_type' => 'required|string',
            'details' => 'nullable|array',
        ]);

        $log = \App\Models\AuditAccessLog::create([
            'audit_session_id' => $session->id,
            'report_type' => $request->report_type,
            'details' => $request->details,
            'accessed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Log berhasil disimpan.',
            'log' => $log
        ], 201);
    }

    /**
     * Public monthly activities report (CSV)
     */
    public function exportMonthly(Request $request)
    {
        $session = $this->validateSession($request);
        if (!$session) {
            return response()->json(['message' => 'Akses ditolak atau sesi kedaluwarsa.'], 403);
        }

        $month = $request->query('month', date('m'));
        $year = $request->query('year', date('Y'));

        // Log the activity
        \App\Models\AuditAccessLog::create([
            'audit_session_id' => $session->id,
            'report_type' => 'Laporan Kebersihan Bulanan (CSV)',
            'details' => ['month' => $month, 'year' => $year],
            'accessed_at' => now(),
        ]);

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
            fputs($file, "\xEF\xBB\xBF");
            fputcsv($file, [
                'ID', 'Tanggal', 'Shift', 'Area', 'Petugas', 
                'Waktu Mulai', 'Waktu Selesai', 'Durasi (Menit)', 
                'Status', 'Catatan'
            ]);

            foreach ($activities as $act) {
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
                    $act->status?->label() ?? '-',
                    $act->notes ?? ''
                ]);
            }
            fclose($file);
        };

        return new StreamedResponse($callback, 200, $headers);
    }

    /**
     * Public monthly audit scores report (CSV)
     */
    public function exportAudit(Request $request)
    {
        $session = $this->validateSession($request);
        if (!$session) {
            return response()->json(['message' => 'Akses ditolak atau sesi kedaluwarsa.'], 403);
        }

        $month = $request->query('month', date('m'));
        $year = $request->query('year', date('Y'));

        // Log the activity
        \App\Models\AuditAccessLog::create([
            'audit_session_id' => $session->id,
            'report_type' => 'Laporan Audit Kebersihan (CSV)',
            'details' => ['month' => $month, 'year' => $year],
            'accessed_at' => now(),
        ]);

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

    /**
     * Public area checklist matrix (Excel XML)
     */
    public function exportMatrix(Request $request)
    {
        $session = $this->validateSession($request);
        if (!$session) {
            return response()->json(['message' => 'Akses ditolak atau sesi kedaluwarsa.'], 403);
        }

        $request->validate([
            'area_id' => 'required|exists:areas,id',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2000|max:2100',
        ]);

        $areaId = $request->area_id;
        $month = $request->month;
        $year = $request->year;

        $area = Area::with(['areaObjects.cleaningObject'])->findOrFail($areaId);

        // Log the activity
        \App\Models\AuditAccessLog::create([
            'audit_session_id' => $session->id,
            'report_type' => 'Matriks Ceklist Kebersihan (Excel)',
            'details' => ['area_id' => $areaId, 'area_name' => $area->name, 'month' => $month, 'year' => $year],
            'accessed_at' => now(),
        ]);

        $startDate = \Illuminate\Support\Carbon::create($year, $month, 1);
        $daysInMonth = $startDate->daysInMonth;

        $activities = CleaningActivity::with(['items.areaObject', 'shift'])
            ->where('area_id', $areaId)
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->get();

        $data = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $data[$d] = [1 => [], 2 => [], 3 => []];
        }

        foreach ($activities as $act) {
            $day = \Illuminate\Support\Carbon::parse($act->date)->day;
            $shiftOrder = $act->shift->sort_order ?? 1;
            
            foreach ($act->items as $item) {
                if ($item->is_checked) {
                    $data[$day][$shiftOrder][$item->area_object_id] = true;
                }
            }
        }

        $groupedObjects = $area->areaObjects->groupBy('room_name');
        $monthName = strtoupper($startDate->translatedFormat('F Y'));
        $areaName = strtoupper($area->name);

        $logoUrl = asset('Logo RS JEC ORBITA.png');
        $totalColumns = 3 + ($daysInMonth * 2);
        $titleColspan = $totalColumns - 2;
        $spacerColspan = max(1, ($daysInMonth * 2) - 5);

        // MSO-compatible inline styles (Excel requires mso-border-alt format)
        $bdr  = "border:.5pt solid windowtext; mso-border-alt:.5pt solid windowtext;";
        $nb   = "border:none; mso-border-alt:none;";
        $fs   = "font-family:Arial; font-size:10pt;";
        $ctr  = "text-align:center; vertical-align:middle;";
        $lft  = "text-align:left; vertical-align:middle;";
        $bld  = "font-weight:bold;";

        $html = "
        <html xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:x='urn:schemas-microsoft-com:office:excel' xmlns='http://www.w3.org/TR/REC-html40'>
        <head>
            <meta charset='utf-8'>
            <!--[if gte mso 9]><xml>
            <x:ExcelWorkbook>
                <x:ExcelWorksheets>
                    <x:ExcelWorksheet>
                        <x:Name>Ceklist</x:Name>
                        <x:WorksheetOptions>
                            <x:DisplayGridlines/>
                            <x:Print>
                                <x:ValidPrinterInfo/>
                                <x:PaperSizeIndex>9</x:PaperSizeIndex>
                                <x:HorizontalResolution>600</x:HorizontalResolution>
                                <x:VerticalResolution>600</x:VerticalResolution>
                            </x:Print>
                        </x:WorksheetOptions>
                    </x:ExcelWorksheet>
                </x:ExcelWorksheets>
            </x:ExcelWorkbook>
            </xml><![endif]-->
            <style>
                table { mso-displayed-decimal-separator:\".\"; mso-displayed-thousand-separator:\" \"; }
                td, th { mso-style-parent:style0; }
                .bd { border:.5pt solid windowtext; }
                .nb { border:none; }
            </style>
        </head>
        <body>
            <!-- ======== HEADER TABLE (no borders) ======== -->
            <table style='{$fs}'>
                <tr>
                    <td rowspan='4' style='{$nb} {$ctr} padding:5px;' width='120'>
                        <img src='{$logoUrl}' height='55' alt='JEC'>
                    </td>
                    <td colspan='{$titleColspan}' style='{$nb} {$ctr} {$bld} font-size:13pt;'>
                        CEKLIST KEBERSIHAN CLEANING SERVICE RS JEC ORBITA MAKASSAR
                    </td>
                </tr>
                <tr>
                    <td colspan='{$titleColspan}' style='{$nb} {$ctr} {$bld} font-size:11pt; color:red;'>
                        {$areaName}
                    </td>
                </tr>
                <tr><td colspan='{$titleColspan}' style='{$nb} height:5px;'>&nbsp;</td></tr>
                <tr><td colspan='{$titleColspan}' style='{$nb} height:5px;'>&nbsp;</td></tr>
                <tr>
                    <td colspan='2' style='{$nb} {$lft} {$bld} {$fs} border-bottom:.5pt solid windowtext;'>LOKASI : {$areaName}</td>
                </tr>
                <tr>
                    <td colspan='2' style='{$nb} {$lft} {$bld} {$fs} border-bottom:.5pt solid windowtext;'>PERIODE : {$monthName}</td>
                </tr>
            </table>
            <br>
            <!-- ======== DATA TABLE (with borders) ======== -->
            <table border='1' bordercolor='black' cellspacing='0' cellpadding='2' style='{$fs}'>
                <tr>
                    <th rowspan='3' class='bd' style='{$bdr} {$ctr} {$bld} {$fs}' width='30'>NO</th>
                    <th rowspan='3' class='bd' style='{$bdr} {$ctr} {$bld} {$fs}' width='100'>Area</th>
                    <th rowspan='3' class='bd' style='{$bdr} {$ctr} {$bld} {$fs}' width='120'>Area Dibersihkan</th>
                    <th colspan='" . ($daysInMonth * 2) . "' class='bd' style='{$bdr} {$ctr} {$bld} {$fs}'>TANGGAL</th>
                </tr>
                <tr>";

        // Day numbers row
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $html .= "<th colspan='2' class='bd' style='{$bdr} {$ctr} {$bld} {$fs}'>{$d}</th>";
        }
        $html .= "</tr><tr>";

        // Shift numbers row (1 | 2)
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $html .= "<th class='bd' style='{$bdr} {$ctr} {$bld} {$fs}'>1</th><th class='bd' style='{$bdr} {$ctr} {$bld} {$fs}'>2</th>";
        }
        $html .= "</tr>";

        // Data rows
        $no = 1;
        foreach ($groupedObjects as $roomName => $objects) {
            $roomNameDisplay = $roomName ?: 'Umum';
            $rowCount = $objects->count();
            $first = true;

            foreach ($objects as $obj) {
                $html .= "<tr>";
                if ($first) {
                    $html .= "<td rowspan='{$rowCount}' class='bd' style='{$bdr} {$ctr} {$fs}'>{$no}</td>";
                    $html .= "<td rowspan='{$rowCount}' class='bd' style='{$bdr} {$lft} {$fs}'>{$roomNameDisplay}</td>";
                    $first = false;
                    $no++;
                }
                $html .= "<td class='bd' style='{$bdr} {$lft} {$fs}'>" . strtolower($obj->cleaningObject->name) . "</td>";

                for ($d = 1; $d <= $daysInMonth; $d++) {
                    $chk1 = isset($data[$d][1][$obj->id]) ? '✓' : '&nbsp;';
                    $chk2 = isset($data[$d][2][$obj->id]) ? '✓' : '&nbsp;';
                    $html .= "<td class='bd' style='{$bdr} {$ctr} {$fs}'>{$chk1}</td><td class='bd' style='{$bdr} {$ctr} {$fs}'>{$chk2}</td>";
                }
                $html .= "</tr>";
            }
        }

        // PARAF CLEANING row
        $html .= "<tr><td colspan='3' class='bd' style='{$bdr} {$lft} {$bld} {$fs} background:#cceeff;'>PARAF CLEANING(Sign)</td>";
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $html .= "<td class='bd' style='{$bdr} {$fs} background:#cceeff;'>&nbsp;</td><td class='bd' style='{$bdr} {$fs} background:#cceeff;'>&nbsp;</td>";
        }
        $html .= "</tr>";

        // PARAF PJ UNIT row
        $html .= "<tr><td colspan='3' class='bd' style='{$bdr} {$lft} {$bld} {$fs} background:#ffcccc; color:red;'>PARAF PJ UNIT (Sign)</td>";
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $html .= "<td class='bd' style='{$bdr} {$fs} background:#ffcccc;'>&nbsp;</td><td class='bd' style='{$bdr} {$fs} background:#ffcccc;'>&nbsp;</td>";
        }
        $html .= "</tr></table>";

        // Footer table (legend & signatures, no borders)
        $html .= "
            <br>
            <table style='{$fs}'>
                <tr>
                    <td style='{$nb} {$lft} {$fs}' width='250'>
                        Ket : ✓ BERSIH<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;✗ KOTOR
                    </td>
                    <td style='{$nb}' width='400'>&nbsp;</td>
                    <td style='{$nb} {$ctr} {$bld} {$fs}'>PJ {$areaName}</td>
                </tr>
                <tr><td colspan='3' style='{$nb} height:40px;'>&nbsp;</td></tr>
                <tr>
                    <td style='{$nb} {$lft} {$bld} {$fs}'>(Housekeeping RS)</td>
                    <td style='{$nb}'>&nbsp;</td>
                    <td style='{$nb} {$ctr} {$bld} {$fs}'>..............................</td>
                </tr>
            </table>
        </body></html>";

        $filename = "Ceklist_{$areaName}_{$monthName}.xls";
        return response($html)
            ->header('Content-Type', 'application/vnd.ms-excel')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    // ==========================================
    // PROTECTED ENDPOINTS (ADMINS)
    // ==========================================

    /**
     * Get all active & inactive audit links
     */
    public function getLinks()
    {
        $links = AuditLink::with('creator:id,name')
            ->withCount(['sessions' => function($q) {
                $q->where('status', 'approved');
            }])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['data' => $links]);
    }

    /**
     * Generate a new temporary link
     */
    public function generateLink(Request $request)
    {
        $request->validate([
            'expires_at' => 'nullable|date|after_or_equal:today',
        ]);

        $link = AuditLink::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'expires_at' => $request->expires_at,
            'is_active' => true,
            'created_by' => auth()->id(),
        ]);

        // Reload relationship
        $link->load('creator:id,name');
        $link->sessions_count = 0;

        return response()->json([
            'message' => 'Tautan audit berhasil dibuat.',
            'link' => $link
        ], 201);
    }

    /**
     * Toggle status of an audit link
     */
    public function toggleLink($id)
    {
        $link = AuditLink::findOrFail($id);
        $link->is_active = !$link->is_active;
        $link->save();

        return response()->json([
            'message' => 'Status tautan berhasil diperbarui.',
            'link' => $link
        ]);
    }

    /**
     * Get all pending sessions
     */
    public function getPendingSessions()
    {
        $sessions = AuditSession::with('auditLink')
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['data' => $sessions]);
    }

    /**
     * Get all active (approved & unexpired) sessions
     */
    public function getActiveSessions()
    {
        $sessions = AuditSession::with('auditLink')
            ->where('status', 'approved')
            ->where(function($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
            })
            ->orderBy('approved_at', 'desc')
            ->get();

        return response()->json(['data' => $sessions]);
    }

    /**
     * Approve or reject an access request session
     */
    public function approveSession(Request $request, $id)
    {
        $session = AuditSession::findOrFail($id);

        $request->validate([
            'status' => 'required|in:approved,rejected',
        ]);

        $status = $request->status;

        $session->status = $status;
        if ($status === 'approved') {
            $session->approved_by = auth()->id();
            $session->approved_at = now();
            // Automatically expires at the end of the day (23:59:59)
            $session->expires_at = now()->endOfDay();
        }
        $session->save();

        // Broadcast to guest listener channel
        broadcast(new \App\Events\AuditSessionApproved($session))->toOthers();

        return response()->json([
            'message' => 'Permintaan sesi berhasil ' . ($status === 'approved' ? 'disetujui.' : 'ditolak.'),
            'session' => $session
        ]);
    }

    /**
     * Revoke / disconnect an approved auditor session
     */
    public function revokeSession($id)
    {
        $session = AuditSession::findOrFail($id);
        $session->status = 'rejected'; // or 'revoked'
        $session->expires_at = now();
        $session->save();

        // Broadcast to guest listener channel
        broadcast(new \App\Events\AuditSessionApproved($session))->toOthers();

        return response()->json([
            'message' => 'Sesi berhasil diputuskan / diputus.',
            'session' => $session
        ]);
    }

    /**
     * Get all auditor activity logs
     */
    public function getAccessLogs()
    {
        $logs = \App\Models\AuditAccessLog::with('auditSession.auditLink')
            ->orderBy('accessed_at', 'desc')
            ->get();

        return response()->json(['data' => $logs]);
    }
}
