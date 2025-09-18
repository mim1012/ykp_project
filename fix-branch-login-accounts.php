<?php

/**
 * 🔧 지사 계정 로그인 정보 통일화 스크립트
 *
 * 목적:
 * 1. 모든 지사 계정의 비밀번호를 '123456'으로 통일
 * 2. 비활성화된 계정들 활성화
 * 3. 지사 관리 페이지 표시 정보와 실제 계정 정보 동기화
 */

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\Hash;

// Laravel 앱 부트스트랩
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

echo "🔧 지사 계정 로그인 정보 통일화 시작...\n";

try {
    // 모든 지사 계정 조회
    $branchUsers = App\Models\User::where('role', 'branch')->get();

    echo "📊 발견된 지사 계정: {$branchUsers->count()}개\n";

    foreach ($branchUsers as $user) {
        echo "\n🔍 지사 계정 확인: {$user->email}\n";
        echo "   이름: {$user->name}\n";
        echo "   지사 ID: {$user->branch_id}\n";
        echo '   활성 상태: '.($user->is_active ? '활성' : '비활성')."\n";

        $needsUpdate = false;
        $updateData = [];

        // 비밀번호 통일 (모든 지사 계정을 123456으로)
        if (! Hash::check('123456', $user->password)) {
            echo "   🔑 비밀번호 업데이트 필요\n";
            $updateData['password'] = Hash::make('123456');
            $needsUpdate = true;
        } else {
            echo "   ✅ 비밀번호 이미 올바름 (123456)\n";
        }

        // 계정 활성화
        if (! $user->is_active) {
            echo "   🔄 계정 활성화 필요\n";
            $updateData['is_active'] = true;
            $needsUpdate = true;
        } else {
            echo "   ✅ 계정 이미 활성화됨\n";
        }

        // 업데이트 실행
        if ($needsUpdate) {
            echo "   💾 계정 정보 업데이트 중...\n";

            // PostgreSQL boolean 호환성을 위해 직접 업데이트
            if (isset($updateData['is_active'])) {
                DB::statement('UPDATE users SET password = ?, is_active = ?::boolean, updated_at = ? WHERE id = ?', [
                    $updateData['password'] ?? $user->password,
                    'true',
                    now(),
                    $user->id,
                ]);
            } else {
                DB::statement('UPDATE users SET password = ?, updated_at = ? WHERE id = ?', [
                    $updateData['password'],
                    now(),
                    $user->id,
                ]);
            }

            echo "   ✅ 업데이트 완료\n";

        } else {
            echo "   ℹ️ 업데이트 불필요 - 이미 올바른 상태\n";
        }

        // 지사 정보 확인
        $branch = App\Models\Branch::find($user->branch_id);
        if ($branch) {
            echo "   📍 소속 지사: {$branch->name} ({$branch->code})\n";
        }

        echo "   🔑 최종 로그인 정보:\n";
        echo "      📧 이메일: {$user->email}\n";
        echo "      🔐 비밀번호: 123456\n";
        echo '   '.str_repeat('-', 50)."\n";
    }

    // 최종 상태 확인
    echo "\n📊 최종 지사 계정 상태:\n";
    $activeCount = App\Models\User::where('role', 'branch')->where('is_active', true)->count();
    $totalCount = App\Models\User::where('role', 'branch')->count();

    echo "   전체 지사 계정: {$totalCount}개\n";
    echo "   활성 계정: {$activeCount}개\n";
    echo '   비활성 계정: '.($totalCount - $activeCount)."개\n";

    echo "\n🎉 지사 계정 로그인 정보 통일화 완료!\n";
    echo "\n📋 표준 로그인 정보:\n";
    echo "   📧 이메일: branch_[지사코드]@ykp.com\n";
    echo "   🔐 비밀번호: 123456\n";
    echo "\n✅ 모든 지사 계정이 동일한 비밀번호(123456)를 사용합니다.\n";

} catch (Exception $e) {
    echo '❌ 오류 발생: '.$e->getMessage()."\n";
    echo "스택 트레이스:\n".$e->getTraceAsString()."\n";
    exit(1);
}
