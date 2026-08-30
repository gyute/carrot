<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Departments
    |--------------------------------------------------------------------------
    |
    | The 所属 a tool or a user may belong to, comma separated in
    | CATALOG_DEPARTMENTS, in display order. Forms offer exactly this list and
    | validation rejects anything else. Leave it unset and the field falls
    | back to free text: an org chart is not this repository's data.
    |
    */

    'departments' => array_values(array_filter(
        array_map('trim', explode(',', (string) env('CATALOG_DEPARTMENTS', ''))),
        fn (string $department): bool => $department !== '',
    )),

];
