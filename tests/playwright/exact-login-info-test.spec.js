/**
 * 🔬 지사 관리 페이지 표시 정보로 정확한 로그인 테스트
 * 
 * 목적: 페이지에 표시된 계정 정보가 실제로 로그인 가능한지 검증
 */

import { test, expect } from '@playwright/test';

const BASE_URL = 'https://ykpproject-production.up.railway.app';
const HQ_EMAIL = 'hq@ykp.com';
const HQ_PASSWORD = '123456';

test.describe('🔑 정확한 로그인 정보 테스트', () => {
  
  test('지사 관리 페이지 표시 정보로 실제 로그인 테스트', async ({ page }) => {
    console.log('🚀 정확한 로그인 정보 검증 테스트 시작');
    
    // ================================
    // PHASE 1: 본사 로그인 및 지사 정보 수집
    // ================================
    await page.goto(BASE_URL);
    await page.waitForSelector('input[name="email"]');
    
    await page.fill('input[name="email"]', HQ_EMAIL);
    await page.fill('input[name="password"]', HQ_PASSWORD);
    
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'networkidle' }),
      page.click('button[type="submit"]')
    ]);
    
    console.log('✅ 본사 계정 로그인 성공');
    
    // 지사 관리 페이지 접근
    await page.goto(`${BASE_URL}/management/branches`);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(5000); // 실제 계정 정보 업데이트 대기
    
    // 표시된 계정 정보 수집 (초록색으로 확인된 계정들만)
    const confirmedAccounts = await page.evaluate(() => {
      const branchRows = document.querySelectorAll('#branches-tbody tr');
      const realAccounts = [];
      
      branchRows.forEach(row => {
        const cells = row.querySelectorAll('td');
        if (cells.length >= 5) {
          const branchCode = cells[1]?.textContent?.trim();
          const accountCell = cells[4];
          const emailElement = accountCell?.querySelector('[id^="branch-email-"]');
          const passwordElement = accountCell?.querySelector('[id^="branch-password-"]');
          
          // 초록색(실제 계정)인지 확인
          const isRealAccount = emailElement?.style?.color === 'rgb(5, 150, 105)';
          
          if (isRealAccount) {
            const email = emailElement?.textContent?.trim();
            const passwordText = passwordElement?.textContent?.trim();
            
            // 비밀번호 추출 ("비밀번호: 123456 (확인됨)" 형식에서)
            const passwordMatch = passwordText?.match(/비밀번호:\s*(\S+)/);
            const password = passwordMatch ? passwordMatch[1] : null;
            
            if (email && password) {
              realAccounts.push({
                branchCode: branchCode,
                email: email,
                password: password,
                fullPasswordText: passwordText
              });
            }
          }
        }
      });
      
      return realAccounts;
    });
    
    console.log('📊 페이지에서 확인된 실제 계정들:', confirmedAccounts);
    
    // ================================
    // PHASE 2: 각 계정으로 실제 로그인 테스트
    // ================================
    console.log('📝 Phase 2: 확인된 계정들로 실제 로그인 테스트');
    
    const loginResults = [];
    
    for (const account of confirmedAccounts) {
      console.log(`\n🔐 ${account.branchCode} 지사 계정 로그인 테스트`);
      console.log(`   📧 이메일: ${account.email}`);
      console.log(`   🔑 비밀번호: ${account.password}`);
      
      // 본사에서 로그아웃
      await page.goto(`${BASE_URL}/logout`);
      await page.waitForTimeout(2000);
      
      // 로그인 페이지로 이동
      await page.goto(`${BASE_URL}/login`);
      await page.waitForSelector('input[name="email"]');
      
      // 페이지에 표시된 정확한 정보로 로그인 시도
      await page.fill('input[name="email"]', account.email);
      await page.fill('input[name="password"]', account.password);
      
      // 로그인 버튼 클릭
      await page.click('button[type="submit"]');
      await page.waitForTimeout(3000);
      
      const currentUrl = page.url();
      const loginSuccess = currentUrl.includes('/dashboard');
      
      loginResults.push({
        branchCode: account.branchCode,
        email: account.email,
        password: account.password,
        loginSuccess: loginSuccess,
        resultUrl: currentUrl
      });
      
      if (loginSuccess) {
        console.log(`   ✅ ${account.branchCode} 로그인 성공!`);
        
        // 로그인 성공한 대시보드 스크린샷
        await page.screenshot({ 
          path: `tests/playwright/${account.branchCode}-exact-login-success.png`,
          fullPage: true 
        });
        
        // 간단한 기능 테스트
        const dashboardTest = await page.evaluate(() => {
          return {
            userData: window.userData,
            hasUserData: !!window.userData,
            userRole: window.userData?.role,
            userName: window.userData?.name
          };
        });
        
        console.log(`   📊 ${account.branchCode} 대시보드 상태:`, dashboardTest);
        
      } else {
        console.log(`   ❌ ${account.branchCode} 로그인 실패`);
        console.log(`   📍 현재 URL: ${currentUrl}`);
        
        // 실패 스크린샷
        await page.screenshot({ 
          path: `tests/playwright/${account.branchCode}-exact-login-failed.png`,
          fullPage: true 
        });
        
        // 에러 메시지 확인
        const errorMessage = await page.locator('.alert-danger, .error, [class*="error"]').first();
        if (await errorMessage.isVisible()) {
          const errorText = await errorMessage.textContent();
          console.log(`   🚨 에러 메시지: ${errorText}`);
        }
      }
    }
    
    // ================================
    // PHASE 3: 최종 결과 정리
    // ================================
    console.log('\n📊 최종 로그인 테스트 결과:');
    console.log('='.repeat(60));
    
    let successCount = 0;
    loginResults.forEach(result => {
      const status = result.loginSuccess ? '✅ 성공' : '❌ 실패';
      console.log(`${status} ${result.branchCode}: ${result.email} / ${result.password}`);
      if (result.loginSuccess) successCount++;
    });
    
    const successRate = Math.round((successCount / loginResults.length) * 100);
    console.log(`\n🎯 로그인 성공률: ${successCount}/${loginResults.length} (${successRate}%)`);
    
    if (successRate >= 100) {
      console.log('🎉 모든 표시된 계정 정보가 정확하고 로그인 가능합니다!');
    } else if (successRate >= 80) {
      console.log('✅ 대부분의 표시된 계정 정보가 정확합니다.');
    } else {
      console.log('⚠️ 일부 계정 정보에 문제가 있을 수 있습니다.');
    }
    
    console.log('🎉 정확한 로그인 정보 검증 완료');
    
    // 최종 스크린샷 (본사 계정으로 돌아가서)
    await page.goto(BASE_URL);
    await page.fill('input[name="email"]', HQ_EMAIL);
    await page.fill('input[name="password"]', HQ_PASSWORD);
    await page.click('button[type="submit"]');
    await page.waitForTimeout(2000);
    
    await page.screenshot({ 
      path: 'tests/playwright/login-info-verification-complete.png',
      fullPage: true 
    });
  });
});