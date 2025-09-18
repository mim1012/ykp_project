import { test, expect } from '@playwright/test';

test.describe('[Deploy E2E] HQ Carrier Share Chart', () => {
  test('chart renders after seeding multiple carriers', async ({ page }) => {
    await page.goto('/quick-login/headquarters');

    const today = new Date().toISOString().slice(0, 10);
    // Seed few sales for multiple carriers
    const carriers = ['SK','KT','LG','MVNO'];
    for (const c of carriers) {
      await page.request.post('/api/sales/bulk', {
        data: { sales: [{ sale_date: today, carrier: c, activation_type: '신규', model_name: `C-${c}`, base_price: 1000 }] },
        headers: { 'Content-Type': 'application/json' },
      });
    }

    await page.goto('/statistics');
    await expect(page.locator('#carrierShareChart')).toBeVisible();
  });
});

