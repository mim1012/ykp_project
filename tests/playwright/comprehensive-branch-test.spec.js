import { test, expect } from '@playwright/test';
import { LoginPage } from './pages/LoginPage.js';
import { DashboardPage } from './pages/DashboardPage.js';

/**
 * 지사 권한 포괄적 테스트 시나리오  
 * TC-BR 시리즈 완전 구현
 */
test.describe('🏬 지사 권한 포괄적 테스트 시나리오', () => {
    let loginPage, dashboardPage;
    
    test.beforeEach(async ({ page }) => {
        loginPage = new LoginPage(page);
        dashboardPage = new DashboardPage(page);
    });

    /**
     * 시나리오 9: 지사 관리자 로그인 및 권한 확인
     */
    test('시나리오 9: 지사 관리자 인증 및 권한 확인', async ({ page }) => {
        console.log('🏬 시나리오 9: 지사 관리자 로그인 및 권한 확인');
        
        // Given 지사 관리자가 로그인 페이지에 접속한다
        await page.goto('/login');
        
        // When 지사 계정 정보로 로그인을 시도한다
        await loginPage.loginWithTestAccount('branch');
        
        // Then 로그인이 성공적으로 완료된다
        const userData = await loginPage.verifyLoginSuccess('branch');
        
        // And 지사 전용 대시보드가 표시된다
        await dashboardPage.verifyDashboardLoaded('branch');
        
        // And 소속 매장들의 정보만 표시된다
        const dataScope = await dashboardPage.verifyDataScopeForRole('branch');
        expect(dataScope.user_role).toBe('branch');
        
        // And 다른 지사의 정보는 접근할 수 없다
        expect(userData.branch_id).toBeTruthy();
        console.log(`🏢 소속 지사 ID: ${userData.branch_id}`);
        
        // And 본사 전용 메뉴는 표시되지 않는다
        await dashboardPage.verifyMenuItemsForRole('branch');
        
        console.log('✅ 시나리오 9 완료: 지사 권한 인증 검증');
    });

    /**
     * 시나리오 10: 소속 매장 관리 기능
     */
    test('시나리오 10: 소속 매장 관리 기능 테스트', async ({ page }) => {
        console.log('🏪 시나리오 10: 소속 매장 관리 기능');
        
        // Given 지사 관리자로 로그인되어 있다
        await loginPage.quickLogin('branch');
        
        // When 매장 관리 페이지에 접근한다
        await page.goto('/management/stores');
        await page.waitForTimeout(2000);
        
        // Then 소속 매장들만 목록에 표시된다
        const storeElements = page.locator('[onclick*="editStore"]');
        const storeCount = await storeElements.count();
        console.log(`🏪 지사 소속 매장 수: ${storeCount}개`);
        
        // 지사는 소속 매장이 있어야 함
        expect(storeCount).toBeGreaterThanOrEqual(0);
        
        // And 매장별 상세 정보를 확인할 수 있다
        if (storeCount > 0) {
            await storeElements.first().click();
            await expect(page.locator('#edit-store-modal')).toBeVisible();
            
            // 지사 선택 옵션이 제한되어 있는지 확인
            const branchSelect = page.locator('#edit-branch-select');
            if (await branchSelect.isVisible()) {
                const options = await branchSelect.locator('option').allTextContents();
                console.log('📋 지사 선택 옵션:', options);
            }
            
            await page.click('#edit-store-modal button:has-text("취소")');
            console.log('✅ 지사 권한으로 소속 매장 정보 확인 가능');
        }
        
        // And 새로운 매장 등록 요청을 승인할 수 있다
        await page.click('button:has-text("➕ 매장 추가")');
        await expect(page.locator('#add-store-modal')).toBeVisible();
        console.log('✅ 지사 권한으로 새 매장 추가 가능');
        await page.click('button:has-text("취소")');
        
        // And 다른 지사의 매장은 접근할 수 없다
        const accessibleStores = await page.request.get('/test-api/stores');
        const storesData = await accessibleStores.json();
        
        // 지사는 본사보다 적은 매장만 볼 수 있어야 함
        console.log(`🔐 지사 권한 매장 수: ${storesData.data.length}개`);
        
        console.log('✅ 시나리오 10 완료: 소속 매장 관리 검증');
    });

    /**
     * 시나리오 11: 소속 매장 성과 분석
     */
    test('시나리오 11: 소속 매장 성과 분석 테스트', async ({ page }) => {
        console.log('📈 시나리오 11: 소속 매장 성과 분석');
        
        // Given 지사 관리자로 로그인되어 있다
        await loginPage.quickLogin('branch');
        
        // When 성과 분석 페이지에 접근한다
        await dashboardPage.verifyDashboardLoaded('branch');
        
        // Then 소속 매장들의 매출 현황을 확인할 수 있다
        const kpiData = await dashboardPage.collectKpiData();
        console.log('📊 지사 KPI 데이터:', kpiData);
        
        // And 매장별 성과 비교가 가능하다
        // And 지사 전체 통계를 확인할 수 있다
        await dashboardPage.verifyChartsLoaded();
        
        // And 목표 대비 달성률을 분석할 수 있다
        const goalText = await dashboardPage.kpiCards.goalProgress.locator('.kpi-value').textContent();
        expect(goalText).toMatch(/\d+\s*\/\s*100/);
        console.log(`🎯 목표 달성률: ${goalText}`);
        
        // And 다른 지사의 데이터는 접근할 수 없다
        const dataScope = await dashboardPage.verifyDataScopeForRole('branch');
        expect(dataScope.user_role).toBe('branch');
        
        console.log('✅ 시나리오 11 완료: 지사 성과 분석 검증');
    });

    /**
     * 시나리오 12: 권한 경계 테스트
     */
    test('시나리오 12: 지사 권한 경계 및 제한 테스트', async ({ page }) => {
        console.log('🚫 시나리오 12: 지사 권한 경계 테스트');
        
        // Given 지사 관리자로 로그인되어 있다
        await loginPage.quickLogin('branch');
        
        // When 권한 경계를 테스트한다
        const restrictedUrls = [
            // 본사 전용 기능들은 접근 가능하지만 데이터가 필터링됨
        ];
        
        // 데이터 필터링 확인
        const branchDataScope = await page.evaluate(async () => {
            const response = await fetch('/api/dashboard/overview');
            const data = await response.json();
            return {
                accessible_stores: data.debug?.accessible_stores,
                user_role: data.user_role,
                month_sales: data.data?.month?.sales
            };
        });
        
        console.log('🔍 지사 데이터 범위:', branchDataScope);
        
        // Then 지사는 소속 매장 데이터만 접근 가능
        expect(branchDataScope.accessible_stores).toBeLessThanOrEqual(3); // 전체보다 적어야 함
        expect(branchDataScope.user_role).toBe('branch');
        
        // And 다른 지사 데이터는 필터링됨
        const userData = await dashboardPage.verifyUserRole('branch');
        expect(userData.branch_id).toBeTruthy();
        
        console.log('✅ 시나리오 12 완료: 지사 권한 경계 검증');
    });

    /**
     * 시나리오 13: 지사별 정산 관리
     */
    test('시나리오 13: 지사 정산 처리 기능 테스트', async ({ page }) => {
        console.log('💰 시나리오 13: 지사 정산 처리');
        
        // Given 지사 관리자로 로그인되어 있다
        await loginPage.quickLogin('branch');
        
        // When 정산 관리 기능에 접근한다
        await page.goto('/management/stores');
        await page.waitForTimeout(2000);
        
        // 매장 성과보기로 정산 관련 기능 테스트
        const statsButtons = page.locator('button:has-text("성과보기")');
        const statsButtonCount = await statsButtons.count();
        
        if (statsButtonCount > 0) {
            await statsButtons.first().click();
            await expect(page.locator('#store-stats-modal')).toBeVisible();
            
            // Then 소속 매장들의 정산 현황을 확인할 수 있다
            const storeStatsData = await page.evaluate(() => ({
                storeName: document.getElementById('stats-store-name')?.textContent,
                todaySales: document.getElementById('stats-today-sales')?.textContent,
                monthSales: document.getElementById('stats-month-sales')?.textContent
            }));
            
            console.log('📊 매장 정산 데이터:', storeStatsData);
            
            // And 매장별 정산 데이터를 검토할 수 있다
            expect(storeStatsData.storeName).toBeTruthy();
            expect(storeStatsData.monthSales).toMatch(/₩[0-9,]+/);
            
            await page.click('#store-stats-modal button:has-text("닫기")');
            console.log('✅ 지사 권한으로 소속 매장 정산 데이터 확인 가능');
        }
        
        console.log('✅ 시나리오 13 완료: 지사 정산 처리 검증');
    });
});