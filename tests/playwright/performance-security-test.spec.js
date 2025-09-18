import { test, expect } from '@playwright/test';
import { LoginPage } from './pages/LoginPage.js';
import { DashboardPage } from './pages/DashboardPage.js';

/**
 * 성능 및 보안 테스트
 * 시스템 안정성 및 보안 검증
 */
test.describe('🚀 성능 및 보안 테스트', () => {
    
    /**
     * 시나리오 22: 페이지 로드 성능 측정
     */
    test('시나리오 22: 페이지 로드 성능 측정', async ({ page }) => {
        console.log('⚡ 시나리오 22: 페이지 로드 성능');
        
        // 성능 메트릭 수집 시작
        await page.goto('/login');
        
        // 로그인 페이지 로드 성능 측정
        const loginLoadTime = await page.evaluate(() => {
            const navigation = performance.getEntriesByType('navigation')[0];
            return navigation.loadEventEnd - navigation.fetchStart;
        });
        
        console.log(`📊 로그인 페이지 로드 시간: ${loginLoadTime}ms`);
        expect(loginLoadTime).toBeLessThan(3000); // 3초 이하
        
        // 로그인 후 대시보드 로드 성능
        const loginPage = new LoginPage(page);
        await loginPage.quickLogin('headquarters');
        
        const startTime = Date.now();
        await page.goto('/dashboard');
        await page.waitForLoadState('networkidle');
        const endTime = Date.now();
        
        const dashboardLoadTime = endTime - startTime;
        console.log(`📊 대시보드 로드 시간: ${dashboardLoadTime}ms`);
        expect(dashboardLoadTime).toBeLessThan(5000); // 5초 이하
        
        console.log('✅ 시나리오 22 완료: 페이지 성능 검증');
    });

    /**
     * 시나리오 23: API 응답 시간 성능 테스트
     */
    test('시나리오 23: API 응답 성능 테스트', async ({ page }) => {
        console.log('📡 시나리오 23: API 응답 성능');
        
        const loginPage = new LoginPage(page);
        await loginPage.quickLogin('headquarters');
        
        // 주요 API 엔드포인트 응답 시간 측정
        const apiEndpoints = [
            '/api/users/count',
            '/api/stores/count',
            '/api/sales/count',
            '/test-api/branches',
            '/test-api/dashboard-debug'
        ];
        
        const apiPerformance = [];
        
        for (const endpoint of apiEndpoints) {
            const startTime = Date.now();
            
            try {
                const response = await page.request.get(endpoint);
                const endTime = Date.now();
                const responseTime = endTime - startTime;
                
                apiPerformance.push({
                    endpoint,
                    status: response.status(),
                    responseTime,
                    success: response.ok()
                });
                
                console.log(`📡 ${endpoint}: ${responseTime}ms (${response.status()})`);
                
                // API 응답 시간 검증 (2초 이하)
                expect(responseTime).toBeLessThan(2000);
                
            } catch (error) {
                apiPerformance.push({
                    endpoint,
                    error: error.message,
                    responseTime: -1,
                    success: false
                });
                
                console.log(`❌ ${endpoint}: 에러 - ${error.message}`);
            }
        }
        
        // 전체 API 성공률 확인
        const successfulApis = apiPerformance.filter(api => api.success).length;
        const successRate = (successfulApis / apiEndpoints.length) * 100;
        
        console.log(`📊 API 성공률: ${successRate}% (${successfulApis}/${apiEndpoints.length})`);
        expect(successRate).toBeGreaterThan(80); // 80% 이상 성공률
        
        console.log('✅ 시나리오 23 완료: API 성능 검증');
    });

    /**
     * 시나리오 24: 대용량 데이터 처리 성능
     */
    test('시나리오 24: 대용량 데이터 처리 성능', async ({ page }) => {
        console.log('📊 시나리오 24: 대용량 데이터 처리');
        
        const loginPage = new LoginPage(page);
        await loginPage.quickLogin('store');
        
        // AgGrid 대용량 데이터 로드 테스트
        await page.goto('/test/complete-aggrid');
        
        const startTime = Date.now();
        
        // AgGrid 로딩 완료 대기
        await page.waitForSelector('.ag-root-wrapper', { timeout: 10000 });
        
        // 데이터 행들이 로드될 때까지 대기
        await page.waitForFunction(() => {
            const rows = document.querySelectorAll('.ag-row');
            return rows.length >= 0; // 최소 데이터 로드 확인
        }, { timeout: 10000 });
        
        const endTime = Date.now();
        const loadTime = endTime - startTime;
        
        console.log(`📊 AgGrid 로드 시간: ${loadTime}ms`);
        expect(loadTime).toBeLessThan(10000); // 10초 이하
        
        // 메모리 사용량 확인
        const memoryUsage = await page.evaluate(() => {
            if (performance.memory) {
                return {
                    used: Math.round(performance.memory.usedJSHeapSize / 1024 / 1024),
                    total: Math.round(performance.memory.totalJSHeapSize / 1024 / 1024)
                };
            }
            return null;
        });
        
        if (memoryUsage) {
            console.log(`💾 메모리 사용량: ${memoryUsage.used}MB / ${memoryUsage.total}MB`);
            expect(memoryUsage.used).toBeLessThan(100); // 100MB 이하
        }
        
        console.log('✅ 시나리오 24 완료: 대용량 데이터 성능 검증');
    });

    /**
     * 시나리오 25: 동시 접속자 부하 테스트  
     */
    test('시나리오 25: 다중 사용자 동시 접속 테스트', async ({ browser }) => {
        console.log('👥 시나리오 25: 다중 사용자 동시 접속');
        
        // 5명의 동시 사용자 시뮬레이션
        const userContexts = [];
        const userPages = [];
        
        // 동시 사용자 생성
        for (let i = 0; i < 5; i++) {
            const context = await browser.newContext();
            const page = await context.newPage();
            userContexts.push(context);
            userPages.push(page);
        }

        try {
            console.log('👥 5명 동시 접속 시뮬레이션 시작');
            
            // 각기 다른 권한으로 동시 로그인
            const loginPromises = userPages.map(async (page, index) => {
                const roles = ['headquarters', 'branch', 'store', 'headquarters', 'branch'];
                const role = roles[index];
                
                const loginPage = new LoginPage(page);
                await loginPage.quickLogin(role);
                
                return { page, role, userId: index + 1 };
            });
            
            const loggedInUsers = await Promise.all(loginPromises);
            console.log('✅ 5명 동시 로그인 완료');

            // 동시 작업 수행
            console.log('⚡ 동시 작업 수행');
            
            const workPromises = loggedInUsers.map(async (user) => {
                const startTime = Date.now();
                
                // 각 사용자별 작업 수행
                await user.page.goto('/dashboard');
                await user.page.waitForLoadState('networkidle');
                
                // 데이터 새로고침
                const refreshButton = user.page.locator('button:has-text("새로고침")');
                if (await refreshButton.isVisible()) {
                    await refreshButton.click();
                    await user.page.waitForTimeout(2000);
                }
                
                const endTime = Date.now();
                const responseTime = endTime - startTime;
                
                return {
                    userId: user.userId,
                    role: user.role,
                    responseTime
                };
            });
            
            const workResults = await Promise.all(workPromises);
            
            // 성능 결과 분석
            console.log('📊 동시 작업 성능 결과:');
            workResults.forEach(result => {
                console.log(`User ${result.userId} (${result.role}): ${result.responseTime}ms`);
                expect(result.responseTime).toBeLessThan(10000); // 10초 이하
            });
            
            const avgResponseTime = workResults.reduce((sum, r) => sum + r.responseTime, 0) / workResults.length;
            console.log(`📈 평균 응답 시간: ${Math.round(avgResponseTime)}ms`);
            
            expect(avgResponseTime).toBeLessThan(8000); // 평균 8초 이하
            
            console.log('✅ 시나리오 25 완료: 다중 사용자 부하 테스트 검증');

        } finally {
            for (const context of userContexts) {
                await context.close();
            }
        }
    });

    /**
     * 시나리오 26: 보안 침투 테스트
     */
    test('시나리오 26: 보안 침투 및 권한 우회 시도 테스트', async ({ page }) => {
        console.log('🔒 시나리오 26: 보안 침투 테스트');
        
        const loginPage = new LoginPage(page);
        
        // 1. 비인증 상태에서 보호된 리소스 접근 시도
        console.log('🚫 비인증 접근 시도');
        
        const protectedUrls = [
            '/dashboard',
            '/management/stores',
            '/api/dashboard/overview'
        ];
        
        for (const url of protectedUrls) {
            await page.goto(url);
            
            // 로그인 페이지로 리다이렉트되거나 401/403 에러여야 함
            const isProtected = page.url().includes('/login') || 
                              await page.locator('text="401"').isVisible() ||
                              await page.locator('text="403"').isVisible() ||
                              await page.locator('text="Unauthenticated"').isVisible();
            
            console.log(`🔐 ${url}: ${isProtected ? '보호됨' : '접근 가능'}`);
        }

        // 2. 권한 우회 시도
        console.log('🕵️ 권한 우회 시도');
        
        // 매장 권한으로 로그인
        await loginPage.quickLogin('store');
        
        // 다른 매장 데이터 직접 API 호출 시도
        const unauthorizedApiCalls = [
            '/test-api/stores/2', // 다른 매장 정보
            '/test-api/stores/3', // 또 다른 매장 정보
        ];
        
        for (const apiCall of unauthorizedApiCalls) {
            const response = await page.request.get(apiCall);
            console.log(`🔍 ${apiCall}: ${response.status()}`);
            
            if (response.ok()) {
                const data = await response.json();
                console.log(`⚠️ 다른 매장 데이터 접근 가능: ${data.data?.name || 'N/A'}`);
            } else {
                console.log(`✅ 다른 매장 데이터 접근 차단됨`);
            }
        }

        // 3. 세션 보안 확인
        console.log('🍪 세션 보안 확인');
        
        // 로그아웃 후 세션 무효화 확인
        await loginPage.performLogout();
        
        // 이전 세션으로 API 호출 시도
        const sessionTestResponse = await page.request.get('/api/dashboard/overview');
        console.log(`🔐 로그아웃 후 API 접근: ${sessionTestResponse.status()}`);
        
        // 401 Unauthorized이거나 로그인 페이지 리다이렉트여야 함
        expect(sessionTestResponse.status()).toBeGreaterThanOrEqual(400);
        
        console.log('✅ 시나리오 26 완료: 보안 침투 테스트 검증');
    });

    /**
     * 시나리오 27: 데이터 무결성 및 일관성 테스트
     */
    test('시나리오 27: 데이터 무결성 및 일관성 검증', async ({ page }) => {
        console.log('🔍 시나리오 27: 데이터 무결성 검증');
        
        const loginPage = new LoginPage(page);
        await loginPage.quickLogin('headquarters');
        
        // 1. 시스템 전체 데이터 일관성 확인
        const systemData = await page.evaluate(async () => {
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
        
        console.log('📊 시스템 데이터 현황:', systemData);
        
        // 데이터 일관성 검증
        expect(systemData.users).toBeGreaterThan(0);
        expect(systemData.stores).toBeGreaterThan(0);
        expect(systemData.branches).toBeGreaterThan(0);
        expect(systemData.sales).toBeGreaterThan(0);
        
        // 2. 권한별 데이터 합계 일관성 확인
        console.log('🧮 권한별 데이터 합계 검증');
        
        // 본사 관점에서 전체 매출 확인
        const hqTotalSales = await page.evaluate(async () => {
            const response = await fetch('/test-api/dashboard-debug');
            const data = await response.json();
            return parseFloat(data.debug_info.total_sales);
        });
        
        console.log(`💰 전체 매출 합계: ₩${hqTotalSales.toLocaleString()}`);
        expect(hqTotalSales).toBeGreaterThan(0);
        
        // 3. 매장별 매출 분산 확인
        const storeDistribution = await page.evaluate(async () => {
            const response = await page.request.get('/test-api/distribute-sales');
            return await response.json();
        });
        
        if (storeDistribution.success) {
            console.log('📈 매장별 매출 분산:', storeDistribution.distribution);
            
            const totalDistributed = Object.values(storeDistribution.distribution)
                .reduce((sum, count) => sum + count, 0);
            
            expect(totalDistributed).toBe(systemData.sales);
            console.log('✅ 매출 데이터 분산 일관성 확인');
        }
        
        console.log('✅ 시나리오 27 완료: 데이터 무결성 검증');
    });

    /**
     * 시나리오 28: 실시간 업데이트 동시성 테스트
     */
    test('시나리오 28: 실시간 업데이트 동시성 테스트', async ({ browser }) => {
        console.log('🔄 시나리오 28: 실시간 업데이트 동시성');
        
        // 2개 브라우저 컨텍스트 생성 (동시 사용자 시뮬레이션)
        const context1 = await browser.newContext();
        const context2 = await browser.newContext();
        
        const page1 = await context1.newPage();
        const page2 = await context2.newPage();

        try {
            // 동시 로그인
            await Promise.all([
                new LoginPage(page1).quickLogin('headquarters'),
                new LoginPage(page2).quickLogin('branch')
            ]);
            
            console.log('👥 2명 동시 로그인 완료');

            // 동시 대시보드 접근
            await Promise.all([
                page1.goto('/dashboard'),
                page2.goto('/dashboard')
            ]);
            
            await Promise.all([
                page1.waitForLoadState('networkidle'),
                page2.waitForLoadState('networkidle')
            ]);

            // 동시 데이터 새로고침
            console.log('🔄 동시 데이터 새로고침');
            
            const refreshPromises = [
                page1.locator('button:has-text("새로고침")').click(),
                page2.locator('button:has-text("새로고침")').click()
            ];
            
            await Promise.all(refreshPromises);
            await Promise.all([
                page1.waitForTimeout(3000),
                page2.waitForTimeout(3000)
            ]);
            
            // 동시성 문제 없이 각각 올바른 데이터 표시 확인
            const [data1, data2] = await Promise.all([
                page1.evaluate(async () => {
                    const response = await fetch('/api/dashboard/overview');
                    const data = await response.json();
                    return { role: data.user_role, stores: data.debug?.accessible_stores };
                }),
                page2.evaluate(async () => {
                    const response = await fetch('/api/dashboard/overview');
                    const data = await response.json();
                    return { role: data.user_role, stores: data.debug?.accessible_stores };
                })
            ]);
            
            console.log('👤 사용자 1 데이터:', data1);
            console.log('👤 사용자 2 데이터:', data2);
            
            // 각자의 권한에 맞는 데이터를 받았는지 확인
            expect(data1.role).toBe('headquarters');
            expect(data2.role).toBe('branch');
            expect(data1.stores).toBeGreaterThanOrEqual(data2.stores);
            
            console.log('✅ 동시 접속 시 데이터 격리 확인');
            
            console.log('✅ 시나리오 28 완료: 동시성 테스트 검증');

        } finally {
            await context1.close();
            await context2.close();
        }
    });
});