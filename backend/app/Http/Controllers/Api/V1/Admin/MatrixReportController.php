<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\CleaningActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class MatrixReportController extends Controller
{
    public function export(Request $request)
    {
        $request->validate([
            'area_id' => 'required|exists:areas,id',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2000|max:2100',
        ]);

        $areaId = $request->area_id;
        $month = $request->month;
        $year = $request->year;

        $area = Area::with(['areaObjects.cleaningObject'])->findOrFail($areaId);
        
        $startDate = Carbon::create($year, $month, 1);
        $daysInMonth = $startDate->daysInMonth;

        // Fetch all activities for this area and month
        $activities = CleaningActivity::with(['items.areaObject', 'shift'])
            ->where('area_id', $areaId)
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->get();

        // Map activities to a structured array: [day][shift_order][area_object_id] => is_checked
        $data = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $data[$d] = [1 => [], 2 => [], 3 => []]; // Shifts 1, 2, 3
        }

        foreach ($activities as $act) {
            $day = Carbon::parse($act->date)->day;
            $shiftOrder = $act->shift->sort_order ?? 1;
            
            foreach ($act->items as $item) {
                if ($item->is_checked) {
                    $data[$day][$shiftOrder][$item->area_object_id] = true;
                }
            }
        }

        // Group objects by room
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
}

