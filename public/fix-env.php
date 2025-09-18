<?php

/**
 * .env.railway를 .env로 복사하고 Railway 환경변수로 업데이트
 */
echo '<h1>🔧 Railway .env 설정 수정</h1>';

try {
    // .env.railway 파일을 .env로 복사
    $railwayEnvPath = '../.env.railway';
    $envPath = '../.env';

    if (! file_exists($railwayEnvPath)) {
        echo '<p>❌ .env.railway 파일을 찾을 수 없습니다.</p>';
        exit;
    }

    echo '<p>📂 .env.railway 파일 발견</p>';

    // 파일 복사
    $success = copy($railwayEnvPath, $envPath);
    if (! $success) {
        echo '<p>❌ 파일 복사 실패</p>';
        exit;
    }

    echo '<p>✅ .env.railway → .env 복사 완료</p>';

    // .env 파일 읽기
    $envContent = file_get_contents($envPath);

    // Railway 환경변수로 업데이트
    $updates = [];

    // APP_KEY 업데이트 (Railway 환경변수에서)
    if (isset($_ENV['APP_KEY']) && ! empty($_ENV['APP_KEY'])) {
        $envContent = preg_replace('/^APP_KEY=.*$/m', 'APP_KEY='.$_ENV['APP_KEY'], $envContent);
        $updates[] = 'APP_KEY updated from Railway env var';
    }

    // APP_URL 업데이트
    $envContent = preg_replace('/^APP_URL=.*$/m', 'APP_URL=https://ykpproject-production.up.railway.app', $envContent);
    $updates[] = 'APP_URL updated to Railway domain';

    // DATABASE_URL 업데이트 (Railway 환경변수에서)
    if (isset($_ENV['DATABASE_URL']) && ! empty($_ENV['DATABASE_URL'])) {
        $envContent = preg_replace('/^DATABASE_URL=.*$/m', 'DATABASE_URL="'.$_ENV['DATABASE_URL'].'"', $envContent);
        $updates[] = 'DATABASE_URL updated from Railway env var';
    }

    // 업데이트된 내용 저장
    $saveSuccess = file_put_contents($envPath, $envContent);

    if ($saveSuccess !== false) {
        echo "<p>✅ .env 파일 업데이트 완료 ($saveSuccess bytes)</p>";

        echo '<h2>📝 적용된 업데이트:</h2>';
        echo '<ul>';
        foreach ($updates as $update) {
            echo "<li>✅ $update</li>";
        }
        echo '</ul>';

        // 캐시 파일들 삭제
        echo '<h2>🧹 Laravel 캐시 클리어</h2>';
        $cacheFiles = [
            '../bootstrap/cache/config.php',
            '../bootstrap/cache/routes.php',
            '../bootstrap/cache/events.php',
            '../storage/framework/cache/data',
        ];

        foreach ($cacheFiles as $file) {
            if (file_exists($file)) {
                if (is_dir($file)) {
                    // 디렉토리인 경우 내용 삭제
                    $files = glob($file.'/*');
                    foreach ($files as $f) {
                        if (is_file($f)) {
                            unlink($f);
                        }
                    }
                    echo '<p>🗑️ 캐시 디렉토리 클리어: '.basename($file).'</p>';
                } else {
                    unlink($file);
                    echo '<p>🗑️ 캐시 파일 삭제: '.basename($file).'</p>';
                }
            }
        }

        echo '<hr>';
        echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
        echo '<h2>🎉 설정 완료!</h2>';
        echo '<p><strong>이제 Laravel 애플리케이션이 정상 작동할 것입니다.</strong></p>';
        echo "<p>🔄 <a href='/' style='color: green; font-weight: bold; font-size: 18px;'>메인 사이트 테스트하기</a></p>";
        echo '</div>';

    } else {
        echo '<p>❌ .env 파일 저장 실패</p>';
    }

} catch (Exception $e) {
    echo '<p>❌ 에러 발생: '.$e->getMessage().'</p>';
}

echo '<hr><small>Generated at: '.date('Y-m-d H:i:s T').'</small>';
