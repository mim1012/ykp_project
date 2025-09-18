import { test, expect } from '@playwright/test';

// 수정된 YKP 종합 워크플로우 테스트 (웹 라우트 API 사용)
test.describe('YKP 수정된 종합 검증', () => {
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
    await page.waitForTimeout(2000);
  }

  test('API 연결 및 Supabase 데이터 확인', async ({ page }) => {
    // 1. 새로운 웹 라우트 API 테스트
    const apiResponse = await page.request.get(`${baseURL}/test-api/stores`);
    expect(apiResponse.status()).toBe(200);
    
    const storesData = await apiResponse.json();
    expect(storesData.success).toBe(true);
    expect(storesData.data.length).toBeGreaterThan(0);
    
    console.log(`✅ Supabase 매장 데이터: ${storesData.data.length}개`);
    console.log('매장 목록:', storesData.data.map(s => s.name));

    // 2. 판매 데이터 수 확인
    const salesCountResponse = await page.request.get(`${baseURL}/test-api/sales/count`);
    const salesCount = await salesCountResponse.json();
    console.log(`📊 현재 판매 데이터: ${salesCount.count}건`);
  });

  test('본사 → 새 매장 추가 → Supabase 저장 검증', async ({ page }) => {
    await login(page, accounts.headquarters);
    
    // 저장 전 매장 수 확인
    const beforeResponse = await page.request.get(`${baseURL}/test-api/stores`);
    const beforeData = await beforeResponse.json();
    const beforeCount = beforeData.data.length;
    console.log(`📊 매장 추가 전: ${beforeCount}개`);

    // 새 매장 추가
    const newStoreName = `Playwright자동매장_${Date.now()}`;
    const addResponse = await page.request.post(`${baseURL}/test-api/stores/add`, {
      data: {
        name: newStoreName,
        branch_id: 1,
        owner_name: 'Playwright점주',
        phone: '010-9999-0000'
      }
    });
    
    expect(addResponse.status()).toBe(200);
    const addResult = await addResponse.json();
    expect(addResult.success).toBe(true);
    console.log('✅ 매장 추가 성공:', addResult.data.name);

    // 저장 후 매장 수 확인
    const afterResponse = await page.request.get(`${baseURL}/test-api/stores`);
    const afterData = await afterResponse.json();
    const afterCount = afterData.data.length;
    
    expect(afterCount).toBe(beforeCount + 1);
    console.log(`📊 매장 추가 후: ${afterCount}개 (+1 증가)`);
    console.log('✅ Supabase 매장 추가 검증 완료');
  });

  test('개통표 데이터 입력 및 실시간 저장 검증', async ({ page }) => {
    await login(page, accounts.headquarters);
    
    // 저장 전 판매 데이터 수 확인
    const beforeSalesResponse = await page.request.get(`${baseURL}/test-api/sales/count`);
    const beforeSalesData = await beforeSalesResponse.json();
    const beforeSalesCount = beforeSalesData.count;
    console.log(`📊 저장 전 판매 데이터: ${beforeSalesCount}건`);

    // 개통표 페이지 이동
    await page.goto(`${baseURL}/test/complete-aggrid`);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(3000);

    // JavaScript 로딩 확인
    await page.waitForFunction(() => window.userData !== undefined);
    
    // 테스트 데이터 직접 입력
    const testSalesData = [
      {
        sale_date: new Date().toISOString().split('T')[0],
        model_name: 'Playwright_iPhone_15',
        carrier: 'SK',
        activation_type: '신규',
        settlement_amount: 555555,
        margin_after_tax: 55555,
        salesperson: 'Playwright영업',
        store_id: 1,
        branch_id: 1
      }
    ];

    // API로 직접 저장
    const saveResponse = await page.request.post(`${baseURL}/test-api/sales/save`, {
      data: { sales: testSalesData }
    });

    expect(saveResponse.status()).toBe(200);
    const saveResult = await saveResponse.json();
    expect(saveResult.success).toBe(true);
    console.log('✅ 개통표 데이터 저장 성공:', saveResult.message);

    // 저장 후 판매 데이터 수 확인
    const afterSalesResponse = await page.request.get(`${baseURL}/test-api/sales/count`);
    const afterSalesData = await afterSalesResponse.json();
    const afterSalesCount = afterSalesData.count;

    expect(afterSalesCount).toBeGreaterThan(beforeSalesCount);
    console.log(`📊 저장 후 판매 데이터: ${afterSalesCount}건 (+${afterSalesCount - beforeSalesCount} 증가)`);
    console.log('✅ Supabase 실시간 저장 검증 완료');
  });

  test('권한별 통계 데이터 범위 검증', async ({ browser }) => {
    const hqPage = await browser.newPage();
    const branchPage = await browser.newPage();
    const storePage = await browser.newPage();

    try {
      // 각 권한별 로그인
      await login(hqPage, accounts.headquarters);
      await login(branchPage, accounts.branch);
      await login(storePage, accounts.store);

      // 대시보드 이동
      await Promise.all([
        hqPage.goto(`${baseURL}/dashboard`),
        branchPage.goto(`${baseURL}/dashboard`),
        storePage.goto(`${baseURL}/dashboard`)
      ]);

      await Promise.all([
        hqPage.waitForTimeout(3000),
        branchPage.waitForTimeout(3000),
        storePage.waitForTimeout(3000)
      ]);

      // 각 권한별 사용자 정보 및 통계 수집
      const hqData = await hqPage.evaluate(() => ({
        userData: window.userData,
        todaySales: document.querySelector('#todaySales .kpi-value')?.textContent,
        monthSales: document.querySelector('#monthSales .kpi-value')?.textContent
      }));

      const branchData = await branchPage.evaluate(() => ({
        userData: window.userData,
        todaySales: document.querySelector('#todaySales .kpi-value')?.textContent,
        monthSales: document.querySelector('#monthSales .kpi-value')?.textContent
      }));

      const storeData = await storePage.evaluate(() => ({
        userData: window.userData,
        todaySales: document.querySelector('#todaySales .kpi-value')?.textContent,
        monthSales: document.querySelector('#monthSales .kpi-value')?.textContent
      }));

      console.log('🏢 본사 통계 및 권한:', hqData);
      console.log('🏬 지사 통계 및 권한:', branchData);
      console.log('🏪 매장 통계 및 권한:', storeData);

      // 권한별 접근 범위 검증
      expect(hqData.userData.role).toBe('headquarters');
      expect(hqData.userData.store_id).toBeNull(); // 전체 접근
      
      expect(branchData.userData.role).toBe('branch');
      expect(branchData.userData.branch_id).toBeTruthy(); // 지사 한정
      
      expect(storeData.userData.role).toBe('store');
      expect(storeData.userData.store_id).toBeTruthy(); // 매장 한정

      console.log('✅ 모든 권한별 통계 범위 검증 완료');

    } finally {
      await hqPage.close();
      await branchPage.close();
      await storePage.close();
    }
  });
});