import { test, expect } from '@playwright/test';

test.describe('🎯 Final Branch Login Test', () => {
  test('확인된 지사 계정으로 로그인 테스트', async ({ page }) => {
    
    console.log('🎯 최종 지사 계정 로그인 테스트...');
    
    const testAccounts = [
      { email: 'branch_gg001@ykp.com', password: '123456', name: '최종테스트' },
      { email: 'branch_test001@ykp.com', password: '123456', name: '테스트지점' }
    ];
    
    for (const account of testAccounts) {
      console.log(`\\n🔑 ${account.name} 지사 계정 테스트: ${account.email}`);
      
      // 로그인 페이지 이동
      await page.goto('https://ykpproject-production.up.railway.app/login');
      await page.waitForLoadState('networkidle');
      
      // 로그인 전 스크린샷
      await page.screenshot({
        path: `tests/playwright/before-${account.name}-login.png`
      });
      
      // 계정 정보 입력
      await page.fill('input[name="email"]', '');
      await page.fill('input[name="password"]', '');
      await page.fill('input[name="email"]', account.email);
      await page.fill('input[name="password"]', account.password);
      
      console.log(`✅ 계정 정보 입력 완료: ${account.email}`);
      
      // 로그인 시도
      await page.click('button[type="submit"]');
      await page.waitForTimeout(5000);
      
      // 결과 확인
      const currentUrl = page.url();
      const loginSuccess = currentUrl.includes('/dashboard');
      
      console.log(`📍 로그인 후 URL: ${currentUrl}`);
      console.log(`🎯 로그인 성공: ${loginSuccess ? '✅' : '❌'}`);
      
      if (loginSuccess) {
        console.log(`🎉 ${account.name} 지사 계정 로그인 성공!`);
        
        // 대시보드 확인
        const dashboardTitle = await page.locator('h1').textContent();
        console.log(`📊 대시보드 제목: ${dashboardTitle}`);
        
        // 성공 스크린샷
        await page.screenshot({
          path: `tests/playwright/success-${account.name}-dashboard.png`,
          fullPage: true
        });
        
        // 로그아웃
        await page.goto('https://ykpproject-production.up.railway.app/logout');
        await page.waitForTimeout(2000);
        
      } else {
        console.log(`❌ ${account.name} 지사 계정 로그인 실패`);
        
        // 오류 메시지 확인
        const errorMsg = await page.locator('text=로그인 정보가 올바르지 않습니다').isVisible();
        if (errorMsg) {
          console.log('🚨 "로그인 정보가 올바르지 않습니다" 메시지 확인됨');
        }
        
        // 실패 스크린샷
        await page.screenshot({
          path: `tests/playwright/failed-${account.name}-login.png`
        });
      }
    }
    
    console.log('\\n🏁 최종 지사 계정 로그인 테스트 완료');
    
  });
  
  // 추가: 직접 API 테스트
  test('지사 계정 API 직접 검증', async ({ page, request }) => {
    
    console.log('🔍 지사 계정 API 직접 검증...');
    
    try {
      // 1. 지사 목록 확인
      const branchResponse = await request.get('https://ykpproject-production.up.railway.app/test-api/branches');
      if (branchResponse.ok()) {
        const branchData = await branchResponse.json();
        console.log(`🏢 지사 수: ${branchData.data?.length || 0}개`);
        
        branchData.data?.forEach(branch => {
          console.log(`  - ${branch.name} (${branch.code}): ${branch.stores_count}개 매장`);
        });
      }
      
      // 2. 사용자 수 확인  
      const userResponse = await request.get('https://ykpproject-production.up.railway.app/api/users/count');
      if (userResponse.ok()) {
        const userData = await userResponse.json();
        console.log(`👥 총 사용자 수: ${userData.count}명`);
      }
      
    } catch (e) {
      console.log(`⚠️ API 검증 실패: ${e.message}`);
    }
    
  });
  
});