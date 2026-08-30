<?php

namespace App\Sandbox;

final readonly class RunResult
{
    public function __construct(
        public ?int $exitCode,
        public string $stdout,
        public string $stderr,
        public int $durationMs,
        public bool $timedOut = false,
        public bool $truncated = false,
    ) {}

    public function succeeded(): bool
    {
        return ! $this->timedOut && $this->exitCode === 0;
    }
}
