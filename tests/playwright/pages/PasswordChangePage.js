import { expect } from '@playwright/test';
import { BasePage } from './BasePage.js';

/**
 * 비밀번호 변경 페이지 객체 모델
 * 사용자 비밀번호 변경 모달 기능 담당
 */
export class PasswordChangePage extends BasePage {
    constructor(page) {
        super(page);

        // 사용자 프로필 요소들
        this.userAvatar = page.locator('button.bg-gradient-to-br, div.bg-gradient-to-br').first();
        this.passwordMenuItem = page.locator('button:has-text("비밀번호 변경")');

        // 모달 요소들
        this.modal = page.locator('div.fixed.inset-0.bg-black.bg-opacity-50');
        this.modalContent = page.locator('div.bg-white.rounded-lg.shadow-xl');
        this.modalTitle = page.locator('h2:has-text("비밀번호 변경")');
        this.closeButton = page.locator('button:has(svg path[d*="M6 18L18 6"])');

        // 폼 입력 요소들
        this.currentPasswordInput = page.locator('input[name="current_password"]');
        this.newPasswordInput = page.locator('input[name="password"]');
        this.confirmPasswordInput = page.locator('input[name="password_confirmation"]');

        // 비밀번호 강도 표시 요소들
        this.strengthIndicators = {
            minLength: page.locator('text=최소 8자').locator('..'),
            hasLetter: page.locator('text=영문 포함').locator('..'),
            hasNumber: page.locator('text=숫자 포함').locator('..'),
            hasSymbol: page.locator('text=특수문자 포함').locator('..')
        };

        // 버튼들
        this.submitButton = page.locator('button[type="submit"]:has-text("비밀번호 변경")');
        this.cancelButton = page.locator('button:has-text("취소")');
        this.loadingButton = page.locator('button:has-text("변경 중...")');

        // 메시지 요소들
        this.successMessage = page.locator('text=비밀번호가 성공적으로 변경되었습니다');
        this.successContainer = page.locator('div.bg-green-50');
        this.errorMessage = page.locator('[class*="text-red"]');
        this.errorContainer = page.locator('div.bg-red-50');
        this.fieldError = page.locator('p.text-red-600');
    }

    /**
     * 사용자 프로필 메뉴에서 비밀번호 변경 모달 열기
     */
    async openPasswordChangeModal() {
        console.log('🔒 비밀번호 변경 모달 열기 시작');

        // 사용자 아바타 클릭
        await this.userAvatar.click();

        // 드롭다운 메뉴 애니메이션 대기
        await this.page.waitForTimeout(500);

        // "비밀번호 변경" 메뉴 아이템 클릭
        await this.passwordMenuItem.click();

        // 모달 표시 대기
        await this.modal.waitFor({ state: 'visible', timeout: 5000 });
        await this.modalTitle.waitFor({ state: 'visible' });

        console.log('✅ 비밀번호 변경 모달 열림');
    }

    /**
     * 비밀번호 변경 수행
     */
    async changePassword(currentPwd, newPwd, confirmPwd) {
        console.log(`🔑 비밀번호 변경 시도`);

        // 입력 필드 채우기
        await this.currentPasswordInput.fill(currentPwd);
        await this.newPasswordInput.fill(newPwd);
        await this.confirmPasswordInput.fill(confirmPwd);

        // 제출 버튼 클릭
        await this.submitButton.click();

        console.log('📤 비밀번호 변경 요청 전송');
    }

    /**
     * 비밀번호 강도 표시 검증
     * @param {Object} expectedStrength - { minLength: true/false, hasLetter: true/false, ... }
     */
    async verifyPasswordStrength(expectedStrength) {
        console.log('🔍 비밀번호 강도 표시 검증 중');

        for (const [key, shouldBeGreen] of Object.entries(expectedStrength)) {
            const indicator = this.strengthIndicators[key];

            if (shouldBeGreen) {
                // 초록색이어야 함
                await expect(indicator).toHaveClass(/text-green-600/);
            } else {
                // 회색이어야 함
                await expect(indicator).toHaveClass(/text-gray-400/);
            }
        }

        console.log('✅ 비밀번호 강도 표시 검증 완료');
    }

    /**
     * 성공 메시지 확인
     */
    async verifySuccessMessage() {
        console.log('🎉 성공 메시지 확인 중');

        await expect(this.successContainer).toBeVisible({ timeout: 5000 });
        await expect(this.successMessage).toBeVisible();

        console.log('✅ 성공 메시지 확인됨');
    }

