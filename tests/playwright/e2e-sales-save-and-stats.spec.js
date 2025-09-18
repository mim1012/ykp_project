import { test, expect } from '@playwright/test';

test.describe('[Deploy E2E] Sales Save and Stats Update', () => {
  test('store user saves sales and stats reflect', async ({ page }) => {
    // Login as store (auto-creates store/branch if missing)
    await page.goto('/quick-login/store');
    await expect(page).toHaveURL(/.*dashboard/);

    // Prepare today date
    const today = new Date().toISOString().slice(0, 10);

    // Create 3 sales via protected API
    const payload = {
      sales: Array.from({ length: 3 }).map((_, i) => ({
        sale_date: today,
        carrier: 'SK',
        activation_type: '신규',
        model_name: `PLAY-${i + 1}`,
        base_price: 100000,
        additional_amount: 0,
        cash_activation: 0,
        usim_fee: 0,
        new_mnp_discount: 0,
        deduction: 0,
        cash_received: 0,
        payback: 0,
      }))
    };

    const bulkRes = await page.request.post('/api/sales/bulk', {
      data: payload,
      headers: { 'Content-Type': 'application/json' },
    });
    expect(bulkRes.status()).toBe(201);
    const bulk = await bulkRes.json();
    expect(bulk.success).toBeTruthy();
    expect(bulk.saved_count).toBeGreaterThanOrEqual(3);

    // Verify dashboard overview reflects at least 3 activations today
    const overviewRes = await page.request.get('/api/dashboard/overview');
    expect(overviewRes.status()).toBe(200);
    const overview = await overviewRes.json();
    expect(overview.success).toBeTruthy();
    expect(overview.data.today.activations).toBeGreaterThanOrEqual(3);

    // Verify statistics API for today
    const statsRes = await page.request.get(`/api/sales/statistics?start_date=${today}&end_date=${today}`);
    expect(statsRes.status()).toBe(200);
    const stats = await statsRes.json();
    expect(stats.summary.total_count).toBeGreaterThanOrEqual(3);
  });
});

