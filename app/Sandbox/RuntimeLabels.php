<?php

namespace App\Sandbox;

use Throwable;

/**
 * What the configured driver runs scripts with, for showing to people who
 * write or read them. The web host binds the null driver, which reads the
 * labels from config; a driver that cannot answer falls back to config too
 * rather than break a form.
 */
class RuntimeLabels
{
    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        try {
            return app(SandboxRunner::class)->runtimeLabels();
        } catch (Throwable) {
            return array_map('strval', (array) config('sandbox.runtimes', []));
        }
    }

    public function for(string $runtime): string
    {
        return $this->all()[$runtime] ?? $runtime;
    }
}
