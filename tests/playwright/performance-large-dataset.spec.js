import { test, expect } from '@playwright/test';

/**
 * Test Suite: Large Dataset Performance
 * Purpose: Verify system handles 1000+ records without freezing
 * Priority: HIGH
 * Coverage Gap: Performance testing
 */

test.describe('Large Dataset Performance Tests', () => {
  test.setTimeout(120000); // 2 minute timeout for these tests

  test.beforeEach(async ({ page }) => {
    // Login
    await page.goto('/login');
    await page.fill('input[name="email"]', 'store@ykp.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('/dashboard');
  });

  test('Handles 1000+ sales records without freezing', async ({ page }) => {
    // First, seed database with large dataset
    console.log('Seeding 1000 records via API...');

    const seedStart = Date.now();
    await page.evaluate(async () => {
      const records = Array.from({ length: 1000 }, (_, i) => ({
        store_id: 1,
        sale_date: '2025-10-29',
        product_model: `Model-${i}`,
        customer_name: `Customer ${i}`,
        customer_phone: `010-${String(i).padStart(4, '0')}-${String(i).padStart(4, '0')}`,
        agency: 'SKT',
        price_setting: 800000,
        verbal1: 100000,
        verbal2: 50000,
        grade_amount: 30000,
        addon_amount: 20000,
        paper_cash: 50000,
        usim_fee: 3000,
        new_mnp_discount: 10000,
        deduction: 5000,
        cash_received: 30000,
        payback: 20000,
        settlement_amount: Math.floor(Math.random() * 500000) + 100000
      }));

      // Send in batches of 100
      for (let i = 0; i < records.length; i += 100) {
        const batch = records.slice(i, i + 100);
        await fetch('/api/sales/bulk-save', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': window.csrfToken || document.querySelector('meta[name="csrf-token"]')?.content || ''
          },
          body: JSON.stringify({ sales: batch })
        });
      }
    });

    const seedDuration = Date.now() - seedStart;
    console.log(`Seeding completed in ${seedDuration}ms`);

    // Navigate to sales grid and measure load time
    console.log('Loading sales grid with 1000+ records...');
    const startTime = Date.now();

    await page.goto('/sales/complete-aggrid');
    await page.waitForSelector('.ag-grid', { timeout: 30000 });
    await page.waitForTimeout(2000); // Wait for data to load

    const loadTime = Date.now() - startTime;
    console.log(`Page load time: ${loadTime}ms`);

    // Verify performance - should load in < 10 seconds
    expect(loadTime).toBeLessThan(10000);

    // Check if grid rendered
    const gridVisible = await page.locator('.ag-grid').isVisible();
    expect(gridVisible).toBe(true);

    // Test scrolling performance
    console.log('Testing scroll performance...');
    const scrollStart = Date.now();

    await page.evaluate(() => {
      const viewport = document.querySelector('.ag-body-viewport');
      if (viewport) {
        viewport.scrollTop = 10000;
      }
    });

    await page.waitForTimeout(500);

    const scrollTime = Date.now() - scrollStart;
    console.log(`Scroll time: ${scrollTime}ms`);

    // Verify rows are still visible after scroll
    const rowCount = await page.locator('.ag-row').count();
    expect(rowCount).toBeGreaterThan(0);
    console.log(`Visible rows after scroll: ${rowCount}`);

    // Test filtering performance
    console.log('Testing filter performance...');
    const filterStart = Date.now();

    // Apply a filter (if filter UI exists)
    const filterInput = page.locator('input[placeholder*="검색"], input[placeholder*="필터"]');
    if (await filterInput.count() > 0) {
      await filterInput.first().fill('Model-5');
      await page.waitForTimeout(1000);

      const filterTime = Date.now() - filterStart;
      console.log(`Filter time: ${filterTime}ms`);

      // Filter should complete in < 2 seconds
      expect(filterTime).toBeLessThan(2000);
    }
  });

  test('AG-Grid virtual scrolling works correctly', async ({ page }) => {
    await page.goto('/sales/complete-aggrid');
    await page.waitForSelector('table.min-w-full', { timeout: 10000 });
    await page.waitForTimeout(2000);

    // Get initial row count (should be limited due to virtual scrolling)
    const initialRowCount = await page.locator('.ag-row').count();
    console.log(`Initial rendered rows: ${initialRowCount}`);

    // Scroll to middle
    await page.evaluate(() => {
      const viewport = document.querySelector('.ag-body-viewport');
      if (viewport) {
        viewport.scrollTop = viewport.scrollHeight / 2;
      }
    });
    await page.waitForTimeout(1000);

    const middleRowCount = await page.locator('.ag-row').count();
    console.log(`Rows after scroll to middle: ${middleRowCount}`);

    // Scroll to bottom
    await page.evaluate(() => {
      const viewport = document.querySelector('.ag-body-viewport');
      if (viewport) {
        viewport.scrollTop = viewport.scrollHeight;
      }
    });
    await page.waitForTimeout(1000);

    const bottomRowCount = await page.locator('.ag-row').count();
    console.log(`Rows after scroll to bottom: ${bottomRowCount}`);

    // Virtual scrolling should maintain roughly same number of rendered rows
    expect(Math.abs(initialRowCount - bottomRowCount)).toBeLessThan(50);
  });

  test('Bulk save of 500 records completes within reasonable time', async ({ page }) => {
    await page.goto('/sales/complete-aggrid');
    await page.waitForSelector('table.min-w-full', { timeout: 10000 });

    // Add 500 rows via API
    console.log('Creating 500 records in bulk...');
    const bulkStart = Date.now();

    await page.evaluate(async () => {
      const records = Array.from({ length: 500 }, (_, i) => ({
        store_id: 1,
        sale_date: '2025-10-30',
        product_model: `Bulk-${i}`,
        settlement_amount: 150000 + i
      }));

      await fetch('/api/sales/bulk-save', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': window.csrfToken || document.querySelector('meta[name="csrf-token"]')?.content || ''
        },
        body: JSON.stringify({ sales: records })
      });
    });

    const bulkDuration = Date.now() - bulkStart;
    console.log(`Bulk save completed in ${bulkDuration}ms`);

    // Should complete in < 30 seconds
    expect(bulkDuration).toBeLessThan(30000);

    // Reload and verify data appears
    await page.reload();
    await page.waitForSelector('table.min-w-full', { timeout: 10000 });
    await page.waitForTimeout(2000);

    const rowCount = await page.locator('.ag-row').count();
    expect(rowCount).toBeGreaterThan(0);
  });
});
