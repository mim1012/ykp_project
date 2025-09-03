import { test, expect } from '@playwright/test';

test.describe('[Deploy E2E] Statistics Filters UI', () => {
  test('HQ: change ranking period and limit', async ({ page }) => {
    await page.goto('/quick-login/headquarters');
    await page.goto('/statistics');

    const period = page.locator('#hq-ranking-period');
    const limit = page.locator('#hq-ranking-limit');
    const apply = page.locator('#hq-apply-filters');
    await expect(period).toBeVisible();
    await expect(limit).toBeVisible();

    await period.selectOption('daily');
    await limit.fill('5');
    await apply.click();

    // chart labels should render (at most 5 bars)
    await expect(page.locator('#branchComparisonChart')).toBeVisible();
  });

  test('Branch: change ranking period, limit and trend days', async ({ page }) => {
    await page.goto('/quick-login/branch');
    await page.goto('/statistics');

    const period = page.locator('#branch-ranking-period');
    const limit = page.locator('#branch-ranking-limit');
    const days = page.locator('#branch-trend-days');
    const apply = page.locator('#branch-apply-filters');

    await expect(period).toBeVisible();
    await period.selectOption('weekly');
    await limit.fill('7');
    await days.selectOption('60');
    await apply.click();

    await expect(page.locator('#storeComparisonChart')).toBeVisible();
    await expect(page.locator('#branchTrendChart')).toBeVisible();
  });
});

