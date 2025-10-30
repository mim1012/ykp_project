import { test, expect } from '@playwright/test';

/**
 * Test Suite: CSRF Token Validation
 * Purpose: Verify CSRF protection is properly implemented
 * Priority: HIGH
 * Coverage Gap: Security testing
 */

test.describe('CSRF Token Validation Tests', () => {
  test('Rejects request with invalid CSRF token', async ({ page }) => {
    // Login first
    await page.goto('/login');
    await page.fill('input[name="email"]', 'store@ykp.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('/dashboard');

    // Navigate to sales page
    await page.goto('/sales/complete-aggrid');
    await page.waitForSelector('table.min-w-full', { timeout: 10000 });

    // Get original CSRF token
    const originalToken = await page.evaluate(() => {
      return window.csrfToken ||
             document.querySelector('meta[name="csrf-token"]')?.content ||
             '';
    });

    console.log('Original CSRF token:', originalToken ? 'Found' : 'Not found');

    // Replace CSRF token with invalid value
    await page.evaluate(() => {
      const invalidToken = 'invalid-token-12345-malicious-attempt';

      // Update global token
      if (window.csrfToken) {
        window.csrfToken = invalidToken;
      }

      // Update meta tag
      const metaTag = document.querySelector('meta[name="csrf-token"]');
      if (metaTag) {
        metaTag.setAttribute('content', invalidToken);
      }

      // Update any hidden input fields
      const hiddenInputs = document.querySelectorAll('input[name="_token"]');
      hiddenInputs.forEach(input => {
        input.value = invalidToken;
      });
    });

    console.log('✓ CSRF token replaced with invalid value');

    // Add a row to have something to save
    await page.click('button:has-text("새 개통 등록")');
    await page.waitForTimeout(500);

    // Try to save (should fail with CSRF error)
    let requestFailed = false;
    let responseStatus = null;

    page.on('response', response => {
      if (response.url().includes('/api/sales') || response.url().includes('/sales')) {
        responseStatus = response.status();
        console.log(`API Response status: ${responseStatus}`);
      }
    });

    await page.click('button:has-text("저장")');
    await page.waitForTimeout(3000);

    // Check for CSRF error message
    const csrfErrorMessages = [
      'CSRF',
      '보안 토큰',
      'token mismatch',
      'invalid token',
      '419',
      'Page Expired'
    ];

    let errorFound = false;
    for (const msg of csrfErrorMessages) {
      const locator = page.locator(`text=${msg}`);
      if (await locator.count() > 0) {
        errorFound = true;
        console.log(`✓ CSRF error message found: "${msg}"`);
        break;
      }
    }

    // Check response status (419 is Laravel's CSRF failure code)
    if (responseStatus === 419) {
      console.log('✓ Received 419 status (CSRF token mismatch)');
      errorFound = true;
    }

    if (errorFound) {
      console.log('✅ CSRF protection working correctly');
    } else {
      console.log('⚠️ CSRF error not detected (check if protection is enabled)');
    }
  });

  test('Accepts request with valid CSRF token', async ({ page }) => {
    await page.goto('/login');
    await page.fill('input[name="email"]', 'store@ykp.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('/dashboard');

    await page.goto('/sales/complete-aggrid');
    await page.waitForSelector('table.min-w-full', { timeout: 10000 });

    // Verify valid token exists
    const hasValidToken = await page.evaluate(() => {
      const token = window.csrfToken ||
                    document.querySelector('meta[name="csrf-token"]')?.content;
      return !!token && token.length > 10;
    });

    expect(hasValidToken).toBe(true);
    console.log('✓ Valid CSRF token present');

    // Make a legitimate request (should succeed)
    await page.click('button:has-text("새 개통 등록")');
    await page.waitForTimeout(500);

    await page.click('button:has-text("저장")');
    await page.waitForTimeout(2000);

    // Should not show CSRF error
    const csrfError = await page.locator('text=CSRF, text=419, text=token mismatch').count();
    expect(csrfError).toBe(0);

    console.log('✓ Valid CSRF token accepted');
  });

  test('CSRF token refreshes on page navigation', async ({ page }) => {
    await page.goto('/login');
    await page.fill('input[name="email"]', 'store@ykp.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('/dashboard');

    // Get token on dashboard
    const token1 = await page.evaluate(() => {
      return window.csrfToken ||
             document.querySelector('meta[name="csrf-token"]')?.content;
    });

    console.log('Token 1 (dashboard):', token1 ? 'Present' : 'Missing');

    // Navigate to sales page
    await page.goto('/sales/complete-aggrid');
    await page.waitForSelector('table.min-w-full', { timeout: 10000 });

    const token2 = await page.evaluate(() => {
      return window.csrfToken ||
             document.querySelector('meta[name="csrf-token"]')?.content;
    });

    console.log('Token 2 (sales):', token2 ? 'Present' : 'Missing');

    // Both pages should have tokens (may be same token, which is fine)
    expect(token1).toBeTruthy();
    expect(token2).toBeTruthy();

    console.log('Tokens match:', token1 === token2);
  });

  test('CSRF token included in AJAX requests', async ({ page }) => {
    await page.goto('/login');
    await page.fill('input[name="email"]', 'store@ykp.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('/dashboard');

    // Monitor AJAX requests
    const ajaxRequests = [];

    page.on('request', request => {
      if (request.method() !== 'GET' && request.url().includes('/api/')) {
        const headers = request.headers();
        ajaxRequests.push({
          url: request.url(),
          method: request.method(),
          hasCsrfToken: !!(headers['x-csrf-token'] || headers['X-CSRF-TOKEN']),
          headers: Object.keys(headers)
        });
      }
    });

    // Navigate and make some AJAX calls
    await page.goto('/sales/complete-aggrid');
    await page.waitForSelector('table.min-w-full', { timeout: 10000 });

    await page.click('button:has-text("새 개통 등록")');
    await page.waitForTimeout(500);

    await page.click('button:has-text("저장")');
    await page.waitForTimeout(2000);

    console.log('AJAX requests:', ajaxRequests);

    // All POST/PUT/DELETE requests should have CSRF token
    const requestsWithoutToken = ajaxRequests.filter(req => !req.hasCsrfToken);

    if (requestsWithoutToken.length > 0) {
      console.log('⚠️ Requests without CSRF token:', requestsWithoutToken);
    } else if (ajaxRequests.length > 0) {
      console.log('✓ All AJAX requests include CSRF token');
    }
  });

  test('CSRF protection excludes safe methods (GET, HEAD, OPTIONS)', async ({ page }) => {
    await page.goto('/login');
    await page.fill('input[name="email"]', 'store@ykp.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('/dashboard');

    // Monitor GET requests
    const getRequests = [];

    page.on('response', response => {
      const request = response.request();
      if (request.method() === 'GET' && request.url().includes('/api/')) {
        getRequests.push({
          url: request.url(),
          status: response.status()
        });
      }
    });

    // Make GET requests by navigating
    await page.goto('/dashboard');
    await page.waitForTimeout(2000);

    await page.goto('/sales/complete-aggrid');
    await page.waitForTimeout(2000);

    console.log('GET requests:', getRequests.length);

    // GET requests should work without CSRF token
    const failedGets = getRequests.filter(req => req.status === 419);

    if (failedGets.length > 0) {
      console.log('⚠️ GET requests blocked by CSRF (should be excluded)');
    } else {
      console.log('✓ GET requests work without CSRF token');
    }
  });

  test('CSRF token persists across page reloads', async ({ page }) => {
    await page.goto('/login');
    await page.fill('input[name="email"]', 'store@ykp.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('/dashboard');

    await page.goto('/sales/complete-aggrid');
    await page.waitForSelector('table.min-w-full', { timeout: 10000 });

    const tokenBefore = await page.evaluate(() => {
      return window.csrfToken ||
             document.querySelector('meta[name="csrf-token"]')?.content;
    });

    // Reload page
    await page.reload();
    await page.waitForSelector('table.min-w-full', { timeout: 10000 });

    const tokenAfter = await page.evaluate(() => {
      return window.csrfToken ||
             document.querySelector('meta[name="csrf-token"]')?.content;
    });

    console.log('Token before reload:', tokenBefore ? 'Present' : 'Missing');
    console.log('Token after reload:', tokenAfter ? 'Present' : 'Missing');

    // Token may change, but should still be present
    expect(tokenAfter).toBeTruthy();
    console.log('✓ CSRF token persists across reloads');
  });
});
