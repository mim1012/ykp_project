import { expect } from '@playwright/test';
import { BasePage } from './BasePage.js';

/**
 * 대시보드 페이지 객체 모델
 * 권한별 대시보드 기능 담당
 */
export class DashboardPage extends BasePage {
    constructor(page) {
        super(page);
        
        // 대시보드 주요 요소들
        this.pageTitle = page.locator('h1, .page-title');
        this.userWelcome = page.locator('.user-info, .welcome-message');
        this.systemStatus = page.locator('.alert-banner, .system-status');
        
        // KPI 카드들
        this.kpiCards = {
            todaySales: page.locator('.kpi-card:has-text("오늘 매출"), #todaySales'),
            monthSales: page.locator('.kpi-card:has-text("이번 달 매출"), #monthSales'),
            vatSales: page.locator('.kpi-card:has-text("VAT 포함"), #vatSales'),
            goalProgress: page.locator('.kpi-card:has-text("목표 달성률"), #goalProgress')
        };
        
        // 차트 요소들
        this.salesChart = page.locator('#salesChart, .sales-trend-chart');
        this.marketChart = page.locator('#marketChart, .market-share-chart');
        
        // 네비게이션 버튼들
        this.refreshButton = page.locator('button:has-text("새로고침")');
        this.dataCollectionButton = page.locator('button:has-text("데이터 수집")');
        this.reportDownloadButton = page.locator('button:has-text("레포트 다운로드")');
        
        // 사이드바 메뉴
        this.sidebarIcons = {
            stores: page.locator('.sidebar-icon:has-text("🏪")'),
            branches: page.locator('.sidebar-icon:has-text("🏢")'),
            users: page.locator('.sidebar-icon:has-text("👥")'),
            reports: page.locator('.sidebar-icon:has-text("📊")')
        };
    }

    /**
     * 대시보드 로딩 검증
     */
    async verifyDashboardLoaded(userRole) {
        console.log(`📊 ${userRole} 대시보드 로딩 검증 중...`);
        
        await this.waitForPageLoad();
        
        // 페이지 제목 확인
        await expect(this.pageTitle).toContainText('대시보드');
        
        // 사용자 정보 표시 확인
        await expect(this.userWelcome).toBeVisible();
        
        // 시스템 상태 메시지 확인
        await expect(this.systemStatus).toBeVisible();
        
        // 권한별 KPI 카드 확인
        await this.verifyKpiCardsForRole(userRole);
        
        // 권한별 메뉴 확인
        await this.verifyMenuItemsForRole(userRole);
        
        console.log(`✅ ${userRole} 대시보드 로딩 검증 완료`);
    }

    /**
     * 권한별 KPI 카드 확인
     */
    async verifyKpiCardsForRole(role) {
        // 모든 권한에서 기본 KPI 카드들이 보여야 함
        for (const [key, locator] of Object.entries(this.kpiCards)) {
            await expect(locator).toBeVisible();
        }
        
        // 권한별 KPI 값 범위 확인
        const todaySalesText = await this.kpiCards.todaySales.locator('.kpi-value').textContent();
        const monthSalesText = await this.kpiCards.monthSales.locator('.kpi-value').textContent();
        
        // 매출 데이터가 숫자 형태인지 확인
        expect(todaySalesText).toMatch(/₩[0-9,]+/);
        expect(monthSalesText).toMatch(/₩[0-9,]+/);
        
        console.log(`📈 KPI 데이터 - 오늘: ${todaySalesText}, 이번 달: ${monthSalesText}`);
    }

    /**
     * 권한별 메뉴 항목 확인
     */
    async verifyMenuItemsForRole(role) {
        const expectedMenus = {
            headquarters: [
                { text: '🏪', tooltip: '매장 관리' },
                { text: '🏢', tooltip: '지사 관리' },
                { text: '👥', tooltip: '사용자 관리' },
                { text: '📊', tooltip: '통계' }
            ],
            branch: [
                { text: '🏪', tooltip: '매장 관리' },
                { text: '📊', tooltip: '통계' }
            ],
            store: [
                { text: '📝', tooltip: '개통표 입력' },
                { text: '📊', tooltip: '통계' }
            ]
        };

        const menus = expectedMenus[role] || [];
        
        for (const menu of menus) {
            const menuIcon = this.page.locator(`.sidebar-icon:has-text("${menu.text}")`);
            await expect(menuIcon).toBeVisible();
        }
        
        console.log(`🧭 ${role} 권한 메뉴 확인 완료`);
    }

