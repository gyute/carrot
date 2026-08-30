<?php

namespace App\Sandbox;

use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * The per-run directory the sandbox reads from: the entrypoint and the
 * inputs file, and nothing else. World-readable so a non-root user inside a
 * user namespace can open it; removed when the run is over.
 */
final class Workdir
{
    private function __construct(public readonly string $path) {}

    public static function create(RunSpec $spec, string $base): self
    {
        $path = rtrim($base, '/').'/'.$spec->id;

        if (! File::isDirectory($path) && ! File::makeDirectory($path, 0755, true)) {
            throw new RuntimeException("Could not create sandbox workdir {$path}.");
        }

        File::put($path.'/'.$spec->entrypoint(), $spec->source);
        File::put($path.'/inputs.json', json_encode($spec->inputs, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        File::chmod($path.'/'.$spec->entrypoint(), 0644);
        File::chmod($path.'/inputs.json', 0644);

        return new self($path);
    }

    public function remove(): void
    {
        File::deleteDirectory($this->path);
    }
}
