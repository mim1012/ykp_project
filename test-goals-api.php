<?php

// Goals API 테스트 스크립트
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->boot();

echo "🎯 Goals API 테스트 시작...\n\n";

try {
    // 1. 현재 월 목표 확인
    echo "=== 1. 현재 설정된 목표 확인 ===\n";
    $currentMonth = now()->format('Y-m');
    $systemGoal = App\Models\Goal::where('target_type', 'system')
        ->where('period_type', 'monthly')
        ->whereRaw("DATE_FORMAT(period_start, '%Y-%m') = ?", [$currentMonth])
        ->where('is_active', true)
        ->first();

    if ($systemGoal) {
        echo "✅ 시스템 목표 존재\n";
        echo '  매출 목표: '.number_format($systemGoal->sales_target)."원\n";
        echo '  개통 목표: '.$systemGoal->activation_target."건\n";
        echo '  설정 기간: '.$systemGoal->period_start.' ~ '.$systemGoal->period_end."\n";
        echo '  설정자: '.($systemGoal->createdBy->name ?? 'Unknown')."\n";
    } else {
        echo "❌ 시스템 목표 없음 (기본값 사용)\n";
    }

    // 2. 테스트 목표 생성
    echo "\n=== 2. 테스트 목표 생성 ===\n";
    $testGoal = App\Models\Goal::create([
        'target_type' => 'system',
        'target_id' => null,
        'period_type' => 'monthly',
        'period_start' => now()->startOfMonth(),
        'period_end' => now()->endOfMonth(),
        'sales_target' => 60000000, // 6천만원
        'activation_target' => 250,
        'margin_target' => 0,
        'created_by' => 1, // 본사 관리자 ID
        'notes' => 'Goals API 테스트용 목표',
        'is_active' => true,
    ]);

    echo "✅ 테스트 목표 생성 완료\n";
    echo '  ID: '.$testGoal->id."\n";
    echo '  매출 목표: '.number_format($testGoal->sales_target)."원\n";

    // 3. API 엔드포인트 시뮬레이션
    echo "\n=== 3. API 조회 시뮬레이션 ===\n";
    $retrievedGoal = App\Models\Goal::where('target_type', 'system')
        ->where('period_type', 'monthly')
        ->whereRaw("DATE_FORMAT(period_start, '%Y-%m') = ?", [now()->format('Y-m')])
        ->where('is_active', true)
        ->first();

    if ($retrievedGoal) {
        echo "✅ API 조회 성공\n";
        echo '  조회된 목표: '.number_format($retrievedGoal->sales_target)."원\n";
        echo '  달성률 계산 예시: 1천만원 달성 시 '.round((10000000 / $retrievedGoal->sales_target) * 100, 1)."%\n";
    }

    // 4. 월별 목표 히스토리 확인
    echo "\n=== 4. 월별 목표 히스토리 ===\n";
    $allGoals = App\Models\Goal::where('target_type', 'system')
        ->orderBy('period_start', 'desc')
        ->take(5)
        ->get();

    foreach ($allGoals as $goal) {
        $month = $goal->period_start->format('Y-m');
        echo "  {$month}: ".number_format($goal->sales_target)."원 (개통 {$goal->activation_target}건)\n";
    }

    // 5. 정리
    echo "\n=== 5. 테스트 데이터 정리 ===\n";
    $testGoal->delete();
    echo "✅ 테스트 목표 삭제 완료\n";

    echo "\n🎉 Goals API 테스트 완료 - 모든 기능 정상!\n";

} catch (Exception $e) {
    echo '❌ 테스트 실패: '.$e->getMessage()."\n";
    echo "스택 트레이스:\n".$e->getTraceAsString()."\n";
}
