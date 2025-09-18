<?php

/**
 * Laravel 상세 에러 진단
 */
echo '<h1>🔍 Laravel 에러 진단</h1>';

try {
    // Laravel 부트스트랩 시도
    require_once '../vendor/autoload.php';
    echo '<p>✅ Autoload 성공</p>';

    $app = require_once '../bootstrap/app.php';
    echo '<p>✅ App 부트스트랩 성공</p>';

    // 환경변수 확인
    echo '<h2>🌍 환경변수 상태</h2>';
    $envVars = ['APP_KEY', 'APP_ENV', 'DATABASE_URL', 'DB_CONNECTION'];
    foreach ($envVars as $var) {
        $value = env($var) ?: $_ENV[$var] ?? getenv($var) ?: 'NOT SET';
        if ($var === 'DATABASE_URL' && $value !== 'NOT SET') {
            $value = substr($value, 0, 30).'...';
        }
        echo "<p>$var: <code>$value</code></p>";
    }

    // 설정 테스트
    echo '<h2>⚙️ Laravel 설정 테스트</h2>';

    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    echo '<p>✅ HTTP Kernel 생성 성공</p>';

    // 간단한 요청 생성
    $request = Illuminate\Http\Request::create('/', 'GET');
    echo '<p>✅ Request 객체 생성 성공</p>';

    // 실제 요청 처리 시도
    echo '<p>🚀 실제 요청 처리 시도...</p>';
    $response = $kernel->handle($request);

    echo '<p>✅ 요청 처리 성공!</p>';
    echo '<p>응답 상태 코드: <strong>'.$response->getStatusCode().'</strong></p>';

    if ($response->getStatusCode() === 200) {
        echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px;'>";
        echo '<h2>🎉 Laravel 애플리케이션이 정상 작동합니다!</h2>';
        echo "<p>메인 사이트로 이동해보세요: <a href='/'>메인 사이트</a></p>";
        echo '</div>';
    } else {
        echo '<p>⚠️ 응답 상태가 200이 아닙니다.</p>';
        echo '<p>응답 내용 (처음 500자):</p>';
        echo '<pre>'.htmlspecialchars(substr($response->getContent(), 0, 500)).'</pre>';
    }

} catch (Throwable $e) {
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px;'>";
    echo '<h2>❌ Laravel 에러 발생</h2>';
    echo '<p><strong>에러 메시지:</strong> '.$e->getMessage().'</p>';
    echo '<p><strong>파일:</strong> '.$e->getFile().':'.$e->getLine().'</p>';
    echo '<h3>스택 트레이스:</h3>';
    echo "<pre style='font-size: 12px;'>".$e->getTraceAsString().'</pre>';
    echo '</div>';
}

echo '<hr><small>Generated at: '.date('Y-m-d H:i:s T').'</small>';
