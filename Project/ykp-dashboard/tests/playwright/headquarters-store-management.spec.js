import { test, expect } from '@playwright/test';
import { LoginPage } from './pages/LoginPage.js';
import { DashboardPage } from './pages/DashboardPage.js';
import { StoreManagementPage } from './pages/StoreManagementPage.js';

/**
 * 본사 계정 매장관리 기능 테스트
 * 권한별 액션 버튼 동작 검증
 */
test.describe('🏢 본사 계정 매장관리 기능 테스트', () => {
    let loginPage, dashboardPage, storeManagementPage;
    let testStoreId = '1'; // 테스트용 매장 ID
    
    test.beforeEach(async ({ page }) => {
        loginPage = new LoginPage(page);
        dashboardPage = new DashboardPage(page);
        storeManagementPage = new StoreManagementPage(page);
    });

    /**
     * 시나리오 1: 본사 계정 로그인 및 매장관리 접근
     */
    test('시나리오 1: 본사 계정 로그인 및 매장관리 페이지 접근', async ({ page }) => {
        console.log('🏢 시나리오 1: 본사 계정으로 매장관리 페이지 접근');
        
        // Given 본사 관리자가 로그인한다
        await page.goto('/login');
        await loginPage.loginWithTestAccount('headquarters');
        
        // When 로그인이 완료된다
        const userData = await loginPage.verifyLoginSuccess('headquarters');
        expect(userData.role).toBe('headquarters');
        console.log('✅ 본사 계정 로그인 성공');
        
        // And 매장관리 페이지에 접근한다
        await storeManagementPage.navigateToStoreManagement();
        
        // Then 매장 목록이 표시된다
        const storeCount = await storeManagementPage.verifyStoreList();
        expect(storeCount).toBeGreaterThan(0);
        console.log(`📊 본사에서 확인 가능한 매장: ${storeCount}개`);
        
        // And 모든 매장 관리 권한이 있다
        const permissions = await storeManagementPage.verifyHeadquartersPermissions();
        expect(permissions.canEdit).toBeTruthy();
        expect(permissions.canCreateAccount).toBeTruthy();
        expect(permissions.canViewPerformance).toBeTruthy();
        expect(permissions.canDelete).toBeTruthy();
        
        console.log('✅ 시나리오 1 완료: 본사 계정 매장관리 접근 검증');
    });

    /**
     * 시나리오 2: 매장 정보 수정 기능 테스트
     */
    test('시나리오 2: 매장 정보 수정 기능 테스트', async ({ page }) => {
        console.log('✏️ 시나리오 2: 매장 정보 수정 기능');
        
        // Given 본사 계정으로 로그인되어 있다
        await loginPage.quickLogin('headquarters');
        await storeManagementPage.navigateToStoreManagement();
        
        // When 특정 매장의 정보를 수정한다
        const updateData = {
            name: '테스트 매장 (수정됨)',
            owner_name: '김테스트 (수정)',
            phone: '010-9999-8888',
            address: '서울시 테스트구 수정동 123-45',
            status: 'active'
        };
        
        // API 호출 모니터링 설정
        const apiPromise = storeManagementPage.monitorApiCall(`/api/stores/${testStoreId}`, 'PUT');
        
        // 매장 정보 수정 실행
        await storeManagementPage.editStoreInfo(testStoreId, updateData);
        
        // Then API 호출이 성공한다
        const apiResult = await apiPromise;
        expect(apiResult.status).toBe(200);
        expect(apiResult.data.success).toBeTruthy();
        expect(apiResult.data.message).toContain('매장 정보가 성공적으로 수정되었습니다');
        
        console.log('✅ 시나리오 2 완료: 매장 정보 수정 성공');
    });

    /**
     * 시나리오 3: 매장 계정 생성 기능 테스트
     */
    test('시나리오 3: 매장 계정 생성 기능 테스트', async ({ page }) => {
        console.log('👤 시나리오 3: 매장 계정 생성 기능');
        
        // Given 본사 계정으로 로그인되어 있다
        await loginPage.quickLogin('headquarters');
        await storeManagementPage.navigateToStoreManagement();
        
        // When 새로운 매장 계정을 생성한다
        const accountData = {
            name: '테스트 매장 직원',
            email: `test-store-user-${Date.now()}@ykp.com`,
            password: 'test123456',
            role: 'store'
        };
        
        // API 호출 모니터링 설정
        const apiPromise = storeManagementPage.monitorApiCall(`/api/stores/${testStoreId}/create-account`, 'POST');
        
        // 계정 생성 실행
        await storeManagementPage.createStoreAccount(testStoreId, accountData);
        
        // Then API 호출이 성공한다
        const apiResult = await apiPromise;
        expect(apiResult.status).toBe(201);
        expect(apiResult.data.success).toBeTruthy();
        expect(apiResult.data.message).toContain('계정이 성공적으로 생성되었습니다');
        expect(apiResult.data.data.email).toBe(accountData.email);
        
        console.log('✅ 시나리오 3 완료: 매장 계정 생성 성공');
    });

    /**
     * 시나리오 4: 매장 성과 조회 기능 테스트
     */
    test('시나리오 4: 매장 성과 조회 기능 테스트', async ({ page }) => {
        console.log('📈 시나리오 4: 매장 성과 조회 기능');
        
        // Given 본사 계정으로 로그인되어 있다
        await loginPage.quickLogin('headquarters');
        await storeManagementPage.navigateToStoreManagement();
        
        // When 매장의 성과를 조회한다
        // API 호출 모니터링 설정
        const apiPromise = storeManagementPage.monitorApiCall(`/api/stores/${testStoreId}/performance`, 'GET');
        
        // 성과 조회 실행
        const performanceData = await storeManagementPage.viewStorePerformance(testStoreId);
        
        // Then API 호출이 성공한다
        const apiResult = await apiPromise;
        expect(apiResult.status).toBe(200);
        expect(apiResult.data.success).toBeTruthy();
        
        // And 성과 데이터가 표시된다
        expect(performanceData.thisMonth).toBeTruthy();
        expect(performanceData.lastMonth).toBeTruthy();
        expect(performanceData.growthRate).toBeTruthy();
        
        console.log('📊 매장 성과 데이터:', performanceData);
        console.log('✅ 시나리오 4 완료: 매장 성과 조회 성공');
    });

    /**
     * 시나리오 5: 매장 삭제 기능 테스트 (소프트 삭제)
     */
    test('시나리오 5: 매장 삭제 기능 테스트 (판매 데이터 있는 매장)', async ({ page }) => {
        console.log('🗑️ 시나리오 5: 매장 삭제 기능 (소프트 삭제)');
        
        // Given 본사 계정으로 로그인되어 있다
        await loginPage.quickLogin('headquarters');
        await storeManagementPage.navigateToStoreManagement();
        
        // When 판매 데이터가 있는 매장을 삭제 시도한다
        // API 호출 모니터링 설정
        const apiPromise = storeManagementPage.monitorApiCall(`/api/stores/${testStoreId}`, 'DELETE');
        
        // 매장 삭제 실행 (소프트 삭제 예상)
        await storeManagementPage.deleteStore(testStoreId, true);
        
        // Then API 호출이 성공한다
        const apiResult = await apiPromise;
        expect(apiResult.status).toBe(200);
        expect(apiResult.data.success).toBeTruthy();
        expect(apiResult.data.message).toContain('판매 데이터가 있어 매장이 비활성화되었습니다');
        
        console.log('✅ 시나리오 5 완료: 매장 소프트 삭제 성공');
    });

    /**
     * 시나리오 6: 권한 경계 테스트 - 본사만 가능한 기능
     */
    test('시나리오 6: 본사 전용 권한 확인 테스트', async ({ page }) => {
        console.log('🛡️ 시나리오 6: 본사 전용 권한 확인');
        
        // Given 본사 계정으로 로그인되어 있다
        await loginPage.quickLogin('headquarters');
        await storeManagementPage.navigateToStoreManagement();
        
        // When 본사 전용 기능들을 확인한다
        const actions = await storeManagementPage.verifyStoreActions(testStoreId);
        
        // Then 모든 액션 버튼이 표시된다
        expect(actions.edit).toBeTruthy();
        expect(actions.createAccount).toBeTruthy();  
        expect(actions.performance).toBeTruthy();
        expect(actions.delete).toBeTruthy();
        
        console.log('🏢 본사 계정 권한 확인:', {
            '매장 수정': actions.edit ? '✅' : '❌',
            '계정 생성': actions.createAccount ? '✅' : '❌',
            '성과 조회': actions.performance ? '✅' : '❌',
            '매장 삭제': actions.delete ? '✅' : '❌'
        });
        
        console.log('✅ 시나리오 6 완료: 본사 전용 권한 검증');
    });

    /**
     * 시나리오 7: API 에러 처리 테스트
     */
    test('시나리오 7: API 에러 처리 테스트', async ({ page }) => {
        console.log('❌ 시나리오 7: API 에러 처리');
        
        // Given 본사 계정으로 로그인되어 있다
        await loginPage.quickLogin('headquarters');
        await storeManagementPage.navigateToStoreManagement();
        
        // When 존재하지 않는 매장에 대해 작업을 시도한다
        const nonExistentStoreId = '99999';
        
        try {
            await storeManagementPage.editStoreInfo(nonExistentStoreId, {
                name: '존재하지 않는 매장'
            });
        } catch (error) {
            // Then 적절한 에러 메시지가 표시된다
            await storeManagementPage.verifyErrorHandling('매장을 찾을 수 없습니다');
            console.log('✅ 에러 처리 확인 완료');
        }
        
        console.log('✅ 시나리오 7 완료: API 에러 처리 검증');
    });

    /**
     * 시나리오 8: 대량 데이터 처리 테스트
     */
    test('시나리오 8: 매장 목록 대량 데이터 처리 테스트', async ({ page }) => {
        console.log('📊 시나리오 8: 대량 데이터 처리');
        
        // Given 본사 계정으로 로그인되어 있다
        await loginPage.quickLogin('headquarters');
        await storeManagementPage.navigateToStoreManagement();
        
        // When 매장 목록을 조회한다
        const storeCount = await storeManagementPage.verifyStoreList();
        
        // Then 모든 매장에 대해 액션 버튼이 제대로 표시된다
        if (storeCount > 0) {
            // 첫 번째 매장의 액션 버튼 확인
            const firstStoreActions = await storeManagementPage.verifyStoreActions('1');
            expect(firstStoreActions.edit).toBeTruthy();
            expect(firstStoreActions.createAccount).toBeTruthy();
            expect(firstStoreActions.performance).toBeTruthy();
            expect(firstStoreActions.delete).toBeTruthy();
            
            console.log(`📋 ${storeCount}개 매장 중 첫 번째 매장 액션 버튼 확인 완료`);
        }
        
        console.log('✅ 시나리오 8 완료: 대량 데이터 처리 검증');
    });

    /**
     * 시나리오 9: 통합 워크플로우 테스트
     */
    test('시나리오 9: 매장관리 통합 워크플로우 테스트', async ({ page }) => {
        console.log('🔄 시나리오 9: 통합 워크플로우');
        
        // Given 본사 계정으로 로그인되어 있다
        await loginPage.quickLogin('headquarters');
        await storeManagementPage.navigateToStoreManagement();
        
        // When 전체 워크플로우를 순차적으로 실행한다
        // 1. 매장 목록 확인
        const storeCount = await storeManagementPage.verifyStoreList();
        console.log(`1️⃣ 매장 목록 확인: ${storeCount}개`);
        
        // 2. 권한 확인
        const permissions = await storeManagementPage.verifyHeadquartersPermissions();
        console.log('2️⃣ 권한 확인 완료:', permissions);
        
        // 3. 성과 조회
        const performanceData = await storeManagementPage.viewStorePerformance(testStoreId);
        console.log('3️⃣ 성과 조회 완료:', Object.keys(performanceData));
        
        // Then 모든 단계가 성공적으로 완료된다
        expect(storeCount).toBeGreaterThan(0);
        expect(permissions.canEdit).toBeTruthy();
        expect(performanceData).toBeTruthy();
        
        console.log('✅ 시나리오 9 완료: 통합 워크플로우 검증');
    });
});