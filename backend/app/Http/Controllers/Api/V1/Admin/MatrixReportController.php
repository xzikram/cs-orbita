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

        // Generate HTML
        $monthName = strtoupper($startDate->translatedFormat('F Y'));
        $areaName = strtoupper($area->name);

        $html = "
        <html xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:x='urn:schemas-microsoft-com:office:excel' xmlns='http://www.w3.org/TR/REC-html40'>
        <head>
            <meta charset='utf-8'>
            <style>
                table { border-collapse: collapse; font-family: Arial, sans-serif; font-size: 11px; }
                th, td { border: 1px solid black; padding: 3px; text-align: center; }
                .text-left { text-align: left; }
                .text-center { text-align: center; }
                .bold { font-weight: bold; }
                .header-title { font-size: 14px; border: none; text-align: center; font-weight: bold; }
            </style>
        </head>
        <body>
            <table width='100%'>
                <tr>
                    <td colspan='" . (2 + ($daysInMonth * 2)) . "' class='header-title'>CEKLIST KEBERSIHAN CLEANING SERVICE RS JEC ORBITA MAKASSAR</td>
                </tr>
                <tr><td colspan='2' style='border:none;' class='text-left bold'>PERIODE : {$monthName}</td></tr>
                <tr><td colspan='2' style='border:none;' class='text-left bold'>LOKASI : {$areaName}</td></tr>
                <tr><td colspan='" . (2 + ($daysInMonth * 2)) . "' style='border:none;'></td></tr>
            </table>

            <table border='1'>
                <thead>
                    <tr>
                        <th rowspan='2' width='30'>NO</th>
                        <th rowspan='2' width='150'>Area</th>
                        <th rowspan='2' width='150'>Area Dibersihkan</th>
                        <th colspan='" . ($daysInMonth * 2) . "'>HARI/TANGGAL</th>
                    </tr>
                    <tr>";
        
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $html .= "<th>{$d}</th><th></th>"; // Visual merge or just two columns? The screenshot has 1 | 2 for shifts under each date?
            // Wait, screenshot shows day numbers are NOT there, it just says 1 2 1 2 1 2...
            // Let's make it EXACTLY like screenshot: row for Dates?
            // Actually screenshot has "HARI/TANGGAL" and then "1 | 2 | 1 | 2 | 1 | 2". 
        }
        $html .= "</tr><tr><th colspan='3'></th>";
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
                    $chk1 = isset($data[$d][1][$obj->id]) ? 'v' : '';
                    $chk2 = isset($data[$d][2][$obj->id]) ? 'v' : '';
                    $html .= "<td>{$chk1}</td><td>{$chk2}</td>";
                }

                $html .= "</tr>";
            }
        }

        // Approval rows
        $html .= "
                <tr>
                    <td colspan='3' class='text-left bold' style='background-color:#cceeff;'>PARAF CLEANING(Sign)</td>";
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $html .= "<td style='background-color:#cceeff;'></td><td style='background-color:#cceeff;'></td>";
        }
        $html .= "</tr>
                <tr>
                    <td colspan='3' class='text-left bold' style='background-color:#ffcccc; color:red;'>PARAF PJ UNIT (Sign)</td>";
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $html .= "<td style='background-color:#ffcccc;'></td><td style='background-color:#ffcccc;'></td>";
        }
        $html .= "</tr>
            </tbody></table>
            <br>
            <table style='border:none;'>
                <tr>
                    <td colspan='3' style='border:none;' class='text-left'>
                        Ket : v BERSIH<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;X KOTOR
                    </td>
                    <td colspan='" . (($daysInMonth * 2) - 4) . "' style='border:none;'></td>
                    <td colspan='4' style='border:none;' class='text-center bold'>PJ {$areaName}</td>
                </tr>
                <tr><td colspan='" . (3 + ($daysInMonth * 2)) . "' style='border:none; height:40px;'></td></tr>
                <tr>
                    <td colspan='3' style='border:none;' class='text-left bold'>(Housekeeping RS)</td>
                    <td colspan='" . (($daysInMonth * 2) - 4) . "' style='border:none;'></td>
                    <td colspan='4' style='border:none;' class='text-center bold'>..............................</td>
                </tr>
            </table>
        </body></html>";

        $filename = "Ceklist_{$areaName}_{$monthName}.xls";
        return response($html)
            ->header('Content-Type', 'application/vnd.ms-excel')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }
}
