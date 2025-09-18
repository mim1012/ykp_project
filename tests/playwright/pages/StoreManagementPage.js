import { expect } from '@playwright/test';
import { BasePage } from './BasePage.js';

/**
 * 매장관리 페이지 객체 모델
 * 본사/지사 매장관리 기능 담당
 */
export class StoreManagementPage extends BasePage {
    constructor(page) {
        super(page);
        
        // 매장 목록 요소들
        this.storeList = page.locator('[data-testid="store-list"], .store-table, table');
        this.storeRows = page.locator('tr[data-store-id], .store-row');
        this.addStoreButton = page.locator('button:has-text("매장 추가"), button:has-text("+ 매장")');
        
        // 매장 액션 버튼들
        this.editButton = (storeId) => page.locator(`[data-store-id="${storeId}"] button:has-text("수정"), button[data-action="edit"][data-store="${storeId}"]`);
        this.createAccountButton = (storeId) => page.locator(`[data-store-id="${storeId}"] button:has-text("계정"), button[data-action="create-account"][data-store="${storeId}"]`);
        this.performanceButton = (storeId) => page.locator(`[data-store-id="${storeId}"] button:has-text("성과"), button[data-action="performance"][data-store="${storeId}"]`);
        this.deleteButton = (storeId) => page.locator(`[data-store-id="${storeId}"] button:has-text("삭제"), button[data-action="delete"][data-store="${storeId}"]`);
        
        // 모달 및 폼 요소들
        this.modal = page.locator('.modal, [role="dialog"], .popup');
        this.editForm = page.locator('[data-testid="edit-store-form"], form.edit-store');
        this.accountForm = page.locator('[data-testid="create-account-form"], form.create-account');
        this.deleteConfirmModal = page.locator('[data-testid="delete-confirm"], .delete-confirm');
        
        // 입력 필드들
        this.storeNameInput = page.locator('input[name="name"], input[data-field="name"]');
        this.ownerNameInput = page.locator('input[name="owner_name"], input[data-field="owner_name"]');
        this.phoneInput = page.locator('input[name="phone"], input[data-field="phone"]');
        this.addressInput = page.locator('input[name="address"], textarea[name="address"]');
        this.statusSelect = page.locator('select[name="status"], select[data-field="status"]');
        
        // 계정 생성 필드들
        this.userNameInput = page.locator('input[name="user_name"], input[data-field="user_name"]');
        this.emailInput = page.locator('input[name="email"], input[data-field="email"]');
        this.passwordInput = page.locator('input[name="password"], input[data-field="password"]');
        this.roleSelect = page.locator('select[name="role"], select[data-field="role"]');
        
        // 버튼들
        this.saveButton = page.locator('button:has-text("저장"), button[type="submit"]');
        this.cancelButton = page.locator('button:has-text("취소"), button[data-action="cancel"]');
        this.confirmDeleteButton = page.locator('button:has-text("확인"), button:has-text("삭제 확인")');
        
        // 성과 관련 요소들
        this.performanceModal = page.locator('[data-testid="performance-modal"], .performance-modal');
        this.thisMonthStats = page.locator('[data-testid="this-month"], .this-month-stats');
        this.lastMonthStats = page.locator('[data-testid="last-month"], .last-month-stats');
        this.growthRate = page.locator('[data-testid="growth-rate"], .growth-rate');
    }

    /**
     * 매장관리 페이지로 이동
     */
    async navigateToStoreManagement() {
        await this.page.goto('/management/stores');
        await this.page.waitForLoadState('networkidle');
        await this.waitForElement(this.storeList, '매장 목록 로딩');
    }

    /**
     * 매장 목록 조회 및 검증
     */
    async verifyStoreList() {
        const storeCount = await this.storeRows.count();
        console.log(`📊 매장 목록: ${storeCount}개 매장 확인`);
        
        // 최소 1개 이상의 매장이 있어야 함
        expect(storeCount).toBeGreaterThan(0);
        
        return storeCount;
    }

    /**
     * 특정 매장의 액션 버튼들 확인
     */
    async verifyStoreActions(storeId) {
        const actions = {
            edit: await this.editButton(storeId).isVisible(),
            createAccount: await this.createAccountButton(storeId).isVisible(),
            performance: await this.performanceButton(storeId).isVisible(),
            delete: await this.deleteButton(storeId).isVisible()
        };
        
        console.log(`🏪 매장 ${storeId} 액션 버튼:`, actions);
        return actions;
    }

