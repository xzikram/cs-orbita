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
        $users = [];

        foreach ($users as $user) {
            User::firstOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'employee_id' => $user['employee_id'],
                    'phone' => $user['phone'],
                    'role' => $user['role'],
                    'password' => $user['password'],
                ]
            );
        }
    }
}
