<?php

/**
 * 🔧 지사 계정 수동 생성 스크립트
 * GG001 지사 계정을 강제로 생성합니다.
 */

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\Hash;

// Laravel 앱 부트스트랩
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

echo "🔧 지사 계정 수동 생성 시작...\n";

try {
    // 1. GG001 지사 확인
    $branch = App\Models\Branch::where('code', 'GG001')->first();
    if (! $branch) {
        echo "❌ GG001 지사를 찾을 수 없습니다.\n";
        exit(1);
    }

    echo "✅ GG001 지사 발견: {$branch->name}\n";

    // 2. 기존 계정 확인
    $existingUser = App\Models\User::where('email', 'branch_gg001@ykp.com')->first();
    if ($existingUser) {
        echo "⚠️ 기존 계정 발견, 삭제 후 재생성...\n";
        $existingUser->delete();
    }

    // 3. 새 계정 생성
    $user = App\Models\User::create([
        'name' => '최종테스트 관리자',
        'email' => 'branch_gg001@ykp.com',
        'password' => Hash::make('123456'),
        'role' => 'branch',
        'branch_id' => $branch->id,
        'store_id' => null,
        'is_active' => true,
        'created_by_user_id' => 1, // 본사 관리자가 생성
    ]);

    echo "✅ 지사 계정 생성 완료!\n";
    echo "📧 이메일: branch_gg001@ykp.com\n";
    echo "🔐 비밀번호: 123456\n";
    echo "🆔 사용자 ID: {$user->id}\n";

    // 4. TEST001 지사 계정도 확인/생성
    $testBranch = App\Models\Branch::where('code', 'TEST001')->first();
    if ($testBranch) {
        echo "\n🔧 TEST001 지사 계정도 확인 중...\n";

        $testUser = App\Models\User::where('email', 'branch_test001@ykp.com')->first();
        if (! $testUser) {
            $testUser = App\Models\User::create([
                'name' => '테스트지점 관리자',
                'email' => 'branch_test001@ykp.com',
                'password' => Hash::make('123456'),
                'role' => 'branch',
                'branch_id' => $testBranch->id,
                'store_id' => null,
                'is_active' => true,
                'created_by_user_id' => 1,
            ]);

            echo "✅ TEST001 지사 계정도 생성 완료!\n";
            echo "📧 이메일: branch_test001@ykp.com\n";
            echo "🔐 비밀번호: 123456\n";
        } else {
            echo "✅ TEST001 지사 계정 이미 존재\n";
        }
    }

    // 5. 최종 확인
    $totalUsers = App\Models\User::count();
    $branchUsers = App\Models\User::where('role', 'branch')->count();

    echo "\n📊 최종 상태:\n";
    echo "   총 사용자: {$totalUsers}명\n";
    echo "   지사 관리자: {$branchUsers}명\n";

    echo "\n🎉 지사 계정 수동 생성 완료!\n";

} catch (Exception $e) {
    echo '❌ 오류 발생: '.$e->getMessage()."\n";
    echo "스택 트레이스:\n".$e->getTraceAsString()."\n";
    exit(1);
}
