/**
 * 매장 목록 로딩 문제 진단 테스트
 *
 * 목적:
 * - /management/stores 페이지의 로딩 문제 원인 파악
 * - API 엔드포인트 응답 확인
 * - 네트워크 에러 및 콘솔 에러 수집
 */

import { test, expect } from '@playwright/test';

test.describe('매장 목록 로딩 진단', () => {
    let consoleLogs = [];
    let consoleErrors = [];
    let networkRequests = [];
    let networkResponses = [];

    test.beforeEach(async ({ page }) => {
        // 콘솔 로그 수집
        page.on('console', (msg) => {
            const text = msg.text();
            if (msg.type() === 'error') {
                consoleErrors.push(text);
                console.log('🔴 Console Error:', text);
            } else {
                consoleLogs.push(text);
                console.log('📝 Console:', text);
            }
        });

        // 네트워크 요청 모니터링
        page.on('request', (request) => {
            const url = request.url();
            const method = request.method();
            networkRequests.push({ url, method, timestamp: Date.now() });
            console.log(`📤 Request: ${method} ${url}`);
        });

        // 네트워크 응답 모니터링
        page.on('response', async (response) => {
            const url = response.url();
            const status = response.status();
            const method = response.request().method();

            let responseData = null;
            try {
                if (url.includes('/api/')) {
                    responseData = await response.json();
                }
            } catch (e) {
                // JSON 파싱 실패는 무시
            }

            networkResponses.push({
                url,
                method,
                status,
                responseData,
                timestamp: Date.now()
            });

            const statusEmoji = status >= 200 && status < 300 ? '✅' :
                               status >= 400 && status < 500 ? '⚠️' :
                               status >= 500 ? '🔴' : '📥';

            console.log(`${statusEmoji} Response: ${method} ${url} - ${status}`);

            if (responseData) {
                console.log('   Data:', JSON.stringify(responseData, null, 2).substring(0, 200));
            }
        });
    });

    test('1단계: 서버 응답 확인 - 로그인 전', async ({ page }) => {
        console.log('\n=== 1단계: 로그인 없이 페이지 접속 ===\n');

        // 페이지 접속
        const response = await page.goto('http://localhost:8000/management/stores', {
            waitUntil: 'networkidle',
            timeout: 60000
        });

        console.log(`\n📊 페이지 응답 상태: ${response?.status()}`);

        // 스크린샷 캡처
        await page.screenshot({
            path: 'tests/playwright/screenshots/store-loading-no-auth.png',
            fullPage: true
        });
        console.log('📸 스크린샷 저장: store-loading-no-auth.png');

        // URL 확인 (로그인 페이지로 리다이렉트 되었는지)
        const currentUrl = page.url();
        console.log(`\n현재 URL: ${currentUrl}`);

        if (currentUrl.includes('/login')) {
            console.log('✅ 인증 필요 - 로그인 페이지로 리다이렉트됨');
        } else {
            console.log('⚠️ 예상과 다름 - 로그인 없이 접근 가능?');
        }
    });

    test('2단계: 로그인 후 매장 목록 로딩 테스트', async ({ page }) => {
        console.log('\n=== 2단계: 로그인 후 매장 목록 접속 ===\n');

        // 로그인 페이지 접속
        await page.goto('http://localhost:8000/login', { waitUntil: 'networkidle' });
        console.log('📍 로그인 페이지 접속 완료');

        // 로그인 (본사 계정)
        await page.fill('input[name="email"]', 'admin@ykp.com');
        await page.fill('input[name="password"]', 'password');
        console.log('✍️ 로그인 정보 입력 완료');

        await page.click('button[type="submit"]');
        console.log('🔐 로그인 버튼 클릭');

        // 로그인 완료 대기 (대시보드로 이동)
        await page.waitForURL('**/dashboard', { timeout: 10000 });
        console.log('✅ 로그인 성공 - 대시보드 진입');

        // 매장 관리 페이지로 이동
        console.log('\n📍 매장 관리 페이지로 이동 중...');
        await page.goto('http://localhost:8000/management/stores', {
            waitUntil: 'domcontentloaded',
            timeout: 60000
        });

        // 로딩 상태 확인
        const loadingExists = await page.locator('text=매장 데이터 로딩 중').isVisible({ timeout: 5000 }).catch(() => false);

        if (loadingExists) {
            console.log('⏳ 로딩 스피너 감지됨');
            await page.screenshot({
                path: 'tests/playwright/screenshots/store-loading-spinner.png',
                fullPage: true
            });
            console.log('📸 스크린샷 저장: store-loading-spinner.png');

            // 로딩이 완료될 때까지 대기 (최대 30초)
            console.log('⏱️ 로딩 완료 대기 중... (최대 30초)');
            const loadingDisappeared = await page.locator('text=매장 데이터 로딩 중')
                .waitFor({ state: 'hidden', timeout: 30000 })
                .then(() => true)
                .catch(() => false);

            if (loadingDisappeared) {
                console.log('✅ 로딩 완료!');
            } else {
                console.log('❌ 로딩이 30초 내에 완료되지 않음 - 문제 발생!');
            }
        } else {
            console.log('✅ 로딩 스피너 없음 - 즉시 로드됨');
        }

        // 최종 페이지 상태 캡처
        await page.screenshot({
            path: 'tests/playwright/screenshots/store-loading-final.png',
            fullPage: true
        });
        console.log('📸 최종 스크린샷 저장: store-loading-final.png');

        // 매장 데이터가 표시되는지 확인
        const hasStoreData = await page.locator('.space-y-6').isVisible({ timeout: 5000 }).catch(() => false);
        console.log(`\n매장 데이터 표시: ${hasStoreData ? '✅ 예' : '❌ 아니오'}`);

        // 에러 메시지 확인
        const errorVisible = await page.locator('text=매장 데이터 로드 실패').isVisible({ timeout: 2000 }).catch(() => false);
        if (errorVisible) {
            const errorText = await page.locator('text=매장 데이터 로드 실패').textContent();
            console.log(`\n🔴 에러 메시지 발견: ${errorText}`);
        }
    });

    test('3단계: API 엔드포인트 직접 분석', async ({ page }) => {
        console.log('\n=== 3단계: /api/stores 엔드포인트 분석 ===\n');

        // 먼저 로그인
        await page.goto('http://localhost:8000/login', { waitUntil: 'networkidle' });
        await page.fill('input[name="email"]', 'admin@ykp.com');
        await page.fill('input[name="password"]', 'password');
        await page.click('button[type="submit"]');
        await page.waitForURL('**/dashboard', { timeout: 10000 });
        console.log('✅ 로그인 완료');

        // API 요청 캡처를 위한 Promise 생성
        const apiPromise = page.waitForResponse(
            response => response.url().includes('/api/stores') && !response.url().includes('/api/stores/'),
            { timeout: 30000 }
        );

        // 매장 관리 페이지 접속 (API 요청 트리거)
        await page.goto('http://localhost:8000/management/stores', { waitUntil: 'domcontentloaded' });

        try {
            const apiResponse = await apiPromise;
            const status = apiResponse.status();
            const url = apiResponse.url();

            console.log(`\n📡 API 엔드포인트: ${url}`);
            console.log(`📊 응답 상태 코드: ${status}`);

            // 응답 헤더 확인
            const headers = apiResponse.headers();
            console.log('\n📋 응답 헤더:');
            console.log(`   Content-Type: ${headers['content-type']}`);
            console.log(`   Cache-Control: ${headers['cache-control']}`);

            // 응답 본문 확인
            try {
                const responseData = await apiResponse.json();
                console.log('\n📦 응답 데이터:');
                console.log(`   Success: ${responseData.success}`);

                if (responseData.success) {
                    console.log(`   ✅ 데이터 개수: ${responseData.data?.length || 0}개`);
                    if (responseData.data && responseData.data.length > 0) {
                        console.log('\n   첫 번째 매장 샘플:');
                        console.log(JSON.stringify(responseData.data[0], null, 2).substring(0, 300));
                    } else {
                        console.log('   ⚠️ 매장 데이터가 비어있음');
                    }
                } else {
                    console.log(`   ❌ 에러: ${responseData.error || 'Unknown error'}`);
                }
            } catch (e) {
                console.log(`\n🔴 JSON 파싱 실패: ${e.message}`);
                const text = await apiResponse.text();
                console.log('응답 텍스트:', text.substring(0, 500));
            }

            // 상태 코드별 진단
            if (status === 200) {
                console.log('\n✅ API 응답 정상 (200 OK)');
            } else if (status === 401) {
                console.log('\n🔴 인증 실패 (401 Unauthorized) - 세션 문제');
            } else if (status === 403) {
                console.log('\n🔴 권한 부족 (403 Forbidden) - RBAC 문제');
            } else if (status === 404) {
                console.log('\n🔴 엔드포인트 없음 (404 Not Found) - 라우트 문제');
            } else if (status === 500) {
                console.log('\n🔴 서버 에러 (500 Internal Server Error) - Laravel 로그 확인 필요');
            } else {
                console.log(`\n⚠️ 예상치 못한 상태 코드: ${status}`);
            }

        } catch (e) {
            console.log(`\n🔴 API 요청 실패 또는 타임아웃: ${e.message}`);
            console.log('\n가능한 원인:');
            console.log('   1. Laravel 서버가 실행되지 않음');
            console.log('   2. PostgreSQL 데이터베이스 연결 실패');
            console.log('   3. API 라우트가 정의되지 않음');
            console.log('   4. 미들웨어에서 요청 차단');
        }
    });

    test.afterEach(async () => {
        // 테스트 후 진단 리포트 출력
        console.log('\n\n=== 📋 진단 리포트 ===\n');

        console.log(`📝 콘솔 로그: ${consoleLogs.length}개`);
        console.log(`🔴 콘솔 에러: ${consoleErrors.length}개`);

        if (consoleErrors.length > 0) {
            console.log('\n콘솔 에러 목록:');
            consoleErrors.forEach((err, idx) => {
                console.log(`   ${idx + 1}. ${err}`);
            });
        }

        console.log(`\n📤 네트워크 요청: ${networkRequests.length}개`);
        console.log(`📥 네트워크 응답: ${networkResponses.length}개`);

        // /api/stores 요청 찾기
        const storeApiRequests = networkResponses.filter(r =>
            r.url.includes('/api/stores') && !r.url.includes('/api/stores/')
        );

        if (storeApiRequests.length > 0) {
            console.log('\n/api/stores 요청 상세:');
            storeApiRequests.forEach((req, idx) => {
                console.log(`\n   요청 ${idx + 1}:`);
                console.log(`   - URL: ${req.url}`);
                console.log(`   - Method: ${req.method}`);
                console.log(`   - Status: ${req.status}`);
                if (req.responseData) {
                    console.log(`   - Success: ${req.responseData.success}`);
                    console.log(`   - Data Count: ${req.responseData.data?.length || 0}`);
                }
            });
        } else {
            console.log('\n⚠️ /api/stores 요청이 발견되지 않음!');
        }

        // 최종 진단 결과
        console.log('\n=== 🔍 최종 진단 ===\n');

        const hasApiError = storeApiRequests.some(r => r.status >= 400);
        const hasNoApiRequest = storeApiRequests.length === 0;
        const hasConsoleError = consoleErrors.length > 0;

        if (hasNoApiRequest) {
            console.log('❌ 문제: API 요청이 전송되지 않음');
            console.log('   원인: JavaScript 에러 또는 컴포넌트 마운트 실패');
            console.log('   해결: React 컴포넌트 및 콘솔 에러 확인');
        } else if (hasApiError) {
            const errorResponse = storeApiRequests.find(r => r.status >= 400);
            console.log(`❌ 문제: API 응답 에러 (${errorResponse.status})`);

            if (errorResponse.status === 401) {
                console.log('   원인: 인증 실패');
                console.log('   해결: 세션 미들웨어 또는 로그인 상태 확인');
            } else if (errorResponse.status === 403) {
                console.log('   원인: 권한 부족');
                console.log('   해결: RBAC 미들웨어 설정 확인');
            } else if (errorResponse.status === 500) {
                console.log('   원인: 서버 내부 에러');
                console.log('   해결: storage/logs/laravel.log 확인');
            }
        } else if (hasConsoleError) {
            console.log('⚠️ 경고: 콘솔 에러 발견');
            console.log('   확인: JavaScript 에러 해결 필요');
        } else {
            console.log('✅ 정상: 모든 요청이 성공적으로 처리됨');
        }

        console.log('\n================================\n');
    });
});
