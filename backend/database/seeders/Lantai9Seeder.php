<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Area;
use App\Models\Floor;
use App\Models\CleaningObject;
use App\Models\AreaObject;

class Lantai9Seeder extends Seeder
{
    public function run(): void
    {
        // Temukan lantai 9
        $floor = Floor::where('name', 'Lantai 9')->first();
        if (!$floor) {
            $this->command->error("Lantai 9 tidak ditemukan. Jalankan seeder lantai terlebih dahulu.");
            return;
        }

        // Temukan atau buat Area Janitor Lantai 9
        $area = Area::firstOrCreate(
            ['code' => 'JANITOR-LANTAI9'],
            [
                'floor_id' => $floor->id,
                'name' => 'Ruang Janitor Lantai 9',
                'category' => 'other',
            ]
        );

        // Data ruangan beserta daftar objeknya
        $rooms = [
            'Toilet Karywan' => [
                'langit-langit', 'dinding', 'cermin', 'watafel', 'Closet', 
                'tempat sampah', 'Lantai'
            ],
            'Ruang Operasi 1' => [
                'langit-langit', 'dinding', 'meja', 'washtafel scrub 1', 
                'rak sendal', 'tempat sampah', 'Lantai Vinyl'
            ],
            'Ruang Operasi 2' => [
                'langit-langit', 'dinding', 'meja', 'tempat sampah', 'lantai vinyl'
            ],
            'Ruang Operasi 3' => [
                'langit-langit', 'dinding', 'meja', 'tempat sampah', 'lantai vinyl'
            ],
            'Ruang Operasi 4' => [
                'langit-langit', 'dinding', 'meja', 'washtafel scrub 2', 
                'lantai vinyl', 'tempat sampah'
            ],
            'Toilet pasien' => [
                'langit-langit', 'dinding', 'cermin', 'watafel', 'Closet', 
                'tempat sampah', 'Lantai'
            ],
            'Ruang isterhat dokter' => [
                'langit-langit', 'dinding', 'meja', 'tempat sampah', 'loker dokter', 
                'mini kitchen', 'washtafel', 'Toilet dokter', 'pintu', 'Jendela', 
                'sofa', 'Musollah dokter', 'lantai'
            ],
            'Ruang Pendaftaran' => [
                'langit-langit', 'dinding', 'meja NS', 'pintu', 'Jendela', 
                'rak sepatu', 'tempat sampah', 'Lantai'
            ],
            'toilet pendaftaran' => [
                'langit-langit', 'dinding', 'lantai', 'watafel', 'Closet', 
                'cermin', 'urinoir'
            ],
            'ruang pemulihan' => [
                'tempat sampah', 'langit-langit', 'dinding', 'meja', 'Tirai', 
                'lemari', 'sofa', 'bad', 'pintu', 'lantai'
            ],
            'Ruang persiapan' => [
                'langit-langit', 'dinding', 'meja', 'Tirai', 'lemari', 'sofa', 
                'bad', 'pintu', 'tempat sampah', 'LANTAI'
            ],
            'Nurse Station' => [
                'langit-langit', 'dinding', 'meja', 'kaca', 'washafel', 
                'tempat sampah', 'Lantai'
            ]
        ];

        // Hapus area_objects sebelumnya khusus untuk area ini agar bersih dari dummy
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

        $this->command->info("Seeder Lantai 9 berhasil dijalankan!");
    }

    private function getIconForObject($name)
    {
        $name = strtolower($name);
        if (str_contains($name, 'sampah') || str_contains($name, 'dustbin')) return 'trash';
        if (str_contains($name, 'lantai') || str_contains($name, 'drain') || str_contains($name, 'air') || str_contains($name, 'vinyl')) return 'floor';
        if (str_contains($name, 'kaca') || str_contains($name, 'cermin') || str_contains($name, 'cerming') || str_contains($name, 'jendela')) return 'glass';
        if (str_contains($name, 'toilet') || str_contains($name, 'washtafel') || str_contains($name, 'washafel') || str_contains($name, 'watafel') || str_contains($name, 'wastafel') || str_contains($name, 'urinal') || str_contains($name, 'urinoir') || str_contains($name, 'closet')) return 'toilet';
        return 'surface';
    }
}
