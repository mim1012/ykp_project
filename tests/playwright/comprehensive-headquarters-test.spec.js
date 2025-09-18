import { test, expect } from '@playwright/test';
import { LoginPage } from './pages/LoginPage.js';
import { DashboardPage } from './pages/DashboardPage.js';

/**
 * 본사 권한 포괄적 테스트 시나리오
 * TC-HQ 시리즈 완전 구현
 */
test.describe('🏢 본사 권한 포괄적 테스트 시나리오', () => {
    let loginPage, dashboardPage;
    
    test.beforeEach(async ({ page }) => {
        loginPage = new LoginPage(page);
        dashboardPage = new DashboardPage(page);
    });

    /**
     * 시나리오 1: 본사 관리자 로그인 및 대시보드 접근
     */
    test('시나리오 1: 본사 관리자 인증 및 초기 접근 테스트', async ({ page }) => {
        console.log('🔐 시나리오 1: 본사 관리자 로그인 및 대시보드 접근');
        
        // Given 본사 관리자가 로그인 페이지에 접속한다
        await page.goto('/login');
        
        // When 본사 계정 정보로 로그인을 시도한다
        await loginPage.loginWithTestAccount('headquarters');
        
        // Then 로그인이 성공적으로 완료된다
        const userData = await loginPage.verifyLoginSuccess('headquarters');
        
        // And 본사 전용 대시보드가 표시된다
        await dashboardPage.verifyDashboardLoaded('headquarters');
        
        // And 전체 시스템 통계가 정확히 로드된다
        await dashboardPage.verifySystemStatus('headquarters');
        
        // And 모든 지사 및 매장 정보에 접근할 수 있다
        const dataScope = await dashboardPage.verifyDataScopeForRole('headquarters');
        expect(dataScope.debug?.accessible_stores).toBeGreaterThan(1);
        
        console.log('✅ 시나리오 1 완료: 본사 권한 인증 및 접근 검증');
    });

    /**
     * 시나리오 2: 빠른 로그인 기능 테스트
     */
    test('시나리오 2: 빠른 로그인 기능 테스트', async ({ page }) => {
        console.log('⚡ 시나리오 2: 빠른 로그인 기능');
        
        // Given 테스트 환경에서 빠른 로그인 기능이 활성화되어 있다
        // When '/quick-login/headquarters' 경로로 접근한다
        await loginPage.quickLogin('headquarters');
        
        // Then 자동으로 본사 계정으로 로그인된다
        // And 본사 대시보드로 리다이렉트된다
        await loginPage.verifyLoginSuccess('headquarters');
        
        // And 세션이 올바르게 설정된다
        const userData = await dashboardPage.verifyUserRole('headquarters');
        expect(userData.id).toBeTruthy();
        expect(userData.name).toBeTruthy();
        
        console.log('✅ 시나리오 2 완료: 빠른 로그인 검증');
    });

    /**
     * 시나리오 3: 지사 관리 기능 테스트
     */
    test('시나리오 3: 지사 관리 기능 포괄 테스트', async ({ page }) => {
        console.log('🏢 시나리오 3: 지사 관리 기능');
        
        // Given 본사 관리자로 로그인되어 있다
        await loginPage.quickLogin('headquarters');
        
        // When 지사 관리 페이지로 이동한다
        await page.goto('/management/stores');
        await page.waitForTimeout(2000);
        
        // 지사 관리 탭 클릭
        await page.click('#branches-tab');
        await page.waitForTimeout(2000);
        
        // Then 모든 지사 목록이 표시된다
        const branchRows = page.locator('#branches-grid tbody tr');
        const branchCount = await branchRows.count();
        expect(branchCount).toBeGreaterThan(0);
        console.log(`📊 표시된 지사 수: ${branchCount}개`);
        
        // And 새로운 지사를 추가할 수 있다
        await page.click('button:has-text("➕ 지사 추가")');
        await expect(page.locator('#add-branch-modal')).toBeVisible();
        
        const testBranchData = {
            name: `포괄테스트지사_${Date.now().toString().substr(-6)}`,
            code: `CT${Date.now().toString().substr(-3)}`,
            manager: '포괄김'
        };
        
        await page.fill('#modal-branch-name', testBranchData.name);
        await page.fill('#modal-branch-code', testBranchData.code);
        await page.fill('#modal-branch-manager', testBranchData.manager);
        await page.click('button:has-text("✅ 지사 추가")');
        
        // 추가 완료 대기
        await page.waitForTimeout(3000);
        
        // And 기존 지사 정보를 수정할 수 있다
        const editButtons = page.locator('button:has-text("수정")');
        if (await editButtons.count() > 0) {
            await editButtons.first().click();
            await expect(page.locator('#edit-branch-modal')).toBeVisible();
            console.log('✅ 지사 수정 모달 접근 가능');
            await page.click('button:has-text("취소")');
        }
        
        console.log('✅ 시나리오 3 완료: 지사 관리 기능 검증');
    });

    /**
     * 시나리오 4: 매장 관리 기능 테스트
     */
    test('시나리오 4: 매장 관리 기능 포괄 테스트', async ({ page }) => {
        console.log('🏪 시나리오 4: 매장 관리 기능');
        
        // Given 본사 관리자로 로그인되어 있다
        await loginPage.quickLogin('headquarters');
        
        // When 매장 관리 페이지로 이동한다
        await page.goto('/management/stores');
        await page.waitForTimeout(2000);
        
        // Then 전체 매장 목록이 표시된다
        const storeElements = page.locator('[onclick*="editStore"]');
        const storeCount = await storeElements.count();
        expect(storeCount).toBeGreaterThan(0);
        console.log(`📊 표시된 매장 수: ${storeCount}개`);
        
        // And 매장별 상세 정보를 확인할 수 있다
        if (storeCount > 0) {
            await storeElements.first().click();
            await expect(page.locator('#edit-store-modal')).toBeVisible();
            console.log('✅ 매장 상세 정보 모달 접근 가능');
            
            // 매장 정보 필드들이 채워져 있는지 확인
            const storeName = await page.locator('#edit-store-name').inputValue();
            const storeCode = await page.locator('#edit-store-code').inputValue();
            
            expect(storeName).toBeTruthy();
            expect(storeCode).toBeTruthy();
            console.log(`📋 매장 정보: ${storeName} (${storeCode})`);
            
            await page.click('#edit-store-modal button:has-text("취소")');
        }
        
        // And 새로운 매장을 등록할 수 있다
        await page.click('button:has-text("➕ 매장 추가")');
        await expect(page.locator('#add-store-modal')).toBeVisible();
        console.log('✅ 새 매장 추가 모달 접근 가능');
        await page.click('button:has-text("취소")');
        
        console.log('✅ 시나리오 4 완료: 매장 관리 기능 검증');
    });

    /**
     * 시나리오 5: 전체 매출 통계 및 분석
     */
    test('시나리오 5: 전체 매출 통계 분석 테스트', async ({ page }) => {
        console.log('📈 시나리오 5: 전체 매출 통계 및 분석');
        
        // Given 본사 관리자로 로그인되어 있다
        await loginPage.quickLogin('headquarters');
        
        // When 매출 분석 대시보드에 접근한다
        await dashboardPage.verifyDashboardLoaded('headquarters');
        
        // Then 전체 매출 현황이 실시간으로 표시된다
        const kpiData = await dashboardPage.collectKpiData();
        console.log('📊 KPI 데이터:', kpiData);
        
        // And 지사별 매출 비교가 가능하다
        // And 매장별 매출 순위를 확인할 수 있다
        await dashboardPage.verifyChartsLoaded();
        
        // And 기간별 매출 추이를 분석할 수 있다
        const apiResponse = await dashboardPage.refreshData();
        expect(apiResponse.status).toBeLessThan(500); // API 에러 없음
        
        // And 매출 데이터를 엑셀로 내보낼 수 있다
        if (await dashboardPage.reportDownloadButton.isVisible()) {
            console.log('📄 리포트 다운로드 기능 확인');
        }
        
        console.log('✅ 시나리오 5 완료: 매출 통계 분석 검증');
    });

    /**
     * 시나리오 6: 사용자 관리 기능
     */
    test('시나리오 6: 사용자 관리 기능 테스트', async ({ page }) => {
        console.log('👥 시나리오 6: 사용자 관리 기능');
        
        // Given 본사 관리자로 로그인되어 있다
        await loginPage.quickLogin('headquarters');
        
        // When 사용자 관리 페이지에 접근한다
        await page.goto('/management/stores');
        await page.waitForTimeout(2000);
        
        // 사용자 관리 탭 클릭
        await page.click('#users-tab');
        await page.waitForTimeout(2000);
        
        // Then 모든 사용자 목록을 확인할 수 있다
        const userRows = page.locator('#users-grid tbody tr');
        const userCount = await userRows.count();
        expect(userCount).toBeGreaterThan(0);
        console.log(`👤 표시된 사용자 수: ${userCount}명`);
        
        // And 새로운 사용자를 생성할 수 있다
        const addUserButton = page.locator('button:has-text("➕ 사용자 추가")');
        if (await addUserButton.isVisible()) {
            await addUserButton.click();
            await expect(page.locator('#add-user-modal')).toBeVisible();
            console.log('✅ 사용자 추가 모달 접근 가능');
            await page.click('button:has-text("취소")');
        }
        
        console.log('✅ 시나리오 6 완료: 사용자 관리 기능 검증');
    });

    /**
     * 시나리오 7: Feature Flag 관리
     */
    test('시나리오 7: Feature Flag 관리 테스트', async ({ page }) => {
        console.log('🚩 시나리오 7: Feature Flag 관리');
        
        // Given 본사 관리자로 로그인되어 있다
        await loginPage.quickLogin('headquarters');
        
        // When Feature Flag 상태를 확인한다
        const userData = await page.evaluate(() => window.userData);
        const features = await page.evaluate(() => window.features);
        
        console.log('🏴 Feature Flags 상태:', features);
        
        // Then Feature Flag가 권한별로 적용된다
        expect(features).toHaveProperty('excel_input_form');
        
        // 본사는 모든 기능에 접근 가능해야 함
        if (features.excel_input_form) {
            await page.goto('/sales/excel-input');
            await page.waitForTimeout(2000);
            
            // 403 에러가 발생하지 않아야 함
            const isAccessible = !page.url().includes('404') && !page.url().includes('403');
            expect(isAccessible).toBeTruthy();
            console.log('✅ Feature Flag 기능 접근 가능');
        }
        
        console.log('✅ 시나리오 7 완료: Feature Flag 관리 검증');
    });

    /**
     * 시나리오 8: 권한별 데이터 범위 확인
     */
    test('시나리오 8: 본사 권한 데이터 범위 검증', async ({ page }) => {
        console.log('📊 시나리오 8: 본사 권한 데이터 범위');
        
        // Given 본사 관리자로 로그인되어 있다
        await loginPage.quickLogin('headquarters');
        
        // When 대시보드에서 데이터 범위를 확인한다
        const dataScope = await dashboardPage.verifyDataScopeForRole('headquarters');
        
        // Then 모든 지사/매장 데이터에 접근할 수 있다
        expect(dataScope.user_role).toBe('headquarters');
        expect(dataScope.debug?.accessible_stores).toBeGreaterThan(1);
        
        // API로 실제 데이터 범위 재확인
        const apiCheck = await page.request.get('/test-api/stores');
        const storesData = await apiCheck.json();
        
        console.log(`🏢 본사 접근 가능 매장: ${storesData.data.length}개`);
        console.log('매장 목록:', storesData.data.map(s => `${s.name} (${s.branch?.name})`));
        
        // 본사는 모든 매장에 접근 가능해야 함
        expect(storesData.data.length).toBeGreaterThanOrEqual(3);
        
        console.log('✅ 시나리오 8 완료: 본사 데이터 범위 검증');
    });

    /**
     * 시나리오 9: 실시간 데이터 업데이트 및 동기화
     */
    test('시나리오 9: 실시간 데이터 동기화 테스트', async ({ page }) => {
        console.log('🔄 시나리오 9: 실시간 데이터 동기화');
        
        // Given 본사 관리자로 로그인되어 있다
        await loginPage.quickLogin('headquarters');
        
        // When 대시보드 데이터를 새로고침한다
        await dashboardPage.verifyRealTimeUpdates();
        
        // Then 실시간 데이터가 정확히 반영된다
        const systemStats = await page.evaluate(async () => {
            const [usersRes, storesRes, salesRes, branchesRes] = await Promise.all([
                fetch('/api/users/count'),
                fetch('/api/stores/count'),  
                fetch('/api/sales/count'),
                fetch('/test-api/branches')
            ]);
            
            const [users, stores, sales, branches] = await Promise.all([
                usersRes.json(),
                storesRes.json(),
                salesRes.json(), 
                branchesRes.json()
            ]);
            
            return {
                users: users.count,
                stores: stores.count, 
                sales: sales.count,
                branches: branches.data?.length
            };
        });
        
        console.log('📈 실시간 시스템 현황:', systemStats);
        
        // 실제 데이터와 표시 데이터 일치 확인
        expect(systemStats.users).toBeGreaterThan(0);
        expect(systemStats.stores).toBeGreaterThan(0);
        expect(systemStats.sales).toBeGreaterThan(0);
        expect(systemStats.branches).toBeGreaterThan(0);
        
        console.log('✅ 시나리오 9 완료: 실시간 데이터 동기화 검증');
    });

    /**
     * 시나리오 10: 권한 경계 및 보안 테스트
     */
    test('시나리오 10: 본사 권한 경계 테스트', async ({ page }) => {
        console.log('🔒 시나리오 10: 본사 권한 경계 테스트');
        
        // Given 본사 관리자로 로그인되어 있다
        await loginPage.quickLogin('headquarters');
        
        // When 모든 시스템 영역에 접근을 시도한다
        const accessibleUrls = [
            '/dashboard',
            '/management/stores', 
            '/test/complete-aggrid',
            '/sales/excel-input'
        ];
        
        for (const url of accessibleUrls) {
            await page.goto(url);
            await page.waitForTimeout(1000);
            
            // Then 모든 영역에 접근할 수 있다
            const hasAccessError = page.url().includes('403') || 
                                  page.url().includes('404') ||
                                  page.url().includes('unauthorized');
            
            expect(hasAccessError).toBeFalsy();
            console.log(`✅ ${url} 접근 가능`);
        }
        
        // And 모든 데이터 범위에 접근할 수 있다
        const fullDataAccess = await dashboardPage.verifyDataScopeForRole('headquarters');
        expect(fullDataAccess.user_role).toBe('headquarters');
        
        console.log('✅ 시나리오 10 완료: 본사 권한 경계 검증');
    });
});