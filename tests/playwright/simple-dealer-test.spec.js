import { test, expect } from '@playwright/test';

test('대리점 추가 및 표시 테스트', async ({ page }) => {
    // 본사 계정 로그인
    await page.goto('http://localhost:8080/login');
    await page.fill('#email', 'admin@ykp.com');
    await page.fill('#password', 'password');
    await page.click('button[type="submit"]');

    await page.waitForLoadState('networkidle');

    // 대시보드로 이동
    await page.goto('http://localhost:8080/dashboard');
    await page.waitForLoadState('networkidle');

    // 대리점 관리 버튼 클릭
    const dealerButton = page.locator('button:has-text("대리점 관리")').first();
    await dealerButton.click();
    await page.waitForTimeout(1000);

    // 새 대리점 추가
    const uniqueCode = 'TEST' + Date.now().toString().slice(-4);
    const dealerName = '테스트대리점_' + Date.now().toString().slice(-6);

    await page.fill('#dealerCode', uniqueCode);
    await page.fill('#dealerName', dealerName);
    await page.fill('#contactPerson', '테스트담당자');
    await page.fill('#phone', '010-1234-5678');

    console.log('추가하는 대리점:', dealerName);

    // 저장 버튼 클릭
    const saveButton = page.locator('#dealerForm button[type="submit"]');
    await saveButton.click();

    // 알림 처리
    page.on('dialog', dialog => {
        console.log('알림:', dialog.message());
        dialog.accept();
    });

    await page.waitForTimeout(2000);

    // 개통표 페이지로 이동
    await page.goto('http://localhost:8080/sales/complete-aggrid');
    await page.waitForLoadState('networkidle');

    // 콘솔 로그 모니터링
    page.on('console', msg => {
        if (msg.text().includes('대리점')) {
            console.log('콘솔:', msg.text());
        }
    });

    await page.waitForTimeout(2000);

    // 새 행 추가
    await page.click('#add-row-btn');
    await page.waitForTimeout(1000);

    // 대리점 드롭다운 확인
    const dealerSelect = page.locator('select[id^="dealer-select-"]').first();
    const dealerOptions = await dealerSelect.locator('option').allTextContents();

    console.log('대리점 옵션 개수:', dealerOptions.length);
    console.log('대리점 목록:', dealerOptions);

    const hasNewDealer = dealerOptions.some(option => option.includes(dealerName));
    if (hasNewDealer) {
        console.log('✅ 새 대리점이 표시됨:', dealerName);
    } else {
        console.log('❌ 새 대리점이 표시되지 않음');
    }
});