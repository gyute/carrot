<?php

namespace App\Sandbox;

/**
 * Collects a process stream up to a byte limit; anything past it is dropped
 * and the buffer remembers that it cut something.
 */
final class OutputBuffer
{
    private string $data = '';

    private bool $truncated = false;

    public function __construct(private int $limit) {}

    public function append(string $chunk): void
    {
        $room = $this->limit - strlen($this->data);

        if ($room <= 0) {
            $this->truncated = $this->truncated || $chunk !== '';

            return;
        }

        if (strlen($chunk) > $room) {
            $this->data .= substr($chunk, 0, $room);
            $this->truncated = true;

            return;
        }

        $this->data .= $chunk;
    }

    public function contents(): string
    {
        return $this->truncated ? $this->data."\n…(truncated)" : $this->data;
    }

    public function truncated(): bool
    {
        return $this->truncated;
    }
}
