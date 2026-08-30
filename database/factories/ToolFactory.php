<?php

namespace Database\Factories;

use App\Enums\ToolKind;
use App\Enums\ToolStatus;
use App\Models\Tool;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tool>
 */
class ToolFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word().' '.fake()->word();

        return [
            'slug' => Str::slug($name),
            'kind' => ToolKind::Link,
            'name' => Str::title($name),
            'summary' => fake()->sentence(),
            'icon' => 'wrench',
            'accent' => 'slate',
            'status' => ToolStatus::Running,
            'owner_id' => User::factory(),
            'department' => fake()->company(),
            'config' => ['url' => fake()->url()],
            'version' => now()->format('YmdHi'),
            'published_at' => now(),
        ];
    }

    public function embed(string $url = 'https://example.com/'): static
    {
        return $this->state(fn (): array => [
            'kind' => ToolKind::Embed,
            'icon' => 'app-window',
            'config' => ['url' => $url],
        ]);
    }

    public function link(string $url): static
    {
        return $this->state(fn (): array => [
            'kind' => ToolKind::Link,
            'config' => ['url' => $url],
        ]);
    }

    public function script(string $source = "<?php\necho 'hello';\n", string $runtime = 'php'): static
    {
        return $this->state(fn (): array => [
            'kind' => ToolKind::Script,
            'icon' => 'terminal',
            'config' => [
                'runtime' => $runtime,
                'timeout_sec' => 30,
                'memory_mb' => 128,
                'network' => 'none',
                'inputs' => [],
            ],
            'source' => $source,
            'source_hash' => hash('sha256', $source),
        ]);
    }

    public function deprecated(): static
    {
        return $this->state(fn (): array => [
            'status' => ToolStatus::Deprecated,
            'deprecated_at' => now(),
        ]);
    }
}
