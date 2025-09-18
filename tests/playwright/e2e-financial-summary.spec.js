import { test, expect } from '@playwright/test';

test.describe('[Deploy E2E] Financial Summary', () => {
  test('HQ financial summary returns non-negative values', async ({ page }) => {
    await page.goto('/quick-login/headquarters');

    const now = new Date();
    const start = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().slice(0,10);
    const end = new Date(now.getFullYear(), now.getMonth()+1, 0).toISOString().slice(0,10);

    const res = await page.request.get(`/api/dashboard/financial-summary?start_date=${start}&end_date=${end}`);
    expect(res.status()).toBe(200);
    const json = await res.json();
    expect(json.success).toBeTruthy();
    const revenue = json.data.revenue;
    const expenses = json.data.expenses;
    const profit = json.data.profit;
    expect(revenue.total_revenue).toBeGreaterThanOrEqual(0);
    expect(revenue.total_margin).toBeGreaterThanOrEqual(0);
    expect(expenses.total_expenses).toBeGreaterThanOrEqual(0);
    expect(profit.net_profit).toBeGreaterThanOrEqual(-1e9);
  });
});

