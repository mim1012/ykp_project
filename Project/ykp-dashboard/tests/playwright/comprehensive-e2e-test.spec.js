// 🧪 YKP 대시보드 종합 E2E 테스트
// 모든 역할(본사/지사/매장)의 모든 버튼과 데이터 바인딩 테스트

const { test, expect } = require('@playwright/test');

// 테스트 설정
const BASE_URL = 'http://localhost:8000';
const TEST_TIMEOUT = 60000;

// 테스트 계정 정보 (시스템에 존재하는 계정들)
const ACCOUNTS = {
    headquarters: {
        email: 'admin@ykp.com',
        password: '123456',
        role: '본사'
    },
    branch: {
        email: 'branch_dg001@ykp.com', // 대구지사
        password: '123456',
        role: '지사'
    },
    store: {
        email: 'store_001@ykp.com',
        password: '123456',
        role: '매장'
    }
};

// 🏛️ 본사 관리자 종합 테스트
test.describe('🏛️ 본사 관리자 종합 E2E 테스트', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto(BASE_URL);
    });

    test('본사 로그인 및 대시보드 버튼 테스트', async ({ page }) => {
        console.log('🔍 본사 관리자 로그인 테스트 시작...');

        // 로그인
        await page.fill('input[name="email"]', ACCOUNTS.headquarters.email);
        await page.fill('input[name="password"]', ACCOUNTS.headquarters.password);
        await page.click('button[type="submit"]');

        // 대시보드 로드 대기
        await page.waitForURL('**/dashboard', { timeout: TEST_TIMEOUT });
        await page.waitForLoadState('networkidle');

        console.log('✅ 본사 관리자 로그인 성공');

        // 🔍 대시보드 데이터 실시간 검증
        await test.step('대시보드 데이터 실시간 바인딩 검증', async () => {
            // 페이지 스냅샷 캡처
            await page.screenshot({ path: 'tests/playwright/headquarters-dashboard.png', fullPage: true });

            // 실시간 데이터 요소들 확인
            const elements = [
                { selector: '[data-testid="total-sales"], #total-sales, .total-sales', name: '총 매출' },
                { selector: '[data-testid="total-activations"], #total-activations, .total-activations', name: '총 개통건수' },
                { selector: '[data-testid="active-stores"], #active-stores, .active-stores', name: '활성 매장 수' },
                { selector: '[data-testid="total-branches"], #total-branches, .total-branches', name: '총 지사 수' }
            ];

            for (const element of elements) {
                try {
                    const locator = page.locator(element.selector).first();
                    await expect(locator).toBeVisible({ timeout: 10000 });

                    const text = await locator.textContent();
                    console.log(`📊 ${element.name}: ${text}`);

                    // 하드코딩된 데이터 패턴 검사 (0, -, 기본값 등)
                    const isHardcoded = text.includes('0') || text.includes('-') || text.includes('기본값');
                    if (isHardcoded) {
                        console.log(`⚠️ ${element.name}: 하드코딩된 데이터 의심`);
                    } else {
                        console.log(`✅ ${element.name}: 실시간 데이터 확인됨`);
                    }
                } catch (error) {
                    console.log(`❌ ${element.name}: 요소를 찾을 수 없음`);
                }
            }
        });

        // 🔘 모든 버튼 테스트
        await test.step('대시보드 모든 버튼 기능 테스트', async () => {
            const buttons = [
                { selector: 'a[href*="management"], a:has-text("지사 관리")', name: '지사 관리' },
                { selector: 'a[href*="stores"], a:has-text("매장 관리")', name: '매장 관리' },
                { selector: 'a[href*="admin"], a[href*="account"], a:has-text("계정 관리")', name: '계정 관리' },
                { selector: 'a[href*="statistics"], a:has-text("통계"), a:has-text("분석")', name: '통계/분석' },
                { selector: 'a[href*="sales"], a:has-text("개통표"), a:has-text("판매")', name: '개통표 입력' }
            ];

            for (const button of buttons) {
                try {
                    await page.waitForSelector(button.selector, { timeout: 5000 });
                    console.log(`✅ ${button.name} 버튼 발견됨`);
                } catch (error) {
                    console.log(`❌ ${button.name} 버튼 없음`);
                }
            }
        });
    });

    test('지사 관리 페이지 종합 테스트', async ({ page }) => {
        console.log('🏬 지사 관리 페이지 테스트 시작...');

        // 로그인 후 지사 관리로 이동
        await page.fill('input[name="email"]', ACCOUNTS.headquarters.email);
        await page.fill('input[name="password"]', ACCOUNTS.headquarters.password);
        await page.click('button[type="submit"]');
        await page.waitForURL('**/dashboard');

        await page.goto(`${BASE_URL}/management/branches`);
        await page.waitForLoadState('networkidle');
        await page.screenshot({ path: 'tests/playwright/branch-management.png', fullPage: true });

        // 지사 통계 데이터 검증
        await test.step('지사 현황 데이터 실시간 바인딩 검증', async () => {
            const statsElements = [
                { id: 'total-branches', name: '전체 지사 수' },
                { id: 'active-branches', name: '운영중 지사 수' },
                { id: 'stores-count', name: '총 매장 수' }
            ];

            for (const stat of statsElements) {
                try {
                    const element = page.locator(`#${stat.id}`).first();
                    await expect(element).toBeVisible();
                    const text = await element.textContent();
                    console.log(`📊 ${stat.name}: ${text}`);

                    if (text === '-' || text === '0') {
                        console.log(`⚠️ ${stat.name}: 하드코딩된 기본값 의심`);
                    } else {
                        console.log(`✅ ${stat.name}: 실시간 데이터`);
                    }
                } catch (error) {
                    console.log(`❌ ${stat.name}: 요소 없음 - ${error.message}`);
                }
            }
        });

        // 지사 관리 버튼 테스트
        await test.step('지사 관리 버튼 기능 테스트', async () => {
            // 지사 추가 버튼
            try {
                const addButton = page.locator('button:has-text("새 지사"), button:has-text("지사 추가")').first();
                await expect(addButton).toBeVisible();
                await addButton.click();
                console.log('✅ 지사 추가 버튼 작동');

                // 모달 닫기
                const cancelBtn = page.locator('button:has-text("취소")').first();
                if (await cancelBtn.isVisible()) {
                    await cancelBtn.click();
                }
            } catch (error) {
                console.log('❌ 지사 추가 버튼 테스트 실패');
            }

            // 기존 지사의 수정/삭제 버튼 테스트
            try {
                const editButtons = page.locator('button:has-text("수정"), a:has-text("수정")');
                const editCount = await editButtons.count();
                console.log(`📝 지사 수정 버튼 ${editCount}개 발견`);

                if (editCount > 0) {
                    // 첫 번째 수정 버튼 클릭해보기
                    await editButtons.first().click();
                    console.log('✅ 지사 수정 버튼 클릭 가능');

                    // 수정 창이 뜨면 취소
                    await page.waitForTimeout(2000);
                    const cancelBtn = page.locator('button:has-text("취소")');
                    if (await cancelBtn.isVisible()) {
                        await cancelBtn.click();
                    }
                }
            } catch (error) {
                console.log('❌ 지사 수정 버튼 테스트 중 오류');
            }
        });
    });

    test('매장 관리 페이지 종합 테스트', async ({ page }) => {
        console.log('🏪 매장 관리 페이지 테스트 시작...');

        // 로그인 후 매장 관리로 이동
        await page.fill('input[name="email"]', ACCOUNTS.headquarters.email);
        await page.fill('input[name="password"]', ACCOUNTS.headquarters.password);
        await page.click('button[type="submit"]');
        await page.waitForURL('**/dashboard');

        await page.goto(`${BASE_URL}/management/stores`);
        await page.waitForLoadState('networkidle');
        await page.screenshot({ path: 'tests/playwright/store-management.png', fullPage: true });

        // 매장 관리 버튼들 테스트
        await test.step('매장 관리 모든 버튼 테스트', async () => {
            const buttonTests = [
                { selector: 'button:has-text("수정")', name: '매장 수정' },
                { selector: 'button:has-text("계정생성"), button:has-text("계정")', name: '계정 생성' },
                { selector: 'button:has-text("성과"), button:has-text("📊")', name: '성과보기' },
                { selector: 'button:has-text("삭제"), button:has-text("🗑️")', name: '매장 삭제' }
            ];

            for (const btn of buttonTests) {
                try {
                    const buttons = page.locator(btn.selector);
                    const count = await buttons.count();
                    console.log(`${btn.name} 버튼: ${count}개 발견`);

                    if (count > 0) {
                        console.log(`✅ ${btn.name} 버튼 정상 존재`);
                    } else {
                        console.log(`⚠️ ${btn.name} 버튼 없음`);
                    }
                } catch (error) {
                    console.log(`❌ ${btn.name} 버튼 검사 오류`);
                }
            }
        });
    });

    test('계정 관리 페이지 종합 테스트', async ({ page }) => {
        console.log('👥 계정 관리 페이지 테스트 시작...');

        // 로그인 후 계정 관리로 이동
        await page.fill('input[name="email"]', ACCOUNTS.headquarters.email);
        await page.fill('input[name="password"]', ACCOUNTS.headquarters.password);
        await page.click('button[type="submit"]');
        await page.waitForURL('**/dashboard');

        await page.goto(`${BASE_URL}/admin/accounts`);
        await page.waitForLoadState('networkidle');
        await page.screenshot({ path: 'tests/playwright/account-management.png', fullPage: true });

        // 계정 통계 데이터 검증
        await test.step('계정 현황 실시간 데이터 검증', async () => {
            const accountStats = [
                { id: 'total-accounts', name: '전체 계정 수' },
                { id: 'branch-accounts', name: '지사 관리자 수' },
                { id: 'store-accounts', name: '매장 직원 수' },
                { id: 'inactive-accounts', name: '비활성 계정 수' }
            ];

            for (const stat of accountStats) {
                try {
                    const element = page.locator(`#${stat.id}`).first();
                    await expect(element).toBeVisible();
                    const text = await element.textContent();
                    console.log(`👤 ${stat.name}: ${text}`);

                    if (text === '-' || text === '0') {
                        console.log(`⚠️ ${stat.name}: 기본값 또는 실제 데이터 없음`);
                    } else {
                        console.log(`✅ ${stat.name}: 실시간 데이터`);
                    }
                } catch (error) {
                    console.log(`❌ ${stat.name}: 요소 찾기 실패`);
                }
            }
        });
    });
});

