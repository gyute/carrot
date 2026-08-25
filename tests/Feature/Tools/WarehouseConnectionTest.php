<?php

/**
 * .env.example ships the WAREHOUSE_DB_* keys blank so they are easy to fill
 * in. A blank env value is still a value, so the connection has to treat it
 * as "not set" and fall back to the application database - otherwise a fresh
 * checkout exports against host "".
 */
test('the warehouse connection falls back to the app database when its env vars are blank', function () {
    foreach (['HOST', 'PORT', 'DATABASE', 'USERNAME', 'PASSWORD', 'URL', 'SSLMODE'] as $key) {
        putenv("WAREHOUSE_DB_{$key}=");
        $_ENV["WAREHOUSE_DB_{$key}"] = '';
        $_SERVER["WAREHOUSE_DB_{$key}"] = '';
    }

    $warehouse = (require base_path('config/database.php'))['connections']['warehouse'];

    expect($warehouse['host'])->toBe(env('DB_HOST', '127.0.0.1'))
        ->and($warehouse['port'])->toBe(env('DB_PORT', '5432'))
        ->and($warehouse['database'])->toBe(env('DB_DATABASE', 'carrot'))
        ->and($warehouse['username'])->toBe(env('DB_USERNAME', 'carrot'))
        ->and($warehouse['sslmode'])->toBe('prefer')
        ->and($warehouse['url'])->toBeNull();
});
