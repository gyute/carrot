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

        'users' => [
            'label' => '利用者一覧',
            'description' => 'CARROT に登録されている利用者を出力します。',
            'connection' => env('EXPORT_CONNECTION', 'warehouse'),
            'sql' => 'select id, username, name, email, created_at from users order by id',
        ],

        'user_registrations' => [
            'label' => '月別登録者数',
            'description' => '利用者の登録数を月ごとに集計します。',
            'connection' => env('EXPORT_CONNECTION', 'warehouse'),
            'sql' => "select to_char(created_at, 'YYYY-MM') as month, count(*) as users from users group by 1 order by 1",
        ],

    ],

];
