<?php
/**
 * Debug script for Railway 502 errors
 */

header('Content-Type: text/plain; charset=utf-8');
echo "=== YKP Dashboard Debug Info ===\n\n";

// 1. PHP 정보
echo "1. PHP Version: " . PHP_VERSION . "\n";
echo "   SAPI: " . PHP_SAPI . "\n";
echo "   Memory Limit: " . ini_get('memory_limit') . "\n\n";

// 2. 파일 시스템 체크
echo "2. File System Check:\n";
$checks = [
    'vendor/autoload.php' => __DIR__ . '/../vendor/autoload.php',
    '.env' => __DIR__ . '/../.env',
    'bootstrap/app.php' => __DIR__ . '/../bootstrap/app.php',
    'storage/logs' => __DIR__ . '/../storage/logs',
];

foreach ($checks as $name => $path) {
    echo "   - $name: " . (file_exists($path) ? "✅ EXISTS" : "❌ MISSING") . "\n";
}

// 3. 환경 변수 체크
echo "\n3. Environment Variables:\n";
$envVars = ['APP_KEY', 'APP_ENV', 'DB_HOST', 'DB_DATABASE', 'DB_USERNAME', 'PORT'];
foreach ($envVars as $var) {
    $value = $_ENV[$var] ?? getenv($var) ?? 'NOT SET';
    if ($var === 'APP_KEY' && $value !== 'NOT SET') {
        $value = substr($value, 0, 20) . '...'; // 일부만 표시
    }
    echo "   - $var: $value\n";
}

// 4. .env 파일 내용 (일부)
echo "\n4. .env File Check:\n";
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, 'APP_') === 0 || strpos($line, 'DB_') === 0) {
            // 민감한 정보 마스킹
            if (strpos($line, 'PASSWORD') !== false || strpos($line, 'KEY') !== false) {
                $parts = explode('=', $line, 2);
                echo "   " . $parts[0] . "=***HIDDEN***\n";
            } else {
                echo "   $line\n";
            }
        }
    }
} else {
    echo "   ❌ .env file not found\n";
}

// 5. Composer Autoload 테스트
echo "\n5. Composer Autoload Test:\n";
try {
    require_once __DIR__ . '/../vendor/autoload.php';
    echo "   ✅ Autoload successful\n";

    // Filament 클래스 체크
    if (class_exists('Filament\Support\ServiceProvider')) {
        echo "   ✅ Filament classes loaded\n";
    } else {
        echo "   ⚠️ Filament classes not found\n";
    }
} catch (Exception $e) {
    echo "   ❌ Autoload failed: " . $e->getMessage() . "\n";
}

// 6. 데이터베이스 연결 테스트
echo "\n6. Database Connection Test:\n";
if (extension_loaded('pdo_pgsql')) {
    echo "   ✅ PDO PostgreSQL extension loaded\n";

    // .env에서 DB 정보 읽기
    if (file_exists($envFile)) {
        $envContent = file_get_contents($envFile);
        preg_match('/DB_HOST=(.+)/', $envContent, $host);
        preg_match('/DB_PORT=(.+)/', $envContent, $port);
        preg_match('/DB_DATABASE=(.+)/', $envContent, $db);
        preg_match('/DB_USERNAME=(.+)/', $envContent, $user);
        preg_match('/DB_PASSWORD=(.+)/', $envContent, $pass);

        if ($host && $db && $user && $pass) {
            try {
                $dsn = "pgsql:host={$host[1]};port=" . ($port[1] ?? '5432') . ";dbname={$db[1]}";
                $pdo = new PDO($dsn, $user[1], $pass[1], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_TIMEOUT => 5
                ]);
                echo "   ✅ Database connection successful\n";

                // 간단한 쿼리 테스트
                $stmt = $pdo->query("SELECT VERSION()");
                $version = $stmt->fetchColumn();
                echo "   PostgreSQL: " . substr($version, 0, 50) . "...\n";
            } catch (PDOException $e) {
                echo "   ❌ Database connection failed: " . $e->getMessage() . "\n";
            }
        } else {
            echo "   ❌ Database credentials not found in .env\n";
        }
    }
} else {
    echo "   ❌ PDO PostgreSQL extension not loaded\n";
}

// 7. Laravel Bootstrap 테스트
echo "\n7. Laravel Bootstrap Test:\n";
try {
    $app = require_once __DIR__ . '/../bootstrap/app.php';
    echo "   ✅ Laravel app created\n";

    // 커널 생성 테스트
    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    echo "   ✅ HTTP Kernel created\n";

} catch (Exception $e) {
    echo "   ❌ Laravel bootstrap failed: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

// 8. 로그 파일 체크
echo "\n8. Log Files:\n";
$logDir = __DIR__ . '/../storage/logs';
if (is_dir($logDir)) {
    $logs = glob($logDir . '/*.log');
    foreach ($logs as $log) {
        $size = filesize($log);
        $modified = date('Y-m-d H:i:s', filemtime($log));
        echo "   - " . basename($log) . " (Size: {$size}B, Modified: $modified)\n";

        // 최근 에러 표시
        if (basename($log) === 'laravel.log' && $size > 0) {
            $lines = file($log);
            $lastErrors = array_slice($lines, -5);
            if ($lastErrors) {
                echo "   Last errors:\n";
                foreach ($lastErrors as $line) {
                    echo "     " . trim($line) . "\n";
                }
            }
        }
    }
} else {
    echo "   ❌ Log directory not found\n";
}

echo "\n=== End Debug Info ===\n";