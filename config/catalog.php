<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Departments
    |--------------------------------------------------------------------------
    |
    | The departments a tool may belong to, as a comma separated list in
    | CATALOG_DEPARTMENTS. Forms offer exactly this list and validation
    | rejects anything else, so the 所属 filter in the catalog stays a clean,
    | finite set. Order is display order.
    |
    | Leave it unset and the field falls back to free text: the list is an
    | organisation's own data and does not belong in the repository.
    |
    */

    'departments' => array_values(array_filter(
        array_map('trim', explode(',', (string) env('CATALOG_DEPARTMENTS', ''))),
        fn (string $department): bool => $department !== '',
    )),

];
