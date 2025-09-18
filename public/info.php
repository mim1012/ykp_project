<?php
// Railway debugging info
header('Content-Type: text/plain');

echo "=== SERVER INFO ===\n";
echo "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'not set') . "\n";
echo "HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'not set') . "\n";
echo "SERVER_PORT: " . ($_SERVER['SERVER_PORT'] ?? 'not set') . "\n";
echo "SERVER_NAME: " . ($_SERVER['SERVER_NAME'] ?? 'not set') . "\n";
echo "REMOTE_ADDR: " . ($_SERVER['REMOTE_ADDR'] ?? 'not set') . "\n";

echo "\n=== HEADERS ===\n";
foreach (getallheaders() as $name => $value) {
    echo "$name: $value\n";
}

echo "\n=== ENV ===\n";
echo "PORT: " . ($_ENV['PORT'] ?? 'not set') . "\n";
echo "RAILWAY_ENVIRONMENT: " . ($_ENV['RAILWAY_ENVIRONMENT'] ?? 'not set') . "\n";

echo "\n=== PHP ===\n";
echo "Version: " . PHP_VERSION . "\n";
echo "SAPI: " . php_sapi_name() . "\n";