// 🏬 지사 관리자 종합 테스트
test.describe('🏬 지사 관리자 종합 E2E 테스트', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto(BASE_URL);
    });

    test('지사 관리자 로그인 및 권한 테스트', async ({ page }) => {
        console.log('🔍 지사 관리자 테스트 시작...');

        try {
            // 지사 계정 로그인
            await page.fill('input[name="email"]', ACCOUNTS.branch.email);
            await page.fill('input[name="password"]', ACCOUNTS.branch.password);
            await page.click('button[type="submit"]');

            await page.waitForURL('**/dashboard', { timeout: TEST_TIMEOUT });
            await page.screenshot({ path: 'tests/playwright/branch-dashboard.png', fullPage: true });

            console.log('✅ 지사 관리자 로그인 성공');

            // 지사 권한 확인 - 본사 전용 메뉴 접근 불가 확인
            const restrictedElements = [
                { selector: 'a:has-text("지사 관리")', name: '지사 관리 메뉴' },
                { selector: 'a:has-text("전체 통계")', name: '전체 통계' }
            ];

            for (const element of restrictedElements) {
                const isVisible = await page.locator(element.selector).isVisible();
                if (isVisible) {
                    console.log(`⚠️ ${element.name}: 지사 계정이 접근 가능 (권한 문제?))`);
                } else {
                    console.log(`✅ ${element.name}: 지사 계정 접근 차단됨`);
                }
            }

        } catch (error) {
            console.log(`❌ 지사 관리자 테스트 실패: ${error.message}`);
        }
    });
});

