<?php
/**
 * Railway 환경변수를 기반으로 .env 파일 생성
 */

// .env 파일 내용 생성
$envContent = "# Generated for Railway deployment\n";
$envContent .= "APP_NAME=\"YKP ERP\"\n";
$envContent .= "APP_ENV=" . ($_ENV['APP_ENV'] ?? 'production') . "\n";
$envContent .= "APP_DEBUG=" . ($_ENV['APP_DEBUG'] ?? 'false') . "\n";
$envContent .= "APP_URL=" . ($_ENV['APP_URL'] ?? 'https://ykpproject-production.up.railway.app') . "\n";
$envContent .= "APP_KEY=" . ($_ENV['APP_KEY'] ?? '') . "\n\n";

$envContent .= "# Database\n";
$envContent .= "DB_CONNECTION=pgsql\n";
if (isset($_ENV['DATABASE_URL'])) {
    $envContent .= "DATABASE_URL=\"" . $_ENV['DATABASE_URL'] . "\"\n";
}

$envContent .= "\n# Features\n";
$envContent .= "FEATURE_EXCEL_INPUT=true\n";
$envContent .= "FEATURE_ADVANCED_REPORTS=true\n";

$envContent .= "\n# Cache & Session\n";
$envContent .= "CACHE_STORE=database\n";
$envContent .= "SESSION_DRIVER=database\n";

// .env 파일 쓰기 시도
$envPath = '../.env';
$success = file_put_contents($envPath, $envContent);

echo "<h1>🔧 .env File Creation</h1>";

if ($success !== false) {
    echo "<p>✅ .env 파일 생성 성공! ($success bytes written)</p>";
    
    if (file_exists($envPath)) {
        echo "<p>✅ .env 파일 확인됨</p>";
        echo "<h2>📄 생성된 .env 내용:</h2>";
        echo "<pre>" . htmlspecialchars($envContent) . "</pre>";
    }
    
    echo "<p>🔄 <a href='/'>메인 사이트로 이동하기</a></p>";
    
} else {
    echo "<p>❌ .env 파일 생성 실패</p>";
    echo "<p>Current directory: " . getcwd() . "</p>";
    echo "<p>Target path: $envPath</p>";
    echo "<p>Directory writable: " . (is_writable('..') ? 'YES' : 'NO') . "</p>";
}

echo "<hr>";
echo "<p><a href='/env-check.php'>env-check로 다시 확인하기</a></p>";
?>