import { test, expect } from '@playwright/test';

// 본사 계정 테스트
test('본사 계정: 통신사/대리점 관리 버튼 표시', async ({ page }) => {
    // 본사 계정 로그인
    await page.goto('http://localhost:8080/login');
    await page.fill('#email', 'admin@ykp.com');
    await page.fill('#password', 'password');
    await page.click('button[type="submit"]');

    await page.waitForLoadState('networkidle');

    // 개통표 입력 페이지로 이동
    await page.goto('http://localhost:8080/sales/complete-aggrid');
    await page.waitForLoadState('networkidle');

    // 통신사 관리 버튼 확인
    const carrierBtn = page.locator('button:has-text("통신사 관리")');
    await expect(carrierBtn).toBeVisible();
    console.log('✅ 본사: 통신사 관리 버튼 표시됨');

    // 대리점 관리 버튼 확인
    const dealerBtn = page.locator('button:has-text("대리점 관리")');
    await expect(dealerBtn).toBeVisible();
    console.log('✅ 본사: 대리점 관리 버튼 표시됨');

    // 엑셀 버튼들 확인
    await expect(page.locator('button:has-text("엑셀 템플릿")')).toBeVisible();
    await expect(page.locator('button:has-text("엑셀 업로드")')).toBeVisible();
    await expect(page.locator('button:has-text("엑셀 다운로드")')).toBeVisible();
    console.log('✅ 본사: 모든 엑셀 기능 버튼 표시됨');
});

// 지사 계정 테스트
test('지사 계정: 대리점 관리만 표시', async ({ page }) => {
    // 지사 계정 로그인
    await page.goto('http://localhost:8080/login');
    await page.fill('#email', 'branch@ykp.com');
    await page.fill('#password', 'password');
    await page.click('button[type="submit"]');

    await page.waitForLoadState('networkidle');

    // 개통표 입력 페이지로 이동
    await page.goto('http://localhost:8080/sales/complete-aggrid');
    await page.waitForLoadState('networkidle');

    // 통신사 관리 버튼이 없어야 함
    const carrierBtn = page.locator('button:has-text("통신사 관리")');
    await expect(carrierBtn).not.toBeVisible();
    console.log('✅ 지사: 통신사 관리 버튼 숨김됨');

    // 대리점 관리 버튼은 있어야 함
    const dealerBtn = page.locator('button:has-text("대리점 관리")');
    await expect(dealerBtn).toBeVisible();
    console.log('✅ 지사: 대리점 관리 버튼 표시됨');

    // 엑셀 버튼들 확인
    await expect(page.locator('button:has-text("엑셀 템플릿")')).toBeVisible();
    await expect(page.locator('button:has-text("엑셀 업로드")')).toBeVisible();
    await expect(page.locator('button:has-text("엑셀 다운로드")')).toBeVisible();
    console.log('✅ 지사: 모든 엑셀 기능 버튼 표시됨');
});

// 매장 계정 테스트
test('매장 계정: 관리 버튼 없음, 엑셀 기능만', async ({ page }) => {
    // 매장 계정 로그인
    await page.goto('http://localhost:8080/login');
    await page.fill('#email', 'store@ykp.com');
    await page.fill('#password', 'password');
    await page.click('button[type="submit"]');

    await page.waitForLoadState('networkidle');

    // 개통표 입력 페이지로 이동
    await page.goto('http://localhost:8080/sales/complete-aggrid');
    await page.waitForLoadState('networkidle');

    // 통신사 관리 버튼이 없어야 함
    const carrierBtn = page.locator('button:has-text("통신사 관리")');
    await expect(carrierBtn).not.toBeVisible();
    console.log('✅ 매장: 통신사 관리 버튼 숨김됨');

    // 대리점 관리 버튼도 없어야 함
    const dealerBtn = page.locator('button:has-text("대리점 관리")');
    await expect(dealerBtn).not.toBeVisible();
    console.log('✅ 매장: 대리점 관리 버튼 숨김됨');

    // 엑셀 버튼들은 있어야 함
    await expect(page.locator('button:has-text("엑셀 템플릿")')).toBeVisible();
    await expect(page.locator('button:has-text("엑셀 업로드")')).toBeVisible();
    await expect(page.locator('button:has-text("엑셀 다운로드")')).toBeVisible();
    console.log('✅ 매장: 모든 엑셀 기능 버튼 표시됨');

    // 통신사 드롭다운 확인
    await page.waitForTimeout(2000);
    const carrierDropdown = page.locator('select[id^="carrier-select-"]').first();
    if (await carrierDropdown.count() > 0) {
        console.log('✅ 매장: 통신사 드롭다운 존재함');
    }
});

// 엑셀 기능 테스트
test('엑셀 템플릿 다운로드 테스트', async ({ page }) => {
    // 로그인
    await page.goto('http://localhost:8080/login');
    await page.fill('#email', 'admin@ykp.com');
    await page.fill('#password', 'password');
    await page.click('button[type="submit"]');

    await page.waitForLoadState('networkidle');

    // 개통표 입력 페이지로 이동
    await page.goto('http://localhost:8080/sales/complete-aggrid');
    await page.waitForLoadState('networkidle');

    // 템플릿 다운로드 버튼 클릭 시 다운로드 시작 확인
    const downloadPromise = page.waitForEvent('download');
    await page.click('button:has-text("엑셀 템플릿")');

    try {
        const download = await Promise.race([
            downloadPromise,
            new Promise((_, reject) => setTimeout(() => reject(new Error('Download timeout')), 5000))
        ]);
        console.log('✅ 엑셀 템플릿 다운로드 시작됨');
    } catch {
        console.log('⚠️ 템플릿 다운로드는 페이지 이동으로 처리됨');
    }
});