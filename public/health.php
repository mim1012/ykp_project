<?php
// Simple health check - no Laravel dependencies
header('Content-Type: application/json');
http_response_code(200);

echo json_encode([
    'status' => 'healthy',
    'service' => 'ykp-dashboard',
    'timestamp' => date('c'),
    'php_version' => PHP_VERSION
]);