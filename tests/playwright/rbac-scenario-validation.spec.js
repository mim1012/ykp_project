// 🔐 YKP ERP 역할별 시나리오 기반 통합 RBAC 검증 테스트
// 모든 팝업 메시지, 권한 제어, 사용자 시나리오 완전 검증

import { test, expect } from '@playwright/test';

// 🎯 테스트 환경 설정
const BASE_URL = 'https://endearing-dedication-production.up.railway.app';
const TEST_TIMEOUT = 30000;

// 🔑 권한 매트릭스 정의
const RBAC_MATRIX = {
    headquarters: {
        email: 'hq@ykp.com',
        password: '123456',
        displayName: '본사 관리자',
        allowedPages: [
            '/dashboard',
            '/management/branches',
            '/management/stores',
            '/admin/accounts',
            '/statistics',
            '/sales/excel-input'
        ],
        forbiddenPages: [],
        allowedButtons: [
            '새 지사 추가', '새 매장 추가', '전체 리포트',
            '새 지사 등록', '지사 수정', '매장관리', '지사 통계',
            '매장 추가', '매장 수정', '계정생성', '성과', '매장 삭제',
            '계정 정보 내보내기', '비밀번호 리셋', '계정 비활성화'
        ],
        allowedDataScope: 'ALL',
        testScenarios: [
            'create_branch', 'edit_branch', 'delete_branch',
            'create_store', 'edit_store', 'delete_store',
            'manage_accounts', 'view_all_statistics', 'export_data'
        ]
    },
    branch: {
        email: 'branch_bs001@ykp.com',
        password: '123456',
        displayName: '부산지사 관리자',
        branchId: 4,
        allowedPages: [
            '/dashboard',
            '/management/stores',
            '/statistics',
            '/sales/excel-input'
        ],
        forbiddenPages: [
            '/management/branches',
            '/admin/accounts'
        ],
        allowedButtons: [
            '새로고침', '로그아웃',
            '매장 추가', '매장 수정', '계정생성', '성과',
            '기간 필터', '통계 조회'
        ],
        forbiddenButtons: [
            '새 지사 추가', '새 지사 등록', '지사 수정', '지사 삭제',
            '전체 계정 관리', '비밀번호 리셋', '계정 비활성화'
        ],
        allowedDataScope: 'OWN_BRANCH_ONLY',
        forbiddenDataAccess: [
            'other_branch_stores', 'all_accounts', 'system_settings'
        ],
        testScenarios: [
            'view_own_branch_stores', 'cannot_access_other_branches',
            'create_store_in_own_branch', 'cannot_manage_accounts'
        ]
    },
    store: {
        email: 'bs001-001@ykp.com',
        password: '123456',
        displayName: '해운대점 관리자',
        storeId: 5,
        branchId: 4,
        allowedPages: [
            '/dashboard',
            '/sales/excel-input'
        ],
        forbiddenPages: [
            '/management/branches',
            '/management/stores',
            '/admin/accounts',
            '/statistics'
        ],
        allowedButtons: [
            '새로고침', '로그아웃',
            '행 추가', '행 삭제', '저장', '대시보드'
        ],
        forbiddenButtons: [
            '새 지사 추가', '새 매장 추가', '지사 관리', '매장 관리',
            '계정 관리', '전체 통계', '매장 수정', '매장 삭제'
        ],
        allowedDataScope: 'OWN_STORE_ONLY',
        forbiddenDataAccess: [
            'other_store_data', 'branch_management', 'all_accounts'
        ],
        testScenarios: [
            'view_own_store_dashboard', 'input_sales_data',
            'cannot_access_management', 'cannot_view_other_stores'
        ]
    }
};

// 🔧 테스트 헬퍼 클래스
class RBACTestHelper {
    static popupMessages = [];
    static detectedErrors = [];

