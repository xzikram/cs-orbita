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
use App\Models\Setting;

class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        // ========== BUILDING ==========
        $building = Building::create([
            'code' => 'JEC-ORBITA',
            'name' => 'RS Mata JEC Orbita Makassar',
            'address' => 'Jl. Botolempangan No. 5, Makassar, Sulawesi Selatan',
        ]);

        // ========== FLOORS ==========
        $lt1 = Floor::create(['building_id' => $building->id, 'name' => 'Lantai 1', 'level_number' => 1]);
        $lt2 = Floor::create(['building_id' => $building->id, 'name' => 'Lantai 2', 'level_number' => 2]);
        $lt3 = Floor::create(['building_id' => $building->id, 'name' => 'Lantai 3', 'level_number' => 3]);

        // ========== CLEANING OBJECTS (Master) ==========
        $objects = [
            'Langit-langit' => 'ceiling',
            'Meja' => 'table',
            'Dinding' => 'wall',
            'Pintu' => 'door',
            'Lemari Berkas' => 'cabinet',
            'Jendela' => 'window',
            'Tempat Sampah' => 'trash',
            'Lantai' => 'floor',
            'Karpet' => 'carpet',
            'Tempat Wudhu' => 'ablution',
            'Kaca' => 'glass',
            'Lemari' => 'closet',
            'Keset' => 'mat',
            'Wastafel' => 'sink',
            'Toilet/Kloset' => 'toilet',
            'Cermin' => 'mirror',
            'Dispenser Sabun' => 'soap_dispenser',
            'Tissue Holder' => 'tissue_holder',
            'Kursi' => 'chair',
            'Rak' => 'shelf',
            'AC/Pendingin' => 'ac',
            'Lampu' => 'lamp',
            'Komputer/Monitor' => 'computer',
            'Etalase' => 'showcase',
            'Meja Makan' => 'dining_table',
            'Konter' => 'counter',
        ];

        $objectModels = [];
        $order = 0;
        foreach ($objects as $name => $icon) {
            $objectModels[$name] = CleaningObject::create([
                'name' => $name,
                'icon' => $icon,
            ]);
            $order++;
        }

        // ========== AREAS & THEIR CHECKLIST ==========
        $areasConfig = [
            // Lantai 1
            ['floor' => $lt1, 'code' => 'RUANG-GA', 'name' => 'Ruang GA', 'category' => 'office', 'objects' => [
                'Langit-langit', 'Meja', 'Dinding', 'Pintu', 'Lemari Berkas', 'Jendela', 'Tempat Sampah', 'Lantai',
            ]],
            ['floor' => $lt1, 'code' => 'RUANG-RM', 'name' => 'Ruang Rekam Medis', 'category' => 'office', 'objects' => [
                'Langit-langit', 'Meja', 'Dinding', 'Pintu', 'Lemari Berkas', 'Lantai', 'Rak', 'Komputer/Monitor',
            ]],
            ['floor' => $lt1, 'code' => 'MUSHOLLA', 'name' => 'Musholla', 'category' => 'worship', 'objects' => [
                'Karpet', 'Tempat Wudhu', 'Kaca', 'Lemari', 'Keset', 'Lantai', 'Dinding',
            ]],
            ['floor' => $lt1, 'code' => 'SECURITY', 'name' => 'Pos Security', 'category' => 'security', 'objects' => [
                'Meja', 'Kursi', 'Lantai', 'Jendela', 'Tempat Sampah', 'Dinding',
            ]],
            ['floor' => $lt1, 'code' => 'KANTIN', 'name' => 'Kantin', 'category' => 'canteen', 'objects' => [
                'Meja Makan', 'Kursi', 'Lantai', 'Tempat Sampah', 'Konter', 'Dinding', 'Jendela',
            ]],
            ['floor' => $lt1, 'code' => 'TOILET-LT1-A', 'name' => 'Toilet Lt.1 A', 'category' => 'toilet', 'objects' => [
                'Toilet/Kloset', 'Wastafel', 'Cermin', 'Lantai', 'Dinding', 'Dispenser Sabun', 'Tissue Holder', 'Tempat Sampah',
            ]],
            ['floor' => $lt1, 'code' => 'TOILET-LT1-B', 'name' => 'Toilet Lt.1 B', 'category' => 'toilet', 'objects' => [
                'Toilet/Kloset', 'Wastafel', 'Cermin', 'Lantai', 'Dinding', 'Dispenser Sabun', 'Tissue Holder', 'Tempat Sampah',
            ]],
            ['floor' => $lt1, 'code' => 'LOBBY', 'name' => 'Lobby Utama', 'category' => 'lobby', 'objects' => [
                'Lantai', 'Kursi', 'Meja', 'Kaca', 'Tempat Sampah', 'Dinding',
            ]],
            ['floor' => $lt1, 'code' => 'FARMASI', 'name' => 'Farmasi', 'category' => 'pharmacy', 'objects' => [
                'Lantai', 'Meja', 'Etalase', 'Rak', 'Konter', 'Dinding', 'AC/Pendingin', 'Tempat Sampah',
            ]],

            // Lantai 2
            ['floor' => $lt2, 'code' => 'POLI-RETINA', 'name' => 'Poli Retina', 'category' => 'clinic', 'objects' => [
                'Langit-langit', 'Meja', 'Kursi', 'Lantai', 'Dinding', 'Jendela', 'AC/Pendingin', 'Tempat Sampah',
            ]],
            ['floor' => $lt2, 'code' => 'POLI-GLAUKOMA', 'name' => 'Poli Glaukoma', 'category' => 'clinic', 'objects' => [
                'Langit-langit', 'Meja', 'Kursi', 'Lantai', 'Dinding', 'Jendela', 'AC/Pendingin', 'Tempat Sampah',
            ]],
            ['floor' => $lt2, 'code' => 'POLI-REFRAKSI', 'name' => 'Poli Refraksi', 'category' => 'clinic', 'objects' => [
                'Langit-langit', 'Meja', 'Kursi', 'Lantai', 'Dinding', 'Jendela', 'AC/Pendingin', 'Tempat Sampah',
            ]],
            ['floor' => $lt2, 'code' => 'TOILET-LT2', 'name' => 'Toilet Lt.2', 'category' => 'toilet', 'objects' => [
                'Toilet/Kloset', 'Wastafel', 'Cermin', 'Lantai', 'Dinding', 'Dispenser Sabun', 'Tissue Holder', 'Tempat Sampah',
            ]],
            ['floor' => $lt2, 'code' => 'KORIDOR-LT2', 'name' => 'Koridor Lt.2', 'category' => 'corridor', 'objects' => [
                'Lantai', 'Dinding', 'Kaca', 'Tempat Sampah', 'Lampu',
            ]],

            // Lantai 3
            ['floor' => $lt3, 'code' => 'RUANG-OK', 'name' => 'Ruang Operasi (OK)', 'category' => 'clinic', 'objects' => [
                'Langit-langit', 'Lantai', 'Dinding', 'Meja', 'Lampu', 'AC/Pendingin', 'Tempat Sampah',
            ]],
            ['floor' => $lt3, 'code' => 'RUANG-RECOVERY', 'name' => 'Ruang Recovery', 'category' => 'clinic', 'objects' => [
                'Langit-langit', 'Lantai', 'Dinding', 'Meja', 'Kursi', 'Jendela', 'Tempat Sampah',
            ]],
            ['floor' => $lt3, 'code' => 'TOILET-LT3', 'name' => 'Toilet Lt.3', 'category' => 'toilet', 'objects' => [
                'Toilet/Kloset', 'Wastafel', 'Cermin', 'Lantai', 'Dinding', 'Dispenser Sabun', 'Tissue Holder', 'Tempat Sampah',
            ]],
        ];

        foreach ($areasConfig as $config) {
            $area = Area::create([
                'floor_id' => $config['floor']->id,
                'code' => $config['code'],
                'name' => $config['name'],
                'category' => $config['category'],
            ]);

            foreach ($config['objects'] as $idx => $objName) {
                if (isset($objectModels[$objName])) {
                    AreaObject::create([
                        'area_id' => $area->id,
                        'cleaning_object_id' => $objectModels[$objName]->id,
                        'sort_order' => $idx + 1,
                    ]);
                }
            }
        }

        // ========== SHIFTS ==========
        $shift1 = Shift::create([
            'name' => 'Shift 1 (Pagi)',
            'start_time' => '06:00',
            'end_time' => '14:00',
            'sort_order' => 1,
        ]);
        $shift2 = Shift::create([
            'name' => 'Shift 2 (Siang)',
            'start_time' => '14:00',
            'end_time' => '22:00',
            'sort_order' => 2,
        ]);
        $shift3 = Shift::create([
            'name' => 'Shift 3 (Malam)',
            'start_time' => '22:00',
            'end_time' => '06:00',
            'sort_order' => 3,
        ]);

        // ========== AREA SCHEDULES ==========
        // Regular areas: 1 schedule per shift
        $regularAreas = Area::whereNotIn('category', ['toilet'])->get();
        foreach ($regularAreas as $area) {
            AreaSchedule::create([
                'area_id' => $area->id,
                'shift_id' => $shift1->id,
                'scheduled_time' => '08:00',
                'tolerance_minutes' => 30,
            ]);
            AreaSchedule::create([
                'area_id' => $area->id,
                'shift_id' => $shift2->id,
                'scheduled_time' => '15:00',
                'tolerance_minutes' => 30,
            ]);
        }

        // Toilet areas: more frequent schedules
        $toiletAreas = Area::where('category', 'toilet')->get();
        $toiletTimes = ['06:00', '08:00', '10:00', '12:00', '14:00', '16:00'];
        foreach ($toiletAreas as $area) {
            foreach ($toiletTimes as $time) {
                $shiftId = $time < '14:00' ? $shift1->id : $shift2->id;
                AreaSchedule::create([
                    'area_id' => $area->id,
                    'shift_id' => $shiftId,
                    'scheduled_time' => $time,
                    'tolerance_minutes' => 15,
                ]);
            }
        }

        // ========== DEFAULT SETTINGS ==========
        $settings = [
            ['group' => 'general', 'key' => 'app_name', 'value' => 'CLEANTRACK RS', 'type' => 'string', 'description' => 'Nama aplikasi'],
            ['group' => 'general', 'key' => 'hospital_name', 'value' => 'RS Mata JEC Orbita Makassar', 'type' => 'string', 'description' => 'Nama rumah sakit'],
            ['group' => 'cleaning', 'key' => 'default_tolerance_minutes', 'value' => '30', 'type' => 'integer', 'description' => 'Toleransi keterlambatan default (menit)'],
            ['group' => 'cleaning', 'key' => 'max_photos_per_activity', 'value' => '4', 'type' => 'integer', 'description' => 'Maksimal foto per aktivitas'],
            ['group' => 'cleaning', 'key' => 'photo_max_size_kb', 'value' => '300', 'type' => 'integer', 'description' => 'Ukuran maks foto (KB)'],
            ['group' => 'audit', 'key' => 'minimum_passing_score', 'value' => '80', 'type' => 'integer', 'description' => 'Skor minimum audit lulus'],
            ['group' => 'notification', 'key' => 'enable_email', 'value' => 'true', 'type' => 'boolean', 'description' => 'Aktifkan notifikasi email'],
            ['group' => 'notification', 'key' => 'enable_whatsapp', 'value' => 'false', 'type' => 'boolean', 'description' => 'Aktifkan notifikasi WhatsApp'],
            ['group' => 'sla', 'key' => 'complaint_sla_hours', 'value' => '24', 'type' => 'integer', 'description' => 'SLA komplain (jam)'],
            ['group' => 'tv', 'key' => 'tv_refresh_interval', 'value' => '30', 'type' => 'integer', 'description' => 'Interval refresh Smart TV (detik)'],
        ];

        foreach ($settings as $setting) {
            Setting::create($setting);
        }
    }
}
