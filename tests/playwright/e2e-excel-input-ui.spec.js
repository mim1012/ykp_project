import { test, expect } from '@playwright/test';

test.describe('[Deploy E2E] Excel Input UI', () => {
  test('store user fills grid and saves via UI', async ({ page }) => {
    // Login as store to satisfy protected API auth
    await page.goto('/quick-login/store');
    await expect(page).toHaveURL(/.*dashboard/);

    // Navigate to dev excel-input page (public in non-production)
    await page.goto('/test/excel-input');
    await expect(page.locator('#grid')).toBeVisible();

    // Focus grid first cell and tab to 모델명, then type model name
    const grid = page.locator('#grid');
    await grid.click();
    // Move to 모델명 column (sale_date -> store_name -> branch_name -> carrier -> activation_type -> model_name)
    for (let i = 0; i < 5; i++) {
      await page.keyboard.press('Tab');
    }
    await page.keyboard.type('UI-PLAY-1');

    // Move to 기본료(base_price) and enter value
    await page.keyboard.press('Tab');
    await page.keyboard.type('100000');

    // Click save button
    const saveBtn = page.getByTestId('save');
    await expect(saveBtn).toBeVisible();
    await saveBtn.click();

    // Wait for status toast
    const status = page.locator('#status');
    await expect(status).toBeVisible();
    await expect(status).toContainText('저장');

    // Verify dashboard overview updated (at least 1 activation today)
    const overviewRes = await page.request.get('/api/dashboard/overview');
    expect(overviewRes.status()).toBe(200);
    const overview = await overviewRes.json();
    expect(overview.success).toBeTruthy();
    expect(overview.data.today.activations).toBeGreaterThan(0);
  });
});
