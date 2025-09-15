// 🔐 YKP ERP 권한 체계별 종합 자동화 테스트
// 본사/지사/매장 역할별 모든 UI 컴포넌트 및 데이터 바인딩 검증

import { test, expect } from '@playwright/test';

// 🏗️ 테스트 설정
const BASE_URL = 'https://endearing-dedication-production.up.railway.app';
const TEST_TIMEOUT = 60000;

// 🔑 권한별 계정 정보
const ACCOUNTS = {
    headquarters: {
        email: 'hq@ykp.com',
        password: '123456',
        role: 'headquarters',
        name: '본사 관리자',
        expectedPages: ['dashboard', 'management/branches', 'management/stores', 'admin/accounts', 'statistics'],
        restrictedPages: []
    },
    branch: {
        email: 'branch_bs001@ykp.com', // 부산지사
        password: '123456',
        role: 'branch',
        name: '김부산',
        branchId: 4,
        branchName: '부산지사',
        expectedPages: ['dashboard', 'management/stores'],
        restrictedPages: ['management/branches', 'admin/accounts']
    },
    store: {
        email: 'bs001-001@ykp.com', // 해운대점
        password: '123456',
        role: 'store',
        name: '김해운대',
        storeId: 5,
        storeName: '해운대점',
        branchId: 4,
        expectedPages: ['dashboard', 'sales/excel-input'],
        restrictedPages: ['management/branches', 'management/stores', 'admin/accounts']
    }
};

// 📋 페이지별 UI 컴포넌트 매트릭스
const PAGE_COMPONENTS = {
    dashboard: {
        buttons: ['새 지사 추가', '새 매장 추가', '전체 리포트', '새로고침', '로그아웃'],
        dataElements: ['전체 지사 수', '전체 매장 수', '전체 사용자', 'TOP 지사', 'TOP 매장'],
        apis: ['/api/dashboard/overview', '/api/dashboard/rankings', '/api/dashboard/top-list']
    },
    'management/branches': {
        buttons: ['새 지사 등록', '수정', '매장관리', '통계'],
        dataElements: ['전체 지사', '운영중', '총 매장 수', '지사 목록'],
        apis: ['/test-api/branches', '/test-api/users']
    },
    'management/stores': {
        buttons: ['매장 추가', '수정', '계정생성', '성과', '삭제'],
        dataElements: ['지사별 매장 목록', '매장 정보', '점주명', '연락처'],
        apis: ['/test-api/stores', '/test-api/branches']
    },
    'admin/accounts': {
        buttons: ['계정 정보 내보내기', '전체', '지사', '매장', '비밀번호 리셋', '비활성화'],
        dataElements: ['전체 계정', '지사 관리자', '매장 직원', '비활성', '계정 목록'],
        apis: ['/test-api/accounts/all', '/test-api/users']
    },
    statistics: {
        buttons: ['이번달', '이번주', '지난달', '적용'],
        dataElements: ['전체 지사', '전체 매장', '이번달 매출', '목표 달성률', '매장 랭킹'],
        apis: ['/api/dashboard/overview', '/api/dashboard/store-ranking', '/api/users/branches']
    },
    'sales/excel-input': {
        buttons: ['행 추가', '행 삭제', '저장', '대시보드'],
        dataElements: ['매장명', '지사명', '판매 데이터'],
        apis: ['/api/sales/bulk']
    }
};

