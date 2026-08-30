<?php

namespace App\Sandbox;

interface SandboxRunner
{
    /**
     * Check the host can run anything at all. Throws with a reason otherwise;
     * called once per worker before the first run.
     */
    public function ensureReady(): void;

    public function run(RunSpec $spec): RunResult;

    /**
     * Human-readable description of each runtime as this driver actually
     * runs it, keyed by runtime name. Shown to whoever writes a script.
     *
     * @return array<string, string>
     */
    public function runtimeLabels(): array;
}
