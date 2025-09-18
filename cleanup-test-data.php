<?php

/**
 * 테스트 지사 및 데이터 정리 스크립트
 * 실환경에서 테스트 데이터 제거
 */

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';

echo '<h1>🧹 테스트 데이터 정리</h1>';

try {
    // 테스트 지사 목록 (삭제 대상)
    $testBranches = [
        'TEST001' => '테스트지점',
        'GG001' => '최종테스트',
        'test' => '대구',
        'E2E999' => 'E2E테스트지점',
        'BB01' => '테스트',
        '테스트' => '테스트',
    ];

    echo '<h2>🎯 삭제 대상 지사:</h2>';
    foreach ($testBranches as $code => $name) {
        echo "<li>{$name} ({$code})</li>";
    }

    echo '<h2>🔍 실제 삭제 가능 여부 확인:</h2>';

    foreach ($testBranches as $code => $name) {
        $branch = \App\Models\Branch::where('code', $code)->first();

        if ($branch) {
            $storesCount = \App\Models\Store::where('branch_id', $branch->id)->count();
            $salesCount = \App\Models\Sale::where('branch_id', $branch->id)->count();
            $usersCount = \App\Models\User::where('branch_id', $branch->id)->count();

            echo "<div style='border: 1px solid #ccc; margin: 10px; padding: 10px;'>";
            echo "<h3>{$name} (ID: {$branch->id})</h3>";

            if ($storesCount == 0 && $salesCount == 0 && $usersCount == 0) {
                echo "<p style='color: green;'>✅ 삭제 가능 (종속 데이터 없음)</p>";

                // 실제 삭제 실행
                $branch->delete();
                echo "<p style='color: blue;'>🗑️ 삭제 완료</p>";
            } else {
                echo "<p style='color: red;'>❌ 삭제 불가:</p>";
                echo '<ul>';
                if ($storesCount > 0) {
                    echo "<li>매장: {$storesCount}개</li>";
                }
                if ($salesCount > 0) {
                    echo "<li>매출: {$salesCount}건</li>";
                }
                if ($usersCount > 0) {
                    echo "<li>사용자: {$usersCount}개</li>";
                }
                echo '</ul>';
                echo "<p style='color: orange;'>⚠️ 수동으로 종속 데이터 정리 필요</p>";
            }
            echo '</div>';
        } else {
            echo "<p style='color: gray;'>ℹ️ {$name} ({$code}) - 이미 삭제됨 또는 존재하지 않음</p>";
        }
    }

    echo '<h2>🎉 정리 완료!</h2>';
    echo "<a href='/management/stores' style='color: blue; font-weight: bold;'>→ 매장 관리 페이지에서 확인</a>";

} catch (\Exception $e) {
    echo "<div style='color: red; padding: 20px; border: 2px solid red;'>";
    echo '<h2>❌ 오류 발생</h2>';
    echo "<p>{$e->getMessage()}</p>";
    echo '</div>';
}
