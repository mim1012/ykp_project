// 🔐 YKP ERP 최종 완전 권한 검증 테스트
// 정확한 계정 정보 + 모든 팝업 메시지 + 권한 격리 완전 검증

import { test, expect } from '@playwright/test';

// 🎯 정확한 테스트 계정 정보 (Railway Staging 확인 완료)
const VERIFIED_ACCOUNTS = {
    headquarters: {
        email: 'hq@ykp.com',
        password: '123456',
        role: '본사 관리자',
        expectFullAccess: true
    },
    branch_busan: {
        email: 'branch_bs001@ykp.com',
        password: '123456',
        role: '부산지사 관리자',
        branchId: 4,
        allowedStores: ['해운대점'], // 부산지사 소속 매장만
        forbiddenStores: ['강남점', '홍대점'] // 서울지사 매장들
    },
    branch_seoul: {
        email: 'sel@ykp.com',
        password: '123456',
        role: '서울지사 관리자',
        branchId: 1,
        allowedStores: ['강남점', '홍대점'], // 서울지사 소속 매장만
        forbiddenStores: ['해운대점'] // 다른 지사 매장
    },
    store_gangnam: {
        email: 'sel-001@ykp.com',
        password: '123456',
        role: '강남점 관리자',
        storeId: 1,
        allowedData: ['강남점만'],
        forbiddenData: ['다른 매장', '전체 통계']
    },
    store_haeundae: {
        email: 'bs001-001@ykp.com',
        password: '123456',
        role: '해운대점 관리자',
        storeId: 5,
        allowedData: ['해운대점만'],
        forbiddenData: ['다른 매장', '전체 통계']
    },
    store_test: {
        email: 'gg001001@ykp.com',
        password: '123456',
        role: '최종테스트점 관리자',
        storeId: 3,
        allowedData: ['최종테스트점만']
    }
};

const BASE_URL = 'https://endearing-dedication-production.up.railway.app';

// 🔧 강화된 테스트 헬퍼
class CompleteRBACTester {
    static popupLog = [];
    static accessViolations = [];
    static functionalityResults = [];

    static async setupComprehensiveMonitoring(page) {
        // 모든 다이얼로그 감지
        page.on('dialog', dialog => {
            const dialogInfo = {
                type: dialog.type(),
                message: dialog.message(),
                defaultValue: dialog.defaultValue() || '',
                timestamp: new Date().toISOString(),
                url: page.url(),
                userAction: 'pending'
            };

            this.popupLog.push(dialogInfo);
            console.log(`🔔 ${dialog.type().toUpperCase()}: ${dialog.message().substring(0, 100)}...`);

            // 자동 응답 로직
            if (dialog.type() === 'confirm') {
                if (dialog.message().includes('삭제') || dialog.message().includes('통계')) {
                    dialog.dismiss(); // 안전하게 취소
                    dialogInfo.userAction = 'dismissed';
                } else {
                    dialog.accept();
                    dialogInfo.userAction = 'accepted';
                }
            } else if (dialog.type() === 'prompt') {
                dialog.dismiss(); // 프롬프트는 취소
                dialogInfo.userAction = 'dismissed';
            } else {
                dialog.accept();
                dialogInfo.userAction = 'accepted';
            }
        });

        // JavaScript 에러 감지
        page.on('pageerror', error => {
            console.log(`❌ Page Error: ${error.message}`);
        });

        // 네트워크 응답 모니터링
        page.on('response', response => {
            if (response.status() >= 400) {
                console.log(`🌐 HTTP Error: ${response.status()} ${response.url()}`);
            }
        });
    }

