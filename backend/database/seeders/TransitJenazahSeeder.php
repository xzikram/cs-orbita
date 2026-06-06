<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Area;
use App\Models\Floor;
use App\Models\Building;
use App\Models\CleaningObject;
use App\Models\AreaObject;
use App\Models\QrCode;
use Illuminate\Support\Str;

class TransitJenazahSeeder extends Seeder
{
    public function run(): void
    {
        // Temukan gedung pertama
        $building = Building::first();
        if (!$building) {
            $this->command->error("Gedung tidak ditemukan.");
            return;
        }

        // Gunakan Lantai Fasilitas Tambahan
        $floor = Floor::firstOrCreate(
            ['name' => 'Fasilitas Tambahan', 'building_id' => $building->id],
            ['level_number' => 98]
        );

        // Buat Area Transit Jenazah
        $area = Area::firstOrCreate(
            ['code' => 'TRANSIT-JENAZAH'],
            [
                'floor_id' => $floor->id,
                'name' => 'Transit Jenazah',
                'category' => 'other',
            ]
        );

        // Data ruangan beserta daftar objeknya
        $rooms = [
            'Transit Jenazah' => [
                'langit - langit', 'pintu', 'keranda', 'tempat sampah', 'washtafel', 'lantai', 'dinding'
            ]
        ];

        // Hapus area_objects sebelumnya khusus untuk area ini agar bersih
        AreaObject::where('area_id', $area->id)->delete();

        $sortOrder = 1;

        foreach ($rooms as $roomName => $objectNames) {
            $uniqueObjects = array_unique($objectNames);
            
            foreach ($uniqueObjects as $objectName) {
                $cleaningObj = CleaningObject::firstOrCreate(
                    ['name' => strtolower(trim($objectName))],
                    [
                        'name' => ucwords(trim($objectName)),
                        'icon' => $this->getIconForObject($objectName)
                    ]
                );

                AreaObject::create([
                    'area_id' => $area->id,
                    'cleaning_object_id' => $cleaningObj->id,
                    'room_name' => $roomName,
                    'sort_order' => $sortOrder++,
                    'is_required' => true,
                ]);
            }
        }

        // Generate QR Code if missing
        if (!$area->qrCode) {
            QrCode::create([
                'area_id' => $area->id,
                'code' => 'QR-' . strtoupper(Str::random(6)),
                'uuid' => (string) Str::uuid()
            ]);
        }

        $this->command->info("Seeder Transit Jenazah berhasil dijalankan!");
    }

    private function getIconForObject($name)
    {
        $name = strtolower($name);
        if (str_contains($name, 'sampah') || str_contains($name, 'dustbin')) return 'trash';
        if (str_contains($name, 'lantai') || str_contains($name, 'drain') || str_contains($name, 'air')) return 'floor';
        if (str_contains($name, 'kaca') || str_contains($name, 'cermin') || str_contains($name, 'cerming') || str_contains($name, 'jendela')) return 'glass';
        if (str_contains($name, 'toilet') || str_contains($name, 'washtafel') || str_contains($name, 'watafel') || str_contains($name, 'wastafel')) return 'toilet';
        if (str_contains($name, 'keranda')) return 'medical_services';
        return 'surface';
    }
}
