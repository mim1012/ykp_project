<?php

// 매장 계정 일괄 생성 스크립트
require_once 'vendor/autoload.php';

use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

$app = require_once 'bootstrap/app.php';
$app->boot();

echo "🔧 매장 계정 일괄 생성 스크립트 시작...\n\n";

try {
    // 모든 매장 조회
    $stores = Store::with('branch')->get();
    echo "📊 총 {$stores->count()}개 매장 발견\n\n";

    $results = [
        'created' => 0,
        'existed' => 0,
        'activated' => 0,
        'errors' => []
    ];

    foreach ($stores as $store) {
        echo "🏪 매장 처리: {$store->name} (코드: {$store->code})\n";

        // 기존 계정 확인
        $existingUser = User::where('store_id', $store->id)->first();

        if ($existingUser) {
            echo "   ✅ 기존 계정 존재: {$existingUser->email}\n";

            if (!$existingUser->is_active) {
                $existingUser->update(['is_active' => true]);
                echo "   🔄 계정 활성화 완료\n";
                $results['activated']++;
            } else {
                echo "   ✅ 이미 활성화됨\n";
                $results['existed']++;
            }
        } else {
            // 새 계정 생성
            $email = strtolower($store->code) . '@ykp.com';

            try {
                $user = User::create([
                    'name' => $store->name . ' 관리자',
                    'email' => $email,
                    'password' => Hash::make('123456'),
                    'role' => 'store',
                    'store_id' => $store->id,
                    'branch_id' => $store->branch_id,
                    'is_active' => true
                ]);

                echo "   ✅ 새 계정 생성: {$email}\n";
                $results['created']++;
            } catch (Exception $e) {
                echo "   ❌ 계정 생성 실패: {$e->getMessage()}\n";
                $results['errors'][] = "매장 {$store->name}: {$e->getMessage()}";
            }
        }

        echo "\n";
    }

    // 결과 요약
    echo "📋 매장 계정 처리 결과:\n";
    echo "✅ 새로 생성: {$results['created']}개\n";
    echo "🔄 활성화: {$results['activated']}개\n";
    echo "➖ 기존 활성: {$results['existed']}개\n";
    echo "❌ 오류: " . count($results['errors']) . "개\n\n";

    if (count($results['errors']) > 0) {
        echo "❌ 오류 상세:\n";
        foreach ($results['errors'] as $error) {
            echo "   • {$error}\n";
        }
    }

    // 최종 매장 계정 목록 출력
    echo "\n📋 최종 매장 계정 목록:\n";
    $storeUsers = User::where('role', 'store')->with('store')->get();
    foreach ($storeUsers as $user) {
        $status = $user->is_active ? '✅' : '❌';
        $storeName = $user->store->name ?? '매장 정보 없음';
        echo "   {$status} {$user->email} - {$storeName} (ID: {$user->store_id})\n";
    }

} catch (Exception $e) {
    echo "❌ 스크립트 실행 오류: {$e->getMessage()}\n";
    exit(1);
}

echo "\n🎯 매장 계정 처리 완료!\n";