<?php

/**
 * 강제 로그아웃 페이지 - 즉시 리다이렉트 (UX 개선)
 */

// 모든 쿠키 삭제
if (! empty($_COOKIE)) {
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

// 즉시 로그인 페이지로 리다이렉트
header('Location: /login');
exit;
