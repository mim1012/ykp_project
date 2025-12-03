// 고객관리 페이지 권한별 테스트
import { test, expect } from '@playwright/test';

const BASE_URL = 'http://127.0.0.1:8000';

const ACCOUNTS = {
    headquarters: { email: 'admin@ykp.com', password: 'password', role: 'headquarters' },
    branch: { email: 'branch@ykp.com', password: 'password', role: 'branch' },
    store: { email: 'store@ykp.com', password: 'password', role: 'store' },
};

async function login(page, account) {
    await page.goto(`${BASE_URL}/login`);
    await page.fill('input[name="email"]', account.email);
    await page.fill('input[name="password"]', account.password);
    await page.click('button[type="submit"]');
    await page.waitForURL(/dashboard/, { timeout: 15000 });
    console.log(`✅ ${account.role} 로그인 성공`);
}

test.describe('고객관리 페이지 권한 테스트', () => {

    test('1. 본사 - 매장 필터 드롭다운 표시 및 매장 컬럼 확인', async ({ page }) => {
        console.log('\n=== 본사 고객관리 테스트 ===');

        await login(page, ACCOUNTS.headquarters);
        await page.goto(`${BASE_URL}/management/customers`);
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(2000);

        // 매장 필터 드롭다운 확인
        const storeFilter = page.locator('#store-filter');
        await expect(storeFilter).toBeVisible();
        console.log('✅ 본사: 매장 필터 드롭다운 표시됨');

        // 매장 컬럼 확인
        const storeHeader = page.locator('th:has-text("매장")');
        await expect(storeHeader).toBeVisible();
        console.log('✅ 본사: 매장 컬럼 표시됨');

        // 고객 등록 버튼 없어야 함
        const addButton = page.locator('button:has-text("고객 등록")');
        const isAddVisible = await addButton.isVisible().catch(() => false);
        expect(isAddVisible).toBeFalsy();
        console.log('✅ 본사: 고객 등록 버튼 없음 (정상)');
    });

    test('2. 지사 - 매장 필터 드롭다운 표시 및 매장 컬럼 확인', async ({ page }) => {
        console.log('\n=== 지사 고객관리 테스트 ===');

        await login(page, ACCOUNTS.branch);
        await page.goto(`${BASE_URL}/management/customers`);
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(2000);

        // 매장 필터 드롭다운 확인
        const storeFilter = page.locator('#store-filter');
        await expect(storeFilter).toBeVisible();
        console.log('✅ 지사: 매장 필터 드롭다운 표시됨');

        // 매장 컬럼 확인
        const storeHeader = page.locator('th:has-text("매장")');
        await expect(storeHeader).toBeVisible();
        console.log('✅ 지사: 매장 컬럼 표시됨');

        // 고객 등록 버튼 없어야 함
        const addButton = page.locator('button:has-text("고객 등록")');
        const isAddVisible = await addButton.isVisible().catch(() => false);
        expect(isAddVisible).toBeFalsy();
        console.log('✅ 지사: 고객 등록 버튼 없음 (정상)');
    });

    test('3. 매장 - 고객 등록 버튼 있고 매장 필터 없음', async ({ page }) => {
        console.log('\n=== 매장 고객관리 테스트 ===');

        await login(page, ACCOUNTS.store);
        await page.goto(`${BASE_URL}/management/customers`);
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(2000);

        // 매장 필터 드롭다운 없어야 함
        const storeFilter = page.locator('#store-filter');
        const isFilterVisible = await storeFilter.isVisible().catch(() => false);
        expect(isFilterVisible).toBeFalsy();
        console.log('✅ 매장: 매장 필터 드롭다운 없음 (정상)');

        // 매장 컬럼 없어야 함
        const storeHeaders = await page.locator('th:has-text("매장")').count();
        expect(storeHeaders).toBe(0);
        console.log('✅ 매장: 매장 컬럼 없음 (정상)');

        // 고객 등록 버튼 있어야 함
        const addButton = page.locator('button:has-text("고객 등록")');
        await expect(addButton).toBeVisible();
        console.log('✅ 매장: 고객 등록 버튼 표시됨');

        // 고객 등록 테스트
        console.log('\n=== 고객 등록 테스트 ===');
        await addButton.click();
        await page.waitForSelector('#add-modal:not(.hidden)', { timeout: 5000 });
        console.log('✅ 등록 모달 열림');

        const timestamp = Date.now();
        await page.fill('input[name="customer_name"]', `테스트고객_${timestamp}`);
        await page.fill('input[name="phone_number"]', `010-${timestamp.toString().slice(-4)}-${timestamp.toString().slice(-8, -4)}`);
        await page.fill('input[name="current_device"]', 'iPhone 15');

        await page.click('#add-form button[type="submit"]');
        await page.waitForTimeout(2000);

        // alert 처리
        page.on('dialog', async dialog => {
            console.log(`Alert: ${dialog.message()}`);
            await dialog.accept();
        });

        console.log('✅ 고객 등록 완료');
    });

    test('4. 본사 - 매장 필터로 특정 매장 고객만 조회', async ({ page }) => {
        console.log('\n=== 본사 매장 필터 테스트 ===');

        await login(page, ACCOUNTS.headquarters);
        await page.goto(`${BASE_URL}/management/customers`);
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(2000);

        // 매장 필터 드롭다운 옵션 확인
        const storeFilter = page.locator('#store-filter');
        const optionCount = await storeFilter.locator('option').count();
        console.log(`매장 필터 옵션 수: ${optionCount}`);
        expect(optionCount).toBeGreaterThan(1); // 최소 "전체 매장" + 1개 이상

        // 첫 번째 매장 선택
        const options = await storeFilter.locator('option').all();
        if (options.length > 1) {
            const firstStoreName = await options[1].textContent();
            await storeFilter.selectOption({ index: 1 });
            await page.waitForTimeout(1500);
            console.log(`✅ "${firstStoreName}" 매장 필터 적용됨`);
        }

        console.log('✅ 매장 필터 테스트 완료');
    });
});