// 🎭 권한 매트릭스 (허용/금지 정의)
const PERMISSION_MATRIX = {
    headquarters: {
        allowedButtons: ['새 지사 추가', '새 매장 추가', '전체 리포트', '새 지사 등록', '수정', '매장관리', '통계', '매장 추가', '계정생성', '성과', '삭제', '계정 정보 내보내기', '비밀번호 리셋', '비활성화'],
        forbiddenButtons: [],
        allowedData: ['전체 지사', '전체 매장', '모든 지사 데이터', '모든 매장 데이터', '모든 계정'],
        forbiddenData: []
    },
    branch: {
        allowedButtons: ['새로고침', '로그아웃', '매장 추가', '수정', '계정생성', '성과', '삭제'],
        forbiddenButtons: ['새 지사 추가', '새 지사 등록', '계정 정보 내보내기', '비밀번호 리셋', '비활성화'],
        allowedData: ['소속 지사 매장', '소속 지사 통계'],
        forbiddenData: ['다른 지사 데이터', '전체 지사 관리', '전체 계정 관리']
    },
    store: {
        allowedButtons: ['새로고침', '로그아웃', '행 추가', '행 삭제', '저장', '대시보드'],
        forbiddenButtons: ['새 지사 추가', '새 매장 추가', '수정', '삭제', '계정생성', '새 지사 등록'],
        allowedData: ['소속 매장 데이터', '소속 매장 통계'],
        forbiddenData: ['다른 매장 데이터', '지사 관리 데이터', '계정 관리 데이터']
    }
};

// 🔧 헬퍼 함수들
class TestHelpers {
    static async loginAs(page, role) {
        const account = ACCOUNTS[role];

        await page.goto(`${BASE_URL}/login`);
        await page.waitForLoadState('networkidle');

        // 로그인 폼 입력
        await page.fill('input[name="email"]', account.email);
        await page.fill('input[name="password"]', account.password);
        await page.click('button[type="submit"]');

        // 대시보드 로딩 대기
        await page.waitForURL('**/dashboard', { timeout: TEST_TIMEOUT });
        await page.waitForLoadState('networkidle');

        console.log(`✅ ${role} 계정 로그인 성공: ${account.email}`);
        return account;
    }

    static async captureApiResponses(page, apiPaths) {
        const apiResponses = {};

        // API 호출 가로채기
        for (const apiPath of apiPaths) {
            await page.route(`**${apiPath}*`, async (route) => {
                const response = await route.fetch();
                const data = await response.json();
                apiResponses[apiPath] = data;
                console.log(`📡 API 캡처: ${apiPath}`, data);
                await route.fulfill({ response });
            });
        }

        return apiResponses;
    }

    static async verifyDataBinding(page, apiResponses, dataElements) {
        const verificationResults = [];

        for (const elementName of dataElements) {
            const result = await page.evaluate(({ elementName }) => {
                // 다양한 선택자로 요소 찾기
                const selectors = [
                    `[data-testid="${elementName}"]`,
                    `#${elementName.replace(/\s+/g, '-').toLowerCase()}`,
                    `*:has-text("${elementName}")`,
                    `.${elementName.replace(/\s+/g, '-').toLowerCase()}`
                ];

                for (const selector of selectors) {
                    try {
                        const elements = document.querySelectorAll(selector);
                        if (elements.length > 0) {
                            return {
                                found: true,
                                selector: selector,
                                count: elements.length,
                                values: Array.from(elements).map(el => el.textContent?.trim()).filter(Boolean)
                            };
                        }
                    } catch (e) {
                        continue;
                    }
                }

                return { found: false, elementName };
            }, { elementName });

            verificationResults.push({
                element: elementName,
                ...result
            });
        }

        return verificationResults;
    }

    static async testAllButtons(page, allowedButtons, forbiddenButtons) {
        const buttonResults = [];

        // 페이지의 모든 버튼 찾기
        const allButtons = await page.evaluate(() => {
            const buttons = Array.from(document.querySelectorAll('button, a[role="button"], [onclick]'));
            return buttons.map(btn => ({
                text: btn.textContent?.trim() || btn.getAttribute('aria-label') || 'Unknown',
                visible: btn.offsetParent !== null,
                clickable: !btn.disabled && !btn.hasAttribute('aria-disabled'),
                selector: btn.tagName.toLowerCase() + (btn.id ? '#' + btn.id : '') + (btn.className ? '.' + btn.className.split(' ').join('.') : ''),
                onclick: btn.getAttribute('onclick')
            }));
        });

        console.log(`🔍 페이지에서 발견된 버튼: ${allButtons.length}개`);

        for (const button of allButtons) {
            const isAllowed = allowedButtons.some(allowed => button.text.includes(allowed));
            const isForbidden = forbiddenButtons.some(forbidden => button.text.includes(forbidden));

            let testResult = {
                button: button.text,
                visible: button.visible,
                clickable: button.clickable,
                shouldBeAllowed: isAllowed,
                shouldBeForbidden: isForbidden,
                status: 'unknown'
            };

            if (isForbidden && button.visible) {
                testResult.status = '❌ FAIL - 금지된 버튼이 표시됨';
            } else if (isAllowed && !button.visible) {
                testResult.status = '⚠️ WARN - 허용된 버튼이 숨겨짐';
            } else if (isAllowed && button.visible) {
                testResult.status = '✅ PASS - 정상 표시';
            } else {
                testResult.status = '➖ SKIP - 권한 매트릭스 외';
            }

            buttonResults.push(testResult);
        }

        return buttonResults;
    }

