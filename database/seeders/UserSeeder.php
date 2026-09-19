<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Create a Test Client
        User::create([
            'name' => 'John Doe (Client)',
            'email' => 'client@example.com',
            'password' => Hash::make('password'),
            'role' => 'user',
            'available_credits' => 5000, // Demo credits; no cash conversion.
            'rate_per_hour' => null,
        ]);

        // 2. Create Counselor A
        User::create([
            'name' => 'Madame Zelda',
            'email' => 'zelda@example.com',
            'password' => Hash::make('password'),
            'role' => 'counselor',
            'available_credits' => 0,
            'rate_per_hour' => 60,
            'is_approved' => true,
        ]);

        // 3. Create Counselor B
        User::create([
            'name' => 'Oracle Orion',
            'email' => 'orion@example.com',
            'password' => Hash::make('password'),
            'role' => 'counselor',
            'available_credits' => 0,
            'rate_per_hour' => 120,
            'is_approved' => true, 
        ]);
    }
}