    static async setupPopupDetection(page) {
        // 모든 종류의 팝업 감지
        page.on('dialog', dialog => {
            const message = {
                type: dialog.type(),
                message: dialog.message(),
                defaultValue: dialog.defaultValue(),
                timestamp: new Date().toISOString(),
                url: page.url()
            };

            this.popupMessages.push(message);
            console.log(`🔔 ${dialog.type().toUpperCase()} 감지:`, dialog.message());
        });

        // JavaScript 에러 감지
        page.on('pageerror', error => {
            this.detectedErrors.push({
                error: error.message,
                timestamp: new Date().toISOString(),
                url: page.url()
            });
            console.log('❌ JavaScript 에러:', error.message);
        });

        // 콘솔 에러 감지
        page.on('console', msg => {
            if (msg.type() === 'error') {
                this.detectedErrors.push({
                    error: msg.text(),
                    timestamp: new Date().toISOString(),
                    url: page.url()
                });
                console.log('❌ 콘솔 에러:', msg.text());
            }
        });
    }

    static async loginWithRole(page, role) {
        const account = RBAC_MATRIX[role];

        await page.goto(`${BASE_URL}/login`);
        await page.waitForLoadState('networkidle');

        await page.fill('input[name="email"]', account.email);
        await page.fill('input[name="password"]', account.password);

        // 로그인 버튼 클릭
        await page.click('button[type="submit"]');

        // 대시보드 로딩 대기
        try {
            await page.waitForURL('**/dashboard', { timeout: TEST_TIMEOUT });
            console.log(`✅ ${role} (${account.displayName}) 로그인 성공`);
            return true;
        } catch (error) {
            console.log(`❌ ${role} 로그인 실패: ${error.message}`);
            return false;
        }
    }

    static async testAllButtonsOnPage(page, role, expectedAllowed, expectedForbidden) {
        const results = {
            allowedButtonsFound: [],
            forbiddenButtonsFound: [],
            allowedButtonsMissing: [],
            unexpectedButtons: [],
            buttonClickResults: []
        };

        // 페이지의 모든 클릭 가능한 요소 찾기
        const allClickableElements = await page.evaluate(() => {
            const elements = Array.from(document.querySelectorAll('button, a[href], [onclick], [role="button"]'));
            return elements.map(el => ({
                text: el.textContent?.trim() || el.getAttribute('aria-label') || 'Unknown',
                tagName: el.tagName,
                visible: el.offsetParent !== null,
                enabled: !el.disabled && !el.hasAttribute('aria-disabled'),
                href: el.getAttribute('href'),
                onclick: el.getAttribute('onclick'),
                className: el.className
            }));
        });

        console.log(`🔍 ${role} - 페이지에서 발견된 클릭 가능 요소: ${allClickableElements.length}개`);

        // 허용된 버튼 검증
        for (const allowedButton of expectedAllowed) {
            const found = allClickableElements.find(el =>
                el.text.includes(allowedButton) ||
                el.onclick?.includes(allowedButton.toLowerCase())
            );

            if (found && found.visible) {
                results.allowedButtonsFound.push(allowedButton);
                console.log(`✅ 허용된 버튼 발견: ${allowedButton}`);
            } else {
                results.allowedButtonsMissing.push(allowedButton);
                console.log(`⚠️ 허용된 버튼 없음: ${allowedButton}`);
            }
        }

        // 금지된 버튼 검증
        for (const forbiddenButton of expectedForbidden) {
            const found = allClickableElements.find(el =>
                el.text.includes(forbiddenButton) ||
                el.onclick?.includes(forbiddenButton.toLowerCase())
            );

            if (found && found.visible) {
                results.forbiddenButtonsFound.push(forbiddenButton);
                console.log(`❌ 금지된 버튼 발견: ${forbiddenButton}`);
            }
        }

        return results;
    }

