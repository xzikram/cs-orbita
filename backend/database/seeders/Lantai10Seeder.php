<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Area;
use App\Models\Floor;
use App\Models\CleaningObject;
use App\Models\AreaObject;

class Lantai10Seeder extends Seeder
{
    public function run(): void
    {
        // Temukan lantai 10
        $floor = Floor::where('name', 'Lantai 10')->first();
        if (!$floor) {
            $this->command->error("Lantai 10 tidak ditemukan. Jalankan seeder lantai terlebih dahulu.");
            return;
        }

        // Temukan atau buat Area Janitor Lantai 10
        $area = Area::firstOrCreate(
            ['code' => 'JANITOR-LANTAI10'],
            [
                'floor_id' => $floor->id,
                'name' => 'Ruang Janitor Lantai 10',
                'category' => 'other',
            ]
        );

        // Data ruangan beserta daftar objeknya
        $rooms = [
            'Toilet Karywan' => [
                'langit-langit', 'dinding', 'cermin', 'watafel', 'Urinal', 'Closet', 
                'tempat sampah', 'lantai'
            ],
            'R.Wet Lab' => [
                'langit-langit', 'dinding', 'meja', 'kaca', 'kulkas', 'lemari', 
                'washtafel', 'tempat sampah', 'lantai'
            ],
            'R.Dry Lab' => [
                'langit-langit', 'dinding', 'meja', 'kaca', 'washtafel', 'tempat sampah', 'lantai'
            ],
            'Ruang Auditorium' => [
                'langit-langit', 'dinding', 'meja', 'kaca', 'control IT', 'gudang', 
                'kursi', 'Karpet'
            ],
            'Ruang Staff' => [
                'langit-langit', 'dinding / kaca', 'meja', 'asesoris meja', 'lemari', 
                'dampwater', 'tempat sampah', 'lantai'
            ],
            'Ruang Meeting' => [
                'langit-langit', 'dinding / kaca', 'meja', 'asesoris meja', 
                'tempat sampah', 'lantai'
            ],
            'Ruang Komite Medik' => [
                'langit-langit', 'dinding / kaca', 'meja', 'asesoris meja', 
                'lemari', 'tempat sampah', 'lantai'
            ],
            'Ruang HRD' => [
                'langit-langit', 'dinding / kaca', 'meja', 'asesoris meja', 
                'lemari', 'tempat sampah', 'lantai'
            ],
            'Ruang Finance' => [
                'langit-langit', 'dinding / kaca', 'meja', 'asesoris meja', 
                'lemari', 'tempat sampah', 'LANTAI'
            ],
            'Ruang Direksi 1' => [
                'langit-langit', 'dinding', 'meja', 'lemari', 'kulkas', 'sofa', 
                'asesoris meja', 'tempat sampah', 'LANTAI'
            ],
            'Ruang Direksi 2' => [
                'langit-langit', 'dinding', 'meja', 'asesoris meja', 'tempat sampah', 'lantai'
            ],
            'Ruang Direksi 3' => [
                'langit-langit', 'dinding / kaca', 'meja', 'asesoris meja', 
                'lemari', 'tempat sampah', 'lantai'
            ],
            'Ruang Direksi 4' => [
                'langit-langit', 'dinding', 'meja', 'asesoris meja', 'tempat sampah', 'lantai'
            ],
            'Ruang IT' => [
                'langit-langit', 'dinding', 'lemari', 'tempat sampah', 'lantai'
            ],
            'pantri hks' => [
                'dinding', 'washtafel', 'lemari', 'Lantai'
            ],
            'pantry karyawan' => [
                'dinding', 'washtafel', 'lemari', 'lantai'
            ],
            'Rooftop lt 11' => [
                'Ruang AHU', 'ruang area filter ro', 'area pintu dan lantai emergency'
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

        $this->command->info("Seeder Lantai 10 berhasil dijalankan!");
    }

    private function getIconForObject($name)
    {
        $name = strtolower($name);
        if (str_contains($name, 'sampah') || str_contains($name, 'dustbin')) return 'trash';
        if (str_contains($name, 'lantai') || str_contains($name, 'drain') || str_contains($name, 'air') || str_contains($name, 'karpet')) return 'floor';
        if (str_contains($name, 'kaca') || str_contains($name, 'cermin') || str_contains($name, 'cerming') || str_contains($name, 'jendela')) return 'glass';
        if (str_contains($name, 'toilet') || str_contains($name, 'washtafel') || str_contains($name, 'washafel') || str_contains($name, 'watafel') || str_contains($name, 'wastafel') || str_contains($name, 'urinal') || str_contains($name, 'urinoir') || str_contains($name, 'closet')) return 'toilet';
        if (str_contains($name, 'ruang ahu') || str_contains($name, 'filter ro')) return 'settings';
        return 'surface';
    }
}
