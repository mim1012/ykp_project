import { test, expect } from '@playwright/test';
import { YKPTestHelper } from './utils/test-helpers.js';

// 최고 완성도 YKP 종합 테스트
test.describe('YKP Ultimate 완성도 테스트', () => {
  const accounts = {
    headquarters: { email: 'hq@ykp.com', password: '123456', role: '본사' },
    branch: { email: 'branch@ykp.com', password: '123456', role: '지사' },
    store: { email: 'store@ykp.com', password: '123456', role: '매장' }
  };

  test.describe.configure({ mode: 'serial' }); // 순차 실행

  let testHelper;
  let initialData = {};

  test.beforeAll(async ({ browser }) => {
    // 초기 데이터 상태 기록
    const page = await browser.newPage();
    testHelper = new YKPTestHelper(page);
    
    initialData = await testHelper.checkSupabaseData();
    console.log('📊 테스트 시작 전 데이터 상태:', initialData);
    
    await page.close();
  });

  test('1. Supabase 연결 및 기본 데이터 검증', async ({ page }) => {
    testHelper = new YKPTestHelper(page);
    
    const currentData = await testHelper.checkSupabaseData();
    expect(currentData.isConnected).toBe(true);
    expect(currentData.stores).toBeGreaterThan(0);
    
    console.log('✅ Supabase 연결 검증:', currentData);
  });

  test('2. 본사 전체 권한 워크플로우', async ({ page }) => {
    testHelper = new YKPTestHelper(page);
    
    // 안정적인 로그인
    const userData = await testHelper.stableLogin(accounts.headquarters.email, accounts.headquarters.password);
    expect(userData.role).toBe('headquarters');

    // 매장 관리 접근 확인
    const hasStoreAccess = await testHelper.goToStoreManagement();
    expect(hasStoreAccess).toBe(true);
    console.log('✅ 본사 매장 관리 접근 가능');

    // 새 매장 추가
    const storeAddResult = await testHelper.safeApiCall('/test-api/stores/add', {
      method: 'POST',
      data: {
        name: `본사자동매장_${Date.now()}`,
        branch_id: 1,
        owner_name: '자동생성점주',
        phone: '010-auto-1234'
      }
    });
    
    expect(storeAddResult.success).toBe(true);
    console.log('✅ 본사 매장 추가 성공');

    // 개통표 데이터 입력
    const salesPageLoaded = await testHelper.goToSalesPage();
    expect(salesPageLoaded).toBe(true);

    const testSalesData = await testHelper.addTestSalesData({
      model_name: '본사_Ultimate_iPhone',
      settlement_amount: 1111111,
      salesperson: '본사울티메이트팀'
    });

    // 데이터 저장
    const saveResult = await testHelper.safeApiCall('/test-api/sales/save', {
      method: 'POST',
      data: { sales: [testSalesData] }
    });

    expect(saveResult.success).toBe(true);
    console.log('✅ 본사 판매 데이터 저장 성공');

    // 본사 통계 확인
    const hqStats = await testHelper.collectUserStats();
    expect(hqStats.userData.role).toBe('headquarters');
    console.log('📈 본사 통계:', hqStats);
  });

  test('3. 지사 제한 권한 워크플로우', async ({ page }) => {
    testHelper = new YKPTestHelper(page);
    
    // 지사 로그인
    const userData = await testHelper.stableLogin(accounts.branch.email, accounts.branch.password);
    expect(userData.role).toBe('branch');
    expect(userData.branch_id).toBeTruthy();

    // 지사 통계 확인 (제한된 범위)
    const branchStats = await testHelper.collectUserStats();
    expect(branchStats.userData.branch_id).toBe(1);
    console.log('📈 지사 제한 통계:', branchStats);

    // 개통표 접근 (지사 데이터만)
    const salesPageLoaded = await testHelper.goToSalesPage();
    expect(salesPageLoaded).toBe(true);

    const branchSalesData = await testHelper.addTestSalesData({
      model_name: '지사_Galaxy_Ultra',
      settlement_amount: 888888,
      salesperson: '지사팀장',
      branch_id: userData.branch_id
    });

    const branchSaveResult = await testHelper.safeApiCall('/test-api/sales/save', {
      method: 'POST',
      data: { sales: [branchSalesData] }
    });

    expect(branchSaveResult.success).toBe(true);
    console.log('✅ 지사 권한 워크플로우 완료');
  });

  test('4. 매장 단일 접근 워크플로우', async ({ page }) => {
    testHelper = new YKPTestHelper(page);
    
    // 매장 로그인
    const userData = await testHelper.stableLogin(accounts.store.email, accounts.store.password);
    expect(userData.role).toBe('store');
    expect(userData.store_id).toBeTruthy();

    // 매장 관리 접근 시도 (실패해야 함)
    const hasStoreAccess = await testHelper.goToStoreManagement();
    expect(hasStoreAccess).toBe(false); // 매장은 관리 권한 없음
    console.log('✅ 매장 관리 접근 제한 확인');

    // 매장 통계 확인 (자기 매장만)
    const storeStats = await testHelper.collectUserStats();
    expect(storeStats.userData.store_id).toBe(1);
    console.log('📈 매장 제한 통계:', storeStats);

    // 개통표 데이터 입력 (자기 매장용)
    const salesPageLoaded = await testHelper.goToSalesPage();
    expect(salesPageLoaded).toBe(true);

    const storeSalesData = await testHelper.addTestSalesData({
      model_name: '매장_Pixel_Pro',
      settlement_amount: 666666,
      salesperson: userData.name,
      store_id: userData.store_id,
      branch_id: userData.branch_id
    });

    const storeSaveResult = await testHelper.safeApiCall('/test-api/sales/save', {
      method: 'POST',
      data: { sales: [storeSalesData] }
    });

    expect(storeSaveResult.success).toBe(true);
    console.log('✅ 매장 권한 워크플로우 완료');
  });

  test('5. 실시간 동기화 및 데이터 일관성 최종 검증', async ({ page }) => {
    testHelper = new YKPTestHelper(page);
    
    console.log('🔄 실시간 동기화 최종 검증');

    // 저장 전 상태
    const beforeData = await testHelper.checkSupabaseData();
    console.log('📊 동기화 테스트 전:', beforeData);

    // 본사로 로그인
    await testHelper.stableLogin(accounts.headquarters.email, accounts.headquarters.password);

    // 최종 동기화 테스트 데이터
    const finalTestData = {
      model_name: `최종동기화테스트_${Date.now()}`,
      carrier: 'FINAL',
      settlement_amount: 999999,
      salesperson: 'FinalBot',
      store_id: 1,
      branch_id: 1
    };

    const finalSaveResult = await testHelper.safeApiCall('/test-api/sales/save', {
      method: 'POST',
      data: { sales: [finalTestData] }
    });

    expect(finalSaveResult.success).toBe(true);
    console.log('💾 최종 테스트 데이터 저장 완료');

    // 즉시 동기화 확인
    await page.waitForTimeout(2000);
    const afterData = await testHelper.checkSupabaseData();
    
    expect(afterData.sales).toBeGreaterThan(beforeData.sales);
    console.log('📊 동기화 테스트 후:', afterData);
    console.log(`🎉 데이터 증가: +${afterData.sales - beforeData.sales}건`);

    // 최종 통계 수집
    const finalStats = await testHelper.collectUserStats();
    console.log('📈 최종 본사 통계:', finalStats);

    console.log('🎉 모든 테스트 완료! YKP Dashboard 검증 성공!');
  });

  test.afterAll(async ({ browser }) => {
    // 최종 데이터 상태 기록
    const page = await browser.newPage();
    const finalHelper = new YKPTestHelper(page);
    
    const finalData = await finalHelper.checkSupabaseData();
    console.log('📊 전체 테스트 완료 후 최종 데이터:', finalData);
    console.log(`🎯 테스트 결과: 매장 ${finalData.stores}개, 판매 ${finalData.sales}건`);
    
    await page.close();
  });
});