// Playwright 테스트 유틸리티 헬퍼 함수들

export class YKPTestHelper {
  constructor(page, baseURL = 'http://127.0.0.1:8000') {
    this.page = page;
    this.baseURL = baseURL;
  }

  // 안정적인 로그인 (재시도 포함)
  async stableLogin(email, password, maxRetries = 3) {
    for (let attempt = 1; attempt <= maxRetries; attempt++) {
      try {
        await this.page.goto(this.baseURL, { waitUntil: 'domcontentloaded' });
        await this.page.waitForSelector('input[type="email"]', { timeout: 10000 });
        
        await this.page.fill('input[type="email"]', email);
        await this.page.fill('input[type="password"]', password);
        await this.page.click('button[type="submit"]');
        
        await this.page.waitForURL('**/dashboard', { timeout: 15000 });
        await this.page.waitForFunction(() => window.userData !== undefined, { timeout: 10000 });
        await this.page.waitForTimeout(2000);
        
        const userData = await this.page.evaluate(() => window.userData);
        console.log(`✅ 로그인 성공 (시도 ${attempt}/${maxRetries}):`, userData.role);
        return userData;
        
      } catch (error) {
        console.log(`⚠️ 로그인 시도 ${attempt}/${maxRetries} 실패:`, error.message);
        if (attempt === maxRetries) throw error;
        await this.page.waitForTimeout(2000);
      }
    }
  }

  // 안전한 API 호출 (재시도 포함)
  async safeApiCall(endpoint, options = {}, maxRetries = 3) {
    for (let attempt = 1; attempt <= maxRetries; attempt++) {
      try {
        const response = await this.page.request.fetch(endpoint, options);
        
        if (response.status() >= 200 && response.status() < 300) {
          const data = await response.json();
          console.log(`✅ API 성공 (${response.status()}):`, endpoint);
          return { success: true, data, status: response.status() };
        } else {
          throw new Error(`HTTP ${response.status()}: ${response.statusText()}`);
        }
        
      } catch (error) {
        console.log(`⚠️ API 시도 ${attempt}/${maxRetries} 실패:`, error.message);
        if (attempt === maxRetries) {
          return { success: false, error: error.message };
        }
        await this.page.waitForTimeout(1000 * attempt); // 지수 백오프
      }
    }
  }

  // 페이지 안정 대기 (JavaScript 로딩 완료까지)
  async waitForPageStable(timeout = 15000) {
    await this.page.waitForFunction(() => {
      return document.readyState === 'complete' && 
             typeof window !== 'undefined' &&
             window.userData !== undefined;
    }, { timeout });
    
    await this.page.waitForTimeout(1000); // 추가 안정화
  }

  // 데이터 입력 헬퍼
  async addTestSalesData(testData = {}) {
    const defaultData = {
      sale_date: new Date().toISOString().split('T')[0],
      model_name: `TestData_${Date.now()}`,
      carrier: 'SK',
      activation_type: '신규',
      settlement_amount: 500000,
      margin_after_tax: 50000,
      salesperson: 'TestBot',
      store_id: 1,
      branch_id: 1,
      ...testData
    };

    await this.page.evaluate((data) => {
      if (!window.completeTableData) window.completeTableData = [];
      window.completeTableData.push(data);
      console.log('✅ 테스트 데이터 추가:', data);
    }, defaultData);

    return defaultData;
  }

  // Supabase 데이터 상태 확인
  async checkSupabaseData() {
    const storesResult = await this.safeApiCall('/test-api/stores');
    const salesResult = await this.safeApiCall('/test-api/sales/count');
    
    return {
      stores: storesResult.data?.data?.length || 0,
      sales: salesResult.data?.count || 0,
      isConnected: storesResult.success && salesResult.success
    };
  }

  // 권한별 통계 데이터 수집
  async collectUserStats() {
    await this.page.goto(`${this.baseURL}/dashboard`);
    await this.waitForPageStable();

    return await this.page.evaluate(() => ({
      userData: window.userData,
      todaySales: document.querySelector('#todaySales .kpi-value')?.textContent || '₩0',
      monthSales: document.querySelector('#monthSales .kpi-value')?.textContent || '₩0',
      goalProgress: document.querySelector('#goalProgress .kpi-value')?.textContent || '0'
    }));
  }

  // 매장 관리 페이지 안전 이동
  async goToStoreManagement() {
    await this.page.goto(`${this.baseURL}/management/stores`);
    await this.waitForPageStable();
    
    // 권한 체크
    const hasAccess = await this.page.evaluate(() => {
      return !document.body.textContent.includes('403') && 
             !document.body.textContent.includes('접근 권한');
    });
    
    return hasAccess;
  }

  // 개통표 페이지 안전 이동
  async goToSalesPage() {
    await this.page.goto(`${this.baseURL}/test/complete-aggrid`);
    await this.waitForPageStable();
    
    // AgGrid 또는 테이블 로딩 확인
    const isLoaded = await this.page.evaluate(() => {
      return typeof completeTableData !== 'undefined' || 
             document.querySelector('.ag-root') !== null ||
             document.querySelector('table') !== null;
    });
    
    return isLoaded;
  }
}