    /**
     * 에러 메시지 확인
     * @param {string} errorText - 예상되는 에러 메시지 텍스트
     */
    async verifyErrorMessage(errorText) {
        console.log(`❌ 에러 메시지 확인 중: "${errorText}"`);

        // 에러 컨테이너 또는 필드 에러 중 하나가 표시되어야 함
        const errorLocator = this.page.locator(`text=${errorText}`).first();
        await expect(errorLocator).toBeVisible({ timeout: 5000 });

        console.log('✅ 에러 메시지 확인됨');
    }

    /**
     * 모달이 자동으로 닫힐 때까지 대기
     */
    async waitForModalAutoClose() {
        console.log('⏳ 모달 자동 닫힘 대기 (2초 타이머)');

        // 성공 후 2초 대기
        await this.page.waitForTimeout(2500);

        // 모달이 사라졌는지 확인
        await expect(this.modal).not.toBeVisible({ timeout: 1000 });

        console.log('✅ 모달 자동 닫힘 완료');
    }

    /**
     * 모달 수동으로 닫기 (X 버튼 또는 취소 버튼)
     * @param {string} method - 'close' (X버튼) 또는 'cancel' (취소버튼)
     */
    async closeModal(method = 'close') {
        console.log(`🚪 모달 닫기: ${method}`);

        if (method === 'close') {
            await this.closeButton.click();
        } else if (method === 'cancel') {
            await this.cancelButton.click();
        }

        // 모달 닫힘 대기
        await expect(this.modal).not.toBeVisible({ timeout: 2000 });

        console.log('✅ 모달 닫힘 완료');
    }

    /**
     * 폼 입력값이 초기화되었는지 확인
     */
    async verifyFormReset() {
        console.log('🔄 폼 초기화 확인 중');

        // 모달 다시 열기
        await this.openPasswordChangeModal();

        // 모든 입력 필드가 비어있는지 확인
        await expect(this.currentPasswordInput).toHaveValue('');
        await expect(this.newPasswordInput).toHaveValue('');
        await expect(this.confirmPasswordInput).toHaveValue('');

        console.log('✅ 폼 초기화 확인됨');
    }

    /**
     * 로딩 중 상태 확인
     */
    async verifyLoadingState() {
        console.log('⏳ 로딩 상태 확인 중');

        // "변경 중..." 텍스트 표시 확인
        await expect(this.loadingButton).toBeVisible({ timeout: 2000 });

        // 제출 버튼 비활성화 확인
        await expect(this.submitButton).toBeDisabled();

        console.log('✅ 로딩 상태 확인됨');
    }

    /**
     * 모달이 현재 열려있는지 확인
     */
    async isModalOpen() {
        return await this.modal.isVisible();
    }

    /**
     * 특정 입력 필드의 에러 상태 확인
     * @param {string} fieldName - 'current_password', 'password', 'password_confirmation'
     */
    async verifyFieldHasError(fieldName) {
        console.log(`🔍 "${fieldName}" 필드 에러 확인 중`);

        let fieldLocator;
        if (fieldName === 'current_password') {
            fieldLocator = this.currentPasswordInput;
        } else if (fieldName === 'password') {
            fieldLocator = this.newPasswordInput;
        } else if (fieldName === 'password_confirmation') {
            fieldLocator = this.confirmPasswordInput;
        }

        // 에러 상태일 때 border-red-500 클래스 확인
        await expect(fieldLocator).toHaveClass(/border-red-500/);

        console.log(`✅ "${fieldName}" 필드 에러 상태 확인됨`);
    }

    /**
     * 완전한 비밀번호 변경 플로우 (헬퍼 메서드)
     * @param {string} currentPwd
     * @param {string} newPwd
     * @param {string} confirmPwd
     * @returns {Promise<boolean>} 성공 여부
     */
    async performPasswordChange(currentPwd, newPwd, confirmPwd) {
        await this.openPasswordChangeModal();
        await this.changePassword(currentPwd, newPwd, confirmPwd);

        // 성공 또는 실패 대기
        try {
            await this.verifySuccessMessage();
            await this.waitForModalAutoClose();
            return true;
        } catch (error) {
            // 에러 발생 시 실패로 간주
            return false;
        }
    }
}