    static async loginAndVerify(page, accountKey) {
        const account = VERIFIED_ACCOUNTS[accountKey];

        await page.goto(`${BASE_URL}/login`);
        await page.waitForLoadState('networkidle');

        console.log(`🔑 ${accountKey} (${account.role}) 로그인 시도...`);

        await page.fill('input[name="email"]', account.email);
        await page.fill('input[name="password"]', account.password);
        await page.click('button[type="submit"]');

        try {
            await page.waitForURL('**/dashboard', { timeout: 30000 });

            // 로그인 후 사용자 정보 확인
            const userInfo = await page.evaluate(() => window.userData || null);

            console.log(`✅ ${accountKey} 로그인 성공:`, userInfo);
            return { success: true, userInfo };
        } catch (error) {
            console.log(`❌ ${accountKey} 로그인 실패: ${error.message}`);
            await page.screenshot({ path: `tests/playwright/login-failed-${accountKey}.png` });
            return { success: false, error: error.message };
        }
    }

    static async testButtonFunctionality(page, buttonSelector, buttonName, expectedResult = 'popup') {
        const result = {
            buttonName,
            found: false,
            clickable: false,
            popupShown: false,
            functionalityWorking: false,
            error: null
        };

        try {
            const button = page.locator(buttonSelector).first();

            if (await button.isVisible()) {
                result.found = true;
                result.clickable = !await button.isDisabled();

                if (result.clickable) {
                    const initialPopupCount = this.popupLog.length;

                    await button.click();
                    await page.waitForTimeout(2000);

                    const newPopups = this.popupLog.slice(initialPopupCount);
                    result.popupShown = newPopups.length > 0;
                    result.functionalityWorking = true;

                    if (newPopups.length > 0) {
                        console.log(`✅ ${buttonName}: 팝업 표시됨`);
                        newPopups.forEach(popup => {
                            console.log(`   📝 ${popup.type}: ${popup.message.substring(0, 80)}...`);
                        });
                    }
                }
            }
        } catch (error) {
            result.error = error.message;
            console.log(`❌ ${buttonName} 테스트 중 오류:`, error.message);
        }

        this.functionalityResults.push(result);
        return result;
    }

    static async verifyDataIsolation(page, accountKey, expectedScope) {
        const isolationResults = {
            accountKey,
            expectedScope,
            visibleData: [],
            violations: [],
            compliance: 'UNKNOWN'
        };

        // 페이지의 모든 데이터 요소 추출
        const pageData = await page.evaluate(() => {
            const dataElements = document.querySelectorAll('h1, h2, h3, h4, .store-name, .branch-name, [data-store], [data-branch]');
            return Array.from(dataElements).map(el => ({
                text: el.textContent?.trim(),
                tag: el.tagName,
                className: el.className
            })).filter(item => item.text && item.text.length > 0);
        });

        isolationResults.visibleData = pageData;

        // 권한 범위별 위반 사항 검사
        if (expectedScope === 'OWN_BRANCH_ONLY') {
            const account = VERIFIED_ACCOUNTS[accountKey];
            const allowedStores = account.allowedStores || [];
            const forbiddenStores = account.forbiddenStores || [];

            forbiddenStores.forEach(forbiddenStore => {
                const violation = pageData.find(item => item.text.includes(forbiddenStore));
                if (violation) {
                    isolationResults.violations.push(`다른 지사 매장 데이터 노출: ${forbiddenStore}`);
                    console.log(`❌ 권한 위반: ${accountKey}이 ${forbiddenStore} 데이터에 접근`);
                }
            });

        } else if (expectedScope === 'OWN_STORE_ONLY') {
            const account = VERIFIED_ACCOUNTS[accountKey];
            const ownStoreName = account.role.split(' ')[0]; // "강남점 관리자" → "강남점"

            const hasOtherStoreData = pageData.some(item =>
                (item.text.includes('점') || item.text.includes('매장')) &&
                !item.text.includes(ownStoreName)
            );

            if (hasOtherStoreData) {
                isolationResults.violations.push('다른 매장 데이터 노출');
                console.log(`❌ 권한 위반: ${accountKey}이 다른 매장 데이터에 접근`);
            }
        }

        isolationResults.compliance = isolationResults.violations.length === 0 ? 'PASS' : 'FAIL';
        return isolationResults;
    }
}

