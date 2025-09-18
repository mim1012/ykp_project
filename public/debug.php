<?php

/**
 * Railway Debug Information Page
 * Laravel 500 에러 디버깅용 임시 페이지
 */
echo '<h1>🐛 Railway Debug Info</h1>';

// PHP 정보
echo '<h2>📋 PHP Info</h2>';
echo 'PHP Version: '.phpversion().'<br>';
echo 'Server: '.$_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'.'<br>';
echo 'Document Root: '.$_SERVER['DOCUMENT_ROOT'] ?? 'Unknown'.'<br>';

// 환경변수 확인 (민감한 정보 마스킹)
echo '<h2>🔧 Environment Variables</h2>';
$env_vars = [
    'APP_ENV', 'APP_DEBUG', 'APP_KEY', 'APP_URL',
    'DB_CONNECTION', 'DATABASE_URL',
    'FEATURE_EXCEL_INPUT', 'FEATURE_ADVANCED_REPORTS',
];

foreach ($env_vars as $var) {
    $value = getenv($var) ?: $_ENV[$var] ?? 'Not Set';

    // 민감한 정보 마스킹
    if (in_array($var, ['APP_KEY', 'DATABASE_URL']) && $value !== 'Not Set') {
        $value = substr($value, 0, 10).'***MASKED***';
    }

    echo "$var: <code>$value</code><br>";
}

// Laravel 경로 확인
echo '<h2>📁 Laravel Paths</h2>';
$laravel_paths = [
    'Bootstrap' => '../bootstrap/app.php',
    'Vendor Autoload' => '../vendor/autoload.php',
    'Config' => '../config/app.php',
    '.env' => '../.env',
];

foreach ($laravel_paths as $name => $path) {
    $exists = file_exists($path) ? '✅' : '❌';
    echo "$name: $exists <code>$path</code><br>";
}

// Laravel 부트스트랩 시도
echo '<h2>🚀 Laravel Bootstrap Test</h2>';
try {
    if (file_exists('../vendor/autoload.php')) {
        require_once '../vendor/autoload.php';
        echo '✅ Autoload successful<br>';

        if (file_exists('../bootstrap/app.php')) {
            $app = require_once '../bootstrap/app.php';
            echo '✅ App bootstrap successful<br>';

            // 기본 설정 확인
            if (method_exists($app, 'make')) {
                $config = $app->make('config');
                echo '✅ Config service available<br>';
                echo 'App Name: '.$config->get('app.name', 'Unknown').'<br>';
                echo 'App Env: '.$config->get('app.env', 'Unknown').'<br>';
            }
        } else {
            echo '❌ Bootstrap file not found<br>';
        }
    } else {
        echo '❌ Vendor autoload not found<br>';
    }
} catch (Exception $e) {
    echo '❌ Laravel Bootstrap Error: '.$e->getMessage().'<br>';
    echo 'Stack trace:<br><pre>'.$e->getTraceAsString().'</pre>';
}

echo '<hr><small>Generated at: '.date('Y-m-d H:i:s T').'</small>';
