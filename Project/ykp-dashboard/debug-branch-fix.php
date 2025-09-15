<?php
// 🔧 지사 계정 강제 생성 및 디버깅

// Laravel 라우트에 추가할 코드
echo "
Route::get('/dev/fix-branch-accounts', function () {
    try {
        echo '<h2>🔧 지사 계정 강제 생성 디버깅</h2>';
        
        // 1. 현재 상태 확인
        \$totalUsers = App\\Models\\User::count();
        \$branchUsers = App\\Models\\User::where('role', 'branch')->count();
        \$branches = App\\Models\\Branch::count();
        
        echo '<p><strong>현재 상태:</strong></p>';
        echo '<ul>';
        echo \"<li>총 사용자: {\$totalUsers}명</li>\";
        echo \"<li>지사 관리자: {\$branchUsers}명</li>\";
        echo \"<li>총 지사: {\$branches}개</li>\";
        echo '</ul>';
        
        // 2. GG001 지사 확인
        \$gg001Branch = App\\Models\\Branch::where('code', 'GG001')->first();
        if (\$gg001Branch) {
            echo \"<p>✅ GG001 지사 발견: {\$gg001Branch->name} (ID: {\$gg001Branch->id})</p>\";
            
            // 3. 해당 지사 계정 확인
            \$gg001User = App\\Models\\User::where('email', 'branch_gg001@ykp.com')->first();
            if (\$gg001User) {
                echo \"<p>✅ GG001 계정 이미 존재 (ID: {\$gg001User->id})</p>\";
                echo '<p>🔄 비밀번호 재설정 중...</p>';
                \$gg001User->update([
                    'password' => Hash::make('123456'),
                    'is_active' => true
                ]);
                echo '<p>✅ 비밀번호 재설정 완료</p>';
            } else {
                echo '<p>❌ GG001 계정 없음, 새로 생성 중...</p>';
                \$newUser = App\\Models\\User::create([
                    'name' => '최종테스트 관리자',
                    'email' => 'branch_gg001@ykp.com',
                    'password' => Hash::make('123456'),
                    'role' => 'branch',
                    'branch_id' => \$gg001Branch->id,
                    'store_id' => null,
                    'is_active' => true
                ]);
                echo \"<p>✅ 새 계정 생성 완료 (ID: {\$newUser->id})</p>\";
            }
        } else {
            echo '<p>❌ GG001 지사가 존재하지 않습니다!</p>';
        }
        
        // 4. 최종 상태
        \$finalUsers = App\\Models\\User::count();
        \$finalBranchUsers = App\\Models\\User::where('role', 'branch')->count();
        
        echo '<hr>';
        echo '<p><strong>최종 상태:</strong></p>';
        echo '<ul>';
        echo \"<li>총 사용자: {\$finalUsers}명</li>\";
        echo \"<li>지사 관리자: {\$finalBranchUsers}명</li>\";
        echo '</ul>';
        
        echo '<hr>';
        echo '<p><strong>🔑 로그인 정보:</strong></p>';
        echo '<ul>';
        echo '<li>📧 이메일: branch_gg001@ykp.com</li>';
        echo '<li>🔐 비밀번호: 123456</li>';
        echo '</ul>';
        
        return response()->json(['success' => true, 'message' => '지사 계정 처리 완료']);
        
    } catch (Exception \$e) {
        echo \"<p>❌ 오류 발생: {\$e->getMessage()}</p>\";
        echo \"<pre>{\$e->getTraceAsString()}</pre>\";
        return response()->json(['success' => false, 'error' => \$e->getMessage()], 500);
    }
});
";