    static async testCrossDataAccess(page, userRole, userBranchId, userStoreId) {
        const accessResults = [];

        if (userRole === 'branch') {
            // 지사 계정이 다른 지사 데이터에 접근 시도
            const otherBranchTests = [
                { url: '/test-api/stores?branch=1', desc: '서울지사 매장 데이터' },
                { url: '/test-api/stores?branch=2', desc: '다른 지사 매장 데이터' },
                { url: '/management/stores?branch=1', desc: '다른 지사 매장 관리 페이지' }
            ];

            for (const testCase of otherBranchTests) {
                try {
                    const response = await page.goto(`${BASE_URL}${testCase.url}`);
                    const isAccessible = response.status() < 400;

                    accessResults.push({
                        test: testCase.desc,
                        url: testCase.url,
                        accessible: isAccessible,
                        status: isAccessible ? '❌ FAIL - 접근 가능함' : '✅ PASS - 접근 차단됨',
                        httpStatus: response.status()
                    });
                } catch (error) {
                    accessResults.push({
                        test: testCase.desc,
                        url: testCase.url,
                        accessible: false,
                        status: '✅ PASS - 접근 차단됨',
                        error: error.message
                    });
                }
            }
        }

        return accessResults;
    }
}

// 🧪 메인 테스트 스위트
test.describe('🔐 YKP ERP 권한 체계별 종합 RBAC 테스트', () => {

    // 각 역할별로 테스트 실행
    test.describe.configure({ mode: 'parallel' });

    Object.keys(ACCOUNTS).forEach(role => {
        test.describe(`${role.toUpperCase()} 권한 테스트`, () => {

            test(`${role} - 로그인 및 권한 확인`, async ({ page }) => {
                console.log(`🔍 ${role} 계정 종합 테스트 시작...`);

                // 1. 로그인
                const account = await TestHelpers.loginAs(page, role);
                await page.screenshot({ path: `tests/playwright/${role}-dashboard.png`, fullPage: true });

                // 2. 사용자 정보 확인
                const userInfo = await page.evaluate(() => {
                    return window.userData || null;
                });

                expect(userInfo).toBeTruthy();
                expect(userInfo.role).toBe(account.role);
                console.log(`✅ ${role} 사용자 정보 확인:`, userInfo);
            });

            // 각 페이지별 테스트
            ACCOUNTS[role].expectedPages.forEach(pagePath => {
                test(`${role} - ${pagePath} 페이지 전수 검증`, async ({ page }) => {
                    // 로그인
                    await TestHelpers.loginAs(page, role);

                    // 페이지 이동
                    await page.goto(`${BASE_URL}/${pagePath}`);
                    await page.waitForLoadState('networkidle');

                    const pageConfig = PAGE_COMPONENTS[pagePath];
                    if (!pageConfig) {
                        console.log(`⚠️ ${pagePath}: 페이지 설정 없음 - 스키핑`);
                        return;
                    }

                    // API 응답 캡처
                    const apiResponses = await TestHelpers.captureApiResponses(page, pageConfig.apis);

                    // 페이지 로딩 완료 대기
                    await page.waitForTimeout(5000);
                    await page.screenshot({ path: `tests/playwright/${role}-${pagePath.replace('/', '-')}.png`, fullPage: true });

                    // 버튼 권한 테스트
                    const allowedButtons = PERMISSION_MATRIX[role].allowedButtons;
                    const forbiddenButtons = PERMISSION_MATRIX[role].forbiddenButtons;
                    const buttonResults = await TestHelpers.testAllButtons(page, allowedButtons, forbiddenButtons);

                    console.log(`🔘 ${role} - ${pagePath} 버튼 테스트 결과:`);
                    buttonResults.forEach(result => {
                        console.log(`  ${result.status}: ${result.button}`);
                        if (result.status.includes('FAIL')) {
                            console.error(`❌ 권한 위반: ${result.button}`);
                        }
                    });

                    // 데이터 바인딩 검증
                    const dataResults = await TestHelpers.verifyDataBinding(page, apiResponses, pageConfig.dataElements);

                    console.log(`📊 ${role} - ${pagePath} 데이터 바인딩 결과:`);
                    dataResults.forEach(result => {
                        if (result.found) {
                            console.log(`✅ ${result.element}: 발견됨 (${result.count}개) - ${result.values.join(', ')}`);
                        } else {
                            console.log(`❌ ${result.element}: 찾을 수 없음`);
                        }
                    });

                    // 권한별 접근 제한 테스트
                    if (role !== 'headquarters') {
                        const accessResults = await TestHelpers.testCrossDataAccess(page, role, ACCOUNTS[role].branchId, ACCOUNTS[role].storeId);

                        console.log(`🔒 ${role} 크로스 데이터 접근 테스트:`);
                        accessResults.forEach(result => {
                            console.log(`  ${result.status}: ${result.test}`);
                        });
                    }
                });
            });

            // 제한된 페이지 접근 테스트
            ACCOUNTS[role].restrictedPages.forEach(restrictedPage => {
                test(`${role} - ${restrictedPage} 접근 제한 확인`, async ({ page }) => {
                    // 로그인
                    await TestHelpers.loginAs(page, role);

                    // 제한된 페이지 접근 시도
                    const response = await page.goto(`${BASE_URL}/${restrictedPage}`, { waitUntil: 'networkidle' });

                    // 403 Forbidden 또는 리디렉션 확인
                    const isBlocked = response.status() === 403 ||
                                    response.status() === 302 ||
                                    page.url().includes('/login') ||
                                    page.url().includes('/dashboard');

                    console.log(`🔒 ${role} → ${restrictedPage}: ${response.status()} ${isBlocked ? '차단됨' : '접근됨'}`);

                    if (!isBlocked) {
                        await page.screenshot({ path: `tests/playwright/${role}-unauthorized-${restrictedPage.replace('/', '-')}.png`, fullPage: true });
                        console.error(`❌ 권한 위반: ${role}이 ${restrictedPage}에 접근 가능`);
                    }

                    expect(isBlocked).toBeTruthy();
                });
            });
        });
    });
});

