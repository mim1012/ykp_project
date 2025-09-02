import { expect } from '@playwright/test';

/**
 * 모든 페이지 객체가 상속받을 베이스 클래스
 * YKP 시스템 공통 기능 제공
 */
export class BasePage {
    constructor(page) {
        this.page = page;
        this.baseURL = 'http://127.0.0.1:8000';
        
        // 공통 UI 요소들
        this.loadingSpinner = page.locator('.loading-spinner, .text-center:has-text("로딩 중")');
        this.errorMessage = page.locator('.alert-danger, .toast-error, .text-red-500');
        this.successMessage = page.locator('.alert-success, .toast-success, .text-green-500');
        this.confirmDialog = page.locator('.confirm-dialog');
        
        // YKP 시스템 공통 요소
        this.userInfo = page.locator('.user-info, .welcome-message');
        this.navigationMenu = page.locator('.main-navigation, .sidebar');
        this.logoutButton = page.locator('button:has-text("로그아웃")');
    }

    /**
     * 페이지 로딩 완료 대기
     */
    async waitForPageLoad(timeout = 10000) {
        await this.page.waitForLoadState('networkidle', { timeout });
        
        // 로딩 스피너가 있다면 사라질 때까지 대기
        try {
            await this.loadingSpinner.waitFor({ state: 'hidden', timeout: 5000 });
        } catch (error) {
            // 로딩 스피너가 없으면 무시
        }
    }

    /**
     * 스크린샷 촬영 (디버깅용)
     */
    async takeScreenshot(name) {
        const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
        await this.page.screenshot({ 
            path: `test-results/screenshots/${name}-${timestamp}.png`,
            fullPage: true 
        });
    }

    /**
     * 에러 메시지가 없는지 확인
     */
    async verifyNoErrors() {
        await expect(this.errorMessage).not.toBeVisible();
    }

    /**
     * 성공 메시지 확인
     */
    async verifySuccessMessage(expectedMessage = null) {
        await expect(this.successMessage).toBeVisible({ timeout: 10000 });
        if (expectedMessage) {
            await expect(this.successMessage).toContainText(expectedMessage);
        }
    }

    /**
     * 특정 URL로 이동
     */
    async navigateToUrl(url) {
        const fullUrl = url.startsWith('http') ? url : `${this.baseURL}${url}`;
        await this.page.goto(fullUrl);
        await this.waitForPageLoad();
    }

    /**
     * 모달 또는 다이얼로그 확인 처리
     */
    async handleConfirmDialog(accept = true) {
        this.page.on('dialog', dialog => {
            if (accept) {
                dialog.accept();
            } else {
                dialog.dismiss();
            }
        });
    }

    /**
     * 테이블 로딩 완료 대기
     */
    async waitForTableLoad(tableSelector, minRows = 1) {
        await this.page.waitForSelector(tableSelector);
        await this.page.waitForFunction((selector, min) => {
            const table = document.querySelector(selector);
            const rows = table ? table.querySelectorAll('tbody tr') : [];
            return rows.length >= min;
        }, tableSelector, minRows);
    }

    /**
     * API 응답 모니터링
     */
    async monitorApiResponse(urlPattern) {
        return new Promise((resolve) => {
            this.page.on('response', response => {
                if (response.url().includes(urlPattern)) {
                    resolve({
                        status: response.status(),
                        url: response.url(),
                        method: response.request().method()
                    });
                }
            });
        });
    }

    /**
     * 사용자 권한 확인
     */
    async verifyUserRole(expectedRole) {
        const userData = await this.page.evaluate(() => window.userData);
        expect(userData.role).toBe(expectedRole);
        return userData;
    }

    /**
     * 로그아웃 수행
     */
    async logout() {
        await this.logoutButton.click();
        
        // 확인 다이얼로그가 있으면 처리
        try {
            await this.page.waitForEvent('dialog', { timeout: 2000 });
        } catch (error) {
            // 다이얼로그 없으면 무시
        }
        
        await this.page.waitForURL('**/login');
    }
}

export default BasePage;