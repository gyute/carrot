<?php

namespace Database\Factories;

use App\Enums\ToolRequestPriority;
use App\Enums\ToolRequestStatus;
use App\Models\Tool;
use App\Models\ToolRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ToolRequest>
 */
class ToolRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => ToolRequestStatus::Open,
            'title' => '請求書の消費税をまとめて計算したい',
            'body' => "今は電卓で一件ずつ計算しています。\n件数が多い月は半日かかります。",
            'department' => '開発',
            'categories' => ['データ'],
            'desired_kind' => null,
        ];
    }

    public function accepted(): static
    {
        return $this->state(fn (): array => [
            'status' => ToolRequestStatus::Accepted,
            'priority' => ToolRequestPriority::Normal,
            'decided_by' => User::factory()->admin(),
            'decided_at' => now(),
        ]);
    }

    public function delivered(Tool $tool): static
    {
        return $this->state(fn (): array => [
            'status' => ToolRequestStatus::Delivered,
            'tool_id' => $tool->id,
            'decided_by' => User::factory()->admin(),
            'decided_at' => now(),
        ]);
    }
}
