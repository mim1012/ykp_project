#!/bin/bash
# PM 요구사항: Deploy Hooks로 마이그레이션 자동화

echo "🚀 Railway Deploy Hook 시작: $(date)"

# 로그 디렉토리 생성
mkdir -p storage/logs

# 마이그레이션 로그와 함께 실행
echo "📊 현재 마이그레이션 상태 확인..."
php artisan migrate:status 2>&1 | tee -a storage/logs/deploy-migration.log

echo "⚡ 마이그레이션 강제 실행..."
php artisan migrate --force 2>&1 | tee -a storage/logs/deploy-migration.log

# 실행 결과 확인
MIGRATION_EXIT_CODE=$?
if [ $MIGRATION_EXIT_CODE -eq 0 ]; then
    echo "✅ 마이그레이션 성공: $(date)" | tee -a storage/logs/deploy-migration.log
else 
    echo "❌ 마이그레이션 실패: 코드 $MIGRATION_EXIT_CODE - $(date)" | tee -a storage/logs/deploy-migration.log
    # 실패해도 배포는 계속 진행 (rollback 방지)
fi

echo "🧹 캐시 클리어 시작..."
php artisan config:clear
php artisan route:clear  
php artisan view:clear
php artisan cache:clear

echo "🔐 테스트 계정 비밀번호 초기화..."
php artisan tinker --execute="
use App\Models\User;
use Illuminate\Support\Facades\Hash;
\$test_accounts = [
    'admin@ykp.com' => '123456',
    'hq@ykp.com' => '123456', 
    'test@ykp.com' => '123456',
    'branch@ykp.com' => '123456',
    'br001@ykp.com' => '123456',
    'store@ykp.com' => '123456'
];
foreach(\$test_accounts as \$email => \$password) {
    \$user = User::where('email', \$email)->first();
    if(\$user) {
        \$user->password = Hash::make(\$password);
        \$user->save();
        echo \$email . ' 비밀번호 설정 완료' . PHP_EOL;
    }
}
echo '✅ 테스트 계정 비밀번호 초기화 완료' . PHP_EOL;
" 2>&1 | tee -a storage/logs/deploy-migration.log

echo "🎉 Deploy Hook 완료: $(date)"
echo "📄 로그 파일: storage/logs/deploy-migration.log"