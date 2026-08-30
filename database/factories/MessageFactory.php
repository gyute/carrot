<?php

namespace Database\Factories;

use App\Enums\MessageKind;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'recipient_id' => User::factory(),
            'sender_id' => null,
            'kind' => MessageKind::Direct,
            'subject' => fake()->sentence(4),
            'body' => fake()->paragraph(),
        ];
    }

    public function read(): static
    {
        return $this->state(fn (): array => ['read_at' => now()]);
    }
}
