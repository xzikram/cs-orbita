<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Area;
use App\Models\Floor;
use App\Models\CleaningObject;
use App\Models\AreaObject;

class Lantai7Seeder extends Seeder
{
    public function run(): void
    {
        // Temukan lantai 7
        $floor = Floor::where('name', 'Lantai 7')->first();
        if (!$floor) {
            $this->command->error("Lantai 7 tidak ditemukan. Jalankan seeder lantai terlebih dahulu.");
            return;
        }

        // Temukan atau buat Area Janitor Lantai 7
        $area = Area::firstOrCreate(
            ['code' => 'JANITOR-LANTAI7'],
            [
                'floor_id' => $floor->id,
                'name' => 'Ruang Janitor Lantai 7',
                'category' => 'other',
            ]
        );

        // Data ruangan beserta daftar objeknya
        $rooms = [
            'Toilet Karywan' => [
                'langit-langit', 'dinding', 'cerming', 'watafel', 'Urinal', 'Closet', 
                'pintu', 'tempat sampah', 'lantai'
            ],
            'R.Laser Vision Correction center' => [
                'langit-langit', 'dinding/tirai', 'meja', 'kaca', 'pintu', 'sofa', 
                'kulkas', 'rak sepatu', 'tempat sampah', 'lantai'
            ],
            'R.low vision service' => [
                'langit-langit', 'dinding', 'meja', 'pintu', 'washtafel', 'lemari', 
                'tempat sampah', 'lantai'
            ],
            'R.Menyusui' => [
                'langit-langit', 'dinding', 'washatfel', 'sofa', 'kulkas', 'meja', 
                'pintu', 'tempat sampah', 'lantai'
            ],
            'R.Protesa Service' => [
                'langit-langit', 'dinding', 'meja', 'washtafel', 'sofa', 'lemari', 
                'pintu', 'tempat sampah', 'lantai'
            ],
            'BDR' => [
                'langit-langit', 'dinding', 'meja', 'lemari', 'sofa', 'washtafel', 
                'pintu', 'tempat sampah', 'lantai'
            ],
            'Toilet pasien' => [
                'langit-langit', 'dinding', 'cermin', 'watafel', 'Urinal', 'Closet', 
                'pintu', 'tempat sampah', 'lantai'
            ],
            'R.Pediatric Opthamologist' => [
                'langit-langit', 'dinding', 'meja', 'tempat sampah', 'sofa', 
                'lemari', 'pintu', 'wastafel', 'lantai'
            ],
            'Eye Donation Center' => [
                'langit-langit', 'dinding/kaca', 'meja', 'sofa', 'lemari', 
                'washtafel', 'tempat sampah', 'pintu', 'lantai'
            ],
            'Area Lasik' => [
                'Kebersihan Tirai'
            ],
            'Playground' => [
                'Pecucian maninan anak'
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

        $this->command->info("Seeder Lantai 7 berhasil dijalankan!");
    }

    private function getIconForObject($name)
    {
        $name = strtolower($name);
        if (str_contains($name, 'sampah') || str_contains($name, 'dustbin')) return 'trash';
        if (str_contains($name, 'lantai') || str_contains($name, 'drain') || str_contains($name, 'air')) return 'floor';
        if (str_contains($name, 'kaca') || str_contains($name, 'cermin') || str_contains($name, 'cerming') || str_contains($name, 'jendela')) return 'glass';
        if (str_contains($name, 'toilet') || str_contains($name, 'washtafel') || str_contains($name, 'washatfel') || str_contains($name, 'watafel') || str_contains($name, 'wastafel') || str_contains($name, 'urinal') || str_contains($name, 'closet')) return 'toilet';
        return 'surface';
    }
}
