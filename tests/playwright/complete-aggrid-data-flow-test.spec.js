import { test, expect } from '@playwright/test';

/**
 * 개통표 입력 페이지 - 전체보기 및 수정→저장 플로우 테스트
 *
 * 목적: 프론트엔드와 백엔드 간 데이터 바인딩 검증
 * - 전체보기 버튼 클릭 시 all_data=true 파라미터 전송
 * - 수정된 데이터 저장 시 id 필드 포함하여 업데이트
 */

const BASE_URL = 'http://localhost:8000'; // Laravel 기본 포트

test.describe('개통표 데이터 플로우 테스트 (매장 계정)', () => {
    test.beforeEach(async ({ page }) => {
        // 실제 존재하는 매장 계정으로 로그인
        await page.goto(`${BASE_URL}/login`);

        // 로그인 폼 입력
        await page.fill('#email', 'z002-001@ykp.com'); // 이대역점 매장
        await page.fill('#password', 'password');

        // 로그인 버튼 클릭
        await page.click('button[type="submit"]:has-text("로그인")');

        // 대시보드로 리디렉션 대기
        await page.waitForURL('**/dashboard', { timeout: 10000 });
        await page.waitForLoadState('networkidle');

        console.log('✅ 매장 계정 로그인 완료 (z002-001@ykp.com - 이대역점)');
    });

    test('1. 전체보기 기능 - all_data=true 파라미터 전송 확인', async ({ page }) => {
        // 개통표 입력 페이지로 이동
        await page.goto(`${BASE_URL}/sales/complete-aggrid`);
        await page.waitForLoadState('networkidle');

        // API 요청 감시 시작
        let allDataRequest = null;
        page.on('request', request => {
            if (request.url().includes('/api/sales') && request.method() === 'GET') {
                const url = new URL(request.url());
                if (url.searchParams.has('all_data')) {
                    allDataRequest = {
                        url: request.url(),
                        params: Object.fromEntries(url.searchParams)
                    };
                    console.log('📡 전체보기 API 요청 감지:', allDataRequest);
                }
            }
        });

        // "전체 보기" 버튼 클릭 (onclick 속성으로 찾기)
        const viewAllButton = page.locator('button[onclick="clearDateFilter()"]');
        await expect(viewAllButton).toBeVisible({ timeout: 10000 });

        // 버튼 텍스트 확인 (디버깅용)
        const buttonText = await viewAllButton.textContent();
        console.log(`📍 버튼 텍스트: "${buttonText}"`);

        await viewAllButton.click();

        // 네트워크 요청 완료 대기
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(1000); // API 요청 처리 대기

        // 검증: all_data 파라미터 전송 확인
        expect(allDataRequest).not.toBeNull();
        expect(allDataRequest.params).toHaveProperty('all_data');
        expect(allDataRequest.params.all_data).toBe('true');
        console.log('✅ all_data=true 파라미터 전송 확인');

        // 검증: 날짜 파라미터가 없어야 함 (전체 조회)
        expect(allDataRequest.params).not.toHaveProperty('sale_date');
        expect(allDataRequest.params).not.toHaveProperty('start_date');
        expect(allDataRequest.params).not.toHaveProperty('end_date');
        console.log('✅ 날짜 필터 없음 확인 (전체 조회)');

        // 상태 표시 확인
        const statusText = await page.locator('#dateStatus').textContent();
        expect(statusText).toContain('전체');
        console.log('✅ 상태 메시지:', statusText);
    });

    test('2. 신규 데이터 저장 후 전체보기 테스트', async ({ page }) => {
        await page.goto(`${BASE_URL}/sales/complete-aggrid`);
        await page.waitForLoadState('networkidle');

        // "새 개통 등록" 버튼 클릭
        await page.click('button:has-text("새 개통 등록")');
        await page.waitForTimeout(500);

        // 첫 번째 행에 데이터 입력
        const today = new Date().toISOString().split('T')[0];

        // 판매 날짜
        const dateInput = page.locator('input[type="date"]').first();
        await dateInput.fill(today);

        // 판매자
        const salespersonInput = page.locator('input[placeholder*="판매자"], input[id^="salesperson-"]').first();
        await salespersonInput.fill('테스트판매자');

        // 대리점
        const dealerSelect = page.locator('select[id^="dealer-select-"]').first();
        if (await dealerSelect.count() > 0) {
            await dealerSelect.selectOption({ index: 1 }); // 첫 번째 대리점 선택
        }

        // 통신사
        const carrierSelect = page.locator('select[id^="carrier-select-"]').first();
        await carrierSelect.selectOption('SK');

        // 개통방식
        const activationSelect = page.locator('select[id^="activation-select-"]').first();
        await activationSelect.selectOption('신규');

        // 모델명
        const modelInput = page.locator('input[id^="model-"]').first();
        await modelInput.fill('갤럭시S24');

        // 금액 필드 (액면가)
        const basePriceInput = page.locator('input[id^="base-price-"]').first();
        await basePriceInput.fill('100000');

        console.log('✅ 신규 데이터 입력 완료');

        // API 응답 감시
        const saveResponsePromise = page.waitForResponse(
            response => response.url().includes('/api/sales/bulk-save') && response.status() === 201
        );

        // 저장 버튼 클릭
        await page.click('button:has-text("전체 저장")');

        // 저장 완료 대기
        const saveResponse = await saveResponsePromise;
        const saveData = await saveResponse.json();

        expect(saveData.success).toBe(true);
        expect(saveData.saved_count).toBeGreaterThan(0);
        console.log(`✅ 데이터 저장 완료: ${saveData.saved_count}개`);

        // 데이터 재로드 대기
        await page.waitForTimeout(2000);

        // 전체보기 버튼 클릭
        await page.click('button[onclick="clearDateFilter()"]');
        await page.waitForLoadState('networkidle');

        // 저장한 데이터가 표시되는지 확인
        const savedDataCell = page.locator('td:has-text("테스트판매자")');
        await expect(savedDataCell).toBeVisible();
        console.log('✅ 저장된 데이터 전체보기에서 확인됨');
    });

    test('3. 데이터 수정 후 저장 - id 필드 포함 확인', async ({ page }) => {
        await page.goto(`${BASE_URL}/sales/complete-aggrid`);
        await page.waitForLoadState('networkidle');

        // 기존 데이터 로드 대기
        await page.waitForTimeout(2000);

        // 첫 번째 행이 있는지 확인
        const firstRow = page.locator('tbody tr').first();
        const rowCount = await page.locator('tbody tr').count();

        if (rowCount === 0) {
            console.log('⚠️ 기존 데이터가 없어 테스트를 건너뜁니다.');
            test.skip();
            return;
        }

        console.log(`📊 현재 데이터 행 수: ${rowCount}`);

        // 첫 번째 행의 액면가(base_price) 수정
        const basePriceInput = page.locator('input[id^="base-price-"]').first();
        const originalValue = await basePriceInput.inputValue();
        const newValue = '250000'; // 수정된 값

        await basePriceInput.clear();
        await basePriceInput.fill(newValue);
        console.log(`✏️ 액면가 수정: ${originalValue} → ${newValue}`);

        // 구두1(verbal1) 수정
        const verbal1Input = page.locator('input[id^="verbal1-"]').first();
        await verbal1Input.clear();
        await verbal1Input.fill('50000');
        console.log('✏️ 구두1 수정: 50000');

        // API 요청 감시
        let bulkSaveRequest = null;
        page.on('request', request => {
            if (request.url().includes('/api/sales/bulk-save') && request.method() === 'POST') {
                try {
                    const postData = request.postDataJSON();
                    bulkSaveRequest = postData;
                    console.log('📡 저장 API 요청 감지:', JSON.stringify(postData, null, 2));
                } catch (e) {
                    console.log('⚠️ POST 데이터 파싱 실패:', e.message);
                }
            }
        });

        // 저장 버튼 클릭
        const saveResponsePromise = page.waitForResponse(
            response => response.url().includes('/api/sales/bulk-save')
        );

        await page.click('button:has-text("전체 저장")');

        const saveResponse = await saveResponsePromise;
        const saveData = await saveResponse.json();

        // 검증: 저장 성공
        expect(saveData.success).toBe(true);
        console.log(`✅ 저장 성공: ${saveData.message}`);

        // 검증: id 필드 포함 확인
        expect(bulkSaveRequest).not.toBeNull();
        expect(bulkSaveRequest.sales).toBeDefined();
        expect(Array.isArray(bulkSaveRequest.sales)).toBe(true);

        // 첫 번째 항목에 id가 있어야 함 (기존 데이터 수정)
        const firstSale = bulkSaveRequest.sales[0];
        expect(firstSale).toHaveProperty('id');
        expect(typeof firstSale.id).toBe('number');
        console.log(`✅ id 필드 포함 확인: ${firstSale.id}`);

        // 수정된 값 확인
        expect(firstSale.base_price).toBe(250000);
        expect(firstSale.verbal1).toBe(50000);
        console.log('✅ 수정된 값 전송 확인');

        // 페이지 새로고침 후 값 유지 확인
        await page.reload();
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(2000);

        // 수정된 값이 유지되는지 확인
        const reloadedBasePriceInput = page.locator('input[id^="base-price-"]').first();
        const reloadedValue = await reloadedBasePriceInput.inputValue();

        expect(reloadedValue).toBe(newValue);
        console.log(`✅ 새로고침 후 값 유지 확인: ${reloadedValue}`);
    });

    test('4. RBAC 검증 - 매장 계정은 자기 매장 데이터만 조회', async ({ page }) => {
        await page.goto(`${BASE_URL}/sales/complete-aggrid`);
        await page.waitForLoadState('networkidle');

        // API 요청 감시
        let apiRequest = null;
        page.on('request', request => {
            if (request.url().includes('/api/sales') && request.method() === 'GET') {
                const url = new URL(request.url());
                apiRequest = {
                    url: request.url(),
                    params: Object.fromEntries(url.searchParams)
                };
            }
        });

        // 전체보기 클릭
        await page.click('button[onclick="clearDateFilter()"]');
        await page.waitForLoadState('networkidle');

        // 검증: store_id 파라미터 포함 확인
        expect(apiRequest).not.toBeNull();
        expect(apiRequest.params).toHaveProperty('store_id');
        console.log(`✅ RBAC 확인: store_id=${apiRequest.params.store_id} 필터 적용됨`);

        // branch_id나 본사 권한 파라미터는 없어야 함
        expect(apiRequest.params).not.toHaveProperty('branch_id');
        console.log('✅ 매장 계정은 자기 매장만 조회 가능');
    });

    test('5. CSRF 토큰 확인', async ({ page }) => {
        await page.goto(`${BASE_URL}/sales/complete-aggrid`);
        await page.waitForLoadState('networkidle');

        // CSRF 토큰 존재 확인
        const csrfToken = await page.evaluate(() => {
            return window.csrfToken || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        });

        expect(csrfToken).toBeTruthy();
        expect(csrfToken.length).toBeGreaterThan(10);
        console.log('✅ CSRF 토큰 확인:', csrfToken.substring(0, 20) + '...');

        // API 요청 시 CSRF 토큰 헤더 포함 확인
        let csrfHeaderIncluded = false;
        page.on('request', request => {
            if (request.url().includes('/api/sales/bulk-save')) {
                const headers = request.headers();
                if (headers['x-csrf-token'] === csrfToken) {
                    csrfHeaderIncluded = true;
                }
            }
        });

        // 새 행 추가 및 저장 시도
        await page.click('button:has-text("새 개통 등록")');
        await page.waitForTimeout(500);

        // 최소 필수 필드만 입력
        const dateInput = page.locator('input[type="date"]').first();
        await dateInput.fill(new Date().toISOString().split('T')[0]);

        await page.click('button:has-text("전체 저장")');
        await page.waitForTimeout(1000);

        expect(csrfHeaderIncluded).toBe(true);
        console.log('✅ API 요청에 CSRF 토큰 포함 확인');
    });

    test('6. all_data 다양한 값 테스트 (1, "1", true)', async ({ page }) => {
        await page.goto(`${BASE_URL}/sales/complete-aggrid`);
        await page.waitForLoadState('networkidle');

        // 백엔드가 all_data ∈ {true, 'true', 1, '1'}을 모두 허용하는지 테스트
        const testCases = [
            { value: 'true', expected: true },
            { value: '1', expected: true },
            { value: 1, expected: true }
        ];

        for (const testCase of testCases) {
            // API 요청 가로채기
            await page.route('**/api/sales*', route => {
                const url = new URL(route.request().url());
                url.searchParams.set('all_data', testCase.value.toString());

                route.continue({
                    url: url.toString()
                });
            });

            // 전체보기 클릭
            await page.click('button[onclick="clearDateFilter()"]');
            await page.waitForTimeout(1000);

            // 에러 없이 로드되었는지 확인
            const errorElement = page.locator('.error, .alert-danger');
            expect(await errorElement.count()).toBe(0);

            console.log(`✅ all_data=${testCase.value} 처리 확인`);

            // 다음 테스트를 위해 라우트 초기화
            await page.unroute('**/api/sales*');
        }
    });
});

