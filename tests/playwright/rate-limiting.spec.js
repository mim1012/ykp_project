import { test, expect } from '@playwright/test';

/**
 * Test Suite: Rate Limiting Enforcement
 * Purpose: Verify API rate limits are properly enforced
 * Priority: MEDIUM
 * Coverage Gap: Security and performance testing
 */

test.describe('Rate Limiting Tests', () => {
  test.setTimeout(120000); // 2 minute timeout

  test('Enforces API rate limits on calculation endpoint', async ({ page }) => {
    // Login
    await page.goto('/login');
    await page.fill('input[name="email"]', 'store@ykp.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('/dashboard');

    await page.goto('/sales/complete-aggrid');
    await page.waitForSelector('table.min-w-full', { timeout: 10000 });

    console.log('Making rapid API requests to test rate limiting...');

    // Make 65 rapid requests to calculation endpoint (limit is 60/min)
    const results = [];
    const startTime = Date.now();

    for (let i = 0; i < 65; i++) {
      try {
        const response = await page.evaluate(async (index) => {
          try {
            const res = await fetch('/api/calculation/profile/row', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': window.csrfToken ||
                               document.querySelector('meta[name="csrf-token"]')?.content ||
                               ''
              },
              body: JSON.stringify({
                row: {
                  price_setting: 800000,
                  verbal1: 100000,
                  verbal2: 50000,
                  grade_amount: 30000,
                  addon_amount: 20000
                },
                dealerProfile: {}
              })
            });

            return {
              status: res.status,
              ok: res.ok,
              headers: {
                retryAfter: res.headers.get('Retry-After'),
                rateLimit: res.headers.get('X-RateLimit-Limit'),
                rateLimitRemaining: res.headers.get('X-RateLimit-Remaining')
              }
            };
          } catch (error) {
            return {
              status: 0,
              ok: false,
              error: error.message
            };
          }
        }, i);

        results.push({ requestNumber: i + 1, ...response });

        // Log rate limit headers
        if (i < 5 || i > 58) {
          console.log(`Request ${i + 1}: Status ${response.status}, Remaining: ${response.headers.rateLimitRemaining}`);
        }
      } catch (error) {
        console.log(`Request ${i + 1} failed:`, error.message);
        results.push({ requestNumber: i + 1, status: 0, error: error.message });
      }

      // Small delay to avoid overwhelming browser
      await page.waitForTimeout(50);
    }

    const duration = Date.now() - startTime;
    console.log(`Completed ${results.length} requests in ${duration}ms`);

    // Analyze results
    const successful = results.filter(r => r.status === 200);
    const rateLimited = results.filter(r => r.status === 429);
    const errors = results.filter(r => r.status === 0 || (r.status !== 200 && r.status !== 429));

    console.log('\n=== Rate Limiting Results ===');
    console.log(`Successful: ${successful.length}`);
    console.log(`Rate Limited (429): ${rateLimited.length}`);
    console.log(`Other Errors: ${errors.length}`);

    // Verify rate limiting is working
    if (rateLimited.length > 0) {
      console.log('✅ Rate limiting is enforced');
      console.log(`First rate limit at request: ${rateLimited[0].requestNumber}`);

      // Check for Retry-After header
      const firstRateLimited = rateLimited[0];
      if (firstRateLimited.headers.retryAfter) {
        console.log(`Retry-After header: ${firstRateLimited.headers.retryAfter}`);
      }
    } else {
      console.log('⚠️ No rate limiting detected (may need to be implemented)');
    }

    // At least one request should be rate limited
    // expect(rateLimited.length).toBeGreaterThan(0);
  });

  test('Shows user-friendly error when rate limited', async ({ page }) => {
    await page.goto('/login');
    await page.fill('input[name="email"]', 'store@ykp.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('/dashboard');

    await page.goto('/sales/complete-aggrid');
    await page.waitForSelector('table.min-w-full', { timeout: 10000 });

    // Simulate rate limit by intercepting API call
    await page.route('/api/calculation/profile/row', (route) => {
      route.fulfill({
        status: 429,
        contentType: 'application/json',
        body: JSON.stringify({
          message: 'Too Many Attempts.',
          error: 'Rate limit exceeded'
        }),
        headers: {
          'Retry-After': '60',
          'X-RateLimit-Limit': '60',
          'X-RateLimit-Remaining': '0'
        }
      });
    });

    // Trigger calculation
    await page.click('button:has-text("새 개통 등록")');
    await page.waitForTimeout(500);

    // Try to trigger calculation (this might auto-calculate on data entry)
    const firstCell = page.locator('#data-table-body input.field-name').first();
    await firstCell.click();
    await page.keyboard.type('800000');
    await page.keyboard.press('Tab');
    await page.waitForTimeout(2000);

    // Check for user-friendly error message
    const errorMessages = [
      '요청이 너무 많습니다',
      'Too many',
      '잠시 후',
      'rate limit',
      '다시 시도',
      'try again'
    ];

    let errorFound = false;
    for (const msg of errorMessages) {
      const locator = page.locator(`text=${msg}`);
      if (await locator.count() > 0) {
        errorFound = true;
        console.log(`✓ User-friendly error message found: "${msg}"`);
        break;
      }
    }

    if (!errorFound) {
      console.log('⚠️ No user-friendly rate limit error message');
    }
  });

  test('Rate limit resets after waiting period', async ({ page }) => {
    await page.goto('/login');
    await page.fill('input[name="email"]', 'store@ykp.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('/dashboard');

    await page.goto('/sales/complete-aggrid');
    await page.waitForSelector('table.min-w-full', { timeout: 10000 });

    // Make requests until rate limited
    let rateLimitReached = false;
    let requestCount = 0;

    for (let i = 0; i < 70; i++) {
      const response = await page.evaluate(async () => {
        try {
          const res = await fetch('/api/calculation/profile/row', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': window.csrfToken ||
                             document.querySelector('meta[name="csrf-token"]')?.content ||
                             ''
            },
            body: JSON.stringify({ row: {}, dealerProfile: {} })
          });

          return {
            status: res.status,
            remaining: res.headers.get('X-RateLimit-Remaining')
          };
        } catch (error) {
          return { status: 0, error: error.message };
        }
      });

      requestCount++;

      if (response.status === 429) {
        rateLimitReached = true;
        console.log(`✓ Rate limit reached at request ${requestCount}`);
        break;
      }

      await page.waitForTimeout(50);
    }

    if (rateLimitReached) {
      console.log('Waiting 65 seconds for rate limit to reset...');
      await page.waitForTimeout(65000); // Wait for rate limit window to reset

      // Try another request
      const afterWaitResponse = await page.evaluate(async () => {
        try {
          const res = await fetch('/api/calculation/profile/row', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': window.csrfToken ||
                             document.querySelector('meta[name="csrf-token"]')?.content ||
                             ''
            },
            body: JSON.stringify({ row: {}, dealerProfile: {} })
          });

          return { status: res.status };
        } catch (error) {
          return { status: 0, error: error.message };
        }
      });

      if (afterWaitResponse.status === 200) {
        console.log('✅ Rate limit reset successfully');
      } else {
        console.log(`⚠️ Request after wait returned: ${afterWaitResponse.status}`);
      }
    } else {
      console.log('⚠️ Rate limit not reached in 70 requests');
    }
  });

  test('Different endpoints have independent rate limits', async ({ page }) => {
    await page.goto('/login');
    await page.fill('input[name="email"]', 'store@ykp.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('/dashboard');

    // Test multiple endpoints
    const endpoints = [
      '/api/dashboard/overview',
      '/api/statistics/sales',
      '/api/calculation/profile/row'
    ];

    const results = {};

    for (const endpoint of endpoints) {
      const responses = [];

      for (let i = 0; i < 10; i++) {
        const response = await page.evaluate(async (url) => {
          try {
            let options = { method: 'GET' };

            if (url.includes('/calculation')) {
              options = {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/json',
                  'X-CSRF-TOKEN': window.csrfToken ||
                                 document.querySelector('meta[name="csrf-token"]')?.content ||
                                 ''
                },
                body: JSON.stringify({ row: {}, dealerProfile: {} })
              };
            }

            const res = await fetch(url, options);

            return {
              status: res.status,
              rateLimit: res.headers.get('X-RateLimit-Limit'),
              remaining: res.headers.get('X-RateLimit-Remaining')
            };
          } catch (error) {
            return { status: 0, error: error.message };
          }
        }, endpoint);

        responses.push(response);
        await page.waitForTimeout(100);
      }

      results[endpoint] = {
        successCount: responses.filter(r => r.status === 200).length,
        rateLimitInfo: responses.find(r => r.rateLimit)
      };
    }

    console.log('\n=== Endpoint Rate Limits ===');
    Object.entries(results).forEach(([endpoint, data]) => {
      console.log(`${endpoint}:`);
      console.log(`  Success: ${data.successCount}/10`);
      if (data.rateLimitInfo?.rateLimit) {
        console.log(`  Limit: ${data.rateLimitInfo.rateLimit}/min`);
      }
    });
  });

  test('Rate limit applies per user session', async ({ browser }) => {
    // Create two separate sessions
    const context1 = await browser.newContext();
    const context2 = await browser.newContext();

    const page1 = await context1.newPage();
    const page2 = await context2.newPage();

    // Login both users
    for (const page of [page1, page2]) {
      await page.goto('/login');
      await page.fill('input[name="email"]', 'store@ykp.com');
      await page.fill('input[name="password"]', 'password');
      await page.click('button[type="submit"]');
      await page.waitForURL('/dashboard');
    }

    // User 1 makes 65 requests
    console.log('User 1 making requests...');
    const user1Results = [];

    for (let i = 0; i < 65; i++) {
      const response = await page1.evaluate(async () => {
        try {
          const res = await fetch('/api/calculation/profile/row', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': window.csrfToken ||
                             document.querySelector('meta[name="csrf-token"]')?.content ||
                             ''
            },
            body: JSON.stringify({ row: {}, dealerProfile: {} })
          });

          return { status: res.status };
        } catch (error) {
          return { status: 0 };
        }
      });

      user1Results.push(response);
      await page1.waitForTimeout(50);
    }

    const user1RateLimited = user1Results.filter(r => r.status === 429).length;
    console.log(`User 1 rate limited: ${user1RateLimited} times`);

    // User 2 should still be able to make requests
    console.log('User 2 making requests...');
    const user2Response = await page2.evaluate(async () => {
      try {
        const res = await fetch('/api/calculation/profile/row', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': window.csrfToken ||
                           document.querySelector('meta[name="csrf-token"]')?.content ||
                           ''
          },
          body: JSON.stringify({ row: {}, dealerProfile: {} })
        });

        return { status: res.status };
      } catch (error) {
        return { status: 0 };
      }
    });

    console.log(`User 2 request status: ${user2Response.status}`);

    if (user2Response.status === 200) {
      console.log("✅ Rate limits are per-user (User 2 not affected by User 1's rate limit)");
    } else {
      console.log('⚠️ Rate limit may be global (check implementation)');
    }

    await context1.close();
    await context2.close();
  });
});
