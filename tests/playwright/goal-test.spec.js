// 매장 목표 설정 기능 테스트
import { test, expect } from '@playwright/test';

const BASE_URL = 'http://127.0.0.1:8000';

test.describe('매장 월 목표 설정 테스트', () => {

    test('매장 계정으로 목표 설정 및 대시보드 확인', async ({ page }) => {
        console.log('\n=== 매장 목표 설정 테스트 시작 ===\n');

        // 1. 매장 계정 로그인
        console.log('1. 매장 계정 로그인...');
        await page.goto(`${BASE_URL}/login`);
        await page.fill('input[name="email"]', 'store@ykp.com');
        await page.fill('input[name="password"]', 'password');
        await page.click('button[type="submit"]');
        await page.waitForURL(/dashboard/, { timeout: 15000 });
        console.log('   ✅ 로그인 성공\n');

        // 2. 대시보드 목표 카드 확인
        console.log('2. 대시보드 목표 카드 확인...');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(2000); // API 로드 대기

        // 목표 카드가 있는지 확인
        const goalCard = page.locator('#storeGoal');
        await expect(goalCard).toBeVisible();
        console.log('   ✅ 목표 카드 표시됨\n');

        // 현재 목표 상태 확인
        const goalAchievement = await page.locator('#store-goal-achievement').textContent();
        console.log(`   현재 달성률: ${goalAchievement}`);

        // 3. 목표 설정 모달 열기
        console.log('\n3. 목표 설정 모달 열기...');
        await goalCard.click();
        await page.waitForSelector('#goalModal:not(.hidden)', { timeout: 5000 });
        console.log('   ✅ 모달 열림\n');

        // 4. 새 목표 입력
        console.log('4. 새 목표 입력 (2000만원)...');
        await page.fill('#goal-sales-target', '20000000');
        await page.fill('#goal-notes', 'Playwright 테스트 목표');
        console.log('   ✅ 입력 완료\n');

        // 5. 저장
        console.log('5. 목표 저장...');
        await page.click('#goal-save-btn');
        await page.waitForTimeout(2000);

        // alert 처리
        page.on('dialog', async dialog => {
            console.log(`   Alert: ${dialog.message()}`);
            await dialog.accept();
        });

        // 6. 업데이트 확인
        console.log('\n6. 업데이트된 목표 확인...');
        await page.waitForTimeout(1000);

        const updatedAchievement = await page.locator('#store-goal-achievement').textContent();
        const updatedTarget = await page.locator('#store-goal-target').textContent();

        console.log(`   달성률: ${updatedAchievement}`);
        console.log(`   목표 정보: ${updatedTarget}`);

        // 진행 바 확인
        const progressBar = page.locator('#store-goal-progress');
        const progressWidth = await progressBar.evaluate(el => el.style.width);
        console.log(`   진행 바: ${progressWidth}`);

        // 7. API로 직접 확인
        console.log('\n7. API 응답 확인...');
        const apiResponse = await page.evaluate(async () => {
            const response = await fetch('/api/my-goal', {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                },
                credentials: 'same-origin'
            });
            return response.json();
        });

        console.log('   API 응답:');
        console.log(`   - 목표 금액: ${apiResponse.data?.goal?.sales_target?.toLocaleString() || 'N/A'}원`);
        console.log(`   - 현재 매출: ${apiResponse.data?.current_sales?.toLocaleString() || 'N/A'}원`);
        console.log(`   - 달성률: ${apiResponse.data?.achievement_rate || 'N/A'}%`);
        console.log(`   - 남은 일수: ${apiResponse.data?.days_remaining || 'N/A'}일`);

        expect(apiResponse.success).toBe(true);
        expect(apiResponse.data.goal.sales_target).toBe(20000000);

        console.log('\n=== 테스트 완료 ===');
    });

    test('본사 계정에서 목표 API 접근 불가', async ({ page }) => {
        console.log('\n=== 본사 권한 테스트 ===\n');

        // 본사 계정 로그인
        await page.goto(`${BASE_URL}/login`);
        await page.fill('input[name="email"]', 'admin@ykp.com');
        await page.fill('input[name="password"]', 'password');
        await page.click('button[type="submit"]');
        await page.waitForURL(/dashboard/, { timeout: 15000 });
        console.log('본사 계정 로그인 성공');

        // API 호출 시도
        const apiResponse = await page.evaluate(async () => {
            const response = await fetch('/api/my-goal', {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                },
                credentials: 'same-origin'
            });
            return { status: response.status, data: await response.json() };
        });

        console.log(`API 응답 상태: ${apiResponse.status}`);
        expect(apiResponse.status).toBe(403);
        console.log('✅ 본사는 목표 API 접근 불가 (403) - 정상');
    });
});
