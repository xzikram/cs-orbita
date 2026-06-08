<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Enums\RoleEnum;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name' => 'Administrator',
                'email' => 'admin@cleantrack.id',
                'employee_id' => 'ADM001',
                'phone' => '081234567890',
                'role' => RoleEnum::ADMINISTRATOR,
                'password' => Hash::make('password'),
            ],
            [
                'name' => 'Supervisor CS',
                'email' => 'supervisor@cleantrack.id',
                'employee_id' => 'SPV001',
                'phone' => '081234567891',
                'role' => RoleEnum::SUPERVISOR,
                'password' => Hash::make('password'),
            ],
            [
                'name' => 'Dr. Ahmad - Kepala Poli Retina',
                'email' => 'kepala.retina@cleantrack.id',
                'employee_id' => 'KR001',
                'phone' => '081234567892',
                'role' => RoleEnum::KEPALA_RUANGAN,
                'password' => Hash::make('password'),
            ],
            [
                'name' => 'Direktur RS',
                'email' => 'direktur@cleantrack.id',
                'employee_id' => 'MGT001',
                'phone' => '081234567893',
                'role' => RoleEnum::MANAJEMEN,
                'password' => Hash::make('password'),
            ],
            [
                'name' => 'Andi Pratama',
                'email' => 'andi@cleantrack.id',
                'employee_id' => 'CS001',
                'phone' => '081234567894',
                'role' => RoleEnum::CLEANING_SERVICE,
                'password' => Hash::make('password'),
            ],
        ];

        foreach ($users as $user) {
            User::create($user);
        }
    }
}
