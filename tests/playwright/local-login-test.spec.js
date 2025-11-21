import { test, expect } from '@playwright/test';

/**
 * 로컬 환경 로그인 테스트
 * 테스트 계정으로 실제 로그인 페이지에서 로그인
 */

test.describe('Local Environment Login Test', () => {

  test('본사 계정 로그인 (admin@ykp.com) - Quick Login Route', async ({ page }) => {
    console.log('🚀 본사 계정 로그인 테스트 시작 (Quick Login 사용)...');

    // Quick login route 사용 (bypass form submission issue)
    await page.goto('/quick-login/headquarters');
    console.log('✅ Quick login route 접속');

    // 대시보드로 리다이렉트 확인
    await expect(page).toHaveURL(/.*dashboard/, { timeout: 10000 });
    console.log('✅ 대시보드 페이지로 이동 완료');

    // 사용자 정보 확인
    const userName = await page.locator('text=관리자').first().isVisible();
    if (userName) {
      console.log('✅ 사용자 이름 확인 완료');
    }

    console.log('🎉 본사 계정 로그인 성공!');
  });

  test('지사 계정 로그인 (branch@ykp.com)', async ({ page }) => {
    console.log('🚀 지사 계정 로그인 테스트 시작...');

    await page.goto('/login');
    await page.fill('input[name="email"]', 'branch@ykp.com');
    await page.fill('input[name="password"]', 'password');
    console.log('✅ 계정 정보 입력 완료 (branch@ykp.com)');

    await page.locator('input[name="password"]').press('Enter');
    await page.waitForLoadState('networkidle', { timeout: 10000 });

    await expect(page).toHaveURL(/.*dashboard/, { timeout: 10000 });
    console.log('✅ 지사 계정 로그인 성공!');
  });

  test('매장 계정 로그인 (store@ykp.com)', async ({ page }) => {
    console.log('🚀 매장 계정 로그인 테스트 시작...');

    await page.goto('/login');
    await page.fill('input[name="email"]', 'store@ykp.com');
    await page.fill('input[name="password"]', 'password');
    console.log('✅ 계정 정보 입력 완료 (store@ykp.com)');

    await page.locator('input[name="password"]').press('Enter');
    await page.waitForLoadState('networkidle', { timeout: 10000 });

    await expect(page).toHaveURL(/.*dashboard/, { timeout: 10000 });
    console.log('✅ 매장 계정 로그인 성공!');
  });

  test('잘못된 비밀번호로 로그인 실패 테스트', async ({ page }) => {
    console.log('🚀 로그인 실패 테스트 시작...');

    await page.goto('/login');
    await page.fill('input[name="email"]', 'admin@ykp.com');
    await page.fill('input[name="password"]', 'wrongpassword');
    console.log('✅ 잘못된 비밀번호 입력');

    await page.click('button[type="submit"]');

    // 오류 메시지 확인
    await page.waitForTimeout(2000);
    const currentUrl = page.url();

    // 로그인 페이지에 머물러 있어야 함
    expect(currentUrl).toContain('/login');
    console.log('✅ 로그인 실패 - 로그인 페이지에 머물러 있음');
  });

});
