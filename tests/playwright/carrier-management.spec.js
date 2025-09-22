import { test, expect } from '@playwright/test';

test.describe('통신사 관리 기능 테스트', () => {
    // 본사 계정으로 로그인
    test.beforeEach(async ({ page }) => {
        await page.goto('http://localhost:8080/login');
        await page.fill('#email', 'admin@ykp.com');
        await page.fill('#password', 'password');
        await page.click('button:has-text("로그인")');
        await page.waitForURL('**/dashboard');
    });

    test('본사에서 통신사 관리 버튼이 표시되고 작동함', async ({ page }) => {
        // 통신사 관리 버튼 확인
        const carrierButton = page.locator('button:has-text("📡 통신사 관리")');
        await expect(carrierButton).toBeVisible();

        // 통신사 관리 모달 열기
        await carrierButton.click();

        // 모달이 열렸는지 확인
        const modal = page.locator('#carrierManagementModal');
        await expect(modal).toBeVisible();

        // 기본 통신사들이 표시되는지 확인
        await expect(page.locator('text=SK').first()).toBeVisible();
        await expect(page.locator('text=KT').first()).toBeVisible();
        await expect(page.locator('text=LG').first()).toBeVisible();
        await expect(page.locator('text=알뜰').first()).toBeVisible();
    });

    test('새 통신사 추가', async ({ page }) => {
        // 통신사 관리 모달 열기
        await page.click('button:has-text("📡 통신사 관리")');

        // 새 통신사 정보 입력
        await page.fill('#carrier-code', 'TEST');
        await page.fill('#carrier-name', '테스트통신사');
        await page.fill('#carrier-sort-order', '10');

        // 저장 버튼 클릭
        await page.click('button:has-text("저장")');

        // 성공 메시지 확인 (alert 대화상자 처리)
        page.on('dialog', dialog => dialog.accept());

        // 잠시 대기
        await page.waitForTimeout(1000);

        // 새 통신사가 목록에 표시되는지 확인
        await expect(page.locator('text=테스트통신사').first()).toBeVisible();
    });

    test('매장 페이지에서 통신사 드롭다운 확인', async ({ page }) => {
        // 매장 판매 입력 페이지로 이동
        await page.goto('http://localhost:8080/management/stores');

        // 페이지 로드 대기
        await page.waitForLoadState('networkidle');

        // 통신사 드롭다운이 있는지 확인
        const carrierDropdown = page.locator('.field-carrier').first();
        await expect(carrierDropdown).toBeVisible();

        // 드롭다운 클릭하여 옵션 확인
        await carrierDropdown.click();

        // 기본 통신사들이 옵션에 있는지 확인
        const options = await carrierDropdown.locator('option').allTextContents();
        expect(options).toContain('SK');
        expect(options).toContain('KT');
        expect(options).toContain('LG');
        expect(options).toContain('알뜰');
    });

    test('통신사 삭제 (비활성화)', async ({ page }) => {
        // 먼저 테스트용 통신사 추가
        await page.click('button:has-text("📡 통신사 관리")');
        await page.fill('#carrier-code', 'DELTEST');
        await page.fill('#carrier-name', '삭제테스트');
        await page.click('button:has-text("저장")');

        // alert 처리
        page.on('dialog', dialog => dialog.accept());
        await page.waitForTimeout(1000);

        // 삭제 버튼 클릭
        const deleteButton = page.locator('tr:has-text("삭제테스트")').locator('button:has-text("삭제")');
        if (await deleteButton.isVisible()) {
            await deleteButton.click();

            // 확인 대화상자 처리
            page.on('dialog', dialog => dialog.accept());
            await page.waitForTimeout(1000);

            console.log('테스트 통신사가 삭제(비활성화)되었습니다.');
        }
    });
});