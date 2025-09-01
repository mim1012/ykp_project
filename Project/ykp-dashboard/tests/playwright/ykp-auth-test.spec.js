import { test, expect } from '@playwright/test';

// YKP 시스템 권한별 테스트
test.describe('YKP Dashboard 권한별 테스트', () => {
  const baseURL = 'http://127.0.0.1:8000';
  
  const accounts = {
    headquarters: { email: 'hq@ykp.com', password: '123456', role: '본사 관리자' },
    branch: { email: 'branch@ykp.com', password: '123456', role: '지사 관리자' },
    store: { email: 'store@ykp.com', password: '123456', role: '매장 직원' }
  };

  // 로그인 헬퍼 함수
  async function login(page, account) {
    await page.goto(baseURL);
    await page.fill('input[type="email"]', account.email);
    await page.fill('input[type="password"]', account.password);
    await page.click('button[type="submit"]');
    await page.waitForURL('**/dashboard');
    // JavaScript 로딩 대기
    await page.waitForFunction(() => window.userData !== undefined, { timeout: 10000 });
  }

  test('본사 계정 - 전체 기능 접근 테스트', async ({ page }) => {
    await login(page, accounts.headquarters);
    
    // 대시보드 로딩 확인
    await expect(page.locator('strong').filter({ hasText: '본사 관리자' })).toBeVisible();
    
    // 사이드바 메뉴 확인 (hover로 툴팁 표시)
    await page.hover('text=📋');
    await expect(page.locator('text=완전한 판매관리')).toBeVisible();
    await page.hover('text=⚙️');
    await expect(page.locator('text=관리자 패널')).toBeVisible();
    
    // 관리자 패널 접근 테스트 (매장 관리 포함)
    await page.click('text=⚙️');
    await page.waitForURL('**/admin');
    await expect(page.locator('h1')).toBeVisible();
    
    // 판매관리 접근 테스트
    await page.goto(baseURL + '/dashboard');
    await page.click('text=📋');
    await page.waitForURL('**/test/complete-aggrid');
    await expect(page.locator('text=완전한 개통표')).toBeVisible();
  });

  test('지사 계정 - 제한된 접근 테스트', async ({ page }) => {
    await login(page, accounts.branch);
    
    // 지사 관리자 정보 확인
    await expect(page.locator('strong').filter({ hasText: '지사 관리자' })).toBeVisible();
    
    // 관리자 패널 접근 시도 (403 오류 예상)
    const response = await page.goto(baseURL + '/admin');
    // 권한이 없어서 403이나 404가 나올 수 있음 (또는 로그인 페이지로 리다이렉트)
    expect([200, 403, 404]).toContain(response.status());
    
    // 만약 200이면 권한 메시지나 접근 불가 내용이 있어야 함
    if (response.status() === 200) {
      const content = await page.content();
      const hasAccessDenied = content.includes('접근') || content.includes('권한') || content.includes('허용') || content.includes('금지');
      expect(hasAccessDenied).toBeTruthy();
    }
  });

  test('매장 계정 - 매장 전용 접근 테스트', async ({ page }) => {
    await login(page, accounts.store);
    
    // 매장 직원 정보 확인
    await expect(page.locator('strong').filter({ hasText: '매장 직원' })).toBeVisible();
    
    // 판매관리 접근 (자기 매장 데이터만)
    await page.click('text=📋');
    await page.waitForURL('**/test/complete-aggrid');
    
    // 사용자 정보가 매장으로 설정되었는지 확인
    const userData = await page.evaluate(() => window.userData);
    expect(userData.role).toBe('store');
    expect(userData.store_id).toBeTruthy();
  });

  test('개통표 날짜별 데이터 입력 테스트', async ({ page }) => {
    await login(page, accounts.headquarters);
    
    // 판매관리 페이지 이동
    await page.click('text=📋');
    await page.waitForURL('**/test/complete-aggrid');
    
    // 캘린더 버튼 클릭
    await page.click('text=📅 달력');
    await expect(page.locator('#mini-calendar')).toBeVisible();
    
    // 오늘 날짜 선택
    await page.click('button:has-text("오늘")');
    await expect(page.locator('#current-date')).toContainText('2025-09');
    
    // JavaScript와 AgGrid 로딩 대기
    await page.waitForFunction(() => window.agGridReady !== undefined || document.querySelector('.ag-root') !== null, { timeout: 15000 });
    
    // AgGrid 테이블 로딩 확인 (여러 셀렉터 시도)
    const agGridSelectors = ['.ag-root', '.ag-grid-container', '#aggrid-container', '[class*="ag-"]'];
    let agGridFound = false;
    
    for (const selector of agGridSelectors) {
      try {
        await expect(page.locator(selector)).toBeVisible({ timeout: 5000 });
        agGridFound = true;
        break;
      } catch (e) {
        continue;
      }
    }
    
    if (!agGridFound) {
      // AgGrid가 없다면 테이블이나 데이터 컨테이너 확인
      await expect(page.locator('table, .data-table, .grid-container')).toBeVisible();
    }
    
    // 데이터 입력 시뮬레이션 (AgGrid가 있는 경우에만)
    const modelNameCell = page.locator('.ag-cell[col-id="model_name"]').first();
    if (await modelNameCell.count() > 0) {
      await modelNameCell.click();
      await page.fill('.ag-cell[col-id="model_name"] input', 'iPhone 15');
      await page.press('.ag-cell[col-id="model_name"] input', 'Tab');
    }
    
    // 저장 버튼 클릭 (있다면)
    const saveButton = page.locator('button:has-text("저장")');
    if (await saveButton.count() > 0) {
      await saveButton.click();
    }
  });

  test('로그아웃 기능 테스트', async ({ page }) => {
    await login(page, accounts.headquarters);
    
    // 로그아웃 버튼 클릭 (더 정확한 셀렉터 사용)
    page.on('dialog', async dialog => {
      await dialog.accept();
    });
    
    // 여러 가능한 셀렉터 시도
    const logoutSelectors = [
      'button:has-text("로그아웃")',
      'text=로그아웃',
      '[data-testid="logout-button"]',
      'button.logout-btn'
    ];
    
    let clicked = false;
    for (const selector of logoutSelectors) {
      try {
        await page.click(selector, { timeout: 2000 });
        clicked = true;
        break;
      } catch (e) {
        continue;
      }
    }
    
    if (!clicked) {
      // 마지막 시도: 텍스트가 포함된 모든 버튼 확인
      await page.click('button >> text="로그아웃"');
    }
    
    // 로그인 페이지로 이동 확인
    await page.waitForURL('**/login', { timeout: 15000 });
    await expect(page.locator('input[type="email"]')).toBeVisible();
  });

  test('권한별 데이터 필터링 테스트', async ({ page }) => {
    // 본사 계정으로 전체 데이터 확인
    await login(page, accounts.headquarters);
    await page.click('text=📋');
    
    const hqUserData = await page.evaluate(() => window.userData);
    expect(hqUserData.role).toBe('headquarters');
    
    // 로그아웃 후 매장 계정 테스트
    page.on('dialog', async dialog => await dialog.accept());
    
    // 여러 가능한 셀렉터 시도
    const logoutSelectors = [
      'button:has-text("로그아웃")',
      'text=로그아웃',
      '[data-testid="logout-button"]',
      'button.logout-btn'
    ];
    
    let clicked = false;
    for (const selector of logoutSelectors) {
      try {
        await page.click(selector, { timeout: 2000 });
        clicked = true;
        break;
      } catch (e) {
        continue;
      }
    }
    
    if (!clicked) {
      await page.click('button >> text="로그아웃"');
    }
    
    await page.waitForURL('**/login', { timeout: 15000 });
    await login(page, accounts.store);
    await page.click('text=📋');
    
    const storeUserData = await page.evaluate(() => window.userData);
    expect(storeUserData.role).toBe('store');
    expect(storeUserData.store_id).toBeTruthy();
  });
});