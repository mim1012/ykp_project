<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains various security settings for the YKP Dashboard
    | application including session timeouts, login attempt limits, etc.
    |
    */

    // Session timeout in minutes (default: 120 minutes = 2 hours)
    'session_timeout' => env('SESSION_TIMEOUT', 120),

    // Maximum login attempts before lockout
    'max_login_attempts' => env('MAX_LOGIN_ATTEMPTS', 5),

    // Login lockout duration in minutes
    'lockout_duration' => env('LOCKOUT_DURATION', 15),

    // Password requirements
    'password' => [
        'min_length' => 6,
        'require_uppercase' => false,
        'require_lowercase' => false,
        'require_numbers' => false,
        'require_symbols' => false,
    ],

    // RBAC settings
    'rbac' => [
        'enable_logging' => env('RBAC_LOGGING', true),
        'log_channel' => env('RBAC_LOG_CHANNEL', 'daily'),
    ],

    // API rate limiting
    'api_rate_limit' => [
        'enabled' => env('API_RATE_LIMIT_ENABLED', true),
        'max_attempts' => env('API_RATE_LIMIT_ATTEMPTS', 60),
        'decay_minutes' => env('API_RATE_LIMIT_DECAY', 1),
    ],

    // Development settings
    'development' => [
        'show_registration' => env('SHOW_REGISTRATION', true),
        'allow_demo_users' => env('ALLOW_DEMO_USERS', true),
    ],

];
