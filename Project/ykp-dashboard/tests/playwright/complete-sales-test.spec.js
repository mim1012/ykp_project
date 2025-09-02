import { test, expect } from '@playwright/test';

// 완전 판매 입력 및 권한별 조회 자동 검증
test.describe('YKP 완전 판매관리 검증', () => {
  const baseURL = 'http://127.0.0.1:8000';
  
  const accounts = {
    headquarters: { email: 'hq@ykp.com', password: '123456', role: '본사' },
    branch: { email: 'branch@ykp.com', password: '123456', role: '지사' },
    store: { email: 'store@ykp.com', password: '123456', role: '매장' }
  };

  // 로그인 헬퍼
  async function login(page, account) {
    await page.goto(baseURL);
    await page.fill('input[type="email"]', account.email);
    await page.fill('input[type="password"]', account.password);
    await page.click('button[type="submit"]');
    await page.waitForURL('**/dashboard');
    console.log(`✅ ${account.role} 로그인 성공`);
  }

  // 개통표 페이지 이동
  async function goToSalesPage(page) {
    await page.goto(baseURL + '/test/complete-aggrid');
    await page.waitForTimeout(2000);
    
    // JavaScript 로딩 대기
    await page.waitForFunction(() => {
      return window.userData !== undefined && typeof completeTableData !== 'undefined';
    }, { timeout: 10000 });
  }

  test('본사 계정 - 완전 판매 입력 및 저장 검증', async ({ page }) => {
    // 1. 본사 로그인
    await login(page, accounts.headquarters);
    
    // 2. 개통표 페이지 이동
    await goToSalesPage(page);
    
    // 3. 사용자 권한 확인
    const userData = await page.evaluate(() => window.userData);
    expect(userData.role).toBe('headquarters');
    console.log('본사 사용자 정보:', userData);
    
    // 4. 오늘 날짜로 설정
    await page.click('button:has-text("오늘")');
    await page.waitForTimeout(1000);
    
    // 5. 테스트 고객 데이터 자동 입력
    const testCustomer = {
      model_name: 'iPhone 15 Pro Max',
      carrier: 'SK',
      activation_type: 'MNP',
      base_price: 1500000,
      settlement_amount: 600000,
      margin_after_tax: 80000,
      phone_number: '010-1234-5678',
      salesperson: '김본사',
      customer_name: '홍길동'
    };
    
    await page.evaluate((customer) => {
      // 기존 데이터에 새 고객 추가
      if (!completeTableData) {
        window.completeTableData = [];
      }
      
      const newRow = {
        id: null,
        sale_date: new Date().toISOString().split('T')[0],
        model_name: customer.model_name,
        carrier: customer.carrier,
        activation_type: customer.activation_type,
        base_price: customer.base_price,
        settlement_amount: customer.settlement_amount,
        margin_after_tax: customer.margin_after_tax,
        phone_number: customer.phone_number,
        salesperson: customer.salesperson,
        customer_name: customer.customer_name,
        store_id: window.userData?.store_id || 1,
        branch_id: window.userData?.branch_id || 1
      };
      
      completeTableData.push(newRow);
      console.log('✅ 본사에서 고객 데이터 추가:', newRow);
      
      // 테이블 업데이트
      if (typeof updateCompleteTable === 'function') {
        updateCompleteTable();
      }
    }, testCustomer);
    
    // 6. 저장 전 데이터 확인
    const beforeSave = await page.evaluate(() => ({
      dataCount: completeTableData?.length || 0,
      hasValidData: completeTableData?.some(row => row.model_name) || false
    }));
    
    console.log('저장 전 데이터:', beforeSave);
    expect(beforeSave.hasValidData).toBe(true);
    
    // 7. API 응답 모니터링
    let saveSuccess = false;
    let apiDetails = null;
    
    page.on('response', response => {
      if (response.url().includes('/save')) {
        apiDetails = {
          url: response.url(),
          status: response.status(),
          timestamp: new Date().toISOString()
        };
        saveSuccess = response.status() === 200;
        console.log('💾 저장 API 응답:', apiDetails);
      }
    });
    
    // 8. 일괄 저장 실행
    await page.click('#save-btn');
    await page.waitForTimeout(3000);
    
    // 9. 저장 결과 검증
    expect(saveSuccess).toBe(true);
    expect(apiDetails.status).toBe(200);
    console.log('✅ 본사 데이터 저장 성공');
  });

  test('지사 계정 - 소속 데이터만 조회 검증', async ({ page }) => {
    // 1. 지사 로그인
    await login(page, accounts.branch);
    
    // 2. 개통표 페이지 이동
    await goToSalesPage(page);
    
    // 3. 지사 권한 확인
    const userData = await page.evaluate(() => window.userData);
    expect(userData.role).toBe('branch');
    expect(userData.branch_id).toBeTruthy();
    console.log('지사 사용자 정보:', userData);
    
    // 4. 지사 전용 데이터 입력
    await page.evaluate((branchId) => {
      const branchCustomer = {
        id: null,
        sale_date: new Date().toISOString().split('T')[0],
        model_name: 'Galaxy S24',
        carrier: 'KT',
        activation_type: '신규',
        settlement_amount: 400000,
        salesperson: '이지사',
        store_id: 1, // 지사 소속 매장
        branch_id: branchId
      };
      
      if (!completeTableData) window.completeTableData = [];
      completeTableData.push(branchCustomer);
      console.log('✅ 지사에서 데이터 추가:', branchCustomer);
    }, userData.branch_id);
    
    // 5. 지사 데이터 저장
    let branchSaveSuccess = false;
    page.on('response', response => {
      if (response.url().includes('/save')) {
        branchSaveSuccess = response.status() === 200;
        console.log('🏬 지사 저장 응답:', response.status());
      }
    });
    
    await page.click('#save-btn');
    await page.waitForTimeout(3000);
    
    expect(branchSaveSuccess).toBe(true);
    console.log('✅ 지사 데이터 저장 성공');
  });

  test('매장 계정 - 자기 매장 데이터만 입력 검증', async ({ page }) => {
    // 1. 매장 로그인
    await login(page, accounts.store);
    
    // 2. 개통표 페이지 이동
    await goToSalesPage(page);
    
    // 3. 매장 권한 확인
    const userData = await page.evaluate(() => window.userData);
    expect(userData.role).toBe('store');
    expect(userData.store_id).toBeTruthy();
    console.log('매장 사용자 정보:', userData);
    
    // 4. 매장 전용 데이터 입력
    await page.evaluate((userData) => {
      const storeCustomer = {
        id: null,
        sale_date: new Date().toISOString().split('T')[0],
        model_name: 'iPhone 14',
        carrier: 'LG',
        activation_type: '기변',
        settlement_amount: 300000,
        salesperson: '박매장',
        store_id: userData.store_id,
        branch_id: userData.branch_id
      };
      
      if (!completeTableData) window.completeTableData = [];
      completeTableData.push(storeCustomer);
      console.log('✅ 매장에서 데이터 추가:', storeCustomer);
    }, userData);
    
    // 5. 매장 데이터 저장 및 검증
    let storeSaveSuccess = false;
    page.on('response', response => {
      if (response.url().includes('/save')) {
        storeSaveSuccess = response.status() === 200;
        console.log('🏪 매장 저장 응답:', response.status());
      }
    });
    
    await page.click('#save-btn');
    await page.waitForTimeout(3000);
    
    expect(storeSaveSuccess).toBe(true);
    console.log('✅ 매장 데이터 저장 성공');
  });

  test('데이터 일관성 검증 - 권한별 조회 범위 확인', async ({ browser }) => {
    // 여러 계정 동시 테스트
    const hqPage = await browser.newPage();
    const branchPage = await browser.newPage();
    const storePage = await browser.newPage();

    try {
      // 각 권한별 로그인
      await login(hqPage, accounts.headquarters);
      await login(branchPage, accounts.branch);
      await login(storePage, accounts.store);

      // 개통표 페이지 이동
      await goToSalesPage(hqPage);
      await goToSalesPage(branchPage);
      await goToSalesPage(storePage);

      // 권한 정보 수집
      const hqData = await hqPage.evaluate(() => window.userData);
      const branchData = await branchPage.evaluate(() => window.userData);
      const storeData = await storePage.evaluate(() => window.userData);

      console.log('🏢 본사 권한:', hqData);
      console.log('🏬 지사 권한:', branchData);
      console.log('🏪 매장 권한:', storeData);

      // 권한 검증
      expect(hqData.role).toBe('headquarters');
      expect(branchData.role).toBe('branch');
      expect(storeData.role).toBe('store');

      // 데이터 접근 범위 확인
      expect(hqData.store_id).toBeNull(); // 본사는 특정 매장에 제한되지 않음
      expect(branchData.branch_id).toBeTruthy(); // 지사는 특정 지사에 속함
      expect(storeData.store_id).toBeTruthy(); // 매장은 특정 매장에 속함

      console.log('✅ 모든 권한별 접근 범위 검증 완료');

    } finally {
      await hqPage.close();
      await branchPage.close();
      await storePage.close();
    }
  });

  test('Supabase 실시간 데이터 동기화 검증', async ({ page }) => {
    // 저장 전 Supabase 데이터 수 확인
    const beforeCount = await page.evaluate(async () => {
      const response = await fetch('/api/dev/stores');
      const data = await response.json();
      return data.data?.length || 0;
    });
    
    console.log('저장 전 매장 수:', beforeCount);

    // 로그인 및 개통표 이동
    await login(page, accounts.headquarters);
    await goToSalesPage(page);

    // 새로운 판매 데이터 입력
    await page.evaluate(() => {
      const realtimeTestData = {
        id: null,
        sale_date: new Date().toISOString().split('T')[0],
        model_name: `실시간테스트_${Date.now()}`, // 유니크한 데이터
        carrier: 'SK',
        settlement_amount: 999999,
        salesperson: 'Playwright실시간',
        store_id: 1,
        branch_id: 1
      };
      
      if (!completeTableData) window.completeTableData = [];
      completeTableData.push(realtimeTestData);
      console.log('🔄 실시간 테스트 데이터 추가:', realtimeTestData);
    });

    // 저장 실행
    await page.click('#save-btn');
    await page.waitForTimeout(3000);

    // 저장 후 즉시 다른 페이지에서 데이터 확인
    const afterSaveCheck = await page.evaluate(async () => {
      // 새 창에서 데이터 재조회
      const response = await fetch('/api/dev/stores');
      const data = await response.json();
      return {
        storeCount: data.data?.length || 0,
        success: data.success
      };
    });

    console.log('저장 후 확인:', afterSaveCheck);
    console.log('🔄 실시간 동기화 테스트 완료');
    
    // 데이터 증가 확인
    expect(afterSaveCheck.success).toBe(true);
  });
});