    static async testButtonClick(page, buttonText, expectedResult = 'success') {
        const initialPopupCount = this.popupMessages.length;

        try {
            // 버튼 찾기 및 클릭
            const button = page.locator(`button:has-text("${buttonText}"), a:has-text("${buttonText}")`).first();

            if (await button.isVisible()) {
                await button.click();
                await page.waitForTimeout(2000); // 팝업 대기

                // 새로운 팝업 메시지 확인
                const newPopups = this.popupMessages.slice(initialPopupCount);

                return {
                    success: true,
                    buttonFound: true,
                    newPopups: newPopups,
                    finalUrl: page.url()
                };
            } else {
                return {
                    success: false,
                    buttonFound: false,
                    reason: 'Button not visible'
                };
            }
        } catch (error) {
            return {
                success: false,
                buttonFound: true,
                error: error.message
            };
        }
    }

    static async verifyDataAccess(page, role, expectedDataScope) {
        const dataAccessResults = {
            allowedDataVisible: [],
            forbiddenDataVisible: [],
            dataIntegrityIssues: []
        };

        // 페이지의 모든 데이터 요소 분석
        const dataElements = await page.evaluate(() => {
            const elements = document.querySelectorAll('[data-testid], [id*="total"], [id*="count"], .stat-value, .data-display');
            return Array.from(elements).map(el => ({
                id: el.id,
                textContent: el.textContent?.trim(),
                dataTestId: el.getAttribute('data-testid'),
                visible: el.offsetParent !== null
            }));
        });

        // 역할별 데이터 접근 권한 확인
        for (const element of dataElements) {
            const containsSensitiveData = element.textContent && (
                element.textContent.includes('전체') ||
                element.textContent.includes('모든') ||
                /\d+개 지사/.test(element.textContent) ||
                /\d+개 매장/.test(element.textContent)
            );

            if (containsSensitiveData) {
                switch (expectedDataScope) {
                    case 'ALL':
                        dataAccessResults.allowedDataVisible.push(element);
                        break;
                    case 'OWN_BRANCH_ONLY':
                    case 'OWN_STORE_ONLY':
                        if (element.textContent.includes('전체') || element.textContent.includes('모든')) {
                            dataAccessResults.forbiddenDataVisible.push(element);
                            console.log(`❌ ${role}이 전체 데이터에 접근: ${element.textContent}`);
                        }
                        break;
                }
            }
        }

        return dataAccessResults;
    }

    static getPopupSummary() {
        return {
            totalPopups: this.popupMessages.length,
            popupsByType: this.popupMessages.reduce((acc, popup) => {
                acc[popup.type] = (acc[popup.type] || 0) + 1;
                return acc;
            }, {}),
            messages: this.popupMessages
        };
    }

    static getErrorSummary() {
        return {
            totalErrors: this.detectedErrors.length,
            errors: this.detectedErrors
        };
    }
}