test.describe('엣지 케이스 테스트', () => {
    test.beforeEach(async ({ page }) => {
        // 실제 존재하는 매장 계정으로 로그인
        await page.goto(`${BASE_URL}/login`);

        // 로그인 폼 입력
        await page.fill('#email', 'z002-001@ykp.com'); // 이대역점 매장
        await page.fill('#password', 'password');

        // 로그인 버튼 클릭
        await page.click('button[type="submit"]:has-text("로그인")');

        // 대시보드로 리디렉션 대기
        await page.waitForURL('**/dashboard', { timeout: 10000 });
        await page.waitForLoadState('networkidle');

        console.log('✅ 매장 계정 로그인 완료 (z002-001@ykp.com - 이대역점)');
    });

    test('7. 중복 생성 방지 - 업데이트 시 새 행 생성 안 됨', async ({ page }) => {
        await page.goto(`${BASE_URL}/sales/complete-aggrid`);
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(2000);

        // 현재 행 수 확인
        const initialRowCount = await page.locator('tbody tr').count();

        if (initialRowCount === 0) {
            console.log('⚠️ 기존 데이터가 없어 테스트를 건너뜁니다.');
            test.skip();
            return;
        }

        console.log(`📊 초기 행 수: ${initialRowCount}`);

        // 첫 번째 행 수정
        const basePriceInput = page.locator('input[id^="base-price-"]').first();
        await basePriceInput.clear();
        await basePriceInput.fill('999999');

        // 저장
        await page.click('button:has-text("전체 저장")');
        await page.waitForTimeout(2000);

        // 새로고침 후 행 수 확인
        await page.reload();
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(2000);

        const finalRowCount = await page.locator('tbody tr').count();

        // 행 수가 동일해야 함 (중복 생성 없음)
        expect(finalRowCount).toBe(initialRowCount);
        console.log(`✅ 행 수 유지 확인: ${initialRowCount} → ${finalRowCount}`);

        // 수정된 값 확인
        const updatedValue = await page.locator('input[id^="base-price-"]').first().inputValue();
        expect(updatedValue).toBe('999999');
        console.log('✅ 중복 생성 없이 업데이트 확인');
    });

    test('8. 유효성 검증 실패 시 저장 차단', async ({ page }) => {
        await page.goto(`${BASE_URL}/sales/complete-aggrid`);
        await page.waitForLoadState('networkidle');

        // 새 행 추가
        await page.click('button:has-text("새 개통 등록")');
        await page.waitForTimeout(500);

        // 필수 필드를 비워두고 저장 시도
        await page.click('button:has-text("전체 저장")');
        await page.waitForTimeout(1000);

        // 에러 메시지 또는 경고 확인
        const statusIndicator = page.locator('#status-indicator');
        const statusText = await statusIndicator.textContent();

        expect(statusText).toMatch(/오류|검증|실패|없습니다/);
        console.log('✅ 유효성 검증 실패 시 저장 차단 확인:', statusText);
    });
});
