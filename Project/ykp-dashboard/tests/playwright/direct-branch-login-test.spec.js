import { test, expect } from '@playwright/test';

/**
 * 🚨 긴급: 실제 표시된 지사 계정 로그인 디버깅
 * branch_gg001@ykp.com / 123456 로그인 실패 원인 분석
 */

test.describe('🚨 Direct Branch Login Debug', () => {
  test('branch_gg001@ykp.com 계정 로그인 디버깅', async ({ page }) => {
    
    console.log('🚨 긴급: 실제 지사 계정 로그인 디버깅 시작...');
    console.log('📧 계정: branch_gg001@ykp.com');
    console.log('🔐 비밀번호: 123456');
    
    // 콘솔 로그 캡처
    const consoleLogs = [];
    page.on('console', msg => {
      consoleLogs.push(`${msg.type()}: ${msg.text()}`);
    });
    
    // 네트워크 요청 캡처
    const networkRequests = [];
    page.on('request', request => {
      if (request.url().includes('/login')) {
        networkRequests.push({
          url: request.url(),
          method: request.method(),
          postData: request.postData()
        });
      }
    });
    
    const networkResponses = [];
    page.on('response', async response => {
      if (response.url().includes('/login')) {
        const responseBody = await response.text().catch(() => 'Failed to read');
        networkResponses.push({
          url: response.url(),
          status: response.status(),
          body: responseBody.substring(0, 500)
        });
      }
    });
    
    // 1. 로그인 페이지 접속
    await page.goto('https://ykpproject-production.up.railway.app/login');
    console.log('✅ 로그인 페이지 접속 완료');
    
    // 2. 정확한 계정 정보 입력
    await page.fill('input[name="email"]', 'branch_gg001@ykp.com');
    await page.fill('input[name="password"]', '123456');
    
    console.log('✅ 계정 정보 입력 완료');
    
    // 3. 로그인 버튼 클릭 전 스크린샷
    await page.screenshot({
      path: 'tests/playwright/before-branch-login.png',
      fullPage: true
    });
    
    // 4. 로그인 시도
    await page.click('button[type="submit"]');
    console.log('✅ 로그인 버튼 클릭');
    
    // 5. 응답 대기
    await page.waitForTimeout(5000);
    
    // 6. 로그인 후 상태 확인
    const currentUrl = page.url();
    console.log(`📍 로그인 후 URL: ${currentUrl}`);
    
    // 7. 로그인 후 스크린샷
    await page.screenshot({
      path: 'tests/playwright/after-branch-login.png',  
      fullPage: true
    });
    
    // 8. 페이지 내용 분석
    const pageTitle = await page.title();
    console.log(`📄 페이지 제목: ${pageTitle}`);
    
    // 오류 메시지 확인
    const errorMessage = await page.locator('text=로그인 정보가 올바르지 않습니다').isVisible().catch(() => false);
    if (errorMessage) {
      console.log('❌ 로그인 오류 메시지 발견!');
    }
    
    // 성공 여부 판단
    const loginSuccess = currentUrl.includes('/dashboard');
    console.log(`🎯 로그인 성공 여부: ${loginSuccess}`);
    
    // 9. 네트워크 로그 출력
    console.log('\\n📡 === NETWORK REQUESTS ===');
    networkRequests.forEach((req, i) => {
      console.log(`${i + 1}. ${req.method} ${req.url}`);
      if (req.postData) {
        console.log(`   Data: ${req.postData.substring(0, 100)}...`);
      }
    });
    
    console.log('\\n📈 === NETWORK RESPONSES ===');
    networkResponses.forEach((res, i) => {
      console.log(`${i + 1}. [${res.status}] ${res.url}`);
      if (res.status !== 200) {
        console.log(`   Body: ${res.body}`);
      }
    });
    
    console.log('\\n🔍 === CONSOLE LOGS ===');
    consoleLogs.forEach((log, i) => {
      if (log.includes('error') || log.includes('Error') || log.includes('fail')) {
        console.log(`${i + 1}. ${log}`);
      }
    });
    
    // 10. 데이터베이스 직접 확인 (API를 통해)
    if (!loginSuccess) {
      console.log('\\n🔍 === DATABASE VERIFICATION ===');
      
      try {
        // 사용자 존재 여부 확인 (간접적)
        const response = await page.request.get('https://ykpproject-production.up.railway.app/api/users/count');
        if (response.ok()) {
          const data = await response.json();
          console.log(`📊 총 사용자 수: ${data.count}`);
        }
        
        // 지사 정보 확인
        const branchResponse = await page.request.get('https://ykpproject-production.up.railway.app/test-api/branches');
        if (branchResponse.ok()) {
          const branchData = await branchResponse.json();
          console.log(`🏢 총 지사 수: ${branchData.data?.length || 0}`);
          
          const gg001Branch = branchData.data?.find(b => b.code === 'GG001');
          if (gg001Branch) {
            console.log(`✅ GG001 지사 발견: ${gg001Branch.name}`);
          } else {
            console.log('❌ GG001 지사를 찾을 수 없음');
          }
        }
      } catch (e) {
        console.log(`⚠️ API 확인 실패: ${e.message}`);
      }
    }
    
    // 11. 최종 진단
    console.log('\\n🏥 === FINAL DIAGNOSIS ===');
    if (loginSuccess) {
      console.log('✅ 로그인 성공 - 문제 없음');
    } else {
      console.log('❌ 로그인 실패 - 가능한 원인:');
      console.log('   1. 계정이 DB에 실제로 존재하지 않음');
      console.log('   2. 비밀번호 해싱 문제');
      console.log('   3. 계정 비활성화 상태');
      console.log('   4. Laravel Auth 설정 문제');
      console.log('   5. Railway PostgreSQL 연결 문제');
    }
    
  });
  
});