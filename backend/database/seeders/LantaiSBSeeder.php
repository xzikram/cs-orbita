<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Building;
use App\Models\Floor;
use App\Models\Area;
use App\Models\CleaningObject;
use App\Models\AreaObject;

class LantaiSBSeeder extends Seeder
{
    public function run(): void
    {
        $building = Building::where('code', 'JEC-ORBITA')->first();
        if (!$building) return;

        $floor = Floor::firstOrCreate(
            ['building_id' => $building->id, 'name' => 'Lantai SB'],
            ['level_number' => 0]
        );

        $area = Area::firstOrCreate(
            ['floor_id' => $floor->id, 'code' => 'JANITOR-LANTAISB'],
            ['name' => 'Ruang Janitor Lantai SB', 'category' => 'other']
        );

        // Delete existing objects in this area just to be safe
        AreaObject::where('area_id', $area->id)->delete();

        // The exact structure from the Excel screenshot
        $rooms = [
            'Ruang GA' => [
                'langit-langit', 'meja', 'dinding', 'pintu', 'lemari berkas', 'Jendela', 'tempat sampah', 'lantai'
            ],
            'Ruang RM' => [
                'langit-langit', 'meja', 'dinding', 'lemari berkas', 'pintu', 'tempat sampah', 'Jendela', 'dumbwaiter', 'lantai'
            ],
            'Loker Pria/wanita' => [
                'langit-langit', 'dinding', 'lemari loker', 'pintu', 'toilet pria/wanita', 'tempat sampah', 'lantai'
            ],
            'Ruang Security' => [
                'langit-langit', 'dinding', 'Jendela', 'pintu', 'meja', 'lemari', 'tempat sampah', 'lantai'
            ],
            'Kantin' => [
                'langit-langit', 'dinding', 'meja', 'wastafel', 'pintu', 'pantry kantin', 'tempat sampah', 'lantai'
            ],
            'Musollah' => [
                'langit-langit', 'karpet', 'TEMPAT WUDHU', 'pintu', 'lemari', 'keset kaki', 'dinding kaca'
            ],
            'genset' => [
                'langit-langit', 'lemari APD', 'asesoris ruangan', 'LANTAI'
            ],
            'panel' => [
                'langit-langit', 'asesoris ruangan', 'lantai'
            ],
            'area Pompa' => [
                'langit-langit', 'pipa merah', 'asesoris ruangan', 'lantai'
            ]
        ];

        // Let's ensure all these objects exist in the CleaningObject table
        $allObjects = [];
        foreach ($rooms as $roomName => $objects) {
            foreach ($objects as $obj) {
                $objStr = strtolower(trim($obj));
                if (!in_array($objStr, $allObjects)) {
                    $allObjects[] = $objStr;
                }
            }
        }

        // Create missing objects
        $objectModels = [];
        foreach ($allObjects as $objStr) {
            // Check if exists
            $model = CleaningObject::where('name', 'like', $objStr)->first();
            if (!$model) {
                $model = CleaningObject::create([
                    'name' => ucwords($objStr),
                    'icon' => 'default' // Add default icon mapping logic later if needed
                ]);
            }
            $objectModels[$objStr] = $model;
        }

        // Attach to AreaObject
        $order = 1;
        foreach ($rooms as $roomName => $objects) {
            foreach ($objects as $obj) {
                $objStr = strtolower(trim($obj));
                AreaObject::create([
                    'area_id' => $area->id,
                    'cleaning_object_id' => $objectModels[$objStr]->id,
                    'room_name' => $roomName,
                    'sort_order' => $order++
                ]);
            }
        }

        $this->command->info("Lantai SB Rooms and Objects have been seeded based on the Excel layout.");
    }
}
