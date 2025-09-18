import { expect } from '@playwright/test';
import { BasePage } from './BasePage.js';

/**
 * 로그인 페이지 객체 모델
 * YKP 시스템 인증 기능 담당
 */
export class LoginPage extends BasePage {
    constructor(page) {
        super(page);
        
        // 로그인 폼 요소들
        this.emailInput = page.locator('input[name="email"], input[type="email"]');
        this.passwordInput = page.locator('input[name="password"], input[type="password"]');
        this.loginButton = page.locator('button[type="submit"], button:has-text("로그인")');
        this.rememberMeCheckbox = page.locator('input[type="checkbox"]');
        
        // 빠른 로그인 링크들
        this.quickLoginLinks = {
            headquarters: page.locator('a[href="/quick-login/headquarters"]'),
            branch: page.locator('a[href="/quick-login/branch"]'),
            store: page.locator('a[href="/quick-login/store"]')
        };
        
        // 테스트 계정 정보 (개발용)
        this.testAccounts = {
            headquarters: { email: 'hq@ykp.com', password: '123456' },
            branch: { email: 'branch@ykp.com', password: '123456' },
            store: { email: 'store@ykp.com', password: '123456' }
        };
        
        // 로그인 관련 메시지
        this.loginError = page.locator('.login-error, .alert-danger');
        this.forgotPasswordLink = page.locator('a:has-text("비밀번호 찾기")');
        this.registerLink = page.locator('a:has-text("회원가입")');
    }

    /**
     * 기본 로그인 수행
     */
    async login(email, password) {
        console.log(`🔑 로그인 시도: ${email}`);
        
        await this.emailInput.fill(email);
        await this.passwordInput.fill(password);
        await this.loginButton.click();
        
        // 로그인 처리 대기
        await this.page.waitForTimeout(2000);
        
        console.log(`✅ 로그인 완료: ${email}`);
    }

    /**
     * 빠른 로그인 (테스트용)
     */
    async quickLogin(role) {
        console.log(`⚡ 빠른 로그인: ${role}`);
        
        await this.navigateToUrl(`/quick-login/${role}`);
        
        // 대시보드로 리다이렉트 대기
        await this.page.waitForURL('**/dashboard', { timeout: 10000 });
        
        console.log(`✅ 빠른 로그인 완료: ${role}`);
    }

    /**
     * 테스트 계정으로 로그인
     */
    async loginWithTestAccount(role) {
        const account = this.testAccounts[role];
        if (!account) {
            throw new Error(`알 수 없는 역할: ${role}`);
        }
        
        await this.navigateToUrl('/login');
        await this.login(account.email, account.password);
    }

    /**
     * 로그인 성공 검증
     */
    async verifyLoginSuccess(expectedRole) {
        // 대시보드로 리다이렉트 확인
        await expect(this.page).toHaveURL(/.*dashboard.*/, { timeout: 10000 });
        
        // 사용자 정보 로드 대기
        await this.page.waitForTimeout(2000);
        
        // 권한별 대시보드 요소 확인
        const userData = await this.verifyUserRole(expectedRole);
        
        // 권한별 UI 확인
        await this.verifyRoleSpecificUI(expectedRole, userData);
        
        console.log(`✅ ${expectedRole} 권한 로그인 검증 완료`);
        return userData;
    }

    /**
     * 권한별 UI 요소 확인
     */
    async verifyRoleSpecificUI(role, userData) {
        const roleDisplayMap = {
            headquarters: '🏢 본사 관리자',
            branch: '🏬 지사 관리자', 
            store: '🏪 매장 직원'
        };
        
        const expectedRoleText = roleDisplayMap[role];
        if (expectedRoleText) {
            const roleIndicator = this.page.locator(`text="${expectedRoleText}"`);
            await expect(roleIndicator).toBeVisible();
        }

        // 권한별 접근 가능 메뉴 확인
        switch(role) {
            case 'headquarters':
                await expect(this.page.locator('text="전체 매장 관리 권한"')).toBeVisible();
                break;
            case 'branch':
                const branchName = userData.branch_name || '서울지사';
                await expect(this.page.locator(`text="${branchName} 소속 매장 관리"`)).toBeVisible();
                break;
            case 'store':
                const storeName = userData.store_name || '서울 1호점';
                await expect(this.page.locator(`text="${storeName}"`)).toBeVisible();
                break;
        }
    }

    /**
     * 로그인 실패 검증
     */
    async verifyLoginFailure(expectedErrorMessage = null) {
        // 로그인 페이지에 그대로 있는지 확인
        await expect(this.page).toHaveURL(/.*login.*/);
        
        // 에러 메시지 확인
        await expect(this.loginError).toBeVisible();
        
        if (expectedErrorMessage) {
            await expect(this.loginError).toContainText(expectedErrorMessage);
        }
        
        console.log('✅ 로그인 실패 검증 완료');
    }

    /**
     * 잘못된 계정 로그인 시도
     */
    async attemptInvalidLogin() {
        await this.navigateToUrl('/login');
        await this.login('invalid@ykp.com', 'wrongpassword');
        await this.verifyLoginFailure('로그인 정보가 올바르지 않습니다');
    }

    /**
     * 로그아웃 수행
     */
    async performLogout() {
        await this.logout();
        console.log('✅ 로그아웃 완료');
    }
}

export default LoginPage;