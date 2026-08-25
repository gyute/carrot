<?php

namespace Database\Factories;

use App\Enums\ExportJobStatus;
use App\Models\ExportJob;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExportJob>
 */
class ExportJobFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'definition' => 'daily_access_log',
            'status' => ExportJobStatus::Queued,
            'download_code' => ExportJob::newDownloadCode(),
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => ExportJobStatus::Completed,
            'row_count' => 3,
            'file_path' => 'exports/completed.csv',
            'file_size' => 128,
            'started_at' => now()->subMinute(),
            'completed_at' => now(),
            'expires_at' => now()->addDays(7),
        ]);
    }

    public function expired(): static
    {
        return $this->completed()->state(fn (): array => [
            'expires_at' => now()->subDay(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => ExportJobStatus::Failed,
            'error_message' => 'relation "missing" does not exist',
            'started_at' => now()->subMinute(),
            'completed_at' => now(),
        ]);
    }
}