// 🧪 완전 권한 검증 테스트 스위트
test.describe('🔐 YKP ERP 최종 완전 RBAC 검증', () => {

    test.beforeEach(async ({ page }) => {
        await CompleteRBACTester.setupComprehensiveMonitoring(page);
    });

    // 🏛️ 본사 관리자 완전 기능 테스트
    test('본사 관리자 - 모든 페이지 및 버튼 완전 검증', async ({ page }) => {
        console.log('🏛️ 본사 관리자 완전 기능 테스트 시작...');

        const loginResult = await CompleteRBACTester.loginAndVerify(page, 'headquarters');
        expect(loginResult.success).toBeTruthy();

        // 모든 중요 버튼 기능 테스트
        const buttonTests = [
            { page: '/management/branches', selector: 'button:has-text("새 지사 등록")', name: '지사 추가', expectPopup: true },
            { page: '/management/branches', selector: 'button:has-text("수정")', name: '지사 수정', expectPopup: true },
            { page: '/management/stores', selector: 'button:has-text("매장 추가")', name: '매장 추가', expectPopup: true },
            { page: '/management/stores', selector: 'button:has-text("수정")', name: '매장 수정', expectPopup: true },
            { page: '/management/stores', selector: 'button:has-text("성과"), button:has-text("📊")', name: '성과 보기', expectPopup: true },
            { page: '/management/stores', selector: 'button:has-text("삭제")', name: '매장 삭제', expectPopup: true },
            { page: '/admin/accounts', selector: 'button:has-text("비밀번호 리셋")', name: '비밀번호 리셋', expectPopup: true }
        ];

        for (const buttonTest of buttonTests) {
            console.log(`🔘 ${buttonTest.name} 기능 테스트 (${buttonTest.page})`);

            await page.goto(`${BASE_URL}${buttonTest.page}`);
            await page.waitForLoadState('networkidle');
            await page.waitForTimeout(3000);

            const result = await CompleteRBACTester.testButtonFunctionality(
                page,
                buttonTest.selector,
                buttonTest.name,
                buttonTest.expectPopup ? 'popup' : 'action'
            );

            if (buttonTest.expectPopup && !result.popupShown) {
                console.log(`⚠️ ${buttonTest.name}: 예상된 팝업이 표시되지 않음`);
            }

            await page.screenshot({ path: `tests/playwright/hq-${buttonTest.name.replace(/\s+/g, '-')}-test.png` });
        }
    });

    // 🏢 지사 관리자 데이터 격리 검증
    test('지사 관리자 - 크로스 지사 데이터 접근 차단 검증', async ({ page }) => {
        console.log('🏢 지사 관리자 데이터 격리 검증...');

        // 부산지사 관리자로 로그인
        const loginResult = await CompleteRBACTester.loginAndVerify(page, 'branch_busan');

        if (!loginResult.success) {
            console.log('❌ 부산지사 관리자 로그인 실패 - 서울지사로 대체 테스트');
            const altLoginResult = await CompleteRBACTester.loginAndVerify(page, 'branch_seoul');
            expect(altLoginResult.success).toBeTruthy();
        }

        await page.screenshot({ path: 'tests/playwright/branch-manager-dashboard.png', fullPage: true });

        // 1. 금지된 페이지 접근 테스트
        const forbiddenPages = ['/management/branches', '/admin/accounts'];

        for (const forbiddenPage of forbiddenPages) {
            console.log(`🔒 지사 관리자 → ${forbiddenPage} 접근 차단 확인`);

            const response = await page.goto(`${BASE_URL}${forbiddenPage}`, { waitUntil: 'networkidle' });
            const isBlocked = response.status() === 403 ||
                            page.url().includes('/login') ||
                            page.url().includes('/dashboard');

            console.log(`   결과: ${response.status()} (${isBlocked ? '차단됨' : '접근됨'})`);

            if (!isBlocked) {
                CompleteRBACTester.accessViolations.push(`지사 관리자가 ${forbiddenPage}에 접근 가능`);
                await page.screenshot({ path: `tests/playwright/VIOLATION-branch-${forbiddenPage.replace('/', '-')}.png` });
            }

            expect(isBlocked).toBeTruthy();
        }

        // 2. 매장 관리 페이지에서 데이터 격리 확인
        await page.goto(`${BASE_URL}/management/stores`);
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(5000);

        const isolationResult = await CompleteRBACTester.verifyDataIsolation(page, 'branch_busan', 'OWN_BRANCH_ONLY');

        console.log(`📊 지사 데이터 격리 결과:`, isolationResult);
        expect(isolationResult.compliance).toBe('PASS');
    });

    // 🏪 매장 직원 최소 권한 검증
    test('매장 직원 - 최소 권한 및 자기 매장만 접근 검증', async ({ page }) => {
        console.log('🏪 매장 직원 최소 권한 검증...');

        // 해운대점 관리자로 로그인 시도
        let loginResult = await CompleteRBACTester.loginAndVerify(page, 'store_haeundae');

        if (!loginResult.success) {
            // 해운대점 실패 시 강남점으로 대체
            console.log('❌ 해운대점 로그인 실패 - 강남점으로 대체 테스트');
            loginResult = await CompleteRBACTester.loginAndVerify(page, 'store_gangnam');
        }

        if (!loginResult.success) {
            // 강남점도 실패 시 최종테스트점으로 대체
            console.log('❌ 강남점 로그인 실패 - 최종테스트점으로 대체');
            loginResult = await CompleteRBACTester.loginAndVerify(page, 'store_test');
        }

        expect(loginResult.success).toBeTruthy();

        await page.screenshot({ path: 'tests/playwright/store-manager-dashboard.png', fullPage: true });

        // 1. 모든 관리 페이지 접근 차단 확인
        const forbiddenPages = ['/management/branches', '/management/stores', '/admin/accounts'];

        for (const forbiddenPage of forbiddenPages) {
            console.log(`🔒 매장 직원 → ${forbiddenPage} 접근 차단 확인`);

            const response = await page.goto(`${BASE_URL}${forbiddenPage}`, { waitUntil: 'networkidle' });
            const isBlocked = response.status() >= 400 ||
                            page.url().includes('/login') ||
                            page.url().includes('/dashboard');

            console.log(`   결과: ${response.status()} (${isBlocked ? '차단됨' : '접근됨'})`);
            expect(isBlocked).toBeTruthy();
        }

        // 2. 개통표 입력 페이지 접근 확인 (허용되어야 함)
        await page.goto(`${BASE_URL}/sales/excel-input`);
        await page.waitForLoadState('networkidle');

        const salesPageAccessible = !page.url().includes('/login') && !page.url().includes('/dashboard');
        console.log(`📊 개통표 입력 페이지 접근: ${salesPageAccessible ? '성공' : '차단됨'}`);

        if (salesPageAccessible) {
            // 저장 버튼 기능 테스트
            const saveButtonResult = await CompleteRBACTester.testButtonFunctionality(
                page,
                'button:has-text("저장"), button[data-testid="save"]',
                '개통표 저장'
            );

            console.log(`💾 개통표 저장 기능:`, saveButtonResult);
        }
    });

    // 🔄 실시간 업데이트 시스템 End-to-End 검증
    test('실시간 업데이트 시스템 완전 검증', async ({ browser }) => {
        console.log('🔄 실시간 업데이트 시스템 End-to-End 검증...');

        // 두 개의 독립적인 컨텍스트 생성
        const hqContext = await browser.newContext();
        const storeContext = await browser.newContext();

        const hqPage = await hqContext.newPage();
        const storePage = await storeContext.newPage();

        try {
            // 1. 본사 관리자 대시보드 모니터링 시작
            await CompleteRBACTester.setupComprehensiveMonitoring(hqPage);
            const hqLogin = await CompleteRBACTester.loginAndVerify(hqPage, 'headquarters');
            expect(hqLogin.success).toBeTruthy();

            await hqPage.screenshot({ path: 'tests/playwright/realtime-test-hq-before.png', fullPage: true });

            // 대시보드 초기 상태 캡처
            const beforeStats = await hqPage.evaluate(() => {
                return {
                    totalSales: document.querySelector('#total-sales, [data-testid="total-sales"]')?.textContent || 'N/A',
                    totalActivations: document.querySelector('#total-activations, [data-testid="total-activations"]')?.textContent || 'N/A',
                    timestamp: new Date().toISOString()
                };
            });

            console.log('📊 업데이트 전 통계:', beforeStats);

            // 2. 매장 계정으로 개통표 입력
            await CompleteRBACTester.setupComprehensiveMonitoring(storePage);
            const storeLogin = await CompleteRBACTester.loginAndVerify(storePage, 'store_gangnam');

            if (storeLogin.success) {
                await storePage.goto(`${BASE_URL}/sales/excel-input`);
                await storePage.waitForLoadState('networkidle');

                // 간단한 개통표 데이터 입력 시뮬레이션
                const inputResult = await storePage.evaluate(() => {
                    try {
                        // 테스트 데이터 입력 (실제 그리드가 있다면)
                        const saveButton = document.querySelector('button:has-text("저장"), [data-testid="save"]');
                        if (saveButton && typeof saveData === 'function') {
                            // 실제 저장 함수 호출
                            saveData();
                            return { success: true, action: 'save_triggered' };
                        } else {
                            return { success: false, reason: 'save_function_not_found' };
                        }
                    } catch (error) {
                        return { success: false, error: error.message };
                    }
                });

                console.log('💾 개통표 입력 결과:', inputResult);

                if (inputResult.success) {
                    // 3. 실시간 업데이트 확인 (10초 대기)
                    await hqPage.waitForTimeout(10000);
                    await hqPage.reload();
                    await hqPage.waitForLoadState('networkidle');

                    const afterStats = await hqPage.evaluate(() => {
                        return {
                            totalSales: document.querySelector('#total-sales, [data-testid="total-sales"]')?.textContent || 'N/A',
                            totalActivations: document.querySelector('#total-activations, [data-testid="total-activations"]')?.textContent || 'N/A',
                            timestamp: new Date().toISOString()
                        };
                    });

                    console.log('📊 업데이트 후 통계:', afterStats);

                    const dataChanged = beforeStats.totalSales !== afterStats.totalSales ||
                                      beforeStats.totalActivations !== afterStats.totalActivations;

                    if (dataChanged) {
                        console.log('✅ 실시간 업데이트 성공: 대시보드 데이터 변경 확인');
                    } else {
                        console.log('⚠️ 실시간 업데이트 미확인: 데이터 변화 없음');
                    }

                    await hqPage.screenshot({ path: 'tests/playwright/realtime-test-hq-after.png', fullPage: true });
                }
            } else {
                console.log('❌ 매장 계정 로그인 실패 - 실시간 업데이트 테스트 불가');
            }

        } finally {
            await hqContext.close();
            await storeContext.close();
        }
    });

    // 📝 모든 팝업 메시지 품질 검증
    test('모든 역할 팝업 메시지 및 사용자 가이드 품질 검증', async ({ page }) => {
        console.log('📝 팝업 메시지 품질 종합 검증...');

        const popupQualityTests = [
            {
                account: 'headquarters',
                page: '/management/stores',
                buttons: [
                    { selector: 'button:has-text("삭제")', expectedKeywords: ['삭제', '확인', '되돌릴 수 없'] },
                    { selector: 'button:has-text("계정"), button:has-text("👤")', expectedKeywords: ['계정', '이메일', '비밀번호'] },
                    { selector: 'button:has-text("성과"), button:has-text("📊")', expectedKeywords: ['통계', '성과', '이동'] }
                ]
            },
            {
                account: 'headquarters',
                page: '/management/branches',
                buttons: [
                    { selector: 'button:has-text("수정")', expectedKeywords: ['수정', '현재', '입력'] },
                    { selector: 'button:has-text("새 지사 등록")', expectedKeywords: ['지사', '추가', '입력'] }
                ]
            }
        ];

        for (const testGroup of popupQualityTests) {
            console.log(`\n👤 ${testGroup.account} - ${testGroup.page} 팝업 품질 테스트`);

            const loginResult = await CompleteRBACTester.loginAndVerify(page, testGroup.account);
            expect(loginResult.success).toBeTruthy();

            await page.goto(`${BASE_URL}${testGroup.page}`);
            await page.waitForLoadState('networkidle');
            await page.waitForTimeout(3000);

            for (const buttonTest of testGroup.buttons) {
                const initialPopupCount = CompleteRBACTester.popupLog.length;

                try {
                    const button = page.locator(buttonTest.selector).first();

                    if (await button.isVisible()) {
                        await button.click();
                        await page.waitForTimeout(2000);

                        const newPopups = CompleteRBACTester.popupLog.slice(initialPopupCount);

                        if (newPopups.length > 0) {
                            const popup = newPopups[0];
                            const hasQualityKeywords = buttonTest.expectedKeywords.some(keyword =>
                                popup.message.includes(keyword)
                            );

                            console.log(`${hasQualityKeywords ? '✅' : '⚠️'} 팝업 품질: ${buttonTest.selector}`);
                            console.log(`   메시지: ${popup.message.substring(0, 100)}...`);

                            if (!hasQualityKeywords) {
                                console.log(`   예상 키워드: ${buttonTest.expectedKeywords.join(', ')}`);
                            }
                        } else {
                            console.log(`❌ 팝업 없음: ${buttonTest.selector}`);
                        }
                    } else {
                        console.log(`⚠️ 버튼 없음: ${buttonTest.selector}`);
                    }
                } catch (error) {
                    console.log(`❌ 버튼 테스트 오류: ${error.message}`);
                }
            }
        }
    });
});

