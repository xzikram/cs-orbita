<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Area;
use App\Models\Floor;
use App\Models\CleaningObject;
use App\Models\AreaObject;

class Lantai1Seeder extends Seeder
{
    public function run(): void
    {
        // Temukan lantai 1
        $floor = Floor::where('name', 'Lantai 1')->first();
        if (!$floor) {
            $this->command->error("Lantai 1 tidak ditemukan. Jalankan seeder lantai terlebih dahulu.");
            return;
        }

        // Temukan atau buat Area Janitor Lantai 1
        $area = Area::firstOrCreate(
            ['code' => 'JANITOR-LANTAI1'],
            [
                'floor_id' => $floor->id,
                'name' => 'Ruang Janitor Lantai 1',
                'category' => 'other',
            ]
        );

        // Data ruangan beserta daftar objeknya
        $rooms = [
            'IGD' => [
                'langit-langit', 'toilet', 'dinding/Tirai', 'lemari', 'meja', 
                'tempat sampah', 'washtafel', 'Tirai', 'bad', 'jendela', 'lantai'
            ],
            'Lift Pasien' => [
                'langit-langit', 'dinding', 'Cermin', 'tempat sampah', 'lantai'
            ],
            'Farmasi' => [
                'langit-langit', 'pintu', 'dinding', 'meja', 'toilet pria/wanita', 
                'tempat sampah', 'lantai'
            ],
            'Admisi' => [
                'langit-langit', 'dinding', 'meja', 'logo', 'lemari', 
                'tempat sampah', 'pintu', 'dumbwaiter', 'lantai'
            ],
            'Kasir' => [
                'lantai', 'pintu', 'dinding', 'meja', 'langit-langit', 'tempat sampah'
            ],
            'Optik' => [
                'langit-langit', 'Meja', 'Rak kaca mata', 'dinding', 'pintu', 
                'tempat sampah', 'lantai'
            ],
            'Parkiran lantai 1/2/3/4' => [
                'langit-langit', 'pintu', 'dinding', 'Full drain', 'tali air', 
                'Ruang Faset', 'Ruang Office Cleaning', 'Ruang Office Parkir', 'lantai'
            ],
            'Toilet parkiran' => [
                'toilet parkiran lt 2', 'toilet parkiran lt 3', 'toilet parkiran lt 4'
            ],
            'logistik' => [
                'langit-langit', 'meja', 'kulkas', 'dustbin'
            ],
            'lift parkiran' => [
                'dinding', 'langit-langit', 'lantai'
            ]
        ];

        // Hapus area_objects sebelumnya khusus untuk area ini agar bersih
        AreaObject::where('area_id', $area->id)->delete();

        $sortOrder = 1;

        foreach ($rooms as $roomName => $objectNames) {
            foreach ($objectNames as $objectName) {
                // Gunakan huruf kecil untuk pencarian agar seragam, namun biarkan huruf kapital jika membuat baru
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

        $this->command->info("Seeder Lantai 1 berhasil dijalankan!");
    }

    private function getIconForObject($name)
    {
        $name = strtolower($name);
        if (str_contains($name, 'sampah') || str_contains($name, 'dustbin')) return 'trash';
        if (str_contains($name, 'lantai') || str_contains($name, 'drain') || str_contains($name, 'air')) return 'floor';
        if (str_contains($name, 'kaca') || str_contains($name, 'cermin') || str_contains($name, 'jendela')) return 'glass';
        if (str_contains($name, 'toilet') || str_contains($name, 'washtafel')) return 'toilet';
        return 'surface';
    }
}
