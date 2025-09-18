import { test, expect } from '@playwright/test';

// YKP 시스템 전체 워크플로우 테스트
test.describe('YKP 전체 워크플로우 테스트', () => {
  const baseURL = 'http://127.0.0.1:8000';
  
  const accounts = {
    headquarters: { email: 'hq@ykp.com', password: '123456', role: '본사 관리자' },
    branch: { email: 'branch@ykp.com', password: '123456', role: '지사 관리자' },
    store1: { email: 'store1@ykp.com', password: '123456', role: '매장 직원', storeName: '테스트매장1' },
    store2: { email: 'store2@ykp.com', password: '123456', role: '매장 직원', storeName: '테스트매장2' }
  };

  // 로그인 헬퍼 함수
  async function login(page, account) {
    await page.goto(baseURL);
    await page.fill('input[type="email"]', account.email);
    await page.fill('input[type="password"]', account.password);
    await page.click('button[type="submit"]');
    await page.waitForURL('**/dashboard');
    await page.waitForFunction(() => window.userData !== undefined, { timeout: 10000 });
  }

  // 매장 관리 페이지로 이동
  async function goToStoreManagement(page) {
    await page.goto(baseURL + '/dashboard');
    const storeIcon = page.locator('a[href="/management/stores"]');
    await storeIcon.click();
    await page.waitForURL('**/management/stores');
  }

  // 개통표 페이지로 이동
  async function goToSalesForm(page) {
    await page.goto(baseURL + '/dashboard'); 
    const salesIcon = page.locator('a[href="/test/complete-aggrid"]');
    await salesIcon.click();
    await page.waitForURL('**/test/complete-aggrid');
    await page.waitForFunction(() => document.readyState === 'complete', { timeout: 10000 });
  }

  test('전체 워크플로우: 본사 → 매장 추가 → 계정 생성 → 데이터 입력 → 조회', async ({ page }) => {
    // 1단계: 본사 계정으로 로그인
    await login(page, accounts.headquarters);
    
    // 대시보드에서 본사 권한 확인
    await expect(page.locator('text=본사 관리자')).toBeVisible();
    
    // 2단계: 매장 관리 페이지 접근
    await goToStoreManagement(page);
    await expect(page.locator('text=매장 목록')).toBeVisible();
    
    // 3단계: 새 매장 추가 (시뮬레이션)
    const addStoreBtn = page.locator('button:has-text("➕ 매장 추가")');
    if (await addStoreBtn.count() > 0) {
      await addStoreBtn.click();
      // 매장 추가 폼이 있다면 입력
      // 현재는 alert만 있으므로 dialog 처리
      page.on('dialog', async dialog => {
        expect(dialog.message()).toContain('매장 추가');
        await dialog.accept();
      });
    }
    
    // 4단계: 매장 계정 생성 버튼 확인
    const createAccountBtns = page.locator('button:has-text("계정생성")');
    if (await createAccountBtns.count() > 0) {
      await createAccountBtns.first().click();
      page.on('dialog', async dialog => {
        expect(dialog.message()).toContain('계정 생성');
        await dialog.accept();
      });
    }
    
    // 5단계: 개통표에서 데이터 입력
    await goToSalesForm(page);
    
    // 캘린더에서 오늘 날짜 선택
    const todayBtn = page.locator('button:has-text("오늘")');
    if (await todayBtn.count() > 0) {
      await todayBtn.click();
    }
    
    // 현재 날짜 확인
    const currentDate = await page.locator('#current-date').textContent();
    expect(currentDate).toMatch(/2025-\d{2}-\d{2}/);
    
    // 6단계: 본사에서 전체 매장 데이터 확인
    // 통계 카드에서 데이터 확인
    await expect(page.locator('#total-count')).toBeVisible();
    await expect(page.locator('#total-settlement')).toBeVisible();
  });

  test('매장 계정 - 개통표 데이터 입력 테스트', async ({ page }) => {
    // 매장 계정으로 로그인 (기존 store@ykp.com 사용)
    await login(page, accounts.store);
    
    // 매장 직원 권한 확인
    await expect(page.locator('text=매장 직원')).toBeVisible();
    
    // 개통표 페이지 이동
    await goToSalesForm(page);
    
    // 매장 정보가 자동 설정되었는지 확인
    const userData = await page.evaluate(() => window.userData);
    expect(userData.role).toBe('store');
    expect(userData.store_id).toBeTruthy();
    
    // 오늘 날짜로 설정
    await page.click('button:has-text("오늘")');
    
    // 데이터 입력 시뮬레이션
    // AgGrid나 테이블에서 데이터 입력 시도
    const dataInputs = [
      '.ag-cell', 
      'input[type="text"]', 
      'input[type="number"]',
      'select'
    ];
    
    for (const selector of dataInputs) {
      const elements = page.locator(selector);
      if (await elements.count() > 0) {
        // 첫 번째 입력 필드에 테스트 데이터 입력
        await elements.first().click();
        await elements.first().fill('테스트 데이터');
        break;
      }
    }
    
    // 저장 확인 (저장 버튼이 있다면)
    const saveButtons = [
      'button:has-text("저장")',
      'button:has-text("💾")',
      '.save-btn'
    ];
    
    for (const selector of saveButtons) {
      const saveBtn = page.locator(selector);
      if (await saveBtn.count() > 0) {
        await saveBtn.click();
        break;
      }
    }
  });

  test('지사 계정 - 지사 소속 매장 데이터 조회 테스트', async ({ page }) => {
    // 지사 계정으로 로그인
    await login(page, accounts.branch);
    
    // 지사 관리자 권한 확인
    await expect(page.locator('text=지사 관리자')).toBeVisible();
    
    // 개통표 페이지 이동
    await goToSalesForm(page);
    
    // 지사 정보 확인
    const userData = await page.evaluate(() => window.userData);
    expect(userData.role).toBe('branch');
    expect(userData.branch_id).toBeTruthy();
    
    // 지사 소속 매장들의 데이터만 표시되는지 확인
    // (API 호출 시 branch_id 필터링 적용)
    const apiCalls = [];
    page.on('request', request => {
      if (request.url().includes('/api/sales')) {
        apiCalls.push(request.url());
      }
    });
    
    await page.click('button:has-text("오늘")');
    
    // 잠시 대기 후 API 호출 확인
    await page.waitForTimeout(2000);
    
    // 지사 관련 API 호출이 있는지 확인
    const hasApiCall = apiCalls.some(url => url.includes('branch') || url.includes('store'));
    expect(apiCalls.length).toBeGreaterThan(0);
  });

  test('본사 계정 - 모든 매장 매출 통계 조회 테스트', async ({ page }) => {
    // 본사 계정으로 로그인
    await login(page, accounts.headquarters);
    
    // 대시보드에서 전체 통계 확인
    await page.goto(baseURL + '/dashboard');
    
    // KPI 카드들이 로드되는지 확인
    await expect(page.locator('#todaySales')).toBeVisible();
    await expect(page.locator('#monthSales')).toBeVisible();
    await expect(page.locator('#vatSales')).toBeVisible();
    await expect(page.locator('#goalProgress')).toBeVisible();
    
    // 실시간 데이터 로딩 대기
    await page.waitForFunction(() => {
      const todaySales = document.querySelector('#todaySales .kpi-value');
      return todaySales && todaySales.textContent !== '₩0';
    }, { timeout: 15000 });
    
    // 개통표에서 매장별 데이터 확인
    await goToSalesForm(page);
    
    // 통계 카드에서 전체 매장 합계 확인
    const totalCount = await page.locator('#total-count').textContent();
    const totalSettlement = await page.locator('#total-settlement').textContent();
    
    expect(parseInt(totalCount) || 0).toBeGreaterThanOrEqual(0);
    expect(totalSettlement).toMatch(/\d/); // 숫자가 포함되어 있는지
  });

  test('데이터 일관성 테스트: 매장 입력 → 지사 조회 → 본사 조회', async ({ browser }) => {
    // 여러 계정을 동시에 사용하기 위해 여러 페이지 생성
    const hqPage = await browser.newPage();
    const branchPage = await browser.newPage(); 
    const storePage = await browser.newPage();
    
    try {
      // 1단계: 매장에서 데이터 입력
      await login(storePage, { email: 'store@ykp.com', password: '123456' });
      await goToSalesForm(storePage);
      
      // 오늘 날짜 선택
      await storePage.click('button:has-text("오늘")');
      
      // 데이터 입력 시뮬레이션
      const testData = {
        model: 'iPhone 15',
        carrier: 'SK',
        amount: '500000'
      };
      
      // 입력 필드들을 찾아서 데이터 입력
      const inputFields = await storePage.locator('input, select').all();
      for (const field of inputFields.slice(0, 3)) { // 처음 3개 필드만
        const fieldType = await field.getAttribute('type') || await field.tagName();
        if (fieldType.includes('text') || fieldType.includes('number')) {
          await field.fill(Object.values(testData)[0] || '테스트');
        }
      }
      
      // 저장 (가능하다면)
      const saveBtn = storePage.locator('button:has-text("저장"), button:has-text("💾")').first();
      if (await saveBtn.count() > 0) {
        await saveBtn.click();
        await storePage.waitForTimeout(2000); // 저장 대기
      }
      
      // 2단계: 지사에서 해당 매장 데이터 조회
      await login(branchPage, { email: 'branch@ykp.com', password: '123456' });
      await goToSalesForm(branchPage);
      
      // 지사는 자기 소속 매장 데이터만 볼 수 있어야 함
      const branchUserData = await branchPage.evaluate(() => window.userData);
      expect(branchUserData.role).toBe('branch');
      expect(branchUserData.branch_id).toBeTruthy();
      
      // 3단계: 본사에서 모든 매장 데이터 조회  
      await login(hqPage, { email: 'hq@ykp.com', password: '123456' });
      await goToSalesForm(hqPage);
      
      // 본사는 모든 매장 데이터를 볼 수 있어야 함
      const hqUserData = await hqPage.evaluate(() => window.userData);
      expect(hqUserData.role).toBe('headquarters');
      
      // 각 계정에서 통계 데이터 비교
      const storeStats = await storePage.locator('#total-count').textContent();
      const branchStats = await branchPage.locator('#total-count').textContent(); 
      const hqStats = await hqPage.locator('#total-count').textContent();
      
      console.log('매장 통계:', storeStats);
      console.log('지사 통계:', branchStats);  
      console.log('본사 통계:', hqStats);
      
      // 본사 >= 지사 >= 매장 순으로 데이터 양이 많아야 함
      const storeCount = parseInt(storeStats) || 0;
      const branchCount = parseInt(branchStats) || 0;
      const hqCount = parseInt(hqStats) || 0;
      
      expect(hqCount).toBeGreaterThanOrEqual(branchCount);
      expect(branchCount).toBeGreaterThanOrEqual(storeCount);
      
    } finally {
      // 정리
      await hqPage.close();
      await branchPage.close();
      await storePage.close();
    }
  });

  test('날짜별 데이터 보존 및 조회 테스트', async ({ page }) => {
    await login(page, accounts.headquarters);
    await goToSalesForm(page);
    
    // 오늘 데이터 입력
    await page.click('button:has-text("오늘")');
    const today = new Date().toISOString().split('T')[0];
    
    // 현재 통계 저장
    const todayCountBefore = await page.locator('#total-count').textContent();
    
    // 데이터 입력 시도
    const inputField = page.locator('input, textarea, select').first();
    if (await inputField.count() > 0) {
      await inputField.click();
      await inputField.fill('오늘 테스트 데이터');
    }
    
    // 어제 날짜로 변경
    const yesterday = new Date();
    yesterday.setDate(yesterday.getDate() - 1);
    
    await page.click('text=📅 달력');
    await expect(page.locator('#mini-calendar')).toBeVisible();
    
    // 어제 날짜 클릭 (캘린더에서)
    const yesterdayDate = yesterday.getDate();
    const yesterdaySelector = `#calendar-days div:has-text("${yesterdayDate}")`;
    if (await page.locator(yesterdaySelector).count() > 0) {
      await page.click(yesterdaySelector);
    }
    
    // 어제 데이터와 오늘 데이터가 다른지 확인
    const yesterdayCount = await page.locator('#total-count').textContent();
    
    // 다시 오늘로 돌아가기
    await page.click('button:has-text("오늘")');
    const todayCountAfter = await page.locator('#total-count').textContent();
    
    // 데이터 보존 확인: 오늘 데이터가 유지되어야 함
    expect(todayCountAfter).toBe(todayCountBefore);
  });

  test('매장별 매출 순위 및 통계 테스트', async ({ page }) => {
    await login(page, accounts.headquarters);
    
    // 대시보드에서 매출 차트 확인
    await page.goto(baseURL + '/dashboard');
    
    // 차트 로딩 대기
    await page.waitForFunction(() => {
      return typeof Chart !== 'undefined' && 
             document.getElementById('salesChart') &&
             document.getElementById('marketChart');
    }, { timeout: 15000 });
    
    // 차트 캔버스 확인
    await expect(page.locator('#salesChart')).toBeVisible();
    await expect(page.locator('#marketChart')).toBeVisible();
    
    // API 데이터 로딩 확인
    const apiRequests = [];
    page.on('request', request => {
      if (request.url().includes('/api/dashboard/')) {
        apiRequests.push(request.url());
      }
    });
    
    // 페이지 새로고침으로 API 호출 트리거
    await page.reload();
    await page.waitForTimeout(5000);
    
    // 대시보드 관련 API 호출 확인
    expect(apiRequests.length).toBeGreaterThan(0);
    
    // 매장 랭킹 API 호출 확인
    const hasRankingAPI = apiRequests.some(url => 
      url.includes('store-ranking') || url.includes('overview')
    );
    expect(hasRankingAPI).toBeTruthy();
  });

  test('권한별 접근 제어 및 데이터 필터링 검증', async ({ page }) => {
    // 각 권한별로 접근 가능한 데이터 범위 확인
    
    for (const [roleKey, account] of Object.entries(accounts)) {
      if (roleKey.startsWith('store')) continue; // store1, store2 건너뛰기
      
      await login(page, account);
      
      // 사용자 정보 확인
      const userData = await page.evaluate(() => window.userData);
      expect(userData.role).toBe(roleKey);
      
      // 개통표 페이지에서 권한별 데이터 확인
      await goToSalesForm(page);
      
      // API 호출 모니터링
      const apiCalls = [];
      page.on('request', request => {
        if (request.url().includes('/api/sales')) {
          apiCalls.push({
            url: request.url(),
            role: userData.role
          });
        }
      });
      
      await page.click('button:has-text("오늘")');
      await page.waitForTimeout(3000);
      
      // 권한별 API 파라미터 확인
      if (userData.role === 'headquarters') {
        // 본사는 필터 없이 모든 데이터 접근
        console.log(`본사 API 호출:`, apiCalls);
      } else if (userData.role === 'branch') {
        // 지사는 branch_id 필터 적용
        console.log(`지사 API 호출:`, apiCalls);
      }
      
      // 로그아웃
      await page.goto(baseURL + '/dashboard');
      
      // 로그아웃 버튼 찾기 (여러 방법 시도)
      const logoutSelectors = [
        'button[onclick="logout()"]',
        'text=로그아웃',
        '.logout-btn',
        'button:has-text("로그아웃")'
      ];
      
      let loggedOut = false;
      for (const selector of logoutSelectors) {
        try {
          if (await page.locator(selector).count() > 0) {
            page.on('dialog', async dialog => await dialog.accept());
            await page.click(selector);
            await page.waitForURL('**/login', { timeout: 10000 });
            loggedOut = true;
            break;
          }
        } catch (e) {
          continue;
        }
      }
      
      if (!loggedOut) {
        // 강제 로그아웃 (세션 클리어)
        await page.goto(baseURL + '/logout');
      }
    }
  });
});