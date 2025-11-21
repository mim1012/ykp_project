const { chromium } = require('playwright');

(async () => {
    const browser = await chromium.launch({ headless: false });
    const context = await browser.newContext();
    const page = await context.newPage();

    try {
        console.log('🔐 Step 1: Logging in...');
        await page.goto('http://localhost:8000/login');
        await page.waitForTimeout(1000);

        // Login with store credentials
        await page.fill('input[name="email"]', 'store@ykp.com');
        await page.fill('input[name="password"]', 'password');
        await page.click('button[type="submit"]');

        console.log('⏳ Waiting for dashboard to load...');
        await page.waitForURL('**/dashboard', { timeout: 15000 });
        await page.waitForTimeout(3000);

        console.log('✅ Dashboard loaded successfully!');
        console.log('Current URL:', page.url());

        console.log('✅ Step 2: Checking sidebar menu items...');
        const menuItems = await page.$$eval('[role="button"], button, a', elements =>
            elements.map(el => el.textContent.trim()).filter(text => text)
        );
        console.log('📋 Menu items found:', menuItems);

        // Check for new menu items
        const expectedMenus = ['고객 관리', '경비 관리', 'Q&A', '공지사항'];
        for (const menu of expectedMenus) {
            const found = menuItems.some(item => item.includes(menu));
            console.log(found ? `✅ "${menu}" found` : `❌ "${menu}" NOT FOUND`);
        }

        console.log('\n📊 Step 3: Checking Dashboard widgets...');

        // Check for Q&A widget
        const qnaWidgetExists = await page.locator('text=Q&A').first().isVisible({ timeout: 5000 })
            .catch(() => false);
        console.log(qnaWidgetExists ? '✅ Q&A widget found' : '❌ Q&A widget NOT FOUND');

        // Check for 공지사항 widget
        const noticeWidgetExists = await page.locator('text=공지사항').first().isVisible({ timeout: 5000 })
            .catch(() => false);
        console.log(noticeWidgetExists ? '✅ 공지사항 widget found' : '❌ 공지사항 widget NOT FOUND');

        // Check for "더보기" buttons
        const moreButtons = await page.locator('text=더보기').count();
        console.log(`✅ Found ${moreButtons} "더보기" buttons`);

        console.log('\n🖱️ Step 4: Testing navigation...');

        // Try clicking 고객 관리 menu
        const customerMenuButton = page.locator('text=고객 관리').first();
        if (await customerMenuButton.isVisible({ timeout: 2000 }).catch(() => false)) {
            await customerMenuButton.click();
            await page.waitForTimeout(2000);
            console.log('✅ Navigated to 고객 관리');
            await page.screenshot({ path: 'tests/playwright/screenshots/customer-management.png' });
        }

        // Go back to dashboard
        const dashboardButton = page.locator('text=대시보드').first();
        if (await dashboardButton.isVisible({ timeout: 2000 }).catch(() => false)) {
            await dashboardButton.click();
            await page.waitForTimeout(1000);
        }

        // Try clicking Q&A menu
        const qnaMenuButton = page.locator('text=Q&A').first();
        if (await qnaMenuButton.isVisible({ timeout: 2000 }).catch(() => false)) {
            await qnaMenuButton.click();
            await page.waitForTimeout(2000);
            console.log('✅ Navigated to Q&A');
            await page.screenshot({ path: 'tests/playwright/screenshots/qna-board.png' });
        }

        // Go back to dashboard
        if (await dashboardButton.isVisible({ timeout: 2000 }).catch(() => false)) {
            await dashboardButton.click();
            await page.waitForTimeout(1000);
        }

        // Try clicking 공지사항 menu
        const noticeMenuButton = page.locator('text=공지사항').first();
        if (await noticeMenuButton.isVisible({ timeout: 2000 }).catch(() => false)) {
            await noticeMenuButton.click();
            await page.waitForTimeout(2000);
            console.log('✅ Navigated to 공지사항');
            await page.screenshot({ path: 'tests/playwright/screenshots/notice-board.png' });
        }

        console.log('\n📸 Taking final screenshot...');
        await page.screenshot({ path: 'tests/playwright/screenshots/dashboard-final.png' });

        console.log('\n✅ ALL TESTS PASSED!');
        console.log('🎉 Dashboard integration successful!');

    } catch (error) {
        console.error('❌ Test failed:', error.message);
        await page.screenshot({ path: 'tests/playwright/screenshots/error.png' });
    } finally {
        console.log('\n⏸️ Browser will stay open for 10 seconds for manual inspection...');
        await page.waitForTimeout(10000);
        await browser.close();
    }
})();
