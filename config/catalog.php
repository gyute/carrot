<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Departments
    |--------------------------------------------------------------------------
    |
    | The department a tool or a user may belong to, comma separated in
    | CATALOG_DEPARTMENTS, in display order. Forms offer exactly this list and
    | validation rejects anything else. Leave it unset and the field falls
    | back to free text: an org chart is not this repository's data.
    |
    */

    'departments' => array_values(array_filter(
        array_map('trim', explode(',', (string) env('CATALOG_DEPARTMENTS', ''))),
        fn (string $department): bool => $department !== '',
    )),

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    |
    | Which halves of the tool module this deployment runs.
    |
    | `submissions` says who may register a tool: `all`, `admin` (the
    | development team files and approves its own submissions) or `none` (no
    | new tools at all, and the submission and approval screens are gone).
    | `none` is a deployment-time decision, not a switch to flip while people
    | have requests in flight - their pending submissions become unreachable.
    | `all` and `admin` are safe to change at any time.
    |
    | `requests` is the ask-the-development-team queue at /tools/requests.
    |
    */

    'features' => [
        'submissions' => env('CATALOG_SUBMISSIONS', 'all'),
        'requests' => (bool) env('CATALOG_REQUESTS', true),
    ],

];
