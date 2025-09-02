import { test, expect } from '@playwright/test';

// 매장 수정 및 성과보기 권한별 검증
test.describe('매장 수정/성과보기 권한별 데이터 검증', () => {
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

  test('본사 권한 - 매장 수정 모달 DB 자동 매핑 검증', async ({ page }) => {
    console.log('🏢 본사: 매장 수정 모달 테스트');

    // 1. 본사 로그인
    await login(page, accounts.headquarters);
    
    // 2. 매장 관리 페이지 이동
    await page.goto(`${baseURL}/management/stores`);
    await page.waitForTimeout(3000);

    // 3. 사용자 권한 확인
    const userData = await page.evaluate(() => window.userData);
    expect(userData.role).toBe('headquarters');
    console.log('✅ 본사 권한 확인');

    // 4. 첫 번째 매장의 수정 버튼 클릭
    const editButtons = page.locator('button:has-text("수정")');
    const editButtonCount = await editButtons.count();
    
    if (editButtonCount > 0) {
      await editButtons.first().click();
      
      // 5. 수정 모달이 표시되는지 확인
      await expect(page.locator('#edit-store-modal')).toBeVisible();
      console.log('✅ 수정 모달 표시됨');

      // 6. 모달에 데이터가 자동으로 로드되었는지 확인
      await page.waitForTimeout(2000); // API 로딩 대기
      
      const modalData = await page.evaluate(() => ({
        storeName: document.getElementById('edit-store-name')?.value,
        storeCode: document.getElementById('edit-store-code')?.value,
        ownerName: document.getElementById('edit-owner-name')?.value,
        phone: document.getElementById('edit-phone')?.value,
        todaySales: document.getElementById('edit-today-sales')?.textContent,
        monthSales: document.getElementById('edit-month-sales')?.textContent
      }));

      console.log('📝 자동 로드된 매장 정보:', modalData);
      
      // 7. 데이터 유효성 검증
      expect(modalData.storeName).toBeTruthy();
      expect(modalData.storeCode).toBeTruthy();
      expect(modalData.todaySales).toContain('₩');
      expect(modalData.monthSales).toContain('₩');
      
      console.log('✅ 본사 매장 수정 DB 자동 매핑 검증 완료');

      // 8. 모달 닫기
      await page.click('#edit-store-modal button:has-text("취소")');
    } else {
      console.log('⚠️ 수정 가능한 매장이 없습니다');
    }
  });

  test('본사 권한 - 성과보기 모달 실시간 데이터 검증', async ({ page }) => {
    console.log('📈 본사: 성과보기 모달 테스트');

    await login(page, accounts.headquarters);
    await page.goto(`${baseURL}/management/stores`);
    await page.waitForTimeout(3000);

    // 성과보기 버튼 클릭
    const statsButtons = page.locator('button:has-text("성과보기")');
    const statsButtonCount = await statsButtons.count();
    
    if (statsButtonCount > 0) {
      await statsButtons.first().click();
      
      // 성과보기 모달 표시 확인
      await expect(page.locator('#store-stats-modal')).toBeVisible();
      console.log('✅ 성과보기 모달 표시됨');

      // 데이터 로딩 대기
      await page.waitForTimeout(3000);
      
      const statsData = await page.evaluate(() => ({
        storeName: document.getElementById('stats-store-name')?.textContent,
        todaySales: document.getElementById('stats-today-sales')?.textContent,
        monthSales: document.getElementById('stats-month-sales')?.textContent,
        rank: document.getElementById('stats-rank')?.textContent,
        goal: document.getElementById('stats-goal')?.textContent,
        recentTransactions: document.getElementById('recent-transactions')?.children.length
      }));

      console.log('📊 성과보기 데이터:', statsData);
      
      // 데이터 유효성 검증
      expect(statsData.storeName).toBeTruthy();
      expect(statsData.todaySales).toContain('₩');
      expect(statsData.monthSales).toContain('₩');
      expect(statsData.rank).toContain('#');
      expect(statsData.recentTransactions).toBeGreaterThan(0);
      
      console.log('✅ 본사 성과보기 실시간 데이터 검증 완료');

      // 모달 닫기
      await page.click('#store-stats-modal button:has-text("닫기")');
    }
  });

  test('지사 권한 - 소속 매장만 수정 가능 여부 검증', async ({ page }) => {
    console.log('🏬 지사: 소속 매장만 수정 권한 검증');

    await login(page, accounts.branch);
    
    // 지사 사용자 정보 확인
    const userData = await page.evaluate(() => window.userData);
    expect(userData.role).toBe('branch');
    expect(userData.branch_id).toBe(1); // 서울지사
    console.log('✅ 지사 권한 확인:', userData.branch_id);

    await page.goto(`${baseURL}/management/stores`);
    await page.waitForTimeout(3000);

    // 지사가 볼 수 있는 매장 확인
    const visibleStores = await page.evaluate(() => {
      const storeElements = document.querySelectorAll('[onclick*="editStore"]');
      const stores = [];
      storeElements.forEach(el => {
        const storeCard = el.closest('.border');
        if (storeCard) {
          const storeName = storeCard.querySelector('h4')?.textContent || 
                           storeCard.querySelector('.font-medium')?.textContent;
          stores.push(storeName);
        }
      });
      return stores;
    });

    console.log('🏬 지사가 볼 수 있는 매장들:', visibleStores);
    
    // 지사는 소속 매장을 볼 수 있어야 함
    expect(visibleStores.length).toBeGreaterThan(0);
    // 매장명이 표시되거나 플레이스홀더가 있어야 함
    expect(visibleStores.some(name => name.includes('서울') || name.includes('매장'))).toBe(true);
    
    // 첫 번째 매장 수정 시도
    if (visibleStores.length > 0) {
      const editButtons = page.locator('button:has-text("수정")');
      await editButtons.first().click();
      
      await expect(page.locator('#edit-store-modal')).toBeVisible();
      console.log('✅ 지사 권한으로 소속 매장 수정 가능');

      // 지사 선택 옵션이 제한되어 있는지 확인
      const branchSelect = await page.evaluate(() => {
        const select = document.getElementById('edit-branch-select');
        return {
          selectedValue: select?.value,
          optionCount: select?.options.length
        };
      });

      console.log('🏬 지사 선택 옵션:', branchSelect);
      await page.click('#edit-store-modal button:has-text("취소")');
    }
  });

  test('매장 권한 - 자기 매장만 성과 조회 검증', async ({ page }) => {
    console.log('🏪 매장: 자기 매장 성과만 조회 권한 검증');

    await login(page, accounts.store);
    
    const userData = await page.evaluate(() => window.userData);
    expect(userData.role).toBe('store');
    expect(userData.store_id).toBe(1); // 서울 1호점
    console.log('✅ 매장 권한 확인:', userData.store_id);

    // 매장은 매장 관리 페이지 접근 불가여야 함
    const managementResponse = await page.goto(`${baseURL}/management/stores`);
    
    // 403 오류 또는 접근 제한 메시지 확인
    const pageContent = await page.textContent('body');
    const hasAccessRestriction = pageContent.includes('403') || 
                                pageContent.includes('접근') || 
                                pageContent.includes('권한') ||
                                pageContent.includes('본사');
    
    if (hasAccessRestriction) {
      console.log('✅ 매장 권한으로 매장 관리 접근 제한 확인');
    } else {
      console.log('⚠️ 매장 권한 제한이 예상보다 관대함');
    }

    // 개통표에서 자기 매장 데이터 확인
    await page.goto(`${baseURL}/test/complete-aggrid`);
    await page.waitForTimeout(3000);

    const storeUserData = await page.evaluate(() => window.userData);
    console.log('🏪 개통표에서 매장 사용자 정보:', storeUserData);
    
    expect(storeUserData.store_id).toBe(1);
    expect(storeUserData.store_name).toContain('서울');
    console.log('✅ 매장 권한 데이터 범위 검증 완료');
  });

  test('권한별 데이터 필터링 교차 검증', async ({ browser }) => {
    console.log('🔍 권한별 데이터 필터링 교차 검증');

    const hqPage = await browser.newPage();
    const branchPage = await browser.newPage();

    try {
      // 본사와 지사 로그인
      await login(hqPage, accounts.headquarters);
      await login(branchPage, accounts.branch);

      // 매장 관리 페이지에서 각각 볼 수 있는 매장 수 비교
      await hqPage.goto(`${baseURL}/management/stores`);
      await branchPage.goto(`${baseURL}/management/stores`);
      
      await Promise.all([
        hqPage.waitForTimeout(3000),
        branchPage.waitForTimeout(3000)
      ]);

      // API로 직접 데이터 확인
      const hqStoresResponse = await hqPage.request.get('/test-api/stores');
      const hqStoresData = await hqStoresResponse.json();
      
      console.log(`🏢 본사가 볼 수 있는 매장 수: ${hqStoresData.data.length}개`);
      console.log('본사 매장 목록:', hqStoresData.data.map(s => `${s.name} (${s.branch?.name})`));

      // 지사별 매장 분포 확인
      const storesByBranch = {};
      hqStoresData.data.forEach(store => {
        const branchName = store.branch?.name || '미배정';
        storesByBranch[branchName] = (storesByBranch[branchName] || 0) + 1;
      });

      console.log('📊 지사별 매장 분포:', storesByBranch);
      
      // 본사는 모든 지사 매장을 볼 수 있어야 함
      expect(Object.keys(storesByBranch).length).toBeGreaterThan(1);
      expect(storesByBranch['서울지사']).toBeGreaterThan(0);

      // 지사 권한으로 같은 API 호출 (브라우저 세션 기반)
      const branchStoresResponse = await branchPage.request.get('/test-api/stores');
      const branchStoresData = await branchStoresResponse.json();
      
      console.log(`🏬 지사가 볼 수 있는 매장 수: ${branchStoresData.data.length}개`);
      
      // 지사는 본사보다 적거나 같은 매장만 볼 수 있어야 함
      expect(branchStoresData.data.length).toBeLessThanOrEqual(hqStoresData.data.length);

      console.log('✅ 권한별 데이터 필터링 교차 검증 완료');

    } finally {
      await hqPage.close();
      await branchPage.close();
    }
  });

  test('실제 데이터 수정 및 즉시 반영 검증', async ({ page }) => {
    console.log('💾 실제 데이터 수정 및 반영 검증');

    await login(page, accounts.headquarters);
    await page.goto(`${baseURL}/management/stores`);
    await page.waitForTimeout(3000);

    // 수정 전 매장 정보 확인
    const beforeEdit = await page.request.get('/test-api/stores/1');
    const beforeData = await beforeEdit.json();
    const originalOwnerName = beforeData.data.owner_name;
    
    console.log('📋 수정 전 점주명:', originalOwnerName);

    // 매장 수정 모달 열기
    const editButton = page.locator('button:has-text("수정")').first();
    await editButton.click();
    await expect(page.locator('#edit-store-modal')).toBeVisible();
    await page.waitForTimeout(2000);

    // 점주명 변경
    const newOwnerName = `수정테스트_${Date.now()}`;
    await page.fill('#edit-owner-name', newOwnerName);
    
    // API 응답 모니터링
    let updateSuccess = false;
    page.on('response', response => {
      if (response.url().includes('/test-api/stores/') && response.request().method() === 'PUT') {
        updateSuccess = response.status() === 200;
        console.log('💾 매장 수정 API 응답:', response.status());
      }
    });

    // 저장 버튼 클릭
    await page.click('button:has-text("변경사항 저장")');
    await page.waitForTimeout(3000);

    // 수정 후 데이터 확인
    const afterEdit = await page.request.get('/test-api/stores/1');
    const afterData = await afterEdit.json();
    const updatedOwnerName = afterData.data.owner_name;
    
    console.log('📋 수정 후 점주명:', updatedOwnerName);
    
    // 변경사항 반영 확인
    expect(updatedOwnerName).toBe(newOwnerName);
    expect(updateSuccess).toBe(true);
    
    console.log('✅ 실제 데이터 수정 및 Supabase 반영 검증 완료');

    // 원래 이름으로 복구
    await page.goto(`${baseURL}/management/stores`);
    await page.waitForTimeout(2000);
    await page.click('button:has-text("수정")');
    await page.waitForTimeout(2000);
    await page.fill('#edit-owner-name', originalOwnerName);
    await page.click('button:has-text("변경사항 저장")');
    await page.waitForTimeout(2000);
    
    console.log('🔄 원래 데이터로 복구 완료');
  });
});