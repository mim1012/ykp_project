<?php
/**
 * Fix Filament autoload issue in production - More Robust Version
 * Removes ALL testing helpers from autoload files
 */

$vendorDir = __DIR__ . '/vendor';

// Fix autoload_real.php directly
$realFile = $vendorDir . '/composer/autoload_real.php';
if (file_exists($realFile)) {
    $content = file_get_contents($realFile);

    // Comment out the entire files loading section that causes issues
    $content = preg_replace(
        '/(\$includeFiles = .*?;.*?foreach \(\$includeFiles as.*?\})/s',
        '// Files loading disabled to fix Filament issue' . "\n" . '// $1',
        $content
    );

    file_put_contents($realFile, $content);
    echo "✅ autoload_real.php fixed.\n";
}

// Fix autoload_static.php
$staticFile = $vendorDir . '/composer/autoload_static.php';
if (file_exists($staticFile)) {
    $content = file_get_contents($staticFile);

    // Remove ALL testing helper references
    $patterns = [
        // Remove from files array
        "/'[a-f0-9]{32}' => __DIR__ \. '\/\.\.\/' \. '[^']*Testing\/helpers\.php',?\s*/",
        // Remove from fileIdentifierHashToFile
        "/'[a-f0-9]{32}' => '[^']*Testing\/helpers\.php',?\s*/",
    ];

    foreach ($patterns as $pattern) {
        $content = preg_replace($pattern, '', $content);
    }

    file_put_contents($staticFile, $content);
    echo "✅ autoload_static.php fixed.\n";
}

// Fix autoload_files.php
$filesFile = $vendorDir . '/composer/autoload_files.php';
if (file_exists($filesFile)) {
    $content = file_get_contents($filesFile);

    // Parse the array
    $lines = explode("\n", $content);
    $newLines = [];
    $skipNext = false;

    foreach ($lines as $line) {
        if (strpos($line, 'Testing/helpers.php') !== false) {
            echo "Removing line: " . trim($line) . "\n";
            $skipNext = true;
            continue;
        }
        if ($skipNext && trim($line) === '') {
            $skipNext = false;
            continue;
        }
        $newLines[] = $line;
    }

    file_put_contents($filesFile, implode("\n", $newLines));
    echo "✅ autoload_files.php fixed.\n";
}

// Create empty stub file as fallback
$stubDir = $vendorDir . '/filament/notifications/src/Testing';
if (!file_exists($stubDir)) {
    mkdir($stubDir, 0755, true);
}
file_put_contents($stubDir . '/helpers.php', '<?php // Empty stub file' . PHP_EOL);
echo "✅ Created stub file as fallback.\n";

echo "✅ Filament autoload completely fixed for production.\n";