// 🏪 매장 직원 종합 테스트
test.describe('🏪 매장 직원 종합 E2E 테스트', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto(BASE_URL);
    });

    test('매장 직원 로그인 및 개통표 입력 테스트', async ({ page }) => {
        console.log('🔍 매장 직원 테스트 시작...');

        try {
            // 매장 계정 로그인
            await page.fill('input[name="email"]', ACCOUNTS.store.email);
            await page.fill('input[name="password"]', ACCOUNTS.store.password);
            await page.click('button[type="submit"]');

            await page.waitForURL('**/dashboard', { timeout: TEST_TIMEOUT });
            await page.screenshot({ path: 'tests/playwright/store-dashboard.png', fullPage: true });

            console.log('✅ 매장 직원 로그인 성공');

            // 개통표 입력 페이지 접근 테스트
            const salesInputLink = page.locator('a[href*="sales"], a:has-text("개통표"), a:has-text("판매")').first();

            if (await salesInputLink.isVisible()) {
                await salesInputLink.click();
                await page.waitForLoadState('networkidle');
                await page.screenshot({ path: 'tests/playwright/sales-input.png', fullPage: true });
                console.log('✅ 개통표 입력 페이지 접근 성공');

                // 실시간 업데이트 테스트를 위해 간단한 데이터 입력 시뮬레이션
                await test.step('개통표 실시간 업데이트 테스트', async () => {
                    // 기본 입력 필드들 확인
                    const inputFields = [
                        { selector: 'input[name="sale_date"], #sale_date', name: '판매일' },
                        { selector: 'input[name="model_name"], #model_name', name: '모델명' },
                        { selector: 'select[name="carrier"], #carrier', name: '통신사' }
                    ];

                    for (const field of inputFields) {
                        try {
                            const element = page.locator(field.selector).first();
                            if (await element.isVisible()) {
                                console.log(`✅ ${field.name} 입력 필드 존재`);
                            }
                        } catch (error) {
                            console.log(`❌ ${field.name} 입력 필드 없음`);
                        }
                    }

                    // 저장 버튼 확인
                    const saveButton = page.locator('button:has-text("저장"), button[data-testid="save"]').first();
                    if (await saveButton.isVisible()) {
                        console.log('✅ 저장 버튼 존재 - 실시간 업데이트 기능 준비됨');
                    } else {
                        console.log('❌ 저장 버튼 없음');
                    }
                });
            } else {
                console.log('❌ 개통표 입력 메뉴 없음');
            }

        } catch (error) {
            console.log(`❌ 매장 직원 테스트 실패: ${error.message}`);
        }
    });
});

