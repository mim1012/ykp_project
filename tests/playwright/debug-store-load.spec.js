import { test } from '@playwright/test';

const CREDENTIALS = {
    headquarters: { email: 'admin@ykp.com', password: 'password' },
};

test('Debug store loading', async ({ page }) => {
    // Collect console messages
    const consoleMessages = [];
    page.on('console', msg => {
        consoleMessages.push(`[${msg.type()}] ${msg.text()}`);
        console.log(`BROWSER CONSOLE [${msg.type()}]:`, msg.text());
    });

    // Collect page errors
    page.on('pageerror', error => {
        console.log('PAGE ERROR:', error.message);
    });

    // Login
    await page.goto('http://localhost:8000/login');
    await page.fill('input[name="email"]', CREDENTIALS.headquarters.email);
    await page.fill('input[name="password"]', CREDENTIALS.headquarters.password);
    await page.click('button[type="submit"]');
    await page.waitForURL('**/dashboard', { timeout: 10000 });

    console.log('✅ Logged in successfully');

    // Navigate to store management
    await page.goto('http://localhost:8000/management/stores');
    await page.waitForLoadState('networkidle');

    console.log('✅ Navigated to stores page');

    // Wait a bit for JavaScript to execute
    await page.waitForTimeout(5000);

    // Get page content
    const content = await page.content();
    console.log('Page has loadStores function:', content.includes('window.loadStores'));
    console.log('Page has stores-grid:', content.includes('id="stores-grid"'));

    // Evaluate JavaScript in page context
    const debugInfo = await page.evaluate(() => {
        return {
            hasLoadStores: typeof window.loadStores === 'function',
            hasStoresGrid: document.getElementById('stores-grid') !== null,
            storesGridContent: document.getElementById('stores-grid')?.innerHTML.substring(0, 200),
            initialized: window.storesPageInitialized,
        };
    });

    console.log('\n=== DEBUG INFO ===');
    console.log('Has loadStores function:', debugInfo.hasLoadStores);
    console.log('Has stores-grid element:', debugInfo.hasStoresGrid);
    console.log('Stores grid content:', debugInfo.storesGridContent);
    console.log('Page initialized:', debugInfo.initialized);
    console.log('\n=== CONSOLE MESSAGES ===');
    consoleMessages.forEach(msg => console.log(msg));

    // Take screenshot
    await page.screenshot({ path: 'test-results/debug-stores.png', fullPage: true });
    console.log('\n✅ Screenshot saved to test-results/debug-stores.png');

    // Keep browser open for manual inspection
    await page.pause();
});
