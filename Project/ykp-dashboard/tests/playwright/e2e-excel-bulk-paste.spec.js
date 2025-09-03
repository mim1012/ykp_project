import { test, expect } from '@playwright/test';

test.describe('[Deploy E2E] Excel Bulk Paste & Paging', () => {
  test('paste multiple rows and navigate pages', async ({ page, context }) => {
    await page.goto('/quick-login/store');
    await page.goto('/test/excel-input');

    // Set page size small to force multiple pages
    await page.fill('#pageSize', '10');

    // Prepare CSV-like tab-separated rows for paste (5 rows)
    const today = new Date().toISOString().slice(0, 10);
    const rows = Array.from({ length: 5 }).map((_, i) => [
      today, '', '', 'SK', '신규', `PASTE-${i+1}`,
      1000 + i, 0, 0, 0, 0, '', 0, 0, 0, 0, '', 0, 0, 0, 0, '', '', ''
    ].join('\t')).join('\n');

    await context.grantPermissions(['clipboard-read', 'clipboard-write']);
    await page.evaluate(async (text) => {
      await navigator.clipboard.writeText(text);
    }, rows);

    // Focus first cell and paste
    await page.locator('#grid').click();
    await page.keyboard.press('Control+V');

    // Save and expect at least 5 saved
    await page.getByTestId('save').click();
    const res = await page.request.post('/api/sales/bulk', { data: { sales: [] } });
    // above line doesn't save; ensure overview reflects activations >= 5
    const overview = await page.request.get('/api/dashboard/overview');
    expect(overview.status()).toBe(200);
    const json = await overview.json();
    expect(json.data.today.activations).toBeGreaterThanOrEqual(5);

    // Navigate pages
    await page.getByTestId('next-page').click();
    await expect(page.getByTestId('page-info')).toContainText('/');
  });
});
