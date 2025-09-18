<?php

/**
 * Railway 환경변수를 강제로 .env 파일에 쓰기
 */
echo '<h1>🔧 Railway 환경변수 강제 적용</h1>';

// Railway 환경변수들
$railwayVars = [
    'APP_KEY' => $_ENV['APP_KEY'] ?? getenv('APP_KEY') ?? '',
    'APP_ENV' => $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?? 'production',
    'APP_DEBUG' => $_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG') ?? 'false',
    'DATABASE_URL' => $_ENV['DATABASE_URL'] ?? getenv('DATABASE_URL') ?? '',
    'DB_CONNECTION' => $_ENV['DB_CONNECTION'] ?? getenv('DB_CONNECTION') ?? 'pgsql',
];

echo '<h2>📋 Railway 환경변수 상태</h2>';
foreach ($railwayVars as $key => $value) {
    $displayValue = $value;
    if (in_array($key, ['APP_KEY', 'DATABASE_URL']) && $value) {
        $displayValue = substr($value, 0, 20).'...';
    }
    $status = $value ? '✅' : '❌';
    echo "<p>$status $key: <code>$displayValue</code></p>";
}

// .env 파일 강제 생성
$envContent = "# Railway Environment Variables (Force Generated)\n";
$envContent .= "APP_NAME=\"YKP ERP\"\n";

foreach ($railwayVars as $key => $value) {
    if ($value) {
        if ($key === 'DATABASE_URL') {
            $envContent .= "$key=\"$value\"\n";
        } else {
            $envContent .= "$key=$value\n";
        }
    }
}

// 추가 필수 설정
$envContent .= "\n# Additional Laravel Settings\n";
$envContent .= "APP_URL=https://ykpproject-production.up.railway.app\n";
$envContent .= "LOG_CHANNEL=stack\n";
$envContent .= "LOG_LEVEL=error\n";
$envContent .= "CACHE_STORE=array\n";
$envContent .= "SESSION_DRIVER=array\n";
$envContent .= "QUEUE_CONNECTION=sync\n";

// .env 파일 쓰기
$envPath = '../.env';
$success = file_put_contents($envPath, $envContent);

echo '<h2>📝 .env 파일 생성 결과</h2>';
if ($success !== false) {
    echo "<p>✅ .env 파일 생성 성공! ($success bytes)</p>";

    echo '<h3>생성된 .env 내용:</h3>';
    echo "<pre style='background: #f8f9fa; padding: 15px; border-radius: 5px;'>";
    echo htmlspecialchars($envContent);
    echo '</pre>';

    // 캐시 클리어
    echo '<h2>🧹 Laravel 캐시 클리어</h2>';
    $cacheFiles = [
        '../bootstrap/cache/config.php',
        '../bootstrap/cache/routes.php',
        '../storage/framework/cache/data',
    ];

    foreach ($cacheFiles as $file) {
        if (file_exists($file)) {
            if (is_dir($file)) {
                $files = glob($file.'/*');
                foreach ($files as $f) {
                    if (is_file($f)) {
                        unlink($f);
                    }
                }
                echo '<p>🗑️ 디렉토리 클리어: '.basename($file).'</p>';
            } else {
                unlink($file);
                echo '<p>🗑️ 파일 삭제: '.basename($file).'</p>';
            }
        }
    }

    echo '<hr>';
    echo "<div style='background: #d4edda; padding: 20px; border-radius: 8px;'>";
    echo '<h2>🎉 환경변수 강제 적용 완료!</h2>';
    echo "<p>🔄 <a href='/laravel-test.php' style='font-weight: bold;'>Laravel 테스트 다시 실행</a></p>";
    echo "<p>🏠 <a href='/' style='font-weight: bold; color: green;'>메인 사이트 확인</a></p>";
    echo '</div>';

} else {
    echo '<p>❌ .env 파일 생성 실패</p>';
}

echo '<hr><small>Generated at: '.date('Y-m-d H:i:s T').'</small>';