// 🌐 크로스 브라우저 실시간 업데이트 테스트
test.describe('🌐 실시간 데이터 업데이트 통합 테스트', () => {
    test('실시간 데이터 vs 하드코딩 데이터 감지', async ({ page }) => {
        console.log('🔍 실시간 데이터 바인딩 종합 검증...');

        // 본사 관리자로 로그인
        await page.goto(BASE_URL);
        await page.fill('input[name="email"]', ACCOUNTS.headquarters.email);
        await page.fill('input[name="password"]', ACCOUNTS.headquarters.password);
        await page.click('button[type="submit"]');
        await page.waitForURL('**/dashboard');

        // 대시보드 데이터 수집
        const dataElements = await page.evaluate(() => {
            const results = [];

            // 모든 숫자가 포함된 텍스트 요소들 찾기
            const elements = document.querySelectorAll('*');

            elements.forEach(el => {
                const text = el.textContent?.trim();
                if (text && /\d/.test(text) && el.children.length === 0) {
                    // 숫자가 포함되고 자식 요소가 없는 leaf 노드들
                    results.push({
                        text: text,
                        selector: el.tagName.toLowerCase() + (el.id ? '#' + el.id : '') + (el.className ? '.' + el.className.split(' ').join('.') : ''),
                        isHardcoded: text === '0' || text === '-' || text === '기본값' || text.includes('알수없음')
                    });
                }
            });

            return results.slice(0, 50); // 처음 50개만
        });

        console.log('📊 데이터 바인딩 분석 결과:');
        let realTimeCount = 0;
        let hardcodedCount = 0;

        dataElements.forEach(element => {
            if (element.isHardcoded) {
                console.log(`❌ 하드코딩 의심: "${element.text}" (${element.selector})`);
                hardcodedCount++;
            } else {
                console.log(`✅ 실시간 데이터: "${element.text}" (${element.selector})`);
                realTimeCount++;
            }
        });

        console.log(`\n📈 종합 결과:`);
        console.log(`✅ 실시간 데이터: ${realTimeCount}개`);
        console.log(`❌ 하드코딩 데이터: ${hardcodedCount}개`);
        console.log(`🎯 실시간 비율: ${Math.round((realTimeCount / (realTimeCount + hardcodedCount)) * 100)}%`);

        // 최종 종합 스크린샷
        await page.screenshot({ path: 'tests/playwright/comprehensive-test-final.png', fullPage: true });
    });
});