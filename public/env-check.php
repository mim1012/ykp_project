<?php

/**
 * 간단한 .env 파일 상태 확인
 */
echo '<h1>🔍 Environment Check</h1>';

// 현재 작업 디렉토리
echo '<h2>📁 Current Directory</h2>';
echo 'Current Dir: '.getcwd().'<br>';
echo 'Document Root: '.$_SERVER['DOCUMENT_ROOT'].'<br>';

// 파일 존재 확인
echo '<h2>📄 File Status</h2>';
$files = [
    '.env' => '../.env',
    '.env.example' => '../.env.example',
    'bootstrap/app.php' => '../bootstrap/app.php',
    'vendor/autoload.php' => '../vendor/autoload.php',
];

foreach ($files as $name => $path) {
    $exists = file_exists($path);
    $readable = is_readable($path);
    echo "$name: ".($exists ? '✅' : '❌').' exists, '.($readable ? '✅' : '❌').' readable<br>';

    if ($exists && $name === '.env') {
        $size = filesize($path);
        echo "&nbsp;&nbsp;Size: $size bytes<br>";
        if ($size > 0 && $size < 2000) {
            echo '&nbsp;&nbsp;Content preview:<br><pre>'.htmlspecialchars(substr(file_get_contents($path), 0, 500)).'</pre>';
        }
    }
}

// 디렉토리 내용 확인
echo '<h2>📂 Directory Contents</h2>';
echo '<h3>Root directory (../):</h3>';
$files = scandir('..');
foreach ($files as $file) {
    if ($file[0] !== '.') {
        continue;
    }
    echo "$file<br>";
}

echo '<h3>Public directory (./):</h3>';
$files = scandir('.');
foreach ($files as $file) {
    if (substr($file, -4) === '.php') {
        echo "$file<br>";
    }
}

// Laravel 부트스트랩 시도 (단순)
echo '<h2>🚀 Laravel Bootstrap Test</h2>';
if (file_exists('../vendor/autoload.php')) {
    require_once '../vendor/autoload.php';
    echo '✅ Autoload loaded<br>';

    try {
        if (file_exists('../bootstrap/app.php')) {
            $app = require_once '../bootstrap/app.php';
            echo '✅ App created<br>';
            echo 'App class: '.get_class($app).'<br>';
        }
    } catch (Throwable $e) {
        echo '❌ Bootstrap error: '.$e->getMessage().'<br>';
    }
}

echo '<hr><small>Generated at: '.date('Y-m-d H:i:s T').'</small>';
