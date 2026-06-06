<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Area;
use App\Models\Floor;
use App\Models\Building;
use App\Models\CleaningObject;
use App\Models\AreaObject;

class ParkiranTamanSeeder extends Seeder
{
    public function run(): void
    {
        // Temukan gedung pertama
        $building = Building::first();
        if (!$building) {
            $this->command->error("Gedung tidak ditemukan.");
            return;
        }

        // Temukan atau buat Lantai "Parkiran & Taman"
        $floor = Floor::firstOrCreate(
            ['name' => 'Parkiran & Taman', 'building_id' => $building->id],
            ['level_number' => 99]
        );

        // Temukan atau buat Area Parkiran & Taman
        $area = Area::firstOrCreate(
            ['code' => 'AREA-PARKIRAN'],
            [
                'floor_id' => $floor->id,
                'name' => 'Area Parkiran & Taman',
                'category' => 'other',
            ]
        );

        // Data ruangan beserta daftar objeknya
        $rooms = [
            'Parkiran lantai P2 -P4' => [
                'langit-langit', 'pintu', 'dinding', 'Full drain', 'tali air', 'lantai'
            ],
            'Toilet parkiran' => [
                'toilet parkiran lt 2', 'toilet parkiran lt 3', 'toilet parkiran lt 4'
            ],
            'Ruang Faset' => [
                'Lantai', 'langit-langit', 'pintu', 'dinding', 'Full drain', 
                'Tempat sampah', 'Wastafel', 'Lemari'
            ],
            'Ruang Logistik 3/4' => [
                'Lantai', 'langit-langit', 'pintu', 'dinding', 'Full drain', 
                'Tempat sampah', 'Wastafel', 'Lemari'
            ],
            'Ruang Cleaning Service' => [
                'Lantai', 'langit-langit', 'pintu', 'dinding', 'Full drain', 
                'Tempat sampah', 'Wastafel', 'Lemari'
            ],
            'Area Taman & Parkiran Luar Gedung' => [
                'lantai', 'Full drain', 'tali air', 'dinding', 'pos security luar', 
                'taman depan', 'taman samping', 'signet'
            ],
            'Pos security' => [
                'dinding', 'kaca', 'lantai', 'Ac', 'wastafel', 'kursi'
            ],
            'Tempat sampah' => [
                'Tempat sampah belakang gd', 'Tempat sampah parkiran p2-p4', 
                'Tps (LB3, DOMESTIK, KARTON)'
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

        $this->command->info("Seeder Parkiran & Taman berhasil dijalankan!");
    }

    private function getIconForObject($name)
    {
        $name = strtolower($name);
        if (str_contains($name, 'sampah') || str_contains($name, 'dustbin') || str_contains($name, 'tps')) return 'trash';
        if (str_contains($name, 'lantai') || str_contains($name, 'drain') || str_contains($name, 'air')) return 'floor';
        if (str_contains($name, 'kaca') || str_contains($name, 'cermin') || str_contains($name, 'cerming') || str_contains($name, 'jendela')) return 'glass';
        if (str_contains($name, 'toilet') || str_contains($name, 'washtafel') || str_contains($name, 'washafel') || str_contains($name, 'watafel') || str_contains($name, 'wastafel') || str_contains($name, 'urinal') || str_contains($name, 'urinoir') || str_contains($name, 'closet')) return 'toilet';
        if (str_contains($name, 'taman') || str_contains($name, 'rumput') || str_contains($name, 'pohon')) return 'leaf';
        if (str_contains($name, 'security') || str_contains($name, 'pos')) return 'shield';
        return 'surface';
    }
}
