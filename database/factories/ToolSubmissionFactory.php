<?php

namespace Database\Factories;

use App\Enums\SubmissionAction;
use App\Enums\SubmissionStatus;
use App\Models\Tool;
use App\Models\ToolSubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ToolSubmission>
 */
class ToolSubmissionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'tool_id' => null,
            'action' => SubmissionAction::Create,
            'status' => SubmissionStatus::Draft,
            'payload' => [
                'kind' => 'link',
                'name' => '新しいツール',
                'summary' => 'テスト用の申請です。',
                'description' => null,
                'icon' => 'link',
                'accent' => 'sky',
                'department' => '開発',
                'categories' => ['データ'],
                'config' => ['url' => 'https://tool.example/'],
                'source' => null,
            ],
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (): array => [
            'status' => SubmissionStatus::Pending,
            'submitted_at' => now(),
        ]);
    }

    public function script(string $source = "<?php\necho 'hello';\n", string $runtime = 'php'): static
    {
        return $this->state(fn (array $attributes): array => [
            'payload' => [
                ...$attributes['payload'],
                'kind' => 'script',
                'icon' => 'terminal',
                'config' => [
                    'runtime' => $runtime,
                    'timeout_sec' => 30,
                    'memory_mb' => 128,
                    'network' => 'none',
                    'inputs' => [],
                ],
                'source' => $source,
            ],
        ]);
    }

    public function updating(Tool $tool): static
    {
        return $this->state(fn (): array => [
            'tool_id' => $tool->id,
            'action' => SubmissionAction::Update,
            'payload' => [
                'config' => ['url' => 'https://changed.example/'],
                'source' => null,
            ],
        ]);
    }

    public function deprecating(Tool $tool): static
    {
        return $this->state(fn (): array => [
            'tool_id' => $tool->id,
            'action' => SubmissionAction::Deprecate,
            'payload' => [],
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => SubmissionStatus::Approved,
            'submitted_at' => now()->subHour(),
            'reviewed_at' => now(),
            'reviewer_id' => User::factory()->admin(),
        ]);
    }
}