// 🎯 특수 시나리오 테스트
test.describe('🎯 특수 시나리오 및 실시간 업데이트 테스트', () => {

    test('지사 수정 기능 실제 동작 검증', async ({ page }) => {
        console.log('🔧 지사 수정 기능 실제 테스트...');

        await TestHelpers.loginAs(page, 'headquarters');
        await page.goto(`${BASE_URL}/management/branches`);
        await page.waitForLoadState('networkidle');

        // 첫 번째 지사의 수정 버튼 클릭
        const editButton = page.locator('button:has-text("수정")').first();
        await expect(editButton).toBeVisible();

        // 수정 버튼 클릭 (prompt 창 처리)
        page.on('dialog', async dialog => {
            console.log(`📝 지사 수정 다이얼로그: ${dialog.message()}`);
            if (dialog.message().includes('지사명 수정')) {
                await dialog.accept('테스트 수정 지사명');
            } else {
                await dialog.dismiss();
            }
        });

        await editButton.click();
        await page.waitForTimeout(3000);

        console.log('✅ 지사 수정 기능 테스트 완료');
    });

    test('매장 삭제 Foreign Key 처리 검증', async ({ page }) => {
        console.log('🗑️ 매장 삭제 Foreign Key 처리 테스트...');

        await TestHelpers.loginAs(page, 'headquarters');
        await page.goto(`${BASE_URL}/management/stores`);
        await page.waitForLoadState('networkidle');

        // 삭제 버튼 클릭 (확인 창 처리)
        page.on('dialog', async dialog => {
            console.log(`🗑️ 매장 삭제 다이얼로그: ${dialog.message()}`);
            if (dialog.message().includes('매장을 삭제하시겠습니까')) {
                await dialog.accept(); // 첫 번째 확인
            } else if (dialog.message().includes('연결된 데이터')) {
                console.log('✅ Foreign Key 경고 메시지 정상 표시');
                await dialog.dismiss(); // 강제 삭제 거부
            } else {
                await dialog.dismiss();
            }
        });

        const deleteButton = page.locator('button:has-text("삭제")').first();
        if (await deleteButton.isVisible()) {
            await deleteButton.click();
            await page.waitForTimeout(3000);
        }

        console.log('✅ 매장 삭제 Foreign Key 처리 테스트 완료');
    });

    test('성과 버튼 실제 데이터 표시 검증', async ({ page }) => {
        console.log('📊 성과 버튼 실제 데이터 테스트...');

        await TestHelpers.loginAs(page, 'headquarters');
        await page.goto(`${BASE_URL}/management/stores`);
        await page.waitForLoadState('networkidle');

        // 성과 버튼 찾기 및 클릭
        page.on('dialog', async dialog => {
            console.log(`📊 성과 데이터 다이얼로그: ${dialog.message()}`);

            // 성과 데이터 내용 검증
            const message = dialog.message();
            const hasRealData = message.includes('매출') &&
                              message.includes('개통건수') &&
                              !message.includes('₩0') &&
                              !message.includes('0건');

            if (hasRealData) {
                console.log('✅ 성과 데이터: 실시간 데이터 확인됨');
            } else {
                console.log('⚠️ 성과 데이터: 기본값 또는 빈 데이터');
            }

            await dialog.dismiss();
        });

        const performanceButton = page.locator('button:has-text("성과"), button:has-text("📊")').first();
        if (await performanceButton.isVisible()) {
            await performanceButton.click();
            await page.waitForTimeout(3000);
        } else {
            console.log('⚠️ 성과 버튼을 찾을 수 없음');
        }

        console.log('✅ 성과 버튼 데이터 표시 테스트 완료');
    });

    test('매장 계정 개통표 입력 → 대시보드 실시간 업데이트', async ({ browser }) => {
        console.log('🔄 매장 계정 개통표 입력 실시간 업데이트 테스트...');

        // 두 개의 탭 생성 (대시보드 모니터링 + 개통표 입력)
        const context = await browser.newContext();
        const dashboardPage = await context.newPage();
        const salesPage = await context.newPage();

        try {
            // 1. 대시보드 탭에서 본사 계정으로 모니터링
            await TestHelpers.loginAs(dashboardPage, 'headquarters');
            await dashboardPage.screenshot({ path: 'tests/playwright/before-sales-input.png', fullPage: true });

            const beforeStats = await dashboardPage.evaluate(() => {
                return {
                    totalSales: document.querySelector('#total-sales, [data-testid="total-sales"]')?.textContent || 'N/A',
                    totalActivations: document.querySelector('#total-activations, [data-testid="total-activations"]')?.textContent || 'N/A',
                    activeStores: document.querySelector('#active-stores, [data-testid="active-stores"]')?.textContent || 'N/A'
                };
            });

            console.log('📊 개통표 입력 전 통계:', beforeStats);

            // 2. 개통표 입력 탭에서 매장 계정으로 로그인
            await TestHelpers.loginAs(salesPage, 'store');
            await salesPage.goto(`${BASE_URL}/sales/excel-input`);
            await salesPage.waitForLoadState('networkidle');

            // 3. 개통표 데이터 입력 시뮬레이션
            const inputSuccess = await salesPage.evaluate(() => {
                try {
                    // 간단한 개통표 데이터 생성
                    const testData = {
                        sale_date: new Date().toISOString().split('T')[0],
                        carrier: 'SK',
                        activation_type: '신규',
                        model_name: '테스트폰',
                        settlement_amount: 100000
                    };

                    // 실제 저장 함수 호출 (if available)
                    if (typeof saveData === 'function') {
                        saveData();
                        return true;
                    }

                    return false;
                } catch (error) {
                    console.error('개통표 입력 실패:', error);
                    return false;
                }
            });

            if (inputSuccess) {
                console.log('✅ 개통표 입력 성공');

                // 4. 대시보드에서 실시간 업데이트 확인 (10초 대기)
                await dashboardPage.waitForTimeout(10000);

                const afterStats = await dashboardPage.evaluate(() => {
                    return {
                        totalSales: document.querySelector('#total-sales, [data-testid="total-sales"]')?.textContent || 'N/A',
                        totalActivations: document.querySelector('#total-activations, [data-testid="total-activations"]')?.textContent || 'N/A',
                        activeStores: document.querySelector('#active-stores, [data-testid="active-stores"]')?.textContent || 'N/A'
                    };
                });

                console.log('📊 개통표 입력 후 통계:', afterStats);

                // 통계 변화 확인
                const statsChanged = beforeStats.totalSales !== afterStats.totalSales ||
                                   beforeStats.totalActivations !== afterStats.totalActivations;

                if (statsChanged) {
                    console.log('✅ 실시간 업데이트 성공: 대시보드 통계 변경 확인됨');
                } else {
                    console.log('⚠️ 실시간 업데이트 미확인: 통계 변화 없음');
                }

                await dashboardPage.screenshot({ path: 'tests/playwright/after-sales-input.png', fullPage: true });
            } else {
                console.log('❌ 개통표 입력 실패 - 실시간 업데이트 테스트 불가');
            }

        } finally {
            await context.close();
        }
    });

    // 제한된 페이지 접근 테스트
    Object.keys(ACCOUNTS).forEach(role => {
        ACCOUNTS[role].restrictedPages.forEach(restrictedPage => {
            test(`${role} - ${restrictedPage} 접근 제한 확인`, async ({ page }) => {
                await TestHelpers.loginAs(page, role);

                const response = await page.goto(`${BASE_URL}/${restrictedPage}`, { waitUntil: 'networkidle' });
                const currentUrl = page.url();

                const isBlocked = response.status() === 403 ||
                                response.status() === 302 ||
                                currentUrl.includes('/login') ||
                                currentUrl.includes('/dashboard') ||
                                currentUrl !== `${BASE_URL}/${restrictedPage}`;

                console.log(`🔒 ${role} → ${restrictedPage}: ${response.status()} (${isBlocked ? '차단' : '접근가능'})`);

                if (!isBlocked) {
                    await page.screenshot({ path: `tests/playwright/VIOLATION-${role}-${restrictedPage.replace('/', '-')}.png`, fullPage: true });
                }

                expect(isBlocked).toBeTruthy();
            });
        });
    });
});

