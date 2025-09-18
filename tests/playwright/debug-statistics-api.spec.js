import { test, expect } from '@playwright/test';

test.describe('Statistics Page API Debugging', () => {
  test('Debug all API calls on statistics page', async ({ page }) => {
    // 콘솔 로그 캡처
    const consoleLogs = [];
    page.on('console', msg => {
      if (msg.type() === 'log' || msg.type() === 'warn' || msg.type() === 'error') {
        consoleLogs.push(`${msg.type()}: ${msg.text()}`);
      }
    });

    // 네트워크 요청/응답 캡처
    const networkRequests = [];
    page.on('request', request => {
      if (request.url().includes('/api/')) {
        networkRequests.push({
          url: request.url(),
          method: request.method()
        });
      }
    });

    const networkResponses = [];
    page.on('response', async response => {
      if (response.url().includes('/api/')) {
        const responseBody = await response.text().catch(() => 'Failed to read response');
        networkResponses.push({
          url: response.url(),
          status: response.status(),
          body: responseBody.substring(0, 500) // 처음 500자만
        });
      }
    });

    // Railway 통계 페이지 접속
    console.log('🎯 Navigating to Railway statistics page...');
    await page.goto('https://ykpproject-production.up.railway.app/test-statistics', {
      waitUntil: 'networkidle',
      timeout: 30000
    });

    // 페이지가 완전히 로드될 때까지 대기
    await page.waitForTimeout(5000);

    // API 호출 완료까지 대기 (최대 10초)
    let attempts = 0;
    while (attempts < 10) {
      const loadingElement = await page.locator('text=로딩 중').count();
      if (loadingElement === 0) break;
      
      await page.waitForTimeout(1000);
      attempts++;
    }

    // 결과 출력
    console.log('\n📊 === NETWORK REQUESTS ===');
    networkRequests.forEach((req, i) => {
      console.log(`${i + 1}. ${req.method} ${req.url}`);
    });

    console.log('\n📈 === NETWORK RESPONSES ===');
    networkResponses.forEach((res, i) => {
      console.log(`${i + 1}. [${res.status}] ${res.url}`);
      if (res.status !== 200) {
        console.log(`   Response: ${res.body}`);
      }
    });

    console.log('\n🔍 === CONSOLE LOGS ===');
    consoleLogs.forEach((log, i) => {
      if (log.includes('API') || log.includes('error') || log.includes('Error')) {
        console.log(`${i + 1}. ${log}`);
      }
    });

    // 페이지 내용 확인
    const pageTitle = await page.title();
    console.log(`\n📄 Page Title: ${pageTitle}`);

    // 주요 섹션 존재 여부 확인
    const sectionsToCheck = [
      'h1:has-text("통계")',
      'text=지사 성과',
      'text=매장 순위', 
      'text=재무 요약',
      'text=통신사 점유율'
    ];

    console.log('\n🧭 === PAGE SECTIONS ===');
    for (const selector of sectionsToCheck) {
      const exists = await page.locator(selector).count() > 0;
      console.log(`${exists ? '✅' : '❌'} ${selector}`);
    }

    // 오류 메시지 찾기
    const errorMessages = await page.locator('text=오류').count();
    const errorElements = await page.locator('text=오류').all();
    
    console.log(`\n❌ 오류 메시지 발견: ${errorMessages}개`);
    for (const element of errorElements) {
      const text = await element.textContent();
      console.log(`   - ${text}`);
    }

    // 스크린샷 촬영
    await page.screenshot({
      path: 'tests/playwright/debug-statistics-page.png',
      fullPage: true
    });

    console.log('\n📸 스크린샷 저장: tests/playwright/debug-statistics-page.png');

    // 테스트 완료
    expect(networkResponses.length).toBeGreaterThan(0);
  });

  test('Direct API endpoint testing', async ({ request }) => {
    const baseUrl = 'https://ykpproject-production.up.railway.app';
    
    const endpoints = [
      '/api/profile',
      '/api/dashboard/overview', 
      '/api/dashboard/store-ranking',
      '/api/users/branches',
      '/api/dashboard/financial-summary',
      '/api/dashboard/dealer-performance'
    ];

    console.log('\n🔗 === DIRECT API TESTING ===');
    
    for (const endpoint of endpoints) {
      try {
        const response = await request.get(`${baseUrl}${endpoint}`);
        const body = await response.text();
        
        console.log(`\n${response.status()} ${endpoint}`);
        
        if (response.status() !== 200) {
          console.log(`❌ Error Response: ${body.substring(0, 200)}...`);
        } else {
          try {
            const json = JSON.parse(body);
            if (json.success) {
              console.log(`✅ Success: ${JSON.stringify(json.data).substring(0, 100)}...`);
            } else {
              console.log(`⚠️  API Error: ${json.error}`);
            }
          } catch (e) {
            console.log(`✅ HTML Response (length: ${body.length})`);
          }
        }
      } catch (error) {
        console.log(`💥 Request Failed: ${endpoint} - ${error.message}`);
      }
    }
  });
});