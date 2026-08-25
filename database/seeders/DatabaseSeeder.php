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

        $this->call(AccessLogSeeder::class);
    }
}
