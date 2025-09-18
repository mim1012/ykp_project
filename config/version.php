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

    'current' => '2.1.0',

    /*
    |--------------------------------------------------------------------------
    | Version Release Date
    |--------------------------------------------------------------------------
    |
    | The date when this version was released.
    |
    */

    'released_at' => '2025-01-19',

    /*
    |--------------------------------------------------------------------------
    | Version Codename
    |--------------------------------------------------------------------------
    |
    | Optional codename for this release version.
    |
    */

    'codename' => 'Phoenix',

    /*
    |--------------------------------------------------------------------------
    | Build Information
    |--------------------------------------------------------------------------
    |
    | Additional build information for tracking.
    |
    */

    'build' => [
        'number' => env('BUILD_NUMBER', 'prod'),
        'commit' => env('BUILD_COMMIT', 'none'),
        'branch' => env('BUILD_BRANCH', 'main'),
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
        '2.1.0' => '2025-01-19', // Phoenix - Railway deployment stabilization
        '2.0.0' => '2025-01-18', // Evolution - Complete infrastructure overhaul
        '1.2.0' => '2025-09-17', // Emergency release - Dashboard data binding fix
        '1.1.1' => '2025-09-13', // UI/UX fixes and accuracy improvements
        '1.1.0' => '2025-09-13', // PostgreSQL compatibility and account management
        '1.0.0' => '2025-09-13', // First production release - Railway optimization
        '0.9.0' => '2025-01-10', // Beta release
        '0.8.0' => '2025-01-05', // Alpha release
        '0.7.0' => '2024-12-20', // Initial development
    ],
];