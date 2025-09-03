import { test, expect } from '@playwright/test';

test.describe('[Deploy E2E] HQ vs Branch Ranking Visibility', () => {
  test('HQ ranking returns data and includes branch names', async ({ page }) => {
    await page.goto('/quick-login/headquarters');
    await expect(page).toHaveURL(/.*dashboard/);

    const res = await page.request.get('/api/dashboard/store-ranking?period=monthly&limit=50');
    expect(res.status()).toBe(200);
    const json = await res.json();
    expect(json.success).toBeTruthy();
    const rankings = json.data?.rankings || [];
    expect(Array.isArray(rankings)).toBeTruthy();
    if (rankings.length > 0) {
      expect(rankings[0]).toHaveProperty('branch_name');
      expect(rankings[0]).toHaveProperty('store_name');
    }
  });

  test('Branch ranking contains only its branch stores', async ({ page }) => {
    await page.goto('/quick-login/branch');
    await expect(page).toHaveURL(/.*dashboard/);

    const profileRes = await page.request.get('/api/profile');
    expect(profileRes.status()).toBe(200);
    const profile = await profileRes.json();
    const branchName = profile.branch;

    const res = await page.request.get('/api/dashboard/store-ranking?period=monthly&limit=100');
    expect(res.status()).toBe(200);
    const json = await res.json();
    const rankings = json.data?.rankings || [];
    for (const r of rankings) {
      expect(r.branch_name).toBe(branchName);
    }
  });
});

