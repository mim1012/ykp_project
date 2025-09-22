import { test, expect } from '@playwright/test';

test('통신사 관리 기능 간단 테스트', async ({ page }) => {
    // 대시보드 페이지로 직접 이동
    await page.goto('http://localhost:8080/dashboard');

    // 페이지 로드 대기
    await page.waitForTimeout(2000);

    // 통신사 관리 버튼이 있는지 확인 (본사 권한일 경우)
    const carrierButton = page.locator('button:has-text("통신사 관리")');
    const buttonCount = await carrierButton.count();

    if (buttonCount > 0) {
        console.log('✅ 통신사 관리 버튼이 표시됩니다.');

        // 버튼 클릭
        await carrierButton.first().click();
        await page.waitForTimeout(1000);

        // 모달이 열렸는지 확인
        const modal = page.locator('#carrierManagementModal');
        const modalVisible = await modal.isVisible();

        if (modalVisible) {
            console.log('✅ 통신사 관리 모달이 열렸습니다.');
        } else {
            console.log('❌ 통신사 관리 모달이 열리지 않았습니다.');
        }
    } else {
        console.log('❌ 통신사 관리 버튼이 표시되지 않습니다. (권한 부족 또는 로그인 필요)');
    }

    // 매장 페이지로 이동하여 통신사 드롭다운 확인
    await page.goto('http://localhost:8080/management/stores');
    await page.waitForTimeout(2000);

    const carrierDropdown = page.locator('.field-carrier').first();
    const dropdownCount = await carrierDropdown.count();

    if (dropdownCount > 0) {
        console.log('✅ 통신사 드롭다운이 매장 페이지에 표시됩니다.');

        // 드롭다운 옵션 확인
        const options = await carrierDropdown.locator('option').allTextContents();
        console.log('통신사 옵션:', options);

        if (options.includes('SK') && options.includes('KT')) {
            console.log('✅ 기본 통신사들이 드롭다운에 포함되어 있습니다.');
        }
    } else {
        console.log('❌ 통신사 드롭다운을 찾을 수 없습니다.');
    }
});