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
        $metaColspan = $totalColumns - 3;
        $spacerColspan = ($daysInMonth * 2) - 5;

        $html = "
        <html xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:x='urn:schemas-microsoft-com:office:excel' xmlns='http://www.w3.org/TR/REC-html40'>
        <head>
            <meta charset='utf-8'>
            <style>
                table { border-collapse: collapse; font-family: Arial, sans-serif; font-size: 10pt; }
                th, td { border: 1px solid black; padding: 3px; text-align: center; }
                .text-left { text-align: left; }
                .text-center { text-align: center; }
                .bold { font-weight: bold; }
                .header-title { font-size: 13pt; font-weight: bold; text-align: center; }
                .subtitle { font-size: 11pt; font-weight: bold; color: red; text-align: center; }
            </style>
        </head>
        <body>
            <table border='1'>
                <thead>
                    <!-- Logo and Title Row -->
                    <tr>
                        <td rowspan='3' colspan='2' style='background-color: white; border: 1px solid black; text-align: center; vertical-align: middle;'>
                            <img src='{$logoUrl}' height='50' width='120' alt='JEC Logo'>
                        </td>
                        <td colspan='{$titleColspan}' class='header-title' style='height: 25px; vertical-align: middle;'>
                            CEKLIST KEBERSIHAN CLEANING SERVICE RS JEC ORBITA MAKASSAR
                        </td>
                    </tr>
                    <tr>
                        <td colspan='{$titleColspan}' class='subtitle' style='height: 20px; vertical-align: middle;'>
                            {$areaName}
                        </td>
                    </tr>
                    <tr>
                        <td colspan='{$titleColspan}' style='height: 15px;'>&nbsp;</td>
                    </tr>
                    <!-- Meta info Row 4 & 5 -->
                    <tr>
                        <td colspan='3' style='border: none; border-bottom: 1px solid black;' class='text-left bold'>LOKASI : {$areaName}</td>
                        <td colspan='{$metaColspan}' style='border: none; border-bottom: 1px solid black;'>&nbsp;</td>
                    </tr>
                    <tr>
                        <td colspan='3' style='border: none; border-bottom: 1px solid black;' class='text-left bold'>PERIODE : {$monthName}</td>
                        <td colspan='{$metaColspan}' style='border: none; border-bottom: 1px solid black;'>&nbsp;</td>
                    </tr>
                    <!-- Spacer Row -->
                    <tr>
                        <td colspan='{$totalColumns}' style='border: none; height: 10px;'>&nbsp;</td>
                    </tr>
                    <!-- Main headers -->
                    <tr>
                        <th rowspan='3' width='30'>NO</th>
                        <th rowspan='3' width='150'>Area</th>
                        <th rowspan='3' width='150'>Area Dibersihkan</th>
                        <th colspan='" . ($daysInMonth * 2) . "'>TANGGAL</th>
                    </tr>
                    <tr>";
        
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $html .= "<th colspan='2'>{$d}</th>";
        }
        $html .= "</tr>
                    <tr>";
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $html .= "<th>1</th><th>2</th>";
        }
        $html .= "</tr></thead><tbody>";

        $no = 1;
        foreach ($groupedObjects as $roomName => $objects) {
            $roomNameDisplay = $roomName ?: 'Umum';
            $rowCount = $objects->count();
            $first = true;

            foreach ($objects as $obj) {
                $html .= "<tr>";
                
                if ($first) {
                    $html .= "<td rowspan='{$rowCount}'>{$no}</td>";
                    $html .= "<td rowspan='{$rowCount}'>{$roomNameDisplay}</td>";
                    $first = false;
                    $no++;
                }

                $html .= "<td class='text-left'>" . strtolower($obj->cleaningObject->name) . "</td>";

                for ($d = 1; $d <= $daysInMonth; $d++) {
                    $chk1 = isset($data[$d][1][$obj->id]) ? '✓' : '&nbsp;';
                    $chk2 = isset($data[$d][2][$obj->id]) ? '✓' : '&nbsp;';
                    $html .= "<td>{$chk1}</td><td>{$chk2}</td>";
                }

                $html .= "</tr>";
            }
        }

        // Paraf/Approval rows
        $html .= "
                <tr>
                    <td colspan='3' class='text-left bold' style='background-color:#cceeff;'>PARAF CLEANING(Sign)</td>";
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $html .= "<td style='background-color:#cceeff;'>&nbsp;</td><td style='background-color:#cceeff;'>&nbsp;</td>";
        }
        $html .= "</tr>
                <tr>
                    <td colspan='3' class='text-left bold' style='background-color:#ffcccc; color:red;'>PARAF PJ UNIT (Sign)</td>";
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $html .= "<td style='background-color:#ffcccc;'>&nbsp;</td><td style='background-color:#ffcccc;'>&nbsp;</td>";
        }
        $html .= "</tr>
                <!-- Spacer before legend/signatures -->
                <tr>
                    <td colspan='{$totalColumns}' style='border: none; height: 15px;'>&nbsp;</td>
                </tr>
                <!-- Legend and PJ Row -->
                <tr>
                    <td colspan='3' style='border: none;' class='text-left'>
                        Ket : ✓ BERSIH<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;✗ KOTOR
                    </td>
                    <td colspan='{$spacerColspan}' style='border: none;'>&nbsp;</td>
                    <td colspan='5' style='border: none;' class='text-center bold'>PJ {$areaName}</td>
                </tr>
                <!-- Spacer for signatures -->
                <tr>
                    <td colspan='{$totalColumns}' style='border: none; height: 40px;'>&nbsp;</td>
                </tr>
                <!-- Signatures Row -->
                <tr>
                    <td colspan='3' style='border: none;' class='text-left bold'>(Housekeeping RS)</td>
                    <td colspan='{$spacerColspan}' style='border: none;'>&nbsp;</td>
                    <td colspan='5' style='border: none;' class='text-center bold'>..............................</td>
                </tr>
            </tbody></table>
        </body></html>";

        $filename = "Ceklist_{$areaName}_{$monthName}.xls";
        return response($html)
            ->header('Content-Type', 'application/vnd.ms-excel')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }
}
