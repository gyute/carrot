<?php

namespace Database\Factories;

use App\Enums\ToolRunStatus;
use App\Models\Tool;
use App\Models\ToolRun;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ToolRun>
 */
class ToolRunFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tool_id' => Tool::factory()->script(),
            'user_id' => User::factory(),
            'runtime' => 'php',
            'source_hash' => hash('sha256', "<?php\necho 'hello';\n"),
            'status' => ToolRunStatus::Queued,
            'inputs' => [],
        ];
    }

    public function completed(string $stdout = "hello\n"): static
    {
        return $this->state(fn (): array => [
            'status' => ToolRunStatus::Completed,
            'exit_code' => 0,
            'stdout' => $stdout,
            'stderr' => '',
            'duration_ms' => 40,
            'started_at' => now()->subSecond(),
            'finished_at' => now(),
        ]);
    }
}