    /**
     * 매장 정보 수정
     */
    async editStoreInfo(storeId, updateData) {
        console.log(`✏️ 매장 ${storeId} 정보 수정 시작`);
        
        // 수정 버튼 클릭
        await this.editButton(storeId).click();
        await this.waitForElement(this.editForm, '수정 폼 로딩');
        
        // 필드 업데이트
        if (updateData.name) {
            await this.storeNameInput.fill(updateData.name);
        }
        if (updateData.owner_name) {
            await this.ownerNameInput.fill(updateData.owner_name);
        }
        if (updateData.phone) {
            await this.phoneInput.fill(updateData.phone);
        }
        if (updateData.address) {
            await this.addressInput.fill(updateData.address);
        }
        if (updateData.status) {
            await this.statusSelect.selectOption(updateData.status);
        }
        
        // 저장
        await this.saveButton.click();
        
        // 성공 메시지 대기
        await this.waitForSuccessMessage('매장 정보가 성공적으로 수정되었습니다');
        console.log('✅ 매장 정보 수정 완료');
    }

    /**
     * 매장 계정 생성
     */
    async createStoreAccount(storeId, accountData) {
        console.log(`👤 매장 ${storeId} 계정 생성 시작`);
        
        // 계정 생성 버튼 클릭
        await this.createAccountButton(storeId).click();
        await this.waitForElement(this.accountForm, '계정 생성 폼 로딩');
        
        // 계정 정보 입력
        await this.userNameInput.fill(accountData.name);
        await this.emailInput.fill(accountData.email);
        await this.passwordInput.fill(accountData.password);
        await this.roleSelect.selectOption(accountData.role || 'store');
        
        // 저장
        await this.saveButton.click();
        
        // 성공 메시지 대기
        await this.waitForSuccessMessage('계정이 성공적으로 생성되었습니다');
        console.log('✅ 매장 계정 생성 완료');
    }

    /**
     * 매장 성과 조회
     */
    async viewStorePerformance(storeId) {
        console.log(`📈 매장 ${storeId} 성과 조회 시작`);
        
        // 성과 버튼 클릭
        await this.performanceButton(storeId).click();
        await this.waitForElement(this.performanceModal, '성과 모달 로딩');
        
        // 성과 데이터 수집
        const performance = {
            thisMonth: await this.thisMonthStats.textContent(),
            lastMonth: await this.lastMonthStats.textContent(),
            growthRate: await this.growthRate.textContent()
        };
        
        console.log('📊 매장 성과 데이터:', performance);
        return performance;
    }

    /**
     * 매장 삭제
     */
    async deleteStore(storeId, expectSoftDelete = false) {
        console.log(`🗑️ 매장 ${storeId} 삭제 시작`);
        
        // 삭제 버튼 클릭
        await this.deleteButton(storeId).click();
        await this.waitForElement(this.deleteConfirmModal, '삭제 확인 모달 로딩');
        
        // 삭제 확인
        await this.confirmDeleteButton.click();
        
        // 결과 메시지 확인
        if (expectSoftDelete) {
            await this.waitForSuccessMessage('판매 데이터가 있어 매장이 비활성화되었습니다');
            console.log('✅ 매장 비활성화 완료 (소프트 삭제)');
        } else {
            await this.waitForSuccessMessage('매장이 완전히 삭제되었습니다');
            console.log('✅ 매장 완전 삭제 완료');
        }
    }

    /**
     * API 호출 모니터링
     */
    async monitorApiCall(expectedUrl, expectedMethod = 'GET') {
        const apiCall = await this.page.waitForResponse(response => 
            response.url().includes(expectedUrl) && 
            response.request().method() === expectedMethod
        );
        
        const status = apiCall.status();
        const data = await apiCall.json().catch(() => null);
        
        console.log(`🔌 API 호출: ${expectedMethod} ${expectedUrl} - ${status}`);
        if (data) {
            console.log('📋 응답 데이터:', data);
        }
        
        return { status, data };
    }

    /**
     * 권한 확인 (본사 전용 기능)
     */
    async verifyHeadquartersPermissions() {
        const permissions = {
            canEdit: await this.editButton('1').isVisible(),
            canCreateAccount: await this.createAccountButton('1').isVisible(),
            canViewPerformance: await this.performanceButton('1').isVisible(),
            canDelete: await this.deleteButton('1').isVisible()
        };
        
        console.log('🏢 본사 권한 확인:', permissions);
        
        // 본사는 모든 권한을 가져야 함
        expect(permissions.canEdit).toBeTruthy();
        expect(permissions.canCreateAccount).toBeTruthy();
        expect(permissions.canViewPerformance).toBeTruthy();
        expect(permissions.canDelete).toBeTruthy();
        
        return permissions;
    }

    /**
     * 에러 처리 확인
     */
    async verifyErrorHandling(expectedErrorMessage) {
        const errorElement = this.page.locator('.error, .alert-danger, [role="alert"]');
        await this.waitForElement(errorElement, '에러 메시지 대기');
        
        const errorText = await errorElement.textContent();
        expect(errorText).toContain(expectedErrorMessage);
        
        console.log('❌ 에러 메시지 확인:', errorText);
    }
}