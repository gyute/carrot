<?php

/**
 * Edit this file and re-run `php artisan demo:seed --fresh` to publish it.
 */
$inputs = json_decode((string) file_get_contents((string) getenv('TOOL_INPUTS')), true) ?? [];

printf("こんにちは、%s さん。\n", $inputs['name'] ?? 'world');
printf("PHP %s / uid %d / %s\n", PHP_VERSION, function_exists('posix_geteuid') ? posix_geteuid() : -1, date('c'));
