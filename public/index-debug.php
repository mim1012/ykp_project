<?php

// Debug version of index.php for Railway
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '/var/www/html/storage/logs/php_errors.log');

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader with error handling
$autoloadFile = __DIR__.'/../vendor/autoload.php';
if (!file_exists($autoloadFile)) {
    die('Autoload file not found. Run composer install.');
}
require $autoloadFile;

// Bootstrap Laravel and send the response with error handling
try {
    $app = require_once __DIR__.'/../bootstrap/app.php';

    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

    $response = $kernel->handle(
        $request = Request::capture()
    )->send();

    $kernel->terminate($request, $response);
} catch (\Exception $e) {
    // Log error to stderr for Railway logs
    error_log('Laravel Bootstrap Error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());

    // Return 502 with error details
    http_response_code(502);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Application failed to start',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    exit(1);
}