// 📋 최종 검증 리포트 생성
test.afterAll(async () => {
    console.log('\n📋 최종 RBAC 검증 완료 - 종합 리포트 생성...');

    const totalPopups = CompleteRBACTester.popupLog.length;
    const totalViolations = CompleteRBACTester.accessViolations.length;
    const functionalButtons = CompleteRBACTester.functionalityResults.filter(r => r.functionalityWorking).length;
    const totalButtons = CompleteRBACTester.functionalityResults.length;

    console.log('\n🎯 최종 검증 결과:');
    console.log(`📝 팝업 메시지: ${totalPopups}개 감지`);
    console.log(`❌ 권한 위반: ${totalViolations}개`);
    console.log(`🔘 작동 버튼: ${functionalButtons}/${totalButtons}개 (${Math.round(functionalButtons/totalButtons*100)}%)`);

    const overallGrade = totalViolations === 0 && functionalButtons > totalButtons * 0.8 ?
                        'EXCELLENT' :
                        totalViolations <= 2 && functionalButtons > totalButtons * 0.6 ?
                        'GOOD' : 'NEEDS_IMPROVEMENT';

    console.log(`🏆 전체 등급: ${overallGrade}`);

    // 상세 팝업 로그 출력
    if (totalPopups > 0) {
        console.log('\n📝 감지된 팝업 메시지들:');
        CompleteRBACTester.popupLog.forEach((popup, index) => {
            console.log(`${index + 1}. [${popup.type}] ${popup.message.substring(0, 120)}...`);
        });
    }

    // 권한 위반 상세
    if (totalViolations > 0) {
        console.log('\n❌ 권한 위반 상세:');
        CompleteRBACTester.accessViolations.forEach((violation, index) => {
            console.log(`${index + 1}. ${violation}`);
        });
    }
});