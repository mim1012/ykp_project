#!/usr/bin/env node

/**
 * Dashboard Build Script
 * Optimizes the build process for production deployment
 */

import { execSync } from 'child_process';
import { existsSync, rmSync } from 'fs';

console.log('🚀 Starting YKP Dashboard Build Process...\n');

// Clean previous builds
console.log('🧹 Cleaning previous builds...');
if (existsSync('public/build')) {
    rmSync('public/build', { recursive: true, force: true });
}

try {
    // Install dependencies
    console.log('📦 Installing dependencies...');
    execSync('npm install', { stdio: 'inherit' });

    // Build for production
    console.log('🏗️ Building for production...');
    execSync('npm run build', { stdio: 'inherit' });

    console.log('✅ Build completed successfully!');
    console.log('\n📊 Build Summary:');
    console.log('• React components: Optimized and chunked');
    console.log('• CSS: Minified and purged');
    console.log('• Assets: Compressed and versioned');
    console.log('• Code splitting: Enabled for lazy loading');
    
    console.log('\n🎯 Next Steps:');
    console.log('1. Update your route to use modern-dashboard-optimized.blade.php');
    console.log('2. Clear Laravel cache: php artisan cache:clear');
    console.log('3. Clear config cache: php artisan config:clear');
    console.log('4. Test the optimized dashboard');

} catch (error) {
    console.error('❌ Build failed:', error.message);
    process.exit(1);
}