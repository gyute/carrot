<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Sandbox driver
    |--------------------------------------------------------------------------
    |
    | How script tools are run. `docker` spawns a throwaway container per run
    | and is what the runner host uses; `bubblewrap` is a local stand-in on a
    | machine without rootless Docker; `fake` returns canned results for tests;
    | `none` is for hosts that only queue runs and never execute them - the web
    | host - and throws if asked to run anything.
    |
    */

    'driver' => env('SANDBOX_DRIVER', 'none'),

    'binary' => env('SANDBOX_BINARY', 'docker'),

    'images' => [
        'php' => env('SANDBOX_IMAGE_PHP', 'carrot-sandbox-php:8.3'),
        'shell' => env('SANDBOX_IMAGE_SHELL', 'carrot-sandbox-shell:3.20'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Runtime labels
    |--------------------------------------------------------------------------
    |
    | What a person registering a script is told they are writing for. These
    | describe the images above - keep them in step when an image is bumped.
    | The bubblewrap driver ignores them and reports the host's own versions.
    |
    */

    'runtimes' => [
        'php' => env('SANDBOX_RUNTIME_PHP', 'PHP 8.3 (php:8.3-cli-alpine)'),
        'shell' => env('SANDBOX_RUNTIME_SHELL', 'Shell - BusyBox sh + coreutils + jq (alpine:3.20)'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Limits
    |--------------------------------------------------------------------------
    |
    | Upper bounds a tool's own config may not exceed. Output past
    | `output_bytes` is cut and marked as truncated.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Internet access
    |--------------------------------------------------------------------------
    |
    | Runs default to `--network none`. A tool approved with network
    | `internet` is attached to this Docker network instead - point it at a
    | bridge whose egress you control (a firewall or allowlist proxy).
    |
    */

    'internet_network' => env('SANDBOX_INTERNET_NETWORK', 'bridge'),

    'timeout_max' => (int) env('SANDBOX_TIMEOUT_MAX', 120),
    'memory_max' => (int) env('SANDBOX_MEMORY_MAX', 512),
    'cpus' => env('SANDBOX_CPUS', '0.5'),
    'pids' => (int) env('SANDBOX_PIDS', 64),
    'output_bytes' => (int) env('SANDBOX_OUTPUT_BYTES', 262144),

    /** Directory the per-run work directories are created under. */
    'workdir' => env('SANDBOX_WORKDIR', storage_path('app/sandbox')),

    /** Refuse to run against a root dockerd; see docs/sandbox in the README. */
    'require_rootless' => (bool) env('SANDBOX_REQUIRE_ROOTLESS', true),

    'rate_limit_per_minute' => (int) env('SANDBOX_RATE_LIMIT', 5),

    /** Finished runs older than this are pruned by `carrot:prune-runs`. */
    'run_retention_days' => (int) env('SANDBOX_RUN_RETENTION_DAYS', 30),

];