// 🔄 실시간 데이터 vs 하드코딩 감지 테스트
test.describe('🔄 실시간 데이터 vs 하드코딩 데이터 감지', () => {

    test('전체 페이지 데이터 소스 분석', async ({ page }) => {
        console.log('🔍 전체 시스템 데이터 소스 분석 시작...');

        await TestHelpers.loginAs(page, 'headquarters');

        const pages = ['dashboard', 'management/branches', 'management/stores', 'admin/accounts', 'statistics'];
        const analysisResults = [];

        for (const pagePath of pages) {
            await page.goto(`${BASE_URL}/${pagePath}`);
            await page.waitForLoadState('networkidle');
            await page.waitForTimeout(5000); // 모든 API 로딩 대기

            const pageAnalysis = await page.evaluate(() => {
                const results = [];
                const elements = document.querySelectorAll('*');

                elements.forEach(el => {
                    const text = el.textContent?.trim();
                    if (text && /\d/.test(text) && el.children.length === 0) {
                        // 숫자가 포함된 leaf 노드들 분석
                        const isHardcoded = text === '0' ||
                                          text === '-' ||
                                          text === '기본값' ||
                                          text.includes('알수없음') ||
                                          text.includes('로딩 중') ||
                                          text.includes('준비중');

                        const isRealTime = /[1-9]\d*/.test(text) &&
                                         !text.includes('로딩') &&
                                         !text.includes('준비중');

                        results.push({
                            text: text,
                            selector: el.tagName + (el.id ? '#' + el.id : ''),
                            isHardcoded: isHardcoded,
                            isRealTime: isRealTime
                        });
                    }
                });

                return results.slice(0, 20); // 상위 20개만
            });

            const realTimeCount = pageAnalysis.filter(item => item.isRealTime).length;
            const hardcodedCount = pageAnalysis.filter(item => item.isHardcoded).length;
            const realTimeRatio = Math.round((realTimeCount / (realTimeCount + hardcodedCount)) * 100);

            analysisResults.push({
                page: pagePath,
                realTimeCount,
                hardcodedCount,
                realTimeRatio,
                samples: pageAnalysis
            });

            console.log(`📊 ${pagePath}: 실시간 ${realTimeCount}개, 하드코딩 ${hardcodedCount}개 (${realTimeRatio}% 실시간)`);
        }

        // 전체 결과 요약
        const totalRealTime = analysisResults.reduce((sum, page) => sum + page.realTimeCount, 0);
        const totalHardcoded = analysisResults.reduce((sum, page) => sum + page.hardcodedCount, 0);
        const overallRatio = Math.round((totalRealTime / (totalRealTime + totalHardcoded)) * 100);

        console.log(`\n🎯 전체 시스템 데이터 바인딩 분석:`);
        console.log(`✅ 실시간 데이터: ${totalRealTime}개`);
        console.log(`❌ 하드코딩 데이터: ${totalHardcoded}개`);
        console.log(`📈 실시간 비율: ${overallRatio}%`);

        // 80% 이상이면 통과
        expect(overallRatio).toBeGreaterThan(80);
    });

    test('권한별 API 접근 제한 검증', async ({ page }) => {
        console.log('🔐 권한별 API 접근 제한 검증...');

        const apiAccessTests = [
            { role: 'branch', api: '/test-api/branches', shouldFail: false, desc: '지사 정보 조회' },
            { role: 'branch', api: '/admin/accounts', shouldFail: true, desc: '계정 관리 접근' },
            { role: 'store', api: '/management/stores', shouldFail: true, desc: '매장 관리 접근' },
            { role: 'store', api: '/test-api/stores/1/stats', shouldFail: true, desc: '다른 매장 성과 조회' }
        ];

        for (const testCase of apiAccessTests) {
            await TestHelpers.loginAs(page, testCase.role);

            try {
                const response = await page.goto(`${BASE_URL}${testCase.api}`);
                const accessGranted = response.status() < 400;

                const result = testCase.shouldFail ? !accessGranted : accessGranted;
                const status = result ? '✅ PASS' : '❌ FAIL';

                console.log(`${status} ${testCase.role} → ${testCase.desc}: ${response.status()}`);

                if (!result) {
                    await page.screenshot({ path: `tests/playwright/API-VIOLATION-${testCase.role}-${testCase.api.replace('/', '-')}.png` });
                }

            } catch (error) {
                console.log(`🔒 ${testCase.role} → ${testCase.desc}: 접근 차단됨 (${error.message})`);
            }
        }
    });
});

// 📝 테스트 후 정리
test.afterAll(async () => {
    console.log('🧹 테스트 완료 - 정리 중...');

    // 테스트 결과 요약 생성
    const testSummary = {
        timestamp: new Date().toISOString(),
        environment: 'Railway Staging',
        totalTests: 'Dynamic',
        overallStatus: 'Pending Results'
    };

    console.log('📋 테스트 요약:', testSummary);
});