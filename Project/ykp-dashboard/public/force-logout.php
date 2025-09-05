<?php
/**
 * 강제 로그아웃 페이지 - 브라우저 세션 완전 초기화
 */

// 모든 쿠키 삭제
if (!empty($_COOKIE)) {
    foreach ($_COOKIE as $key => $value) {
        setcookie($key, '', time() - 3600, '/', '', false, true);
        setcookie($key, '', time() - 3600, '/');
    }
}

// 세션 시작
session_start();

// 세션 완전 파괴
$_SESSION = [];
if (session_id() != '' || isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}
session_destroy();

?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>로그아웃 - YKP ERP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <meta http-equiv="refresh" content="3;url=/login">
</head>
<body class="bg-gray-50 flex items-center justify-center min-h-screen">
    <div class="bg-white p-8 rounded-lg shadow-md text-center">
        <h1 class="text-2xl font-bold text-gray-800 mb-4">🔓 로그아웃 완료</h1>
        <p class="text-gray-600 mb-4">모든 세션이 완전히 초기화되었습니다.</p>
        
        <div class="space-y-3">
            <p class="text-sm text-green-600">✅ 브라우저 쿠키 삭제</p>
            <p class="text-sm text-green-600">✅ 서버 세션 파괴</p>
            <p class="text-sm text-green-600">✅ 로그인 상태 초기화</p>
        </div>
        
        <div class="mt-6">
            <p class="text-sm text-gray-500 mb-3">3초 후 자동으로 로그인 페이지로 이동합니다.</p>
            <a href="/login" class="bg-blue-500 text-white px-6 py-2 rounded hover:bg-blue-600">
                🔐 바로 로그인하기
            </a>
        </div>
        
        <div class="mt-6 p-4 bg-blue-50 rounded text-sm">
            <p class="font-bold text-blue-800 mb-2">🔄 새 계정으로 로그인하려면:</p>
            <ol class="text-left text-blue-700 space-y-1">
                <li>1. 이 페이지에서 완전 로그아웃</li>
                <li>2. 로그인 페이지에서 새 계정 입력</li>
                <li>3. 브라우저 캐시 없이 깨끗한 로그인</li>
            </ol>
        </div>
        
        <div class="mt-4 text-xs text-gray-400">
            Generated at: <?php echo date('Y-m-d H:i:s T'); ?>
        </div>
    </div>

    <script>
        // localStorage와 sessionStorage도 정리
        localStorage.clear();
        sessionStorage.clear();
        
        console.log('✅ 모든 브라우저 저장소 초기화 완료');
        
        // 3초 후 자동 이동
        setTimeout(() => {
            window.location.href = '/login';
        }, 3000);
    </script>
</body>
</html>