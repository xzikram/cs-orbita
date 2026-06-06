<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\QrCode;
use App\Models\Area;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class QrCodeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $qrCodes = QrCode::with('area.floor.building')
            ->when($request->area_id, fn($q, $v) => $q->where('area_id', $v))
            ->latest()
            ->paginate($request->get('per_page', 50));

        return response()->json($qrCodes);
    }

    public function generate(Area $area): JsonResponse
    {
        // Deactivate old QR codes for this area
        QrCode::where('area_id', $area->id)->update(['is_active' => false]);

        $uuid = Str::uuid()->toString();

        $qrCode = QrCode::create([
            'area_id' => $area->id,
            'uuid' => $uuid,
            'code' => 'QR-' . strtoupper($area->code),
            'qr_data' => json_encode([
                'type' => 'cleantrack',
                'uuid' => $uuid,
                'area_code' => $area->code,
                'area_name' => $area->name,
            ]),
            'generated_at' => now(),
        ]);

        return response()->json([
            'message' => 'QR Code berhasil di-generate',
            'data' => $qrCode->load('area'),
        ], 201);
    }

    public function regenerate(QrCode $qrCode): JsonResponse
    {
        // Deactivate current
        $qrCode->update(['is_active' => false]);

        // Generate new
        $uuid = Str::uuid()->toString();
        $area = $qrCode->area;

        $newQr = QrCode::create([
            'area_id' => $area->id,
            'uuid' => $uuid,
            'code' => 'QR-' . strtoupper($area->code),
            'qr_data' => json_encode([
                'type' => 'cleantrack',
                'uuid' => $uuid,
                'area_code' => $area->code,
                'area_name' => $area->name,
            ]),
            'version' => $qrCode->version + 1,
            'generated_at' => now(),
        ]);

        return response()->json([
            'message' => 'QR Code berhasil di-regenerate',
            'data' => $newQr->load('area'),
        ]);
    }

    public function generateAll(): JsonResponse
    {
        $areas = Area::active()->whereDoesntHave('qrCodes', fn($q) => $q->where('is_active', true))->get();

        $count = 0;
        foreach ($areas as $area) {
            $uuid = Str::uuid()->toString();
            QrCode::create([
                'area_id' => $area->id,
                'uuid' => $uuid,
                'code' => 'QR-' . strtoupper($area->code),
                'qr_data' => json_encode([
                    'type' => 'cleantrack',
                    'uuid' => $uuid,
                    'area_code' => $area->code,
                    'area_name' => $area->name,
                ]),
                'generated_at' => now(),
            ]);
            $count++;
        }

        return response()->json([
            'message' => "{$count} QR Code berhasil di-generate",
        ]);
    }
}
