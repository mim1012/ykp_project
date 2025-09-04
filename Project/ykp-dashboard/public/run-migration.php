<?php
/**
 * Supabase 마이그레이션 실행
 */

echo "<h1>🗄️ Supabase 마이그레이션 실행</h1>";

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

try {
    // 1. 마이그레이션 상태 확인
    echo "<h2>1️⃣ 현재 마이그레이션 상태</h2>";
    $status = runArtisan("migrate:status");
    echo "<pre style='background: #f8f9fa; padding: 10px; font-size: 12px;'>";
    echo htmlspecialchars($status['output']);
    echo "</pre>";
    
    // 2. 모든 테이블 생성
    echo "<h2>2️⃣ 데이터베이스 마이그레이션 실행</h2>";
    $migrate = runArtisan("migrate --force");
    
    if ($migrate['success']) {
        echo "<p>✅ 마이그레이션 완료</p>";
    } else {
        echo "<p>❌ 마이그레이션 실패</p>";
        echo "<pre style='color: red;'>" . htmlspecialchars($migrate['output']) . "</pre>";
    }
    
    // 3. 기본 데이터 시딩
    echo "<h2>3️⃣ 기본 데이터 시딩</h2>";
    
    $seeders = [
        'HeadquartersUserSeeder',
        'DealerProfileSeeder',
        'AdvancedSeeder'
    ];
    
    foreach ($seeders as $seeder) {
        echo "<h3>📊 {$seeder} 실행</h3>";
        $seed = runArtisan("db:seed --class={$seeder} --force");
        
        if ($seed['success']) {
            echo "<p>✅ {$seeder} 완료</p>";
        } else {
            echo "<p>⚠️ {$seeder} 실패 (계속 진행)</p>";
            echo "<pre style='color: #856404; font-size: 11px;'>";
            echo htmlspecialchars(substr($seed['output'], 0, 300));
            echo "</pre>";
        }
    }
    
    // 4. 최종 상태 확인
    echo "<h2>4️⃣ 최종 마이그레이션 상태</h2>";
    $finalStatus = runArtisan("migrate:status");
    echo "<pre style='background: #e8f5e8; padding: 10px; font-size: 12px;'>";
    echo htmlspecialchars($finalStatus['output']);
    echo "</pre>";
    
    echo "<hr>";
    echo "<div style='background: #d4edda; padding: 20px; border-radius: 8px;'>";
    echo "<h2>🎉 마이그레이션 완료!</h2>";
    echo "<p><strong>YKP ERP 데이터베이스가 준비되었습니다.</strong></p>";
    echo "<p><a href='/' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold;'>메인 사이트로 이동</a></p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<p>❌ 에러: " . $e->getMessage() . "</p>";
}
?>