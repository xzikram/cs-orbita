<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Building;
use App\Models\Floor;
use App\Models\Area;
use App\Models\CleaningObject;
use App\Models\AreaObject;
use App\Models\Shift;
use App\Models\AreaSchedule;

class JanitorSeeder extends Seeder
{
    public function run(): void
    {
        $building = Building::where('code', 'JEC-ORBITA')->first();
        if (!$building) {
            $this->command->error('Building not found!');
            return;
        }

        $floorNames = [
            'Lantai SB' => 0,
            'Lantai 1' => 1,
            'Lantai 2' => 2,
            'Lantai 3' => 3,
            'Lantai 4' => 4,
            'Lantai 5' => 5,
            'Lantai 6' => 6,
            'Lantai 7' => 7,
            'Lantai 8' => 8,
            'Lantai 9' => 9,
            'Lantai 10' => 10,
        ];

        // Define what objects are checked when scanning the Janitor QR (acts as floor-wide checkpoint)
        $janitorObjects = [
            'Lantai', 
            'Toilet/Kloset', 
            'Tempat Sampah', 
            'Kaca', 
            'Meja', 
            'Dinding', 
            'Langit-langit',
            'Wastafel'
        ];
        $objectModels = [];
        foreach ($janitorObjects as $objName) {
            $objectModels[$objName] = CleaningObject::where('name', $objName)->first();
        }

        $shifts = Shift::all();

        foreach ($floorNames as $name => $level) {
            // Ensure floor exists
            $floor = Floor::firstOrCreate(
                ['building_id' => $building->id, 'name' => $name],
                ['level_number' => $level]
            );

            // Create Janitor Area
            $code = 'JANITOR-' . strtoupper(str_replace(' ', '', $name));
            $area = Area::firstOrCreate(
                ['floor_id' => $floor->id, 'code' => $code],
                ['name' => 'Ruang Janitor ' . $name, 'category' => 'other']
            );

            // Attach Objects
            foreach ($janitorObjects as $idx => $objName) {
                $objModel = $objectModels[$objName];
                if ($objModel) {
                    AreaObject::firstOrCreate([
                        'area_id' => $area->id,
                        'cleaning_object_id' => $objModel->id,
                    ], [
                        'sort_order' => $idx + 1,
                    ]);
                }
            }

            // Create Schedules (if not exist)
            foreach ($shifts as $shift) {
                // Schedule it once per shift at the start
                $scheduledTime = $shift->start_time;
                // Add 1 hour to start time roughly
                if (str_starts_with($scheduledTime, '06')) $scheduledTime = '07:00';
                if (str_starts_with($scheduledTime, '14')) $scheduledTime = '15:00';
                if (str_starts_with($scheduledTime, '22')) $scheduledTime = '23:00';

                AreaSchedule::firstOrCreate([
                    'area_id' => $area->id,
                    'shift_id' => $shift->id,
                ], [
                    'scheduled_time' => $scheduledTime,
                    'tolerance_minutes' => 60,
                ]);
            }

            $this->command->info("Created Janitor for {$name}");
        }
    }
}
