<?php
/**
 * Railway Staging DB 초기화 스크립트
 * Supabase 데이터베이스 완전 초기화 및 기본 데이터 시딩
 */

echo "<h1>🗄️ YKP ERP Database Reset</h1>";

try {
    // Laravel 부트스트랩
    require_once '../vendor/autoload.php';
    $app = require_once '../bootstrap/app.php';
    
    echo "<p>✅ Laravel 부트스트랩 성공</p>";
    
    // Artisan 명령 실행 함수
    function runArtisan($command) {
        $output = [];
        $returnCode = 0;
        exec("cd .. && php artisan $command 2>&1", $output, $returnCode);
        return [
            'success' => $returnCode === 0,
            'output' => implode("\n", $output),
            'code' => $returnCode
        ];
    }
    
    echo "<h2>🔄 데이터베이스 초기화 시작</h2>";
    
    // 1. 마이그레이션 상태 확인
    echo "<h3>1️⃣ 현재 마이그레이션 상태</h3>";
    $status = runArtisan("migrate:status");
    echo "<pre style='background: #f8f9fa; padding: 10px; border-radius: 5px;'>";
    echo htmlspecialchars($status['output']);
    echo "</pre>";
    
    // 2. 모든 테이블 드롭 및 재생성
    echo "<h3>2️⃣ 데이터베이스 완전 초기화</h3>";
    $fresh = runArtisan("migrate:fresh --force");
    if ($fresh['success']) {
        echo "<p>✅ 모든 테이블 재생성 완료</p>";
    } else {
        echo "<p>❌ 마이그레이션 실패</p>";
        echo "<pre>" . htmlspecialchars($fresh['output']) . "</pre>";
        exit;
    }
    
    // 3. 기본 데이터 시딩
    echo "<h3>3️⃣ 기본 데이터 시딩</h3>";
    
    $seeders = [
        'HeadquartersUserSeeder' => '본사 관리자 계정',
        'DealerProfileSeeder' => '기본 매장 프로필',
        'AdvancedSeeder' => '추가 기본 데이터'
    ];
    
    foreach ($seeders as $seeder => $description) {
        echo "<h4>📊 $description 생성 중...</h4>";
        $seed = runArtisan("db:seed --class=$seeder --force");
        if ($seed['success']) {
            echo "<p>✅ $description 완료</p>";
        } else {
            echo "<p>⚠️ $description 실패 (계속 진행)</p>";
            echo "<pre style='color: #856404; background: #fff3cd; padding: 5px;'>";
            echo htmlspecialchars(substr($seed['output'], 0, 500));
            echo "</pre>";
        }
    }
    
    // 4. 캐시 클리어
    echo "<h3>4️⃣ 시스템 캐시 클리어</h3>";
    $cacheCommands = [
        'config:clear' => '설정 캐시',
        'cache:clear' => '애플리케이션 캐시', 
        'route:clear' => '라우트 캐시',
        'view:clear' => '뷰 캐시'
    ];
    
    foreach ($cacheCommands as $cmd => $desc) {
        $result = runArtisan($cmd);
        echo "<p>" . ($result['success'] ? '✅' : '⚠️') . " $desc 클리어</p>";
    }
    
    // 5. 최종 상태 확인
    echo "<h3>5️⃣ 초기화 완료 확인</h3>";
    $finalStatus = runArtisan("migrate:status");
    echo "<pre style='background: #e8f5e8; padding: 10px; border-radius: 5px;'>";
    echo htmlspecialchars($finalStatus['output']);
    echo "</pre>";
    
    echo "<hr>";
    echo "<div style='background: #d4edda; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
    echo "<h2>🎉 데이터베이스 초기화 완료!</h2>";
    echo "<p><strong>YKP ERP 시스템이 깨끗한 상태로 초기화되었습니다.</strong></p>";
    echo "<ul>";
    echo "<li>✅ 모든 기존 데이터 삭제</li>";
    echo "<li>✅ 테이블 구조 재생성</li>";
    echo "<li>✅ 기본 관리자 계정 생성</li>";
    echo "<li>✅ 시스템 캐시 클리어</li>";
    echo "</ul>";
    echo "<p>🏠 <a href='/' style='color: green; font-weight: bold;'>메인 사이트에서 확인하기</a></p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px;'>";
    echo "<h2>❌ 초기화 중 오류 발생</h2>";
    echo "<p><strong>에러:</strong> " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
    echo "</div>";
}

echo "<hr><small>Generated at: " . date('Y-m-d H:i:s T') . "</small>";
?>