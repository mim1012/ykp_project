import { test, expect } from '@playwright/test';

test.describe('[Deploy E2E] Stats Pages Render', () => {
  test('HQ statistics page renders with KPIs and charts', async ({ page }) => {
    await page.goto('/quick-login/headquarters');
    await expect(page).toHaveURL(/.*dashboard/);

    // Ensure some data exists
    const today = new Date().toISOString().slice(0, 10);
    await page.request.post('/api/sales/bulk', {
      data: { sales: [{ sale_date: today, carrier: 'SK', activation_type: '신규', model_name: 'HQ-STAT', base_price: 12345 }] },
      headers: { 'Content-Type': 'application/json' }
    });

    await page.goto('/statistics');
    // KPI elements
    await expect(page.locator('#total-sales')).toBeVisible();
    await expect(page.locator('#system-goal')).toBeVisible();
    // Charts canvases
    await expect(page.locator('#branchComparisonChart')).toBeVisible();
    await expect(page.locator('#monthlyTrendChart')).toBeVisible();
    await expect(page.locator('#carrierShareChart')).toBeVisible();
  });
});

