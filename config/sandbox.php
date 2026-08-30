<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Runtime labels
    |--------------------------------------------------------------------------
    |
    | What a person registering a script is told they are writing for. These
    | describe the images script tools run in - keep them in step when an
    | image is bumped.
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
    | Upper bounds a tool's own config may not exceed.
    |
    */

    'timeout_max' => (int) env('SANDBOX_TIMEOUT_MAX', 120),
    'memory_max' => (int) env('SANDBOX_MEMORY_MAX', 512),

];
