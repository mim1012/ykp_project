import { test, expect } from '@playwright/test';

test.describe('Deploy Smoke: RBAC + Sales + Stats', () => {
  test('quick login + protected APIs respond', async ({ page, request }) => {
    // Use quick-login in non-production to establish session
    await page.goto('/quick-login/headquarters');
    await expect(page).toHaveURL(/.*dashboard/);

    // Sales statistics (protected)
    const statsRes = await page.request.get('/api/sales/statistics');
    expect(statsRes.status()).toBe(200);
    const stats = await statsRes.json();
    expect(stats).toHaveProperty('period');
    expect(stats).toHaveProperty('summary');

    // Dashboard overview (protected)
    const overviewRes = await page.request.get('/api/dashboard/overview');
    expect(overviewRes.status()).toBe(200);
    const overview = await overviewRes.json();
    expect(overview).toHaveProperty('success', true);
  });
});

