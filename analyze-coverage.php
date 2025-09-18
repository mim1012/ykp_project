<?php

/**
 * Code Coverage Analysis Tool
 * Analyzes code structure to estimate test coverage without PCOV/Xdebug
 */

require_once 'vendor/autoload.php';

use PhpParser\ParserFactory;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

class CoverageAnalyzer
{
    private array $classes = [];
    private array $methods = [];
    private array $testedMethods = [];

    public function analyze(): void
    {
        echo "\n========================================\n";
        echo "  Code Coverage Analysis (Estimated)\n";
        echo "========================================\n\n";

        // Analyze source files
        $this->analyzeDirectory('app/');

        // Analyze test files
        $this->analyzeTests('tests/');

        // Generate report
        $this->generateReport();
    }

    private function analyzeDirectory(string $dir): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $this->analyzeFile($file->getPathname());
            }
        }
    }

    private function analyzeFile(string $file): void
    {
        $code = file_get_contents($file);

        // Count classes and methods
        preg_match_all('/class\s+(\w+)/', $code, $classMatches);
        preg_match_all('/public\s+function\s+(\w+)/', $code, $methodMatches);

        foreach ($classMatches[1] as $class) {
            $this->classes[$class] = $file;
        }

        foreach ($methodMatches[1] as $method) {
            $this->methods[] = $method;
        }
    }

    private function analyzeTests(string $dir): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $code = file_get_contents($file->getPathname());

                // Find tested methods
                preg_match_all('/test_?(\w+)/', $code, $testMatches);
                preg_match_all('/->(\w+)\(/', $code, $callMatches);

                foreach ($testMatches[1] as $test) {
                    $this->testedMethods[] = $test;
                }

                foreach ($callMatches[1] as $call) {
                    $this->testedMethods[] = $call;
                }
            }
        }
    }

    private function generateReport(): void
    {
        $totalClasses = count($this->classes);
        $totalMethods = count($this->methods);
        $testedMethods = count(array_unique($this->testedMethods));

        $estimatedCoverage = $totalMethods > 0
            ? round(($testedMethods / $totalMethods) * 100, 2)
            : 0;

        echo "📊 Coverage Analysis Results\n";
        echo "============================\n\n";

        echo "Source Code Statistics:\n";
        echo "  • Total Classes: $totalClasses\n";
        echo "  • Total Methods: $totalMethods\n";
        echo "  • Tested Methods (estimated): $testedMethods\n\n";

        echo "Coverage by Component:\n";
        $this->analyzeComponent('Models', 'app/Models/');
        $this->analyzeComponent('Controllers', 'app/Http/Controllers/');
        $this->analyzeComponent('Helpers', 'app/Helpers/');
        $this->analyzeComponent('Services', 'app/Application/Services/');

        echo "\n";
        echo "📈 Estimated Overall Coverage: {$estimatedCoverage}%\n";

        if ($estimatedCoverage < 50) {
            echo "⚠️  Coverage is LOW - More tests needed!\n";
        } elseif ($estimatedCoverage < 80) {
            echo "⚡ Coverage is MODERATE - Keep adding tests\n";
        } else {
            echo "✅ Coverage is GOOD - Well done!\n";
        }

        echo "\nRecommendations:\n";
        $this->generateRecommendations($estimatedCoverage);
    }

    private function analyzeComponent(string $name, string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $files = glob($path . '*.php');
        $count = count($files);

        echo "  • $name: $count files\n";
    }

    private function generateRecommendations(float $coverage): void
    {
        if ($coverage < 50) {
            echo "  1. Add unit tests for SalesCalculator methods\n";
            echo "  2. Test all Controller actions\n";
            echo "  3. Cover Model relationships and scopes\n";
        } elseif ($coverage < 80) {
            echo "  1. Add edge case tests\n";
            echo "  2. Test error handling paths\n";
            echo "  3. Cover API response variations\n";
        } else {
            echo "  1. Maintain current coverage level\n";
            echo "  2. Add tests for new features\n";
            echo "  3. Consider mutation testing\n";
        }
    }
}

// Run the analyzer
$analyzer = new CoverageAnalyzer();
$analyzer->analyze();