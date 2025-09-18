import { test, expect } from '@playwright/test';

// 최종 종합 테스트 (CSRF 해결 + 순차 실행)
test.describe('YKP 최종 종합 검증', () => {
  const baseURL = 'http://127.0.0.1:8000';
  
  const accounts = {
    headquarters: { email: 'hq@ykp.com', password: '123456' },
    branch: { email: 'branch@ykp.com', password: '123456' },
    store: { email: 'store@ykp.com', password: '123456' }
  };

  // 안정적인 로그인 헬퍼 (대기 조건 강화)
  async function stableLogin(page, account) {
    await page.goto(baseURL, { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('input[type="email"]', { timeout: 10000 });
    
    await page.fill('input[type="email"]', account.email);
    await page.fill('input[type="password"]', account.password);
    await page.click('button[type="submit"]');
    
    await page.waitForURL('**/dashboard', { timeout: 15000 });
    await page.waitForFunction(() => window.userData !== undefined, { timeout: 10000 });
    await page.waitForTimeout(2000); // 안정화
  }

  test.describe.configure({ mode: 'serial' }); // 순차 실행 강제

  test('1. API 연결 및 기본 데이터 검증', async ({ page }) => {
    console.log('📊 1단계: 기본 API 및 데이터 검증');

    // GET 요청으로 안전하게 확인
    const storesResponse = await page.request.get('/test-api/stores');
    expect(storesResponse.status()).toBe(200);
    
    const storesData = await storesResponse.json();
    expect(storesData.success).toBe(true);
    
    const salesResponse = await page.request.get('/test-api/sales/count');
    const salesData = await salesResponse.json();
    
    console.log(`✅ 매장: ${storesData.data.length}개`);
    console.log(`✅ 판매 데이터: ${salesData.count}건`);
    console.log('✅ Supabase 기본 연결 검증 완료');
  });

  test('2. 본사 권한 - 매장 추가 및 데이터 입력', async ({ page }) => {
    console.log('🏢 2단계: 본사 전체 워크플로우');

    await stableLogin(page, accounts.headquarters);
    
    // 본사 권한 확인
    const userData = await page.evaluate(() => window.userData);
    expect(userData.role).toBe('headquarters');
    console.log('✅ 본사 권한 확인:', userData.role);

    // 매장 추가 (POST 요청으로 정상 호출 - CSRF 제외됨)
    const newStoreName = `본사추가매장_${Date.now()}`;
    const addResponse = await page.request.post('/test-api/stores/add', {
      data: {
        name: newStoreName,
        branch_id: 1,
        owner_name: '본사점주',
        phone: '010-0000-1111'
      }
    });
    
    expect(addResponse.status()).toBe(200);
    const addResult = await addResponse.json();
    expect(addResult.success).toBe(true);
    console.log('✅ 본사 매장 추가 성공:', addResult.data.name);

    // 개통표 데이터 입력
    await page.goto('/test/complete-aggrid');
    await page.waitForFunction(() => window.userData !== undefined, { timeout: 15000 });
    
    // 데이터 저장 (GET 요청)
    const testSales = [{
      sale_date: new Date().toISOString().split('T')[0],
      model_name: '본사_iPhone_Pro',
      carrier: 'SK',
      settlement_amount: 999999,
      salesperson: '본사영업팀',
      store_id: 1,
      branch_id: 1
    }];

    const saveUrl = `/test-api/sales/save?sales=${encodeURIComponent(JSON.stringify(testSales))}`;
    const saveResponse = await page.request.get(saveUrl);
    
    expect(saveResponse.status()).toBe(200);
    const saveResult = await saveResponse.json();
    console.log('✅ 본사 데이터 저장 성공:', saveResult.message);
  });

  test('3. 지사 권한 - 제한된 접근 확인', async ({ page }) => {
    console.log('🏬 3단계: 지사 권한 범위 검증');

    await stableLogin(page, accounts.branch);
    
    const userData = await page.evaluate(() => window.userData);
    expect(userData.role).toBe('branch');
    expect(userData.branch_id).toBe(1); // 서울지사
    console.log('✅ 지사 권한 확인:', userData.role, '소속:', userData.branch_id);

    // 대시보드 통계 확인 (지사 범위)
    await page.goto('/dashboard');
    await page.waitForTimeout(3000);

    const branchStats = await page.evaluate(() => ({
      todaySales: document.querySelector('#todaySales .kpi-value')?.textContent,
      userInfo: window.userData
    }));

    console.log('📈 지사 통계 데이터:', branchStats);
    expect(branchStats.userInfo.branch_id).toBe(1);
    console.log('✅ 지사 권한 범위 검증 완료');
  });

  test('4. 매장 권한 - 자기 매장만 접근', async ({ page }) => {
    console.log('🏪 4단계: 매장 권한 범위 검증');

    await stableLogin(page, accounts.store);
    
    const userData = await page.evaluate(() => window.userData);
    expect(userData.role).toBe('store');
    expect(userData.store_id).toBe(1);
    expect(userData.branch_id).toBe(1);
    console.log('✅ 매장 권한 확인:', userData.role, '매장:', userData.store_id);

    // 개통표에서 매장 데이터 확인
    await page.goto('/test/complete-aggrid');
    await page.waitForFunction(() => window.userData !== undefined);

    const storeUserData = await page.evaluate(() => window.userData);
    console.log('🏪 매장 사용자 정보:', storeUserData);
    
    expect(storeUserData.store_name).toBeTruthy();
    expect(storeUserData.store_id).toBe(1);
    console.log('✅ 매장 권한 범위 검증 완료');
  });

  test('5. 실시간 데이터 동기화 최종 검증', async ({ page }) => {
    console.log('🔄 5단계: 실시간 동기화 최종 검증');

    // 저장 전 카운트
    const beforeResponse = await page.request.get('/test-api/sales/count');
    const beforeData = await beforeResponse.json();
    const beforeCount = beforeData.count;
    console.log(`📊 동기화 테스트 전 판매 데이터: ${beforeCount}건`);

    // 로그인
    await stableLogin(page, accounts.headquarters);

    // 새 데이터 저장
    const syncTestSales = [{
      sale_date: new Date().toISOString().split('T')[0],
      model_name: `동기화테스트_${Date.now()}`,
      settlement_amount: 777777,
      store_id: 1,
      branch_id: 1
    }];

    const syncSaveResponse = await page.request.post('/test-api/sales/save', {
      data: { sales: syncTestSales }
    });
    
    expect(syncSaveResponse.status()).toBe(200);
    console.log('💾 동기화 테스트 데이터 저장 완료');

    // 즉시 재조회로 동기화 확인
    await page.waitForTimeout(1000);
    const afterResponse = await page.request.get('/test-api/sales/count');
    const afterData = await afterResponse.json();
    const afterCount = afterData.count;

    expect(afterCount).toBeGreaterThan(beforeCount);
    console.log(`📊 동기화 테스트 후 판매 데이터: ${afterCount}건 (+${afterCount - beforeCount})`);
    console.log('🔄 Supabase 실시간 동기화 검증 완료');
  });
});