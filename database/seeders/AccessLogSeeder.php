<?php

namespace Database\Seeders;

use App\Models\AccessLog;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Fills the stand-in access log so the export tool has data to hand out.
 */
class AccessLogSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $usernames = User::query()->pluck('username');

        AccessLog::factory()
            ->count(600)
            ->sequence(fn (): array => [
                'username' => $usernames->isEmpty()
                    ? fake()->userName()
                    : $usernames->random(),
            ])
            ->create();
    }
}
