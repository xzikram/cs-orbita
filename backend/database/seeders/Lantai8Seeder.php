<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Area;
use App\Models\Floor;
use App\Models\CleaningObject;
use App\Models\AreaObject;

class Lantai8Seeder extends Seeder
{
    public function run(): void
    {
        // Temukan lantai 8
        $floor = Floor::where('name', 'Lantai 8')->first();
        if (!$floor) {
            $this->command->error("Lantai 8 tidak ditemukan. Jalankan seeder lantai terlebih dahulu.");
            return;
        }

        // Temukan atau buat Area Janitor Lantai 8
        $area = Area::firstOrCreate(
            ['code' => 'JANITOR-LANTAI8'],
            [
                'floor_id' => $floor->id,
                'name' => 'Ruang Janitor Lantai 8',
                'category' => 'other',
            ]
        );

        // Data ruangan beserta daftar objeknya
        $rooms = [
            'Toilet Karywan' => [
                'langit-langit', 'dinding', 'cerming', 'watafel', 'Urinal', 'Closet', 
                'pintu', 'tempat sampah', 'lantai'
            ],
            'Ruang Dokter jaga' => [
                'langit-langit', 'dinding', 'meja', 'kaca', 'Toilet', 'pintu', 
                'lemari', 'tempat sampah', 'lantai'
            ],
            'Ruang pemeriksaan' => [
                'langit-langit', 'dinding', 'pintu', 'meja', 'washtafel', 'bad', 
                'tempat sampah', 'lantai'
            ],
            'Ruang keperawatan' => [
                'langit-langit', 'dinding', 'pintu', 'meja', 'lemari', 'dampwater', 
                'tempat sampah', 'lantai'
            ],
            'Kamar Ranap VVIP' => [
                'langit-langit', 'dinding/tirai', 'meja', 'mini kitchen', 'Bed Pasien', 
                'pintu', 'sofa', 'lemari', 'tempat sampah', 'kulkas', 'lantai', 'Toilet'
            ],
            'VIP A' => [
                'langit-langit', 'dinding/tirai', 'meja', 'Bed Pasien', 'tempat sampah', 
                'kulkas', 'lemari', 'sofa', 'pintu', 'lantai', 'Toilet'
            ],
            'VIP B' => [
                'langit-langit', 'dinding/tirai', 'pintu', 'meja', 'Bed Pasien', 
                'kulkas', 'lemari', 'sofa', 'tempat sampah', 'lantai', 'Toilet'
            ],
            'Kelas 1' => [
                'langit-langit', 'dinding/tirai', 'meja', 'Bed Pasien', 'lemari', 
                'tempat sampah', 'lantai', 'Toilet'
            ],
            'Kelas 2' => [
                'langit-langit', 'dinding/tirai', 'meja', 'Bed Pasien', 'lemari', 
                'tempat sampah', 'lantai', 'Toilet'
            ],
            'Kris A' => [
                'langit-langit', 'dinding/tirai', 'meja', 'Bed Pasien', 'lemari', 
                'tempat sampah', 'lantai', 'Toilet'
            ],
            'Kris B' => [
                'langit-langit', 'dinding/tirai', 'meja', 'Bed Pasien', 'lemari', 
                'tempat sampah', 'lantai', 'Toilet'
            ],
            'Ruang Isolasi' => [
                'langit-langit', 'dinding/tirai', 'meja', 'Bed Pasien', 'lemari', 
                'washtafel', 'tempat sampah', 'lantai', 'Toilet'
            ],
            'pantry' => [
                'langit-langit', 'dinding', 'dampwater', 'lemari', 'meja', 
                'troli makanan', 'kulkas', 'tempat sampah', 'lantai'
            ],
            'ruang Gudang linen' => [
                'langit-langit', 'dinding', 'lemari', 'lantai'
            ],
            'pantri karyawan' => [
                'langit-langit', 'dinding / kaca', 'meja', 'tempat sampah', 'lantai'
            ],
            'gudang logistik' => [
                'langit-langit', 'dinding', 'lemari', 'lantai'
            ]
        ];

        // Hapus area_objects sebelumnya khusus untuk area ini agar bersih dari dummy
        AreaObject::where('area_id', $area->id)->delete();

        $sortOrder = 1;

        foreach ($rooms as $roomName => $objectNames) {
            // Gunakan array_unique untuk menghindari duplikasi (contoh: langit-langit muncul dua kali di Kamar Ranap VVIP)
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

        $this->command->info("Seeder Lantai 8 berhasil dijalankan!");
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
