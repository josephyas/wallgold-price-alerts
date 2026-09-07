<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the demo data. Safe to run repeatedly: every record is looked up
     * before it is created, so restarting the stack never fails on duplicates.
     */
    public function run(): void
    {
        User::query()->firstOrCreate(
            ['email' => 'demo@example.com'],
            ['name' => 'Demo User', 'password' => Hash::make('password')],
        );
    }
}
