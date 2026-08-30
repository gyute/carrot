<?php

namespace App\Sandbox;

/**
 * Returns a canned result and records what it was asked to run. Bound in
 * tests, and usable on a machine with no isolation at all - it never
 * executes anything.
 */
final class FakeSandboxRunner implements SandboxRunner
{
    /** @var array<int, RunSpec> */
    public array $specs = [];

    public function __construct(private ?RunResult $result = null) {}

    public function ensureReady(): void {}

    public function willReturn(RunResult $result): void
    {
        $this->result = $result;
    }

    public function run(RunSpec $spec): RunResult
    {
        $this->specs[] = $spec;

        return $this->result ?? new RunResult(0, "fake: {$spec->runtime}\n", '', 12);
    }

    public function lastSpec(): ?RunSpec
    {
        return $this->specs[array_key_last($this->specs) ?? -1] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public function runtimeLabels(): array
    {
        return array_map('strval', (array) config('sandbox.runtimes', []));
    }
}
