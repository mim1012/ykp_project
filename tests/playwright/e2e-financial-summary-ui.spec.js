import { test, expect } from '@playwright/test';

function monthRange() {
  const now = new Date();
  const yyyy = now.getFullYear();
  const mm = String(now.getMonth()+1).padStart(2,'0');
  const start = `${yyyy}-${mm}-01`;
  const end = new Date(yyyy, now.getMonth()+1, 0).toISOString().slice(0,10);
  return { start, end };
}

test.describe('[Deploy E2E] Financial Summary UI', () => {
  test('HQ: date range updates summary boxes', async ({ page }) => {
    await page.goto('/quick-login/headquarters');
    await page.goto('/statistics');

    const { start, end } = monthRange();
    await page.fill('#hq-start-date', start);
    await page.fill('#hq-end-date', end);
    await page.click('#hq-apply-filters');

    await expect(page.locator('#hq-fin-total-revenue')).toBeVisible();
    await expect(page.locator('#hq-fin-total-margin')).toBeVisible();
    await expect(page.locator('#hq-fin-total-expenses')).toBeVisible();
    await expect(page.locator('#hq-fin-net-profit')).toBeVisible();
  });

  test('Branch: date range updates summary boxes', async ({ page }) => {
    await page.goto('/quick-login/branch');
    await page.goto('/statistics');

    const { start, end } = monthRange();
    await page.fill('#branch-start-date', start);
    await page.fill('#branch-end-date', end);
    await page.click('#branch-apply-filters');

    await expect(page.locator('#branch-fin-total-revenue')).toBeVisible();
    await expect(page.locator('#branch-fin-total-margin')).toBeVisible();
    await expect(page.locator('#branch-fin-total-expenses')).toBeVisible();
    await expect(page.locator('#branch-fin-net-profit')).toBeVisible();
  });
});

