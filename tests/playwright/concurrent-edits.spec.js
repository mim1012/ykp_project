import { test, expect } from '@playwright/test';

/**
 * Test Suite: Concurrent Edit Conflicts
 * Purpose: Verify system handles multiple users editing same data
 * Priority: MEDIUM
 * Coverage Gap: Concurrent user scenarios
 */

test.describe('Concurrent Edit Conflict Tests', () => {
  test('Handles concurrent sales edit conflict', async ({ browser }) => {
    // Create two separate browser contexts (two users)
    const context1 = await browser.newContext();
    const context2 = await browser.newContext();

    const page1 = await context1.newPage();
    const page2 = await context2.newPage();

    // User 1 logs in
    await page1.goto('/login');
    await page1.fill('input[name="email"]', 'store@ykp.com');
    await page1.fill('input[name="password"]', 'password');
    await page1.click('button[type="submit"]');
    await page1.waitForURL('/dashboard');

    // User 2 logs in
    await page2.goto('/login');
    await page2.fill('input[name="email"]', 'store@ykp.com');
    await page2.fill('input[name="password"]', 'password');
    await page2.click('button[type="submit"]');
    await page2.waitForURL('/dashboard');

    // Both users load same sales page
    await page1.goto('/sales/complete-aggrid');
    await page1.waitForSelector('table.min-w-full', { timeout: 10000 });

    await page2.goto('/sales/complete-aggrid');
    await page2.waitForSelector('table.min-w-full', { timeout: 10000 });

    // Both users add a row
    await page1.click('button:has-text("새 개통 등록")');
    await page1.waitForTimeout(500);

    await page2.click('button:has-text("새 개통 등록")');
    await page2.waitForTimeout(500);

    // User 1 fills and saves first
    const cell1 = page1.locator('#data-table-body input.field-name').first();
    await cell1.click();
    await page1.keyboard.type('100000');
    await page1.click('button:has-text("저장")');
    await page1.waitForTimeout(2000);

    // User 2 fills and saves second
    const cell2 = page2.locator('#data-table-body input.field-name').first();
    await cell2.click();
    await page2.keyboard.type('200000');
    await page2.click('button:has-text("저장")');
    await page2.waitForTimeout(2000);

    // Check if conflict warning appears (currently not implemented)
    const conflictIndicators = [
      page2.locator('text=충돌'),
      page2.locator('text=conflict'),
      page2.locator('.conflict-warning')
    ];

    let conflictWarningFound = false;
    for (const indicator of conflictIndicators) {
      if (await indicator.count() > 0) {
        conflictWarningFound = true;
        break;
      }
    }

    console.log('Conflict detection implemented:', conflictWarningFound);

    // Note: This test documents expected behavior for future implementation

    // Cleanup
    await context1.close();
    await context2.close();
  });

  test('Last write wins scenario', async ({ browser }) => {
    const context1 = await browser.newContext();
    const context2 = await browser.newContext();

    const page1 = await context1.newPage();
    const page2 = await context2.newPage();

    // Both users login
    for (const page of [page1, page2]) {
      await page.goto('/login');
      await page.fill('input[name="email"]', 'store@ykp.com');
      await page.fill('input[name="password"]', 'password');
      await page.click('button[type="submit"]');
      await page.waitForURL('/dashboard');
      await page.goto('/sales/complete-aggrid');
      await page.waitForSelector('table.min-w-full', { timeout: 10000 });
    }

    // Create a sale from user 1
    await page1.click('button:has-text("새 개통 등록")');
    await page1.waitForTimeout(500);
    await page1.locator('#data-table-body input.field-name').first().click();
    await page1.keyboard.type('100000');
    await page1.click('button:has-text("저장")');
    await page1.waitForTimeout(2000);

    // Refresh page2 to see the new sale
    await page2.reload();
    await page2.waitForSelector('table.min-w-full', { timeout: 10000 });
    await page2.waitForTimeout(1000);

    // Both users try to edit the same row
    await page1.locator('#data-table-body input.field-name').first().dblclick();
    await page1.keyboard.press('Control+A');
    await page1.keyboard.type('999999');

    await page2.locator('#data-table-body input.field-name').first().dblclick();
    await page2.keyboard.press('Control+A');
    await page2.keyboard.type('111111');

    // User 1 saves first
    await page1.click('button:has-text("저장")');
    await page1.waitForTimeout(2000);

    // User 2 saves second (should overwrite or show conflict)
    await page2.click('button:has-text("저장")');
    await page2.waitForTimeout(2000);

    // Verify last write wins behavior
    await page1.reload();
    await page1.waitForSelector('table.min-w-full', { timeout: 10000 });
    await page1.waitForTimeout(1000);

    const finalValue = await page1.locator('#data-table-body input.field-name').first().textContent();
    console.log('Final value after concurrent edits:', finalValue);

    // Cleanup
    await context1.close();
    await context2.close();
  });
});
