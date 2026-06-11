<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CSUsersSeeder extends Seeder
{
    public function run(): void
    {
        $csNames = [
            'Fadil',
            'Ali',
            'Salmi',
            'Haris',
            'Sahril',
            'Arfah',
            'firman',
            'Rosma',
            'Dewi',
            'Yusran',
            'nur patima',
            'Yasin parkiran',
            'Eko',
            'putra'
        ];

        // Start from 002 based on user request
        $index = 2;

        foreach ($csNames as $name) {
            $username = 'cs' . str_pad($index, 3, '0', STR_PAD_LEFT);
            
            User::firstOrCreate(
                ['username' => $username],
                [
                    'name' => $name,
                    'email' => null,
                    'password' => Hash::make($username), // password sama dengan username
                    'role' => 'cleaning_service',
                ]
            );
            
            $index++;
        }
    }
}
