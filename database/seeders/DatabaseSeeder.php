<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::query()->create([
            'username' => 'Test User',
            'email' => 'test@example.com',
            'email_verified_at' => now(),
            'birth_date' => today()->subYears(25),
            'password' => 'password',
        ]);
    }
}
