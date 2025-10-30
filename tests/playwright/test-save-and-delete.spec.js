import { test, expect } from '@playwright/test';

test('매장 계정 - 엑셀 업로드 → 저장 → 삭제 전체 흐름 테스트', async ({ page }) => {
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
                timestamp: new Date().toISOString()
            });

            // 중요 API만 로깅
            if (url.includes('bulk-save') || url.includes('bulk-delete')) {
                console.log(`\n[${new Date().toISOString()}] API: ${url}`);
                console.log(`Status: ${status}`);
                if (status >= 400) {
                    console.log('❌ ERROR Response:', JSON.stringify(body, null, 2));
                } else {
                    console.log('✅ SUCCESS');
                }
            }
        }
    });

    console.log('\n🚀 Step 1: 로그인');
    await page.goto('http://127.0.0.1:8000/login');
    await page.fill('input[name="email"]', 'store@ykp.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/dashboard**', { timeout: 10000 });
    console.log('✅ 로그인 성공');

    console.log('\n📄 Step 2: 개통표 입력 페이지 이동');
    await page.goto('http://127.0.0.1:8000/sales/complete-aggrid');
    await page.waitForLoadState('networkidle');
    console.log('✅ 페이지 로드 완료');

    console.log('\n📤 Step 3: 엑셀 파일 업로드');
    const fileInput = await page.locator('input[type="file"]').first();
    await fileInput.setInputFiles('C:\\Users\\PC_1M\\Downloads\\sales_template_2025-10-13.xlsx');
    console.log('✅ 파일 선택 완료');

    await page.waitForTimeout(3000);

    console.log('\n💾 Step 4: 전체 저장 버튼 클릭');
    const saveButton = await page.locator('button').filter({ hasText: /저장|save/i }).first();
    await saveButton.click();
    console.log('✅ 저장 버튼 클릭됨');

    // 저장 완료 대기
    await page.waitForTimeout(5000);

    // 저장 API 확인
    const saveRequests = apiRequests.filter(req => req.url.includes('bulk-save'));
    console.log(`\n📊 저장 API 호출 횟수: ${saveRequests.length}`);
    const lastSaveRequest = saveRequests[saveRequests.length - 1];

    if (!lastSaveRequest || lastSaveRequest.status !== 201) {
        console.log('❌ 저장 실패!');
        throw new Error('저장 API가 성공하지 못했습니다.');
    }

    console.log('✅ 저장 성공 (201)');
    console.log('저장된 데이터:', JSON.stringify(lastSaveRequest.body, null, 2));

    console.log('\n🔄 Step 5: 페이지 새로고침하여 저장된 데이터 확인');
    await page.reload({ waitUntil: 'networkidle' });

    // AG-Grid가 완전히 렌더링될 때까지 대기
    console.log('⏳ AG-Grid 렌더링 대기 중...');
    await page.waitForSelector('.ag-root-wrapper', { state: 'visible', timeout: 15000 });
    await page.waitForTimeout(5000); // 추가 대기 시간
    console.log('✅ 페이지 새로고침 완료');

    console.log('\n✅ Step 6: 첫 번째 행 선택');
    // AG-Grid에서 첫 번째 행의 체크박스 찾기
    // 여러 방법 시도
    let firstRowCheckbox = null;

    // 방법 1: .ag-body-viewport 내부의 checkbox
    try {
        firstRowCheckbox = await page.locator('.ag-body-viewport input[type="checkbox"]').first();
        await firstRowCheckbox.waitFor({ state: 'visible', timeout: 5000 });
        console.log('✅ 방법 1 성공: .ag-body-viewport 내부 checkbox 발견');
    } catch (e) {
        console.log('⚠️ 방법 1 실패, 방법 2 시도...');

        // 방법 2: 모든 checkbox 중 첫 번째
        try {
            firstRowCheckbox = await page.locator('input[type="checkbox"]').nth(1); // 0번째는 헤더일 수 있음
            await firstRowCheckbox.waitFor({ state: 'visible', timeout: 5000 });
            console.log('✅ 방법 2 성공: 전체 checkbox 중 두 번째 발견');
        } catch (e2) {
            console.log('⚠️ 방법 2 실패, 방법 3 시도...');

            // 방법 3: 첫 번째 행 전체를 클릭
            const firstRow = await page.locator('.ag-row').first();
            await firstRow.waitFor({ state: 'visible', timeout: 5000 });
            await firstRow.click();
            console.log('✅ 방법 3 성공: 첫 번째 행 클릭');
            firstRowCheckbox = null; // 체크박스 대신 행을 클릭했음
        }
    }

    if (firstRowCheckbox) {
        await firstRowCheckbox.click();
        console.log('✅ 첫 번째 행 체크박스 클릭됨');
    } else {
        console.log('✅ 첫 번째 행 클릭됨 (체크박스 대신)');
    }

    await page.waitForTimeout(1000);

    console.log('\n🗑️ Step 7: 선택 삭제 버튼 클릭');
    const deleteButton = await page.locator('button').filter({ hasText: /선택.*삭제|삭제.*선택/i }).first();
    await deleteButton.click();
    console.log('✅ 삭제 버튼 클릭됨');

    await page.waitForTimeout(3000);

    // 삭제 API 확인
    const deleteRequests = apiRequests.filter(req => req.url.includes('bulk-delete'));
    console.log(`\n📊 삭제 API 호출 횟수: ${deleteRequests.length}`);

    if (deleteRequests.length === 0) {
        console.log('❌ 삭제 API가 호출되지 않았습니다!');
        throw new Error('삭제 API가 호출되지 않았습니다.');
    }

    const lastDeleteRequest = deleteRequests[deleteRequests.length - 1];

    if (lastDeleteRequest.status !== 200) {
        console.log('❌ 삭제 실패!');
        console.log('에러 응답:', JSON.stringify(lastDeleteRequest.body, null, 2));
        throw new Error(`삭제 API가 실패했습니다. Status: ${lastDeleteRequest.status}`);
    }

    console.log('✅ 삭제 성공 (200)');
    console.log('삭제 응답:', JSON.stringify(lastDeleteRequest.body, null, 2));

    console.log('\n🔄 Step 8: 페이지 새로고침하여 삭제된 데이터 확인');
    await page.reload({ waitUntil: 'networkidle' });
    await page.waitForTimeout(3000);
    console.log('✅ 페이지 새로고침 완료');

    console.log('\n\n═══════════════════════════════════════');
    console.log('📊 최종 결과 분석');
    console.log('═══════════════════════════════════════');

    // 저장/삭제 관련 API만 필터링
    const relevantRequests = apiRequests.filter(req =>
        req.url.includes('bulk-save') || req.url.includes('bulk-delete')
    );

    console.log(`\n총 ${relevantRequests.length}개의 저장/삭제 API 요청 발생`);

    relevantRequests.forEach((req, index) => {
        console.log(`\n[${index + 1}] ${req.url}`);
        console.log(`    Status: ${req.status} ${req.status >= 200 && req.status < 300 ? '✅' : '❌'}`);
        console.log(`    Time: ${req.timestamp}`);
    });

    const errorRequests = relevantRequests.filter(req => req.status >= 400);

    if (errorRequests.length > 0) {
        console.log(`\n❌ ${errorRequests.length}개의 에러 발생!`);
        throw new Error(`${errorRequests.length}개의 API 에러 발생. 위 로그 확인 필요.`);
    }

    console.log('\n✅ 모든 저장/삭제 작업 성공!');
    console.log('═══════════════════════════════════════\n');
});
