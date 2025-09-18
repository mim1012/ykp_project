<?php
/**
 * Fix Carbon Test trait issue in production
 * The Carbon\Traits\Test trait is not available in production builds
 */

echo "🔧 Fixing Carbon Test trait issue for production...\n";

$vendorDir = __DIR__ . '/vendor';
$carbonDateFile = $vendorDir . '/nesbot/carbon/src/Carbon/Traits/Date.php';

if (!file_exists($carbonDateFile)) {
    echo "⚠️ Carbon Date.php not found. Skipping...\n";
    exit(0);
}

// Read the file
$content = file_get_contents($carbonDateFile);

// Remove the Test trait from the use statement
$patterns = [
    // Remove Test trait from use statement
    '/use\s+Test,/' => 'use',
    '/,\s*Test\s*;/' => ';',
    '/use\s+Test\s*;/' => '',
    // Remove any methods that depend on Test trait
    '/public\s+static\s+function\s+setTestNow[^}]*}/' => '',
    '/public\s+static\s+function\s+getTestNow[^}]*}/' => '',
];

foreach ($patterns as $pattern => $replacement) {
    $content = preg_replace($pattern, $replacement, $content);
}

// Write back
file_put_contents($carbonDateFile, $content);

echo "✅ Carbon Test trait issue fixed.\n";

// Also check if the Test trait file exists and create a stub if not
$testTraitFile = $vendorDir . '/nesbot/carbon/src/Carbon/Traits/Test.php';
if (!file_exists($testTraitFile)) {
    $dir = dirname($testTraitFile);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    // Create a stub Test trait
    $stubContent = '<?php
namespace Carbon\Traits;

trait Test {
    // Stub trait for production
}';

    file_put_contents($testTraitFile, $stubContent);
    echo "✅ Created stub Test trait.\n";
}