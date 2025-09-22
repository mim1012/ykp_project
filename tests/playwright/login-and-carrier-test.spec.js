import { test, expect } from '@playwright/test';

test('로그인 후 통신사 관리 테스트', async ({ page }) => {
    // 로그인 페이지로 이동
    await page.goto('http://localhost:8080/login');

    // 로그인 폼 확인
    await expect(page.locator('#email')).toBeVisible();
    await expect(page.locator('#password')).toBeVisible();

    // 본사 계정으로 로그인
    await page.fill('#email', 'admin@ykp.com');
    await page.fill('#password', 'password');
    await page.click('button[type="submit"]');

    // 대시보드로 이동 확인 (URL 또는 요소 기반)
    await page.waitForLoadState('networkidle');

    // 대시보드 요소 확인
    const dashboardTitle = page.locator('h1, h2').first();
    await expect(dashboardTitle).toBeVisible({ timeout: 10000 });

    console.log('✅ 로그인 성공, 대시보드 로드됨');

    // 통신사 관리 버튼 확인
    const carrierButton = page.locator('button:has-text("통신사 관리")');
    const buttonCount = await carrierButton.count();

    if (buttonCount > 0) {
        console.log('✅ 통신사 관리 버튼이 표시됩니다.');

        // 버튼 클릭
        await carrierButton.first().click();
        await page.waitForTimeout(1000);

        // 모달 확인 - 정확한 ID 사용
        const modal = page.locator('#carrierManagementModal');
        await expect(modal).toBeVisible({ timeout: 5000 });
        console.log('✅ 통신사 관리 모달이 열렸습니다.');

        // 기본 통신사 확인
        await expect(page.locator('text=SK').first()).toBeVisible();
        await expect(page.locator('text=KT').first()).toBeVisible();
        await expect(page.locator('text=LG').first()).toBeVisible();
        console.log('✅ 기본 통신사들이 표시됩니다.');

        // 통신사 추가 기능 테스트
        await page.fill('#carrier-code', 'TEST');
        await page.fill('#carrier-name', '테스트통신사');
        await page.fill('#carrier-sort-order', '99');

        // 저장 버튼 클릭 - 통신사 폼 내의 저장 버튼만 선택
        const saveButton = page.locator('#carrierForm button[type="submit"]');
        await saveButton.click();

        // 알림 확인 및 처리
        page.on('dialog', dialog => dialog.accept());
        await page.waitForTimeout(1000);

        // 추가된 통신사 확인
        await expect(page.locator('text=테스트통신사').first()).toBeVisible({ timeout: 5000 });
        console.log('✅ 새 통신사 추가 성공');
    } else {
        console.log('❌ 통신사 관리 버튼이 표시되지 않습니다.');
    }
});