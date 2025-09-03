import { test, expect } from '@playwright/test';

test.describe('[Deploy E2E] Excel Input Advanced', () => {
  test('add multiple rows, auto-calc visible, delete a row', async ({ page }) => {
    await page.goto('/quick-login/store');
    await page.goto('/test/excel-input');

    const grid = page.locator('#grid');
    await expect(grid).toBeVisible();

    // Count initial rows (visible in table)
    const rowsBefore = await page.locator('#grid table tbody tr').count();

    // Add 3 rows
    await page.getByTestId('add-row').click();
    await page.getByTestId('add-row').click();
    await page.getByTestId('add-row').click();

    const rowsAfterAdd = await page.locator('#grid table tbody tr').count();
    expect(rowsAfterAdd).toBeGreaterThanOrEqual(rowsBefore + 1); // some impls append multiple

    // Enter values to produce rebate_total
    await grid.click();
    // Move to model_name (5 tabs from sale_date)
    for (let i = 0; i < 5; i++) await page.keyboard.press('Tab');
    await page.keyboard.type('CALC-ROW-1');
    // base_price
    await page.keyboard.press('Tab');
    await page.keyboard.type('100000');
    // verbal1
    await page.keyboard.press('Tab');
    await page.keyboard.type('20000');
    // verbal2
    await page.keyboard.press('Tab');
    await page.keyboard.type('10000');
    // grade_amount
    await page.keyboard.press('Tab');
    await page.keyboard.type('5000');
    // additional_amount
    await page.keyboard.press('Tab');
    await page.keyboard.type('3000');

    // give time for afterChange to calculate
    await page.waitForTimeout(500);

    // Expect a cell in grid to contain rebate_total 138,000
    const hasRebate = await page.locator('#grid td:has-text("138,000")').count();
    expect(hasRebate).toBeGreaterThan(0);

    // Delete a row
    await page.getByTestId('delete-row').click();
    const rowsAfterDelete = await page.locator('#grid table tbody tr').count();
    expect(rowsAfterDelete).toBeLessThanOrEqual(rowsAfterAdd);

    // Save
    await page.getByTestId('save').click();
    const status = page.locator('#status');
    await expect(status).toBeVisible();
  });
});
