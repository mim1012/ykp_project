import { test, expect } from '@playwright/test';

test.describe('🚨 Branch Creation Debug', () => {
  test('지사 생성 시 계정 생성 과정 디버깅', async ({ page }) => {
    
    console.log('🚨 지사 생성 과정 디버깅 시작...');
    
    // 1. 본사 계정으로 로그인 
    await page.goto('https://ykpproject-production.up.railway.app/login');
    await page.fill('input[name="email"]', 'admin@ykp.com');
    await page.fill('input[name="password"]', '123456');
    await page.click('button[type="submit"]');
    
    try {
      await page.waitForURL(/dashboard/, { timeout: 10000 });
      console.log('✅ 본사 계정 로그인 성공');
    } catch (e) {
      console.log('❌ 본사 로그인 실패, 테스트 중단');
      return;
    }
    
    // 2. 지사 관리 페이지로 이동
    await page.goto('https://ykpproject-production.up.railway.app/management/branches');
    await page.waitForLoadState('networkidle');
    console.log('📄 지사 관리 페이지 접근');
    
    // 3. 네트워크 요청 모니터링
    const apiRequests = [];
    page.on('request', request => {
      if (request.url().includes('/test-api/branches/add')) {
        apiRequests.push({
          url: request.url(),
          method: request.method(),
          postData: request.postData()
        });
        console.log(`🌐 지사 생성 API 호출: ${request.method()} ${request.url()}`);
      }
    });
    
    const apiResponses = [];
    page.on('response', async response => {
      if (response.url().includes('/test-api/branches/add')) {
        const responseText = await response.text().catch(() => 'Failed to read');
        apiResponses.push({
          url: response.url(),
          status: response.status(),
          body: responseText
        });
        console.log(`📡 지사 생성 API 응답: ${response.status()}`);
      }
    });
    
    // 4. 테스트 지사 생성 시도
    try {
      // 새 지사 등록 버튼 찾기
      const addButton = page.locator('button:has-text("새 지사 등록")').first();
      if (await addButton.isVisible({ timeout: 5000 })) {
        await addButton.click();
        console.log('✅ 새 지사 등록 버튼 클릭');
        
        await page.waitForTimeout(1000);
        
        // 폼 필드 입력
        const testData = {
          name: `디버그테스트지사_${Date.now()}`,
          code: `DBG${Date.now().toString().slice(-4)}`,
          manager_name: '디버그테스트매니저',
          phone: '010-1234-5678'
        };
        
        await page.fill('input[name="name"]', testData.name);
        await page.fill('input[name="code"]', testData.code);
        await page.fill('input[name="manager_name"]', testData.manager_name);
        await page.fill('input[name="phone"]', testData.phone);
        
        console.log(`📝 폼 데이터 입력 완료: ${testData.name} (${testData.code})`);
        
        // 지사 추가 버튼 클릭
        const submitButton = page.locator('button:has-text("지사 추가")').first();
        await submitButton.click();
        console.log('✅ 지사 추가 버튼 클릭');
        
        // 응답 대기
        await page.waitForTimeout(5000);
        
        // API 호출 결과 분석
        console.log('\\n📊 API 호출 분석:');
        if (apiRequests.length > 0) {
          console.log(`✅ 지사 생성 API 호출됨 (${apiRequests.length}회)`);
          apiRequests.forEach((req, i) => {
            console.log(`  ${i+1}. ${req.method} ${req.url}`);
            if (req.postData) {
              console.log(`     Data: ${req.postData.substring(0, 100)}...`);
            }
          });
        } else {
          console.log('❌ 지사 생성 API가 호출되지 않음');
        }
        
        if (apiResponses.length > 0) {
          console.log('\\n📡 API 응답 분석:');
          apiResponses.forEach((res, i) => {
            console.log(`  ${i+1}. [${res.status}] ${res.url}`);
            if (res.status !== 200) {
              console.log(`     Error Body: ${res.body.substring(0, 300)}...`);
            } else {
              try {
                const data = JSON.parse(res.body);
                if (data.success) {
                  console.log(`     ✅ 성공: ${data.message}`);
                  if (data.data?.manager) {
                    console.log(`     👤 생성된 관리자: ${data.data.manager.email}`);
                    console.log(`     🔑 로그인 정보: ${data.data.login_info?.email} / ${data.data.login_info?.password}`);
                  }
                } else {
                  console.log(`     ❌ 실패: ${data.error}`);
                }
              } catch (e) {
                console.log(`     📄 HTML 응답 (길이: ${res.body.length})`);
              }
            }
          });
        }
        
        // 페이지 상태 확인
        const successMessage = await page.locator('text=성공').isVisible().catch(() => false);
        const errorMessage = await page.locator('text=오류').isVisible().catch(() => false);
        
        console.log(`\\n📄 페이지 상태:`);
        console.log(`  성공 메시지: ${successMessage ? '✅' : '❌'}`);
        console.log(`  오류 메시지: ${errorMessage ? '✅' : '❌'}`);
        
      } else {
        console.log('❌ 새 지사 등록 버튼을 찾을 수 없음');
      }
      
    } catch (e) {
      console.log(`❌ 지사 생성 과정에서 오류: ${e.message}`);
    }
    
    // 최종 스크린샷
    await page.screenshot({
      path: 'tests/playwright/branch-creation-debug.png',
      fullPage: true
    });
    
  });
});