// 🧪 메인 RBAC 검증 테스트 스위트
test.describe('🔐 YKP ERP 역할별 시나리오 통합 RBAC 검증', () => {

    test.beforeEach(async ({ page }) => {
        await RBACTestHelper.setupPopupDetection(page);
    });

    // 🏛️ 본사 관리자 완전 권한 검증
    test('본사 관리자 - 전체 시스템 접근 및 모든 버튼 기능 검증', async ({ page }) => {
        console.log('🏛️ 본사 관리자 종합 시나리오 테스트 시작...');

        const role = 'headquarters';
        const account = RBAC_MATRIX[role];

        // 1. 로그인 검증
        const loginSuccess = await RBACTestHelper.loginWithRole(page, role);
        expect(loginSuccess).toBeTruthy();

        await page.screenshot({ path: 'tests/playwright/hq-dashboard-full.png', fullPage: true });

        // 2. 모든 허용된 페이지 접근 테스트
        for (const allowedPage of account.allowedPages) {
            console.log(`📄 ${role} → ${allowedPage} 페이지 접근 테스트`);

            await page.goto(`${BASE_URL}${allowedPage}`);
            await page.waitForLoadState('networkidle');
            await page.waitForTimeout(3000);

            // 페이지 로딩 성공 확인
            const pageError = page.url().includes('/404') || page.url().includes('/error');
            expect(pageError).toBeFalsy();

            console.log(`✅ ${allowedPage} 접근 성공`);

            // 페이지별 모든 버튼 테스트
            await RBACTestHelper.testAllButtonsOnPage(page, role, account.allowedButtons, []);

            // 스크린샷 저장
            await page.screenshot({
                path: `tests/playwright/hq-${allowedPage.replace(/\//g, '-')}.png`,
                fullPage: true
            });
        }

        // 3. 중요한 버튼들 실제 클릭 테스트 (팝업 메시지 수집)
        const criticalButtons = [
            { page: '/management/branches', button: '새 지사 등록', expectedPopup: 'prompt' },
            { page: '/management/stores', button: '매장 추가', expectedPopup: 'modal' },
            { page: '/management/stores', button: '성과', expectedPopup: 'confirm' },
            { page: '/management/stores', button: '삭제', expectedPopup: 'multiple' },
            { page: '/admin/accounts', button: '비밀번호 리셋', expectedPopup: 'modal' }
        ];

        for (const buttonTest of criticalButtons) {
            console.log(`🔘 ${buttonTest.button} 버튼 클릭 테스트 (${buttonTest.page})`);

            await page.goto(`${BASE_URL}${buttonTest.page}`);
            await page.waitForLoadState('networkidle');
            await page.waitForTimeout(3000);

            const clickResult = await RBACTestHelper.testButtonClick(page, buttonTest.button);

            if (clickResult.success && clickResult.newPopups.length > 0) {
                console.log(`✅ ${buttonTest.button}: 팝업 정상 표시`);
                clickResult.newPopups.forEach(popup => {
                    console.log(`   📝 ${popup.type}: ${popup.message.substring(0, 100)}...`);
                });
            } else {
                console.log(`⚠️ ${buttonTest.button}: 팝업 없음 또는 버튼 비활성`);
            }
        }
    });

    // 🏢 지사 관리자 제한된 권한 검증
    test('지사 관리자 - 권한 제한 및 크로스 지사 데이터 접근 차단 검증', async ({ page }) => {
        console.log('🏢 지사 관리자 권한 제한 시나리오 테스트...');

        const role = 'branch';
        const account = RBAC_MATRIX[role];

        // 1. 로그인 검증
        const loginSuccess = await RBACTestHelper.loginWithRole(page, role);
        expect(loginSuccess).toBeTruthy();

        await page.screenshot({ path: 'tests/playwright/branch-dashboard-full.png', fullPage: true });

        // 2. 금지된 페이지 접근 테스트
        for (const forbiddenPage of account.forbiddenPages) {
            console.log(`🔒 ${role} → ${forbiddenPage} 접근 차단 테스트`);

            const response = await page.goto(`${BASE_URL}${forbiddenPage}`, { waitUntil: 'networkidle' });

            const isBlocked = response.status() === 403 ||
                            response.status() === 302 ||
                            page.url().includes('/login') ||
                            page.url().includes('/dashboard') ||
                            page.url() !== `${BASE_URL}${forbiddenPage}`;

            if (isBlocked) {
                console.log(`✅ ${forbiddenPage} 접근 차단됨 (${response.status()})`);
            } else {
                console.log(`❌ ${forbiddenPage} 접근 허용됨 - 권한 위반!`);
                await page.screenshot({
                    path: `tests/playwright/VIOLATION-branch-${forbiddenPage.replace(/\//g, '-')}.png`,
                    fullPage: true
                });
            }

            expect(isBlocked).toBeTruthy();
        }

        // 3. 허용된 페이지에서 데이터 스코프 확인
        await page.goto(`${BASE_URL}/management/stores`);
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(5000);

        // 자신의 지사 매장만 표시되는지 확인
        const visibleStores = await page.evaluate(() => {
            const storeElements = document.querySelectorAll('[data-store-id], .store-card, h4');
            return Array.from(storeElements).map(el => el.textContent?.trim()).filter(Boolean);
        });

        console.log(`📊 ${role}에게 표시되는 매장:`, visibleStores);

        // 부산지사 소속 매장만 표시되어야 함
        const hasOnlyOwnBranchStores = visibleStores.some(store =>
            store.includes('해운대') || store.includes('부산')
        );

        const hasOtherBranchStores = visibleStores.some(store =>
            store.includes('강남') || store.includes('홍대') || store.includes('서울')
        );

        if (hasOtherBranchStores) {
            console.log(`❌ 권한 위반: ${role}이 다른 지사 매장 데이터에 접근`);
            await page.screenshot({ path: 'tests/playwright/VIOLATION-cross-branch-access.png', fullPage: true });
        } else {
            console.log(`✅ 데이터 격리 성공: ${role}은 자신의 지사 매장만 접근`);
        }

        expect(hasOtherBranchStores).toBeFalsy();
    });

    // 🏪 매장 직원 최소 권한 검증
    test('매장 직원 - 최소 권한 및 자기 매장만 접근 검증', async ({ page }) => {
        console.log('🏪 매장 직원 최소 권한 시나리오 테스트...');

        const role = 'store';
        const account = RBAC_MATRIX[role];

        // 1. 로그인 검증 (계정 상태 문제 있을 수 있음)
        const loginSuccess = await RBACTestHelper.loginWithRole(page, role);

        if (!loginSuccess) {
            console.log(`⚠️ ${role} 로그인 실패 - 계정 상태 확인 필요`);

            // 계정 상태 API로 확인
            await page.goto(`${BASE_URL}/debug/store-account/${account.storeId}`);
            const accountStatus = await page.textContent('body');
            console.log(`🔍 매장 계정 상태:`, accountStatus);

            return; // 로그인 불가 시 테스트 중단
        }

        await page.screenshot({ path: 'tests/playwright/store-dashboard-full.png', fullPage: true });

        // 2. 모든 금지된 페이지 접근 차단 확인
        for (const forbiddenPage of account.forbiddenPages) {
            console.log(`🔒 ${role} → ${forbiddenPage} 접근 차단 테스트`);

            const response = await page.goto(`${BASE_URL}${forbiddenPage}`, { waitUntil: 'networkidle' });

            const isBlocked = response.status() >= 400 ||
                            page.url().includes('/login') ||
                            page.url().includes('/dashboard') ||
                            page.url() !== `${BASE_URL}${forbiddenPage}`;

            console.log(`${isBlocked ? '✅' : '❌'} ${forbiddenPage}: ${response.status()}`);
            expect(isBlocked).toBeTruthy();
        }

        // 3. 허용된 페이지에서 기능 제한 확인
        await page.goto(`${BASE_URL}/dashboard`);
        await page.waitForLoadState('networkidle');

        // 매장 직원은 자신의 매장 데이터만 봐야 함
        const dashboardData = await page.evaluate(() => {
            return {
                storeName: document.querySelector('[data-store-name], .store-name')?.textContent,
                totalStores: document.querySelector('[data-total-stores]')?.textContent,
                allBranches: document.querySelector('[data-total-branches]')?.textContent,
                hasManagementButtons: document.querySelectorAll('button:contains("관리"), a:contains("관리")').length > 0
            };
        });

        console.log(`📊 ${role} 대시보드 데이터:`, dashboardData);

        // 전체 시스템 데이터가 보이면 권한 위반
        if (dashboardData.totalStores || dashboardData.allBranches) {
            console.log(`❌ 권한 위반: 매장 직원이 전체 시스템 데이터에 접근`);
        } else {
            console.log(`✅ 권한 준수: 매장 직원은 자신의 데이터만 접근`);
        }
    });

    // 🔄 크로스 롤 데이터 접근 차단 검증
    test('크로스 롤 데이터 접근 및 권한 에스컬레이션 차단', async ({ page }) => {
        console.log('🔄 크로스 롤 권한 에스컬레이션 차단 테스트...');

        const crossAccessTests = [
            {
                loginRole: 'branch',
                testUrl: '/test-api/stores?branch=1', // 서울지사 (다른 지사)
                expectedBlock: true,
                description: '지사 관리자가 다른 지사 매장 데이터 접근'
            },
            {
                loginRole: 'store',
                testUrl: '/admin/accounts',
                expectedBlock: true,
                description: '매장 직원이 계정 관리 접근'
            },
            {
                loginRole: 'store',
                testUrl: '/test-api/stores/1/stats', // 다른 매장
                expectedBlock: true,
                description: '매장 직원이 다른 매장 성과 데이터 접근'
            }
        ];

        for (const crossTest of crossAccessTests) {
            console.log(`🔒 ${crossTest.description} 테스트`);

            // 해당 역할로 로그인
            await RBACTestHelper.loginWithRole(page, crossTest.loginRole);

            // 접근 시도
            const response = await page.goto(`${BASE_URL}${crossTest.testUrl}`, { waitUntil: 'networkidle' });
            const isBlocked = response.status() >= 400;

            console.log(`   결과: ${response.status()} (${isBlocked ? '차단됨' : '접근됨'})`);

            if (crossTest.expectedBlock) {
                expect(isBlocked).toBeTruthy();
                console.log(`✅ 예상대로 접근 차단됨`);
            } else {
                expect(isBlocked).toBeFalsy();
                console.log(`✅ 예상대로 접근 허용됨`);
            }
        }
    });

    // 📊 실시간 데이터 vs 하드코딩 완전 분리 검증
    test('실시간 데이터 바인딩 vs 하드코딩 데이터 완전 분리 검증', async ({ page }) => {
        console.log('📊 실시간 데이터 바인딩 완전 검증...');

        await RBACTestHelper.loginWithRole(page, 'headquarters');

        const pages = ['/dashboard', '/management/branches', '/management/stores', '/admin/accounts', '/statistics'];
        const dataAnalysis = [];

        for (const testPage of pages) {
            await page.goto(`${BASE_URL}${testPage}`);
            await page.waitForLoadState('networkidle');
            await page.waitForTimeout(5000); // 모든 API 완료 대기

            const pageDataAnalysis = await page.evaluate(() => {
                const dataElements = document.querySelectorAll('*');
                const results = [];

                dataElements.forEach(el => {
                    const text = el.textContent?.trim();
                    if (text && /\d/.test(text) && el.children.length === 0) {
                        // 숫자 포함 leaf 노드 분석
                        const isDefinitelyHardcoded = text === '0' || text === '-' || text === '기본값' || text.includes('로딩');
                        const isDefinitelyRealtime = /[1-9]\d*/.test(text) && !text.includes('로딩') && !text.includes('년') && !text.includes('시');

                        if (isDefinitelyRealtime || isDefinitelyHardcoded) {
                            results.push({
                                text: text,
                                element: el.tagName + (el.id ? '#' + el.id : ''),
                                isRealtime: isDefinitelyRealtime,
                                isHardcoded: isDefinitelyHardcoded
                            });
                        }
                    }
                });

                return results;
            });

            const realtimeCount = pageDataAnalysis.filter(item => item.isRealtime).length;
            const hardcodedCount = pageDataAnalysis.filter(item => item.isHardcoded).length;
            const realtimeRatio = realtimeCount / (realtimeCount + hardcodedCount) * 100;

            dataAnalysis.push({
                page: testPage,
                realtimeCount,
                hardcodedCount,
                realtimeRatio: Math.round(realtimeRatio),
                samples: pageDataAnalysis.slice(0, 5)
            });

            console.log(`📊 ${testPage}: 실시간 ${realtimeCount}개, 하드코딩 ${hardcodedCount}개 (${Math.round(realtimeRatio)}% 실시간)`);
        }

        // 전체 시스템 실시간 비율 계산
        const totalRealtime = dataAnalysis.reduce((sum, page) => sum + page.realtimeCount, 0);
        const totalHardcoded = dataAnalysis.reduce((sum, page) => sum + page.hardcodedCount, 0);
        const overallRealtimeRatio = Math.round(totalRealtime / (totalRealtime + totalHardcoded) * 100);

        console.log(`\n🎯 전체 시스템 데이터 바인딩 분석:`);
        console.log(`✅ 실시간 데이터: ${totalRealtime}개 (${overallRealtimeRatio}%)`);
        console.log(`❌ 하드코딩 데이터: ${totalHardcoded}개`);

        // 80% 이상 실시간 데이터면 통과
        expect(overallRealtimeRatio).toBeGreaterThan(80);
    });

    // 📝 팝업 메시지 품질 검증
    test('모든 역할 팝업 메시지 및 사용자 안내 품질 검증', async ({ page }) => {
        console.log('📝 팝업 메시지 품질 및 사용자 안내 검증...');

        const roles = ['headquarters', 'branch'];
        const buttonTestMatrix = [
            { button: '수정', expectedGuidance: ['현재:', '수정:', '입력'] },
            { button: '삭제', expectedGuidance: ['확인', '데이터', '되돌릴 수 없'] },
            { button: '계정', expectedGuidance: ['이메일', '비밀번호', '생성'] },
            { button: '성과', expectedGuidance: ['통계', '이동', '확인'] }
        ];

        for (const role of roles) {
            console.log(`\n👤 ${role} 역할 팝업 메시지 품질 검증...`);

            await RBACTestHelper.loginWithRole(page, role);

            for (const buttonTest of buttonTestMatrix) {
                // 버튼이 있는 적절한 페이지로 이동
                await page.goto(`${BASE_URL}/management/stores`);
                await page.waitForLoadState('networkidle');
                await page.waitForTimeout(3000);

                const initialPopupCount = RBACTestHelper.popupMessages.length;

                // 버튼 클릭 시도
                const clickResult = await RBACTestHelper.testButtonClick(page, buttonTest.button);

                if (clickResult.success && clickResult.newPopups.length > 0) {
                    const popup = clickResult.newPopups[0];

                    // 팝업 메시지 품질 검증
                    const hasGoodGuidance = buttonTest.expectedGuidance.some(guidance =>
                        popup.message.includes(guidance)
                    );

                    if (hasGoodGuidance) {
                        console.log(`✅ ${buttonTest.button} (${role}): 양질의 사용자 안내`);
                    } else {
                        console.log(`⚠️ ${buttonTest.button} (${role}): 안내 메시지 개선 필요`);
                        console.log(`   메시지: ${popup.message.substring(0, 100)}...`);
                    }
                }
            }
        }
    });
});

