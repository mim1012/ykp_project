<?php
/**
 * Railway Health Check Endpoint
 * 정적 200 응답을 반환하여 컨테이너 상태 확인
 * Laravel 부팅과 무관하게 즉시 응답
 */

http_response_code(200);
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

echo json_encode([
    'status' => 'ok',
    'timestamp' => date('Y-m-d H:i:s T'),
    'service' => 'ykp-dashboard',
    'environment' => 'railway'
]);
exit;