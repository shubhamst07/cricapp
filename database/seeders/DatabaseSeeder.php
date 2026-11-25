<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Ensure Admin users exist with permanent developer credentials
        User::firstOrCreate(
            ['email' => 'shubhamthakurst0007@gmail.com'],
            [
                'name' => 'Shubham Thakur',
                'password' => Hash::make('developer'),
                'is_admin' => true,
                'email_verified_at' => now(),
            ]
        );

        User::firstOrCreate(
            ['email' => 'shubhamthakurst07@gmail.com'],
            [
                'name' => 'Shubham Thakur',
                'password' => Hash::make('developer'),
                'is_admin' => true,
                'email_verified_at' => now(),
            ]
        );

        // 2. Seed starter articles
        $this->call(TrendingArticlesSeeder::class);
    }
}

