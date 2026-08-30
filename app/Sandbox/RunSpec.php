<?php

namespace App\Sandbox;

/**
 * Everything a runner needs to execute one script: which runtime, the code,
 * the inputs the user typed, and the limits the tool was approved with.
 */
final readonly class RunSpec
{
    /**
     * @param  array<string, mixed>  $inputs
     */
    public function __construct(
        public string $id,
        public string $runtime,
        public string $source,
        public array $inputs,
        public int $timeoutSec,
        public int $memoryMb,
        public string $network = self::NETWORK_NONE,
    ) {}

    public const NETWORK_NONE = 'none';

    public const NETWORK_INTERNET = 'internet';

    public function hasNetwork(): bool
    {
        return $this->network === self::NETWORK_INTERNET;
    }

    /**
     * The file the source is written to inside the work directory.
     */
    public function entrypoint(): string
    {
        return $this->runtime === 'php' ? 'main.php' : 'main.sh';
    }

    /**
     * The command run inside the sandbox. `$dir` is where the work directory
     * is mounted; Docker mounts it at /work, bubblewrap under /tmp.
     *
     * @return array<int, string>
     */
    public function command(string $dir = '/work'): array
    {
        return $this->runtime === 'php'
            ? ['php', '-d', 'memory_limit='.max(8, $this->memoryMb - 32).'M', "{$dir}/main.php"]
            : ['sh', "{$dir}/main.sh"];
    }
}