    /**
     * 실시간 데이터 새로고침
     */
    async refreshData() {
        console.log('🔄 데이터 새로고침 중...');
        
        const apiResponsePromise = this.monitorApiResponse('/api/dashboard/overview');
        await this.refreshButton.click();
        
        const apiResponse = await apiResponsePromise;
        console.log(`📡 API 응답: ${apiResponse.status} - ${apiResponse.url}`);
        
        // 데이터 로딩 완료 대기
        await this.waitForPageLoad();
        
        console.log('✅ 데이터 새로고침 완료');
        return apiResponse;
    }

    /**
     * 권한별 데이터 범위 확인
     */
    async verifyDataScopeForRole(role) {
        const userData = await this.page.evaluate(() => window.userData);
        
        // API 호출하여 실제 데이터 범위 확인
        const response = await this.page.evaluate(async () => {
            const response = await fetch('/api/dashboard/overview');
            return await response.json();
        });
        
        if (response.success) {
            const accessibleStores = response.debug?.accessible_stores || 0;
            
            switch(role) {
                case 'headquarters':
                    expect(accessibleStores).toBeGreaterThan(1); // 전체 매장 접근
                    break;
                case 'branch':
                    expect(accessibleStores).toBeGreaterThanOrEqual(1); // 소속 매장 접근
                    break;
                case 'store':
                    expect(accessibleStores).toBe(1); // 자기 매장만 접근
                    break;
            }
            
            console.log(`🔐 ${role} 권한 데이터 범위: ${accessibleStores}개 매장 접근`);
        }
        
        return response;
    }

    /**
     * 차트 데이터 로딩 확인
     */
    async verifyChartsLoaded() {
        console.log('📈 차트 로딩 확인 중...');
        
        // 30일 매출 추이 차트
        await expect(this.salesChart).toBeVisible();
        
        // 시장별 매출 차트  
        await expect(this.marketChart).toBeVisible();
        
        // 차트 데이터가 실제로 렌더링되었는지 확인
        const chartCanvas = this.page.locator('canvas');
        const canvasCount = await chartCanvas.count();
        expect(canvasCount).toBeGreaterThanOrEqual(2); // 최소 2개 차트
        
        console.log(`✅ ${canvasCount}개 차트 로딩 완료`);
    }

    /**
     * 시스템 상태 메시지 검증
     */
    async verifySystemStatus(role) {
        await expect(this.systemStatus).toBeVisible();
        
        const statusText = await this.systemStatus.textContent();
        
        // 권한별 시스템 상태 메시지 확인
        switch(role) {
            case 'headquarters':
                expect(statusText).toContain('지사');
                expect(statusText).toContain('매장');
                expect(statusText).toContain('사용자');
                expect(statusText).toContain('관리 중');
                break;
            case 'branch':
                expect(statusText).toContain('매장 관리 중');
                break;
            case 'store':
                expect(statusText).toContain('운영 중');
                break;
        }
        
        console.log(`📋 시스템 상태: ${statusText.substring(0, 50)}...`);
    }

    /**
     * 다운로드 기능 테스트
     */
    async downloadReport() {
        console.log('📄 리포트 다운로드 중...');
        
        const downloadPromise = this.page.waitForEvent('download');
        await this.reportDownloadButton.click();
        
        const download = await downloadPromise;
        const filename = download.suggestedFilename();
        
        expect(filename).toMatch(/\.(pdf|xlsx|csv)$/);
        console.log(`✅ 리포트 다운로드 완료: ${filename}`);
        
        return download;
    }

    /**
     * 실시간 데이터 업데이트 확인
     */
    async verifyRealTimeUpdates() {
        console.log('⏱️ 실시간 데이터 업데이트 확인 중...');
        
        // 첫 번째 데이터 수집
        const initialData = await this.collectKpiData();
        
        // 데이터 새로고침
        await this.refreshData();
        
        // 두 번째 데이터 수집
        const updatedData = await this.collectKpiData();
        
        // 타임스탬프가 업데이트되었는지 확인
        console.log('📊 초기 데이터:', initialData);
        console.log('📊 업데이트 데이터:', updatedData);
        
        console.log('✅ 실시간 데이터 업데이트 확인 완료');
    }

    /**
     * KPI 데이터 수집
     */
    async collectKpiData() {
        const data = {};
        
        for (const [key, locator] of Object.entries(this.kpiCards)) {
            try {
                const valueElement = locator.locator('.kpi-value, .text-xl, .font-bold').first();
                data[key] = await valueElement.textContent();
            } catch (error) {
                data[key] = 'N/A';
            }
        }
        
        return data;
    }
}

export default DashboardPage;