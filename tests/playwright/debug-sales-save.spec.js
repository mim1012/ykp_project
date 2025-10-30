import { test, expect } from '@playwright/test';

test('매장 계정 - 엑셀 업로드 후 저장 테스트', async ({ page }) => {
    // API 요청 캡처
    const apiRequests = [];
    page.on('response', async (response) => {
        const url = response.url();
        if (url.includes('/api/')) {
            const status = response.status();
            let body = null;
            try {
                body = await response.json();
            } catch (e) {
                try {
                    body = await response.text();
                } catch (e2) {
                    body = 'Unable to read response';
                }
            }
            apiRequests.push({
                url,
                status,
                body,
                headers: response.headers()
            });
            console.log(`\n[API] ${url}`);
            console.log(`Status: ${status}`);
            if (status >= 400) {
                console.log('❌ ERROR Response:', JSON.stringify(body, null, 2));
            }
        }
    });

    console.log('\n🚀 Step 1: 로그인 페이지 접속');
    await page.goto('http://127.0.0.1:8000/login');

    console.log('📝 Step 2: 로그인 정보 입력');
    await page.fill('input[name="email"]', 'store@ykp.com');
    await page.fill('input[name="password"]', 'password');

    console.log('🔐 Step 3: 로그인 버튼 클릭');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/dashboard**', { timeout: 10000 });
    console.log('✅ 로그인 성공');

    console.log('\n📄 Step 4: 개통표 입력 페이지 이동');
    await page.goto('http://127.0.0.1:8000/sales/complete-aggrid');
    await page.waitForLoadState('networkidle');
    console.log('✅ 페이지 로드 완료');

    console.log('\n📤 Step 5: 엑셀 파일 업로드');
    const fileInput = await page.locator('input[type="file"]').first();
    await fileInput.setInputFiles('C:\\Users\\PC_1M\\Downloads\\sales_template_2025-10-13.xlsx');
    console.log('✅ 파일 선택 완료');

    // 업로드 완료 대기
    console.log('⏳ 파일 처리 대기 중...');
    await page.waitForTimeout(3000);

    console.log('\n💾 Step 6: 전체 저장 버튼 클릭');
    // 저장 버튼 찾기 (여러 가능성 시도)
    const saveButton = await page.locator('button').filter({ hasText: /저장|save/i }).first();
    await saveButton.click();
    console.log('✅ 저장 버튼 클릭됨');

    // 저장 완료 대기
    console.log('⏳ 저장 처리 대기 중...');
    await page.waitForTimeout(5000);

    console.log('\n\n═══════════════════════════════════════');
    console.log('📊 API 요청 결과 분석');
    console.log('═══════════════════════════════════════');

    // 모든 API 요청 출력
    console.log(`\n총 ${apiRequests.length}개의 API 요청 발생`);

    // 에러 요청 필터링
    const errorRequests = apiRequests.filter(req => req.status >= 400);

    if (errorRequests.length > 0) {
        console.log(`\n❌ ${errorRequests.length}개의 에러 발생!\n`);
        errorRequests.forEach((req, index) => {
            console.log(`\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━`);
            console.log(`에러 #${index + 1}`);
            console.log(`━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━`);
            console.log(`URL: ${req.url}`);
            console.log(`Status: ${req.status}`);
            console.log(`\nResponse Body:`);
            console.log(JSON.stringify(req.body, null, 2));
            console.log(`\nResponse Headers:`);
            console.log(JSON.stringify(req.headers, null, 2));
        });
    } else {
        console.log('\n✅ 모든 API 요청 성공!');
    }

    // 성공한 요청도 표시
    const successRequests = apiRequests.filter(req => req.status >= 200 && req.status < 300);
    console.log(`\n✅ ${successRequests.length}개의 성공한 요청:`);
    successRequests.forEach(req => {
        console.log(`  - ${req.url.split('/api/')[1]} (${req.status})`);
    });

    console.log('\n═══════════════════════════════════════\n');

    // 테스트 실패 조건: 에러가 있으면 실패
    if (errorRequests.length > 0) {
        throw new Error(`${errorRequests.length}개의 API 에러 발생. 위 로그 확인 필요.`);
    }
});
