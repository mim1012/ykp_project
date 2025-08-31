<?php

// 로그인 테스트 스크립트
$loginUrl = 'http://127.0.0.1:8000/login';
$dashboardUrl = 'http://127.0.0.1:8000/dashboard';
$aggridUrl = 'http://127.0.0.1:8000/sales/aggrid';

// 세션 쿠키를 저장할 임시 파일
$cookieFile = __DIR__ . '/cookies.txt';

echo "=== 인증 시스템 테스트 ===\n";

// 1. 로그인 페이지 접근
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $loginUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_HEADER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "1. 로그인 페이지: HTTP $httpCode\n";

// CSRF 토큰 추출
preg_match('/_token.*?value="([^"]+)"/', $response, $matches);
$csrfToken = $matches[1] ?? '';

if ($csrfToken) {
    echo "2. CSRF 토큰 추출: 성공\n";
    
    // 로그인 시도
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $loginUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        '_token' => $csrfToken,
        'email' => 'admin@ykp.com',
        'password' => 'password'
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_HEADER, true);
    $loginResponse = curl_exec($ch);
    $loginCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "3. 로그인 시도: HTTP $loginCode\n";
    
    if ($loginCode == 302) {
        echo "4. 로그인 성공! 대시보드로 리다이렉션\n";
        
        // 대시보드 접근 테스트
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $dashboardUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
        curl_setopt($ch, CURLOPT_HEADER, true);
        $dashResponse = curl_exec($ch);
        $dashCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        echo "5. 대시보드 접근: HTTP $dashCode\n";
        
        // AgGrid 페이지 접근 테스트
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $aggridUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
        curl_setopt($ch, CURLOPT_HEADER, true);
        $aggridResponse = curl_exec($ch);
        $aggridCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        echo "6. AgGrid 페이지 접근: HTTP $aggridCode\n";
        
        if ($aggridCode == 200) {
            echo "✅ 인증 시스템 완전 작동!\n";
        }
    }
} else {
    echo "2. CSRF 토큰 추출: 실패\n";
}

// 임시 파일 정리
if (file_exists($cookieFile)) {
    unlink($cookieFile);
}

echo "=== 테스트 완료 ===\n";