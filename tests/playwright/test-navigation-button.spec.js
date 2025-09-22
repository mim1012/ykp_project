import { test, expect } from '@playwright/test';

test('개통표 입력 버튼 네비게이션 테스트', async ({ page }) => {
    // 로그인
    await page.goto('http://localhost:8080/login');
    await page.fill('#email', 'admin@ykp.com');
    await page.fill('#password', 'password');
    await page.click('button[type="submit"]');

    // 대시보드 로드 대기
    await page.waitForLoadState('networkidle');
    console.log('✅ 로그인 성공');

    // 현재 URL 확인
    const currentUrl = page.url();
    console.log('현재 URL:', currentUrl);

    // 콘솔 에러 리스너 추가
    page.on('console', msg => {
        if (msg.type() === 'error') {
            console.error('❌ 콘솔 에러:', msg.text());
        }
    });

    // 개통표 입력 버튼 찾기 - 여러 방법 시도
    let salesButton = page.locator('div:has-text("개통표 입력")').first();
    let buttonCount = await salesButton.count();

    if (buttonCount === 0) {
        // onclick 속성으로 찾기
        salesButton = page.locator('[onclick="openManagement()"]');
        buttonCount = await salesButton.count();
    }

    if (buttonCount === 0) {
        // 텍스트로 직접 찾기
        salesButton = page.locator('text=📋');
        buttonCount = await salesButton.count();
    }
    console.log('개통표 입력 버튼 개수:', buttonCount);

    if (buttonCount > 0) {
        // 버튼이 보이는지 확인
        await expect(salesButton.first()).toBeVisible({ timeout: 10000 });
        console.log('✅ 개통표 입력 버튼이 보입니다');

        // 버튼 클릭 시도
        try {
            await salesButton.first().click();
            console.log('✅ 버튼 클릭됨');

            // 페이지 이동 대기
            await page.waitForTimeout(3000);

            // 이동 후 URL 확인
            const newUrl = page.url();
            console.log('이동 후 URL:', newUrl);

            if (newUrl.includes('complete-aggrid')) {
                console.log('✅ 개통표 페이지로 이동 성공');
            } else {
                console.log('❌ 페이지 이동 실패. 현재 URL:', newUrl);
            }
        } catch (error) {
            console.error('❌ 버튼 클릭 중 에러:', error.message);
        }
    } else {
        console.log('❌ 개통표 입력 버튼을 찾을 수 없습니다');
    }

    // openManagement 함수 직접 실행 테스트
    try {
        const result = await page.evaluate(() => {
            if (typeof openManagement === 'function') {
                return 'openManagement 함수가 존재합니다';
            } else {
                return 'openManagement 함수를 찾을 수 없습니다';
            }
        });
        console.log(result);

        // 함수 직접 호출
        await page.evaluate(() => {
            if (typeof openManagement === 'function') {
                openManagement();
            }
        });

        await page.waitForTimeout(3000);
        const finalUrl = page.url();
        console.log('함수 호출 후 URL:', finalUrl);
    } catch (error) {
        console.error('❌ JavaScript 함수 실행 중 에러:', error.message);
    }
});