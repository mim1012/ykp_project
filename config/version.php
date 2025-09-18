<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Application Version
    |--------------------------------------------------------------------------
    |
    | This value is the version of your application. This value is used when
    | the framework needs to place the application's version in a notification
    | or any other location as required by the application or its packages.
    |
    */

    'current' => '1.0.0',

    /*
    |--------------------------------------------------------------------------
    | Version Release Date
    |--------------------------------------------------------------------------
    |
    | The date when this version was released.
    |
    */

    'released_at' => '2025-01-18',

    /*
    |--------------------------------------------------------------------------
    | Version Codename
    |--------------------------------------------------------------------------
    |
    | Optional codename for this release version.
    |
    */

    'codename' => 'Genesis',

    /*
    |--------------------------------------------------------------------------
    | Build Information
    |--------------------------------------------------------------------------
    |
    | Additional build information for tracking.
    |
    */

    'build' => [
        'number' => env('BUILD_NUMBER', 'local'),
        'commit' => env('BUILD_COMMIT', trim(exec('git rev-parse --short HEAD') ?: 'unknown')),
        'branch' => env('BUILD_BRANCH', trim(exec('git rev-parse --abbrev-ref HEAD') ?: 'unknown')),
        'timestamp' => env('BUILD_TIMESTAMP', date('Y-m-d H:i:s')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Version History
    |--------------------------------------------------------------------------
    |
    | Previous versions for reference.
    |
    */

    'history' => [
        '0.9.0' => '2025-01-10', // Beta release
        '0.8.0' => '2025-01-05', // Alpha release
        '0.7.0' => '2024-12-20', // Initial development
    ],
];