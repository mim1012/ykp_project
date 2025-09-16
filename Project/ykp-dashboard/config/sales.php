<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Sales Configuration
    |--------------------------------------------------------------------------
    | YKP ERP 시스템의 판매 관련 설정
    */

    // 기본 목표 설정 (Goals 테이블에 데이터가 없을 때 사용)
    'default_targets' => [
        'system' => [
            'monthly_sales' => env('DEFAULT_SYSTEM_MONTHLY_TARGET', 50000000), // 5천만원
            'monthly_activations' => env('DEFAULT_SYSTEM_ACTIVATION_TARGET', 200),
        ],
        'branch' => [
            'monthly_sales' => env('DEFAULT_BRANCH_MONTHLY_TARGET', 10000000), // 1천만원
            'monthly_activations' => env('DEFAULT_BRANCH_ACTIVATION_TARGET', 50),
        ],
        'store' => [
            'monthly_sales' => env('DEFAULT_STORE_MONTHLY_TARGET', 5000000), // 500만원
            'monthly_activations' => env('DEFAULT_STORE_ACTIVATION_TARGET', 25),
        ]
    ],

    // 판매 데이터 검증 규칙
    'validation' => [
        'max_daily_records' => 100,
        'max_bulk_records' => 1000,
        'required_fields' => ['sale_date', 'carrier', 'activation_type']
    ],

    // 성과 계산 설정
    'performance' => [
        'cache_duration' => 300, // 5분
        'ranking_limit' => 10,
        'trend_analysis_days' => 30
    ]
];