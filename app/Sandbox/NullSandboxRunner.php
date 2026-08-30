<?php

namespace App\Sandbox;

use RuntimeException;

/**
 * Bound on hosts that only queue runs - the web host. If a job ever executes
 * here the deployment is wrong, so fail loudly rather than run code outside
 * a sandbox.
 */
final class NullSandboxRunner implements SandboxRunner
{
    public function ensureReady(): void
    {
        throw new RuntimeException('Sandbox: SANDBOX_DRIVER is `none` on this host. Run the `sandbox` queue on the runner host.');
    }

    public function run(RunSpec $spec): RunResult
    {
        $this->ensureReady();

        throw new RuntimeException('unreachable');
    }

    /**
     * @return array<string, string>
     */
    public function runtimeLabels(): array
    {
        return array_map('strval', (array) config('sandbox.runtimes', []));
    }
}
