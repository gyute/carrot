<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    |
    | Generated CSV files are written to this disk, which must stay private:
    | files are only ever served through the download route. Finished exports
    | are downloadable until they expire, after which they may be pruned.
    |
    */

    'disk' => env('EXPORT_DISK', 'local'),

    'directory' => 'exports',

    'retention_days' => (int) env('EXPORT_RETENTION_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Export definitions
    |--------------------------------------------------------------------------
    |
    | Every export is a named, reviewed query. Users pick a definition by key,
    | they never send SQL, so nothing user supplied reaches the database. Add a
    | definition here to publish a new export; 'connection' names a connection
    | from config/database.php, so an export can read from the warehouse.
    |
    */

    'definitions' => [

        'daily_access_log' => [
            'label' => '日次アクセスログ',
            'description' => 'ポータルへのアクセス履歴を 1 件ずつ出力します。',
            'connection' => env('EXPORT_CONNECTION', 'warehouse'),
            'sql' => 'select accessed_at, username, path, status_code, duration_ms, ip_address from access_logs order by accessed_at desc',
        ],

        'daily_access_summary' => [
            'label' => '日次アクセス集計',
            'description' => 'アクセス数と利用者数を日ごとに集計します。',
            'connection' => env('EXPORT_CONNECTION', 'warehouse'),
            'sql' => 'select date(accessed_at) as date, count(*) as accesses, count(distinct username) as users from access_logs group by 1 order by 1 desc',
        ],

    ],

];
