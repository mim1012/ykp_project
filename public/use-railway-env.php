<?php

/**
 * .env.railway 파일을 .env로 직접 복사하고 Supabase 정보 업데이트
 */
echo '<h1>🔧 Railway 환경 설정 강제 적용</h1>';

// .env.railway를 .env로 복사
$source = '../.env.railway';
$target = '../.env';

if (! file_exists($source)) {
    echo '<p>❌ .env.railway 파일을 찾을 수 없습니다.</p>';
    exit;
}

echo '<p>📂 .env.railway 발견</p>';

// 파일 복사
if (copy($source, $target)) {
    echo '<p>✅ .env.railway → .env 복사 완료</p>';
} else {
    echo '<p>❌ 파일 복사 실패</p>';
    exit;
}

// .env 내용 읽기
$envContent = file_get_contents($target);

// 실제 Supabase 정보로 업데이트
$updates = [
    'APP_KEY=base64:cSaf6mc1LMsF1GmGO/z79LFvda4kwfaJKHVi8qKI6iw=',
    'DATABASE_URL="postgresql://postgres.hekvjnunknzzykuagltr:rlawlgns2233%40@aws-1-ap-southeast-1.pooler.supabase.com:6543/postgres?sslmode=require"',
    'APP_URL=https://ykpproject-production.up.railway.app',
];

foreach ($updates as $update) {
    [$key, $value] = explode('=', $update, 2);
    $pattern = '/^'.preg_quote($key).'=.*$/m';

    if (preg_match($pattern, $envContent)) {
        $envContent = preg_replace($pattern, $update, $envContent);
    } else {
        $envContent .= "\n$update\n";
    }
    echo "<p>✅ $key 업데이트 완료</p>";
}

// .env 파일 저장
if (file_put_contents($target, $envContent)) {
    echo '<p>✅ 업데이트된 .env 파일 저장 완료</p>';

    // 캐시 파일들 모두 삭제
    $cacheFiles = [
        '../bootstrap/cache/config.php',
        '../bootstrap/cache/routes.php',
        '../bootstrap/cache/events.php',
    ];

    foreach ($cacheFiles as $file) {
        if (file_exists($file)) {
            unlink($file);
            echo '<p>🗑️ 삭제: '.basename($file).'</p>';
        }
    }

    // storage 캐시도 클리어
    $storageCache = '../storage/framework/cache/data';
    if (is_dir($storageCache)) {
        $files = glob($storageCache.'/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        echo '<p>🗑️ storage 캐시 클리어</p>';
    }

    echo '<hr>';
    echo "<div style='background: #d4edda; padding: 20px; border-radius: 8px;'>";
    echo '<h2>🎉 Railway 설정 완료!</h2>';
    echo '<p><strong>이제 Laravel이 정상 작동합니다!</strong></p>';
    echo "<p><a href='/' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold;'>메인 사이트 테스트</a></p>";
    echo '</div>';

} else {
    echo '<p>❌ .env 파일 저장 실패</p>';
}

echo '<hr><small>Generated at: '.date('Y-m-d H:i:s T').'</small>';
