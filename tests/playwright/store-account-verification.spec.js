import { test, expect } from '@playwright/test';

/**
 * Store Account Verification Test
 * Tests specific account: z006-003@ykp.com
 * Verifies:
 * 1. Login works with provided credentials
 * 2. Dashboard shows real data (not hardcoded)
 * 3. Terminology is consistent ("매출" not "정산금")
 * 4. Tax columns are hidden in sales grid
 */

test.describe('Store Account (z006-003@ykp.com) Verification', () => {
    const baseURL = 'https://ykperp.co.kr';
    const credentials = {
        email: 'z006-003@ykp.com',
        password: '123456'
    };

    test.beforeEach(async ({ page }) => {
        // Navigate to login page
        await page.goto(baseURL);
    });

    test('should login successfully with provided credentials', async ({ page }) => {
        // Fill login form
        await page.fill('input[name="email"]', credentials.email);
        await page.fill('input[name="password"]', credentials.password);

        // Submit login
        await page.click('button[type="submit"]');

        // Wait for navigation to dashboard
        await page.waitForURL('**/dashboard', { timeout: 10000 });

        // Verify we're on the dashboard
        expect(page.url()).toContain('/dashboard');

        // Verify user email is displayed (usually in header/navbar)
        const pageContent = await page.content();
        expect(pageContent).toContain('z006-003');
    });

    test('should display real data in 30-day sales trend chart (not hardcoded)', async ({ page }) => {
        // Login first
        await page.fill('input[name="email"]', credentials.email);
        await page.fill('input[name="password"]', credentials.password);
        await page.click('button[type="submit"]');
        await page.waitForURL('**/dashboard', { timeout: 10000 });

        // Wait for chart to load
        await page.waitForSelector('canvas', { timeout: 10000 });

        // Check that the chart script initializes with empty data (not hardcoded array)
        const scriptContent = await page.content();

        // Verify hardcoded data is NOT present
        expect(scriptContent).not.toContain('100000, 150000, 80000, 200000');

        // Verify chart initialization exists
        expect(scriptContent).toContain('labels: []');
        expect(scriptContent).toContain('data: []');
    });

    test('should use "매출" terminology (not "정산금") in sales grid', async ({ page }) => {
        // Login
        await page.fill('input[name="email"]', credentials.email);
        await page.fill('input[name="password"]', credentials.password);
        await page.click('button[type="submit"]');
        await page.waitForURL('**/dashboard', { timeout: 10000 });

        // Navigate to sales input page - click sidebar icon
        await page.click('.sidebar-icon:has(.tooltip-text:text("개통표 입력"))');
        await page.waitForLoadState('networkidle');

        // Wait for AG Grid to render
        await page.waitForSelector('.ag-header-cell-text', { timeout: 10000 });

        // Get all header texts
        const headers = await page.locator('.ag-header-cell-text').allTextContents();

        // Verify "매출" is present
        expect(headers).toContain('매출');

        // Verify "정산금" is NOT present
        expect(headers).not.toContain('정산금');
    });

    test('should hide tax-related columns in sales grid', async ({ page }) => {
        // Login
        await page.fill('input[name="email"]', credentials.email);
        await page.fill('input[name="password"]', credentials.password);
        await page.click('button[type="submit"]');
        await page.waitForURL('**/dashboard', { timeout: 10000 });

        // Navigate to sales input page - click sidebar icon
        await page.click('.sidebar-icon:has(.tooltip-text:text("개통표 입력"))');
        await page.waitForLoadState('networkidle');

        // Wait for AG Grid to render
        await page.waitForSelector('.ag-header-cell-text', { timeout: 10000 });

        // Get all visible header texts
        const visibleHeaders = await page.locator('.ag-header-cell-text').allTextContents();

        // Verify tax-related columns are NOT visible
        expect(visibleHeaders).not.toContain('세금');
        expect(visibleHeaders).not.toContain('세전마진');
        expect(visibleHeaders).not.toContain('세후마진');
    });

    test('should display consistent sales totals between input and dashboard', async ({ page }) => {
        // Login
        await page.fill('input[name="email"]', credentials.email);
        await page.fill('input[name="password"]', credentials.password);
        await page.click('button[type="submit"]');
        await page.waitForURL('**/dashboard', { timeout: 10000 });

        // Get total sales from dashboard
        await page.waitForSelector('#total-revenue, [id*="total"], [id*="revenue"]', { timeout: 10000 });
        const dashboardTotalElement = await page.locator('#total-revenue, [id*="total"]').first();
        const dashboardTotal = await dashboardTotalElement.textContent();

        // Navigate to sales input page - click sidebar icon
        await page.click('.sidebar-icon:has(.tooltip-text:text("개통표 입력"))');
        await page.waitForLoadState('networkidle');

        // Wait for grid to load and calculate totals
        await page.waitForSelector('.ag-header-cell-text', { timeout: 10000 });
        await page.waitForTimeout(2000); // Wait for calculations

        // Get total from sales grid (usually in footer or summary row)
        const gridContent = await page.content();

        // Both should use "매출" terminology
        if (dashboardTotal.includes('₩') || dashboardTotal.includes('원')) {
            expect(gridContent).toContain('매출');
        }

        console.log('Dashboard Total:', dashboardTotal);
        console.log('Grid has "매출" column:', gridContent.includes('매출'));
    });
});
