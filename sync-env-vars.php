<?php
/**
 * Sync Railway environment variables to .env file
 */

$envFile = __DIR__ . '/.env';

// Railway 환경변수를 .env에 동기화
$envVars = [
    'APP_KEY',
    'APP_ENV',
    'APP_DEBUG',
    'APP_URL',
    'DB_CONNECTION',
    'DB_HOST',
    'DB_PORT',
    'DB_DATABASE',
    'DB_USERNAME',
    'DB_PASSWORD',
    'DB_SSLMODE',
    'SESSION_DRIVER',
    'CACHE_STORE',
    'QUEUE_CONNECTION',
    'LOG_CHANNEL',
    'LOG_LEVEL',
    'SUPABASE_URL',
    'SUPABASE_ANON_KEY',
];

// .env 파일이 없으면 생성
if (!file_exists($envFile)) {
    copy(__DIR__ . '/.env.example', $envFile);
}

$envContent = file_get_contents($envFile);

foreach ($envVars as $var) {
    $value = getenv($var);
    if ($value !== false) {
        // .env 파일에서 해당 변수 업데이트
        $pattern = "/^{$var}=.*/m";
        if (preg_match($pattern, $envContent)) {
            $envContent = preg_replace($pattern, "{$var}={$value}", $envContent);
        } else {
            $envContent .= "\n{$var}={$value}";
        }
        echo "✅ Set {$var}\n";
    }
}

file_put_contents($envFile, $envContent);
echo "✅ Environment variables synced to .env\n";