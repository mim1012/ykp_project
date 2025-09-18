import { test, expect } from '@playwright/test';

test.describe('[Deploy E2E] Role Statistics Routing', () => {
  test('HQ user sees HQ statistics page', async ({ page }) => {
    await page.goto('/quick-login/headquarters');
    await expect(page).toHaveURL(/.*dashboard/);
    await page.goto('/statistics');
    await expect(page.locator('text=본사 전용')).toBeVisible();
    await expect(page.locator('#branchComparisonChart')).toBeVisible();
  });

  test('Branch user sees Branch statistics page', async ({ page }) => {
    await page.goto('/quick-login/branch');
    await expect(page).toHaveURL(/.*dashboard/);
    await page.goto('/statistics');
    await expect(page.locator('text=지사 전용')).toBeVisible();
    await expect(page.locator('#storeComparisonChart')).toBeVisible();
  });

  test('Store user sees Store statistics page', async ({ page }) => {
    await page.goto('/quick-login/store');
    await expect(page).toHaveURL(/.*dashboard/);
    await page.goto('/statistics');
    await expect(page.locator('text=매장 전용')).toBeVisible();
    await expect(page.locator('#dailyActivationsChart')).toBeVisible();
  });
});

