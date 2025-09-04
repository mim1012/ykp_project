<?php
/**
 * Laravel 로그 파일 직접 확인
 */

echo "<h1>📋 Laravel 로그 확인</h1>";

// 로그 파일 경로들
$logPaths = [
    '../storage/logs/laravel.log',
    '../storage/logs/laravel-' . date('Y-m-d') . '.log',
    '../storage/logs/lumen.log'
];

echo "<h2>📁 로그 파일 검색</h2>";
$foundLogs = [];

foreach ($logPaths as $path) {
    if (file_exists($path)) {
        $foundLogs[] = $path;
        $size = filesize($path);
        echo "<p>✅ 발견: " . basename($path) . " ($size bytes)</p>";
    } else {
        echo "<p>❌ 없음: " . basename($path) . "</p>";
    }
}

if (empty($foundLogs)) {
    echo "<p>⚠️ 로그 파일을 찾을 수 없습니다.</p>";
    
    // 로그 디렉토리 확인
    $logDir = '../storage/logs';
    if (is_dir($logDir)) {
        echo "<h3>📂 storage/logs 내용:</h3>";
        $files = scandir($logDir);
        foreach ($files as $file) {
            if ($file[0] !== '.') {
                echo "<p>📄 $file</p>";
            }
        }
    } else {
        echo "<p>❌ storage/logs 디렉토리가 없습니다.</p>";
    }
} else {
    // 가장 최근 로그 파일 읽기
    $latestLog = $foundLogs[0];
    echo "<h2>📄 최신 로그 내용 (마지막 100줄)</h2>";
    
    $lines = file($latestLog);
    $totalLines = count($lines);
    $startLine = max(0, $totalLines - 100);
    $recentLines = array_slice($lines, $startLine);
    
    echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; max-height: 500px; overflow-y: scroll;'>";
    echo "<pre style='font-size: 12px;'>";
    
    foreach ($recentLines as $index => $line) {
        $lineNumber = $startLine + $index + 1;
        $htmlLine = htmlspecialchars($line);
        
        // 에러 라인 강조
        if (strpos($line, 'ERROR') !== false || strpos($line, 'CRITICAL') !== false) {
            echo "<span style='background: #f8d7da; color: #721c24;'>$lineNumber: $htmlLine</span>";
        } elseif (strpos($line, 'WARNING') !== false) {
            echo "<span style='background: #fff3cd; color: #856404;'>$lineNumber: $htmlLine</span>";
        } else {
            echo "$lineNumber: $htmlLine";
        }
    }
    
    echo "</pre>";
    echo "</div>";
}

// 환경변수 상태도 확인
echo "<h2>🌍 핵심 환경변수</h2>";
$key_vars = ['APP_KEY', 'DB_CONNECTION', 'DATABASE_URL'];
foreach ($key_vars as $var) {
    $value = $_ENV[$var] ?? getenv($var) ?: 'NOT_SET';
    if ($value !== 'NOT_SET' && strlen($value) > 30) {
        $value = substr($value, 0, 30) . '...';
    }
    echo "<p>$var: <code>$value</code></p>";
}

echo "<hr><small>Generated at: " . date('Y-m-d H:i:s T') . "</small>";
?>