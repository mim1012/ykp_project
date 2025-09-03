<?php

return [
    'features' => [
        'excel_input_form' => [
            'enabled' => env('FEATURE_EXCEL_INPUT', false),
            'rollout_percentage' => env('FEATURE_EXCEL_INPUT_ROLLOUT', 0),
            'allowed_users' => array_filter(explode(',', env('FEATURE_EXCEL_INPUT_USERS', ''))),
            'allowed_roles' => ['headquarters', 'admin', 'developer'],
            'description' => '엑셀 스타일 개통표 입력 시스템'
        ],
        
        // 향후 추가할 기능들
        'advanced_reports' => [
            'enabled' => env('FEATURE_ADVANCED_REPORTS', false),
            'rollout_percentage' => 0,
            'allowed_users' => [],
            'allowed_roles' => ['headquarters'],
            'description' => '고급 리포트 시스템'
        ],

        // UI V2 단계적 롤아웃
        'ui_v2' => [
            'enabled' => env('FEATURE_UI_V2', false),
            'rollout_percentage' => env('FEATURE_UI_V2_ROLLOUT', 0),
            'allowed_users' => array_filter(explode(',', env('FEATURE_UI_V2_USERS', ''))),
            'allowed_roles' => ['developer','headquarters'],
            'description' => '차세대 UI 단계적 적용'
        ],

        // Supabase 연동 강화(실시간/서비스키 기반) 순차 오픈
        'supabase_enhanced' => [
            'enabled' => env('FEATURE_SUPABASE_ENHANCED', false),
            'rollout_percentage' => env('FEATURE_SUPABASE_ROLLOUT', 0),
            'allowed_users' => array_filter(explode(',', env('FEATURE_SUPABASE_USERS', ''))),
            'allowed_roles' => ['developer','headquarters'],
            'description' => 'Supabase 연동 고도화'
        ],
    ]
];
