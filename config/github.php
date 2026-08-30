<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Repository
    |--------------------------------------------------------------------------
    |
    | Where the catalog is mirrored, as `owner/name`. Leave either this or the
    | token unset and the mirror is off: nothing is called, nothing is queued,
    | and the portal behaves exactly as it did before.
    |
    | The repository has to be a private one, and not this repository: what is
    | written there is an organisation's internal tooling.
    |
    */

    'repository' => env('GITHUB_REPOSITORY'),

    'token' => env('GITHUB_TOKEN'),

    'branch' => env('GITHUB_BRANCH', 'main'),

    /*
    |--------------------------------------------------------------------------
    | API
    |--------------------------------------------------------------------------
    |
    | github.com unless this points at a GitHub Enterprise install, whose API
    | lives under https://<host>/api/v3.
    |
    */

    'api_url' => rtrim((string) env('GITHUB_API_URL', 'https://api.github.com'), '/'),

    'timeout' => (int) env('GITHUB_TIMEOUT', 15),

    /*
    |--------------------------------------------------------------------------
    | Layout
    |--------------------------------------------------------------------------
    |
    | Each tool is a directory under this path: tool.json for everything but
    | the source, and source.php / source.sh for a script.
    |
    | Nobody's name and no department is written there. Git only ever adds, so
    | a name committed once cannot be taken back - which would undo the whole
    | point of retiring a person rather than deleting them. People are written
    | as ULIDs and resolved back to names by the portal.
    |
    */

    'path' => trim((string) env('GITHUB_PATH', 'tools'), '/'),

    'committer' => [
        'name' => env('GITHUB_COMMITTER_NAME', 'CARROT'),
        'email' => env('GITHUB_COMMITTER_EMAIL', 'carrot@users.noreply.github.com'),
    ],

];
