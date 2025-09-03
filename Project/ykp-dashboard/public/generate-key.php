<?php
/**
 * Laravel APP_KEY 생성 및 .env 업데이트
 */

echo "<h1>🔑 Laravel Key Generation</h1>";

try {
    // Laravel 부트스트랩
    require_once '../vendor/autoload.php';
    
    // .env 파일 읽기
    $envPath = '../.env';
    if (!file_exists($envPath)) {
        echo "<p>❌ .env 파일이 없습니다. <a href='/create-env.php'>먼저 .env 파일을 생성하세요</a></p>";
        exit;
    }
    
    $envContent = file_get_contents($envPath);
    echo "<p>✅ .env 파일 발견</p>";
    
    // 32바이트 랜덤 키 생성 (Laravel 방식)
    $key = 'base64:' . base64_encode(random_bytes(32));
    echo "<p>🔑 새로운 APP_KEY 생성: <code>" . substr($key, 0, 20) . "...</code></p>";
    
    // .env 파일에서 APP_KEY 업데이트
    if (preg_match('/^APP_KEY=.*$/m', $envContent)) {
        $newContent = preg_replace('/^APP_KEY=.*$/m', "APP_KEY=$key", $envContent);
    } else {
        $newContent = $envContent . "\nAPP_KEY=$key\n";
    }
    
    // 파일에 쓰기
    $success = file_put_contents($envPath, $newContent);
    
    if ($success !== false) {
        echo "<p>✅ APP_KEY가 .env 파일에 저장되었습니다!</p>";
        
        // Laravel config 캐시 클리어 시도
        $app = require_once '../bootstrap/app.php';
        if (method_exists($app, 'make')) {
            echo "<p>🧹 Laravel 캐시 클리어 중...</p>";
            
            // 캐시 파일들 직접 삭제
            $cacheFiles = [
                '../bootstrap/cache/config.php',
                '../bootstrap/cache/routes.php',
                '../bootstrap/cache/events.php',
                '../bootstrap/cache/views.php'
            ];
            
            foreach ($cacheFiles as $file) {
                if (file_exists($file)) {
                    unlink($file);
                    echo "<p>🗑️ 삭제됨: " . basename($file) . "</p>";
                }
            }
        }
        
        echo "<hr>";
        echo "<p>🎉 <strong>APP_KEY 설정 완료!</strong></p>";
        echo "<p>🔄 <a href='/' style='color: green; font-weight: bold;'>메인 사이트 테스트하기</a></p>";
        echo "<p>🔍 <a href='/env-check.php'>환경 설정 다시 확인하기</a></p>";
        
    } else {
        echo "<p>❌ .env 파일 쓰기 실패</p>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ 에러 발생: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<hr><small>Generated at: " . date('Y-m-d H:i:s T') . "</small>";
?>