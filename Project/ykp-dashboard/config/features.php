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
        ]
    ]
];