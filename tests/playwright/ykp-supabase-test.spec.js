import { test, expect } from '@playwright/test';

// YKP Supabase 연동 자동화 테스트
test.describe('YKP Supabase 개통표 테스트', () => {
  const baseURL = 'http://127.0.0.1:8000';
  
  // 로그인 헬퍼
  async function login(page, email = 'hq@ykp.com', password = '123456') {
    await page.goto(baseURL);
    await page.fill('input[type="email"]', email);
    await page.fill('input[type="password"]', password);
    await page.click('button[type="submit"]');
    await page.waitForURL('**/dashboard');
  }

  test('개통표 데이터 입력 및 Supabase 저장 자동화', async ({ page }) => {
    // API 요청 모니터링
    const apiRequests = [];
    const apiResponses = [];
    
    page.on('request', request => {
      if (request.url().includes('/api/')) {
        apiRequests.push({
          url: request.url(),
          method: request.method(),
          headers: Object.fromEntries(request.headers()),
          timestamp: new Date().toISOString()
        });
      }
    });
    
    page.on('response', response => {
      if (response.url().includes('/api/')) {
        apiResponses.push({
          url: response.url(),
          status: response.status(),
          statusText: response.statusText(),
          timestamp: new Date().toISOString()
        });
      }
    });

    // 1. 본사 계정 로그인
    await login(page);
    console.log('✅ 로그인 성공');

    // 2. 개통표 페이지 이동
    await page.goto(baseURL + '/test/complete-aggrid');
    await page.waitForLoadState('networkidle');
    console.log('✅ 개통표 페이지 로드');

    // 3. 페이지 로딩 및 JavaScript 초기화 대기
    await page.waitForFunction(() => {
      return window.userData !== undefined && 
             typeof completeTableData !== 'undefined';
    }, { timeout: 15000 });
    console.log('✅ JavaScript 초기화 완료');

    // 4. 현재 데이터 상태 확인
    const initialData = await page.evaluate(() => {
      return {
        userData: window.userData,
        tableData: completeTableData || [],
        tableDataLength: completeTableData?.length || 0
      };
    });
    console.log('초기 데이터:', initialData);

    // 5. 오늘 날짜로 설정
    await page.click('button:has-text("오늘")');
    await page.waitForTimeout(2000);
    console.log('✅ 오늘 날짜 설정');

    // 6. AgGrid 테이블에 데이터 입력
    // AgGrid 셀 선택 및 데이터 입력
    const testData = {
      model_name: 'iPhone 15 Pro',
      carrier: 'SK',
      activation_type: '신규',
      base_price: '1200000',
      settlement_amount: '500000',
      phone_number: '010-1234-5678',
      salesperson: '김직원'
    };

    // AgGrid 로딩 대기 및 데이터 입력
    try {
      // AgGrid 컨테이너 확인
      const agGridSelectors = [
        '.ag-root',
        '.ag-grid-container', 
        '[class*="ag-"]',
        'table',
        '.data-grid'
      ];
      
      let gridFound = false;
      for (const selector of agGridSelectors) {
        if (await page.locator(selector).count() > 0) {
          gridFound = true;
          console.log(`✅ 그리드 발견: ${selector}`);
          break;
        }
      }
      
      if (!gridFound) {
        console.log('⚠️ AgGrid를 찾을 수 없음. JavaScript로 직접 데이터 입력');
        
        // JavaScript로 직접 데이터 추가
        await page.evaluate((testData) => {
          if (typeof completeTableData === 'undefined') {
            window.completeTableData = [];
          }
          
          // 테스트 데이터 추가
          const newRow = {
            id: null,
            sale_date: new Date().toISOString().split('T')[0],
            model_name: testData.model_name,
            carrier: testData.carrier,
            activation_type: testData.activation_type,
            base_price: parseFloat(testData.base_price),
            settlement_amount: parseFloat(testData.settlement_amount),
            phone_number: testData.phone_number,
            salesperson: testData.salesperson,
            store_id: window.userData?.store_id || 1,
            branch_id: window.userData?.branch_id || 1
          };
          
          completeTableData.push(newRow);
          console.log('테스트 데이터 추가:', newRow);
          
          // 테이블 업데이트 함수 호출 (있다면)
          if (typeof updateCompleteTable === 'function') {
            updateCompleteTable();
          }
          
        }, testData);
      }
      
    } catch (error) {
      console.log('그리드 처리 오류:', error);
    }

    // 7. 데이터 입력 확인
    const inputtedData = await page.evaluate(() => {
      return {
        tableData: completeTableData || [],
        tableLength: completeTableData?.length || 0
      };
    });
    console.log('입력된 데이터:', inputtedData);

    // 8. 일괄 저장 버튼 클릭
    const saveBtn = page.locator('#save-btn');
    await expect(saveBtn).toBeVisible();
    
    console.log('일괄 저장 버튼 클릭 시도...');
    await saveBtn.click();
    
    // 9. API 요청 대기 및 확인
    await page.waitForTimeout(5000);
    
    // 10. API 요청/응답 결과 확인
    console.log('📊 API 요청들:', apiRequests);
    console.log('📊 API 응답들:', apiResponses);
    
    // 저장 API 호출 확인
    const saveAPICall = apiRequests.find(req => 
      req.url.includes('/save') || req.url.includes('/bulk')
    );
    
    if (saveAPICall) {
      console.log('✅ 저장 API 호출됨:', saveAPICall.url);
      
      const saveResponse = apiResponses.find(res => res.url === saveAPICall.url);
      if (saveResponse) {
        console.log('📥 저장 응답:', saveResponse.status, saveResponse.statusText);
        expect(saveResponse.status).toBe(200);
      }
    } else {
      console.log('❌ 저장 API 호출되지 않음');
      throw new Error('저장 API가 호출되지 않았습니다');
    }

    // 11. Supabase 데이터 확인
    const finalCheck = await page.evaluate(async () => {
      // 브라우저에서 직접 API 호출로 확인
      try {
        const response = await fetch('/api/dev/stores');
        const data = await response.json();
        return {
          stores: data.data?.length || 0,
          success: data.success || false
        };
      } catch (error) {
        return { error: error.message };
      }
    });
    
    console.log('🗃️ Supabase 최종 확인:', finalCheck);
  });

  test('권한별 데이터 조회 자동화 테스트', async ({ browser }) => {
    const hqPage = await browser.newPage();
    const branchPage = await browser.newPage();
    const storePage = await browser.newPage();

    try {
      // 각 권한별 로그인
      await login(hqPage, 'hq@ykp.com');
      await login(branchPage, 'branch@ykp.com');
      await login(storePage, 'store@ykp.com');

      // 개통표 페이지 이동
      await Promise.all([
        hqPage.goto(baseURL + '/test/complete-aggrid'),
        branchPage.goto(baseURL + '/test/complete-aggrid'),
        storePage.goto(baseURL + '/test/complete-aggrid')
      ]);

      // 각 계정의 사용자 정보 확인
      const hqUserData = await hqPage.evaluate(() => window.userData);
      const branchUserData = await branchPage.evaluate(() => window.userData);
      const storeUserData = await storePage.evaluate(() => window.userData);

      console.log('본사 데이터:', hqUserData);
      console.log('지사 데이터:', branchUserData);  
      console.log('매장 데이터:', storeUserData);

      // 권한별 접근 범위 검증
      expect(hqUserData.role).toBe('headquarters');
      expect(branchUserData.role).toBe('branch');
      expect(storeUserData.role).toBe('store');

    } finally {
      await hqPage.close();
      await branchPage.close();
      await storePage.close();
    }
  });
});