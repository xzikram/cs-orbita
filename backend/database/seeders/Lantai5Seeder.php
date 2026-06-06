<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Area;
use App\Models\Floor;
use App\Models\CleaningObject;
use App\Models\AreaObject;

class Lantai5Seeder extends Seeder
{
    public function run(): void
    {
        // Temukan lantai 5
        $floor = Floor::where('name', 'Lantai 5')->first();
        if (!$floor) {
            $this->command->error("Lantai 5 tidak ditemukan. Jalankan seeder lantai terlebih dahulu.");
            return;
        }

        // Temukan atau buat Area Janitor Lantai 5
        $area = Area::firstOrCreate(
            ['code' => 'JANITOR-LANTAI5'],
            [
                'floor_id' => $floor->id,
                'name' => 'Ruang Janitor Lantai 5',
                'category' => 'other',
            ]
        );

        // Data ruangan beserta daftar objeknya
        $rooms = [
            'Toilet Karywan' => [
                'langit-langit', 'dinding', 'cermin', 'watafel', 'Urinal', 'Closet', 
                'Pintu', 'tempat sampah', 'lantai'
            ],
            'R.Radiologi' => [
                'langit-langit', 'dinding', 'meja', 'Pintu', 'jendela', 'LEMARI', 
                'tempat sampah', 'lantai'
            ],
            'R. LASER RADIOLOGI' => [
                'langit-langit', 'dinding', 'tirai', 'Pintu', 'lemari', 'rak sepatu', 
                'tempat sampah', 'lantai'
            ],
            'Ruang Lab' => [
                'langit-langit', 'dinding', 'meja', 'jendela', 'pintu', 'washtafel', 
                'lemari', 'valet', 'sofa', 'tempat sampah', 'lantai'
            ],
            'PEC' => [
                'langit-langit', 'dinding', 'meja', 'pintu', 'KULKAS', 'tempat sampah', 'lantai'
            ],
            'BDR' => [
                'langit-langit', 'dinding/ kaca', 'meja', 'tempat sampah', 'pintu', 
                'Lemari', 'wastafel', 'lantai'
            ],
            'Toilet pasien' => [
                'dinding', 'cermin', 'pintu', 'watafel', 'Urinal', 'Closet', 
                'tempat sampah', 'lantai'
            ],
            'Poli 5A' => [
                'langit-langit', 'dinding', 'meja', 'tempat sampah', 'pintu', 
                'lemari', 'sofa', 'wastafel', 'lantai'
            ],
            'POLI 5 B' => [
                'langit-langit', 'dinding', 'meja', 'tempat sampah', 'lemari', 
                'pintu', 'sofa', 'wastafel', 'lantai'
            ],
            'POLI 5C' => [
                'langit-langit', 'dinding', 'meja', 'tempat sampah', 'lemari', 
                'pintu', 'sofa', 'wastafel', 'lantai'
            ],
            'POLI 5D (TINDAKAN)' => [
                'langit-langit', 'dinding', 'meja', 'tempat sampah', 'pintu', 
                'lemari', 'bad', 'lantai'
            ],
            'POLI 5E' => [
                'langit-langit', 'dinding', 'meja', 'tempat sampah', 'lemari', 
                'pintu', 'sofa', 'wastafel', 'lantai'
            ],
            'POLI 5F' => [
                'langit-langit', 'dinding', 'meja', 'tempat sampah', 'lemari', 
                'pintu', 'sofa', 'wastafel', 'lantai'
            ],
            'area istirahat karyawan' => [
                'damp water', 'dinding', 'lantai'
            ],
            'Tenant Bekenpound' => [
                'langit-langit', 'dinding', 'lantai'
            ]
        ];

        // Hapus area_objects sebelumnya khusus untuk area ini agar bersih dari dummy
        AreaObject::where('area_id', $area->id)->delete();

        $sortOrder = 1;

        foreach ($rooms as $roomName => $objectNames) {
            foreach ($objectNames as $objectName) {
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

        $this->command->info("Seeder Lantai 5 berhasil dijalankan!");
    }

    private function getIconForObject($name)
    {
        $name = strtolower($name);
        if (str_contains($name, 'sampah') || str_contains($name, 'dustbin')) return 'trash';
        if (str_contains($name, 'lantai') || str_contains($name, 'drain') || str_contains($name, 'air')) return 'floor';
        if (str_contains($name, 'kaca') || str_contains($name, 'cermin') || str_contains($name, 'jendela')) return 'glass';
        if (str_contains($name, 'toilet') || str_contains($name, 'washtafel') || str_contains($name, 'watafel') || str_contains($name, 'wastafel') || str_contains($name, 'urinal') || str_contains($name, 'closet')) return 'toilet';
        return 'surface';
    }
}
