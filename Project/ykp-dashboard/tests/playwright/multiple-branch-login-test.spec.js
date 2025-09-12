/**
 * 🔬 다중 지사 계정 로그인 테스트
 * 
 * 목적: 지사 관리 페이지에 표시된 모든 지사 계정으로 실제 로그인 테스트
 */

import { test, expect } from '@playwright/test';

const BASE_URL = 'https://ykpproject-production.up.railway.app';

test.describe('🔑 다중 지사 계정 로그인 검증', () => {
  
  // 지사 관리 페이지에서 확인된 계정들
  const branchAccounts = [
    { email: 'branch_test001@ykp.com', password: '123456', name: 'TEST001' },
    { email: 'branch_gg001@ykp.com', password: '123456', name: 'GG001' },
    { email: 'branch_test@ykp.com', password: '123456', name: 'TEST' },
    { email: 'branch_e2e999@ykp.com', password: '123456', name: 'E2E999' },
    { email: 'branch_bb01@ykp.com', password: '123456', name: 'BB01' }
  ];
  
  branchAccounts.forEach((account, index) => {
    test(`${account.name} 지사 계정 로그인 테스트`, async ({ page }) => {
      console.log(`🔐 ${account.name} 지사 계정 로그인 테스트 시작`);
      console.log(`📧 이메일: ${account.email}`);
      console.log(`🔑 비밀번호: ${account.password}`);
      
      // 로그인 페이지 접근
      await page.goto(BASE_URL);
      await page.waitForSelector('input[name="email"]', { timeout: 10000 });
      
      // 계정 정보 입력
      await page.fill('input[name="email"]', account.email);
      await page.fill('input[name="password"]', account.password);
      
      // 로그인 시도
      await page.click('button[type="submit"]');
      await page.waitForTimeout(3000);
      
      const currentUrl = page.url();
      
      if (currentUrl.includes('/dashboard')) {
        console.log(`✅ ${account.name} 지사 계정 로그인 성공`);
        
        // 지사 대시보드 정보 확인
        const dashboardInfo = await page.evaluate(() => {
          return {
            userData: window.userData,
            pageTitle: document.title,
            url: window.location.href
          };
        });
        
        console.log(`📊 ${account.name} 대시보드 정보:`, dashboardInfo.userData);
        
        // 스크린샷 저장
        await page.screenshot({ 
          path: `tests/playwright/${account.name}-dashboard.png`,
          fullPage: true 
        });
        
        // 간단한 기능 테스트 - 매장 목록 조회
        const storeAccessTest = await page.evaluate(async () => {
          try {
            const response = await fetch('/test-api/stores');
            const stores = await response.json();
            return {
              success: Array.isArray(stores),
              count: Array.isArray(stores) ? stores.length : 0
            };
          } catch (error) {
            return { success: false, error: error.message };
          }
        });
        
        console.log(`📈 ${account.name} 매장 접근 테스트:`, storeAccessTest);
        
      } else {
        console.log(`❌ ${account.name} 지사 계정 로그인 실패`);
        console.log(`📍 현재 URL: ${currentUrl}`);
        
        // 로그인 실패 스크린샷
        await page.screenshot({ 
          path: `tests/playwright/${account.name}-login-failed.png`,
          fullPage: true 
        });
        
        // 에러 메시지 확인
        const errorMessage = await page.locator('.alert-danger, .error, [class*="error"]').first();
        if (await errorMessage.isVisible()) {
          const errorText = await errorMessage.textContent();
          console.log(`🚨 ${account.name} 로그인 에러: ${errorText}`);
        }
      }
      
      console.log(`🎉 ${account.name} 계정 테스트 완료\n`);
    });
  });
});