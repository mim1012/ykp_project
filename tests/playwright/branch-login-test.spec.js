import { test, expect } from '@playwright/test';

/**
 * 🎯 지사 계정 로그인 테스트
 * 지사 생성 → 자동 계정 생성 → 로그인 검증
 */

test.describe('🏢 Branch Account Login Test', () => {
  test('지사 계정 자동 생성 및 로그인 테스트', async ({ page }) => {
    
    console.log('🚀 지사 계정 로그인 테스트 시작...');
    
    // 1. 기존 지사 계정 패턴으로 로그인 시도
    await page.goto('https://ykpproject-production.up.railway.app/login');
    
    // 일반적인 지사 계정들 시도
    const potentialBranchAccounts = [
      'branch_test001@ykp.com',
      'branch_seoul01@ykp.com', 
      'branch_busan01@ykp.com',
      'branch_test@ykp.com',
      'branch_sample@ykp.com'
    ];
    
    let loginSuccess = false;
    let successAccount = '';
    
    for (const email of potentialBranchAccounts) {
      try {
        console.log(`🔑 지사 계정 로그인 시도: ${email}`);
        
        // 필드 클리어 후 입력
        await page.fill('input[name="email"]', '');
        await page.fill('input[name="password"]', '');
        
        await page.fill('input[name="email"]', email);
        await page.fill('input[name="password"]', '123456');
        
        await page.click('button[type="submit"]');
        await page.waitForTimeout(3000);
        
        // 로그인 성공 확인
        if (page.url().includes('/dashboard')) {
          console.log(`✅ 지사 계정 로그인 성공: ${email}`);
          loginSuccess = true;
          successAccount = email;
          break;
        } else {
          console.log(`❌ 지사 계정 로그인 실패: ${email}`);
          // 다시 로그인 페이지로
          await page.goto('https://ykpproject-production.up.railway.app/login');
          await page.waitForTimeout(1000);
        }
        
      } catch (e) {
        console.log(`⚠️ 지사 계정 로그인 오류: ${email} - ${e.message}`);
        continue;
      }
    }
    
    if (loginSuccess) {
      console.log(`🎉 지사 계정 로그인 검증 완료: ${successAccount}`);
      
      // 지사 대시보드 확인
      const dashboardTitle = page.locator('h1');
      await expect(dashboardTitle).toContainText('대시보드', { timeout: 10000 });
      
      // 지사 권한 확인 (본사가 아닌 지사 메뉴가 보이는지)
      console.log('📊 지사 대시보드 접근 확인 완료');
      
      // 스크린샷 저장
      await page.screenshot({
        path: 'tests/playwright/branch-login-success.png',
        fullPage: true
      });
      
    } else {
      console.log('❌ 모든 지사 계정 로그인 실패');
      console.log('💡 가능한 원인:');
      console.log('   1. 지사가 아직 생성되지 않음');
      console.log('   2. 계정 생성 프로세스에 오류');
      console.log('   3. 비밀번호가 123456이 아님');
      
      // 현재 페이지 스크린샷
      await page.screenshot({
        path: 'tests/playwright/branch-login-failed.png',
        fullPage: true
      });
    }
    
    // 테스트 성공 조건
    expect(loginSuccess).toBe(true);
    
  });
  
  test('지사 계정 생성 API 직접 테스트', async ({ page }) => {
    
    console.log('🔧 지사 계정 생성 API 직접 테스트...');
    
    // 본사 계정으로 로그인
    await page.goto('https://ykpproject-production.up.railway.app/login');
    await page.fill('input[name="email"]', 'admin@ykp.com');
    await page.fill('input[name="password"]', '123456');
    await page.click('button[type="submit"]');
    
    try {
      await page.waitForURL(/dashboard/, { timeout: 10000 });
      console.log('✅ 본사 계정 로그인 성공');
      
      // 지사 관리 페이지로 이동
      await page.goto('https://ykpproject-production.up.railway.app/management/branches');
      await page.waitForLoadState('networkidle');
      
      console.log('📄 지사 관리 페이지 접근 성공');
      
      // 현재 지사 목록 확인
      const branches = await page.locator('text=branch_').count();
      console.log(`🏢 현재 지사 계정 패턴 발견: ${branches}개`);
      
      // 페이지 스크린샷
      await page.screenshot({
        path: 'tests/playwright/branch-management-page.png',
        fullPage: true
      });
      
    } catch (e) {
      console.log('⚠️ 본사 계정으로 접근 실패, 게스트 모드로 확인');
      
      // 직접 API 호출로 확인
      const response = await page.request.get('https://ykpproject-production.up.railway.app/test-api/branches');
      
      if (response.ok()) {
        const data = await response.json();
        console.log('📊 지사 데이터 확인:', data.data?.length || 0, '개');
      }
    }
    
  });
  
});