<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Area;
use App\Models\Floor;
use App\Models\CleaningObject;
use App\Models\AreaObject;

class Lantai6Seeder extends Seeder
{
    public function run(): void
    {
        // Temukan lantai 6
        $floor = Floor::where('name', 'Lantai 6')->first();
        if (!$floor) {
            $this->command->error("Lantai 6 tidak ditemukan. Jalankan seeder lantai terlebih dahulu.");
            return;
        }

        // Temukan atau buat Area Janitor Lantai 6
        $area = Area::firstOrCreate(
            ['code' => 'JANITOR-LANTAI6'],
            [
                'floor_id' => $floor->id,
                'name' => 'Ruang Janitor Lantai 6',
                'category' => 'other',
            ]
        );

        // Data ruangan beserta daftar objeknya
        $rooms = [
            'Toilet Karywan' => [
                'langit-langit', 'dinding', 'cermin', 'watafel', 'Urinal', 'Closet', 
                'tempat sampah', 'lantai'
            ],
            'RUANG CDC' => [
                'langit-langit', 'dinding', 'Tirai/horden', 'meja', 'kaca/ cermin', 
                'jendela', 'pintu', 'lemari', 'tempat sampah', 'lantai'
            ],
            'RUANG PERTACAM' => [
                'langit-langit', 'dinding', 'meja', 'pintu', 'lemari', 'tempat sampah', 'lantai'
            ],
            'RUANG FOTO FUNDUS' => [
                'langit-langit', 'dinding', 'meja', 'pintu', 'lemari', 'tempat sampah', 'lantai'
            ],
            'RUANG AUTOMATIC PERIMETRI' => [
                'langit-langit', 'dinding', 'meja', 'pintu', 'lemari', 'tempat sampah', 'lantai'
            ],
            'RUANG BIOMETRI IMERTION' => [
                'langit-langit', 'dinding', 'meja', 'jendela', 'pintu', 'lemari', 
                'bad', 'tempat sampah', 'lantai'
            ],
            'pantry' => [
                'langit-langit', 'dinding', 'meja', 'pintu', 'tempat sampah', 'lantai'
            ],
            'R.Dry Eye Center' => [
                'langit-langit', 'dinding', 'meja', 'pintu', 'washtel', 'sofa', 
                'tempat sampah', 'lantai'
            ],
            'R.Fitting Lensa' => [
                'langit-langit', 'dinding', 'meja', 'pintu', 'sofa', 'wastafel', 
                'lemari', 'tempat sampah', 'lantai'
            ],
            'R.Laser' => [
                'langit-langit', 'dinding', 'meja', 'pintu', 'washtafel', 'tempat sampah', 'lantai'
            ],
            'BDR' => [
                'langit-langit', 'dinding', 'meja', 'tempat sampah', 'pintu', 
                'jendela', 'wastafel', 'lantai'
            ],
            'Toilet pasien' => [
                'langit-langit', 'dinding', 'cermin', 'watafel', 'Urinal', 'Closet', 
                'pintu', 'tempat sampah', 'lantai'
            ],
            'Café Lantai 6' => [
                'langit-langit', 'dinding', 'tempat sampah', 'lantai'
            ],
            'POLI 6A' => [
                'langit-langit', 'dinding', 'meja', 'tempat sampah', 'pintu', 
                'sofa', 'lemari', 'wastafel', 'lantai'
            ],
            'POLI 6B' => [
                'langit-langit', 'dinding', 'meja', 'tempat sampah', 'pintu', 
                'sofa', 'lemari', 'wastafel', 'lantai'
            ],
            'POLI 6C' => [
                'langit-langit', 'dinding', 'meja', 'tempat sampah', 'pintu', 
                'sofa', 'lemari', 'wastafel', 'lantai'
            ],
            'POLI 6D' => [
                'langit-langit', 'dinding', 'meja', 'tempat sampah', 'pintu', 
                'sofa', 'lemari', 'wastafel', 'lantai'
            ],
            'Poli 6E' => [
                'langit-langit', 'dinding', 'meja', 'tempat sampah', 'pintu', 
                'bad', 'lemari', 'wastafel', 'lantai'
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

        $this->command->info("Seeder Lantai 6 berhasil dijalankan!");
    }

    private function getIconForObject($name)
    {
        $name = strtolower($name);
        if (str_contains($name, 'sampah') || str_contains($name, 'dustbin')) return 'trash';
        if (str_contains($name, 'lantai') || str_contains($name, 'drain') || str_contains($name, 'air')) return 'floor';
        if (str_contains($name, 'kaca') || str_contains($name, 'cermin') || str_contains($name, 'jendela')) return 'glass';
        if (str_contains($name, 'toilet') || str_contains($name, 'washtafel') || str_contains($name, 'washtel') || str_contains($name, 'watafel') || str_contains($name, 'wastafel') || str_contains($name, 'urinal') || str_contains($name, 'closet')) return 'toilet';
        return 'surface';
    }
}
