<?php

namespace Database\Seeders;

use App\Enums\UserRole;
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
        // User::factory(10)->create();

        // Seeding runs as part of `composer setup`, so it has to survive a
        // second run on a database that is already populated.
        if (! User::query()->where('username', 'test')->exists()) {
            User::factory()->create([
                'name' => 'Test User',
                'username' => 'test',
                'email' => 'test@example.com',
            ]);
        }

        // Reviewer accounts for trying the two-stage approval locally.
        User::query()->updateOrCreate(
            ['username' => 'manager'],
            ['name' => '部署管理者', 'email' => 'manager@example.com', 'password' => 'password', 'role' => UserRole::Manager, 'department' => '開発', 'email_verified_at' => now()],
        );
        User::query()->updateOrCreate(
            ['username' => 'admin'],
            ['name' => 'システム管理者', 'email' => 'admin@example.com', 'password' => 'password', 'role' => UserRole::Admin, 'email_verified_at' => now()],
        );
    }
}