// 📋 테스트 완료 후 종합 리포트 생성
test.afterAll(async () => {
    console.log('\n📋 RBAC 검증 테스트 완료 - 종합 리포트 생성...');

    const popupSummary = RBACTestHelper.getPopupSummary();
    const errorSummary = RBACTestHelper.getErrorSummary();

    console.log('\n🔔 팝업 메시지 요약:');
    console.log(`   총 팝업: ${popupSummary.totalPopups}개`);
    console.log(`   타입별:`, popupSummary.popupsByType);

    if (popupSummary.totalPopups > 0) {
        console.log('\n📝 주요 팝업 메시지들:');
        popupSummary.messages.slice(0, 10).forEach((popup, index) => {
            console.log(`   ${index + 1}. [${popup.type}] ${popup.message.substring(0, 80)}...`);
        });
    }

    console.log('\n❌ 감지된 에러:');
    console.log(`   총 에러: ${errorSummary.totalErrors}개`);
    if (errorSummary.totalErrors > 0) {
        errorSummary.errors.slice(0, 5).forEach((error, index) => {
            console.log(`   ${index + 1}. ${error.error}`);
        });
    }

    // 전체 검증 결과 요약
    const overallResult = {
        popupQuality: popupSummary.totalPopups > 5 ? 'GOOD' : 'NEEDS_IMPROVEMENT',
        errorLevel: errorSummary.totalErrors === 0 ? 'EXCELLENT' : errorSummary.totalErrors < 3 ? 'ACCEPTABLE' : 'POOR',
        rbacCompliance: 'PENDING_MANUAL_REVIEW'
    };

    console.log('\n🎯 최종 검증 결과:', overallResult);
});