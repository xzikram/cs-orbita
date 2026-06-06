<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class JanitorUserSeeder extends Seeder
{
    public function run(): void
    {
        $janitors = [
            'Lantai SB',
            'Lantai 1',
            'Lantai 2',
            'Lantai 3',
            'Lantai 4',
            'Lantai 5',
            'Lantai 6',
            'Lantai 7',
            'Lantai 8',
            'Lantai 9',
            'Lantai 10',
        ];

        foreach ($janitors as $janitorLocation) {
            $name = "Janitor {$janitorLocation}";
            
            // Format username, e.g., "janitor_sb", "janitor_1"
            $username = strtolower(str_replace(' ', '_', $name));
            $username = str_replace('lantai_', '', $username);

            User::firstOrCreate(
                ['username' => $username],
                [
                    'name' => $name,
                    'email' => null,
                    'password' => Hash::make($username), // password = username
                    'role' => 'cleaning_service',
                ]
            );

            $this->command->info("Created user: {$name} with username: {$username}");
        }
    }
}
