<?php
/**
 * Fix Filament autoload issue in production
 * Removes testing helpers from autoload files
 */

$vendorDir = __DIR__ . '/vendor';
$autoloadFile = $vendorDir . '/composer/autoload_files.php';

if (!file_exists($autoloadFile)) {
    echo "Autoload file not found. Run composer install first.\n";
    exit(1);
}

// Read the autoload files
$content = file_get_contents($autoloadFile);

// Remove any lines containing 'Testing/helpers.php'
$lines = explode("\n", $content);
$filteredLines = [];

foreach ($lines as $line) {
    if (!strpos($line, 'Testing/helpers.php')) {
        $filteredLines[] = $line;
    } else {
        echo "Removing testing helper: " . trim($line) . "\n";
    }
}

// Write back the filtered content
$newContent = implode("\n", $filteredLines);
file_put_contents($autoloadFile, $newContent);

echo "✅ Filament autoload fixed for production.\n";

// Also fix autoload_static.php
$staticFile = $vendorDir . '/composer/autoload_static.php';
if (file_exists($staticFile)) {
    $content = file_get_contents($staticFile);

    // Remove testing helpers from files array
    $pattern = "/'[a-f0-9]{32}' => __DIR__ \. '\/\.\.\/' \. '[^']*Testing\/helpers\.php',?\n/";
    $content = preg_replace($pattern, '', $content);

    file_put_contents($staticFile, $content);
    echo "✅ Static autoload fixed.\n";
}