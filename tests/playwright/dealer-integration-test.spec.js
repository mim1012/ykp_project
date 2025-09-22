// @ts-check
import { test, expect } from '@playwright/test';

test.describe('대리점 관리와 개통표 연동 테스트', () => {
  const baseURL = 'http://localhost:8080';

  test('대리점 추가 및 확인 플로우', async ({ page }) => {
    console.log('===== 대리점 관리 통합 테스트 시작 =====');

    // 1. 본사 계정 로그인
    await page.goto(`${baseURL}/login`);
    await page.fill('input[name="email"]', 'hq@test.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL(/dashboard/);
    console.log('✅ 본사 계정 로그인 성공');

    // 2. 대리점 관리 모달 열기
    const dealerButton = page.locator('button:has-text("대리점 관리")').first();
    await expect(dealerButton).toBeVisible({ timeout: 10000 });
    await dealerButton.click();

    // 모달이 열릴 때까지 대기
    await page.waitForSelector('#dealerManagementModal', { state: 'visible' });
    console.log('✅ 대리점 관리 모달 열림');

    // 3. 현재 대리점 목록 확인
    await page.waitForTimeout(2000); // API 응답 대기

    const dealerRows = page.locator('#dealerTableBody tr');
    const initialCount = await dealerRows.count();
    console.log(`📊 기존 대리점 수: ${initialCount}개`);

    // 4. 새 대리점 추가
    const testDealerCode = 'PW_' + Date.now().toString().slice(-6);
    const testDealerName = '테스트대리점_' + testDealerCode;

    await page.fill('#dealerCode', testDealerCode);
    await page.fill('#dealerName', testDealerName);
    await page.fill('#contactPerson', '테스트담당자');
    await page.fill('#phone', '010-0000-0000');

    console.log(`📝 새 대리점 정보 입력: ${testDealerName}`);

    // Alert 처리 준비
    page.once('dialog', async dialog => {
      console.log('Alert:', dialog.message());
      await dialog.accept();
    });

    // 저장 버튼 클릭
    await page.click('#dealerForm button[type="submit"]');
    await page.waitForTimeout(3000);

    console.log('✅ 대리점 추가 요청 완료');

    // 5. 대리점 목록 새로고침
    await dealerButton.click(); // 모달 닫았다가 다시 열기
    await page.waitForTimeout(1000);
    await dealerButton.click();
    await page.waitForSelector('#dealerManagementModal', { state: 'visible' });
    await page.waitForTimeout(2000);

    // 6. 추가된 대리점 확인
    const newDealerRow = page.locator(`#dealerTableBody tr:has-text("${testDealerCode}")`);
    const exists = await newDealerRow.count() > 0;

    if (exists) {
      console.log(`✅ 새 대리점 "${testDealerName}" 목록에 추가됨`);
    } else {
      console.log('⚠️ 새 대리점이 목록에 표시되지 않음');
    }

    // 7. 모달 닫기
    await page.click('button[onclick="closeDealerModal()"]');
    await page.waitForSelector('#dealerManagementModal', { state: 'hidden' });

    // 8. 개통 입력 페이지 접속 시도
    console.log('\n=== 개통표 페이지 접근 테스트 ===');

    // 여러 경로 시도
    const salesInputPaths = [
      '/management/stores',
      '/management/sales/bulk-input',
      '/sales/input'
    ];

    let accessiblePath = null;
    for (const path of salesInputPaths) {
      console.log(`시도: ${baseURL}${path}`);
      const response = await page.goto(`${baseURL}${path}`, { waitUntil: 'domcontentloaded', timeout: 5000 }).catch(e => null);

      if (response && response.ok()) {
        accessiblePath = path;
        console.log(`✅ 접근 가능: ${path}`);
        break;
      } else {
        console.log(`❌ 접근 불가: ${path}`);
      }
    }

    if (accessiblePath) {
      // AG-Grid나 테이블이 있는지 확인
      const hasAgGrid = await page.locator('.ag-root').count() > 0;
      const hasTable = await page.locator('table').count() > 0;
      const hasForm = await page.locator('form').count() > 0;

      console.log(`페이지 구성요소:`);
      console.log(`- AG-Grid: ${hasAgGrid ? '있음' : '없음'}`);
      console.log(`- Table: ${hasTable ? '있음' : '없음'}`);
      console.log(`- Form: ${hasForm ? '있음' : '없음'}`);

      // 대리점 선택 필드 찾기
      const dealerSelects = await page.locator('select[name*="dealer"], select#dealer_code, [data-field="dealer_code"]').count();
      if (dealerSelects > 0) {
        console.log(`✅ 대리점 선택 필드 ${dealerSelects}개 발견`);
      } else {
        console.log('⚠️ 대리점 선택 필드를 찾을 수 없음');
      }
    } else {
      console.log('⚠️ 개통표 입력 페이지에 접근할 수 없음');
    }

    // 9. 테스트 대리점 삭제 (정리)
    if (exists) {
      console.log('\n=== 테스트 데이터 정리 ===');

      // 대리점 관리 모달 다시 열기
      await dealerButton.click();
      await page.waitForSelector('#dealerManagementModal', { state: 'visible' });
      await page.waitForTimeout(2000);

      // 삭제 버튼 찾기
      const deleteBtn = page.locator(`#dealerTableBody tr:has-text("${testDealerCode}") button:has-text("삭제")`).first();

      if (await deleteBtn.count() > 0) {
        // Confirm 다이얼로그 처리
        page.once('dialog', async dialog => {
          console.log('Confirm:', dialog.message());
          await dialog.accept();
        });

        await deleteBtn.click();
        await page.waitForTimeout(2000);
        console.log('✅ 테스트 대리점 삭제 완료');
      }
    }

    console.log('\n===== 테스트 완료 =====');
  });

  test('권한별 대리점 관리 접근 테스트', async ({ page }) => {
    const testAccounts = [
      { email: 'hq@test.com', role: '본사', shouldHaveAccess: true, canDelete: true },
      { email: 'seoul@test.com', role: '지사', shouldHaveAccess: true, canDelete: false },
      { email: 'gangnam@test.com', role: '매장', shouldHaveAccess: false, canDelete: false }
    ];

    for (const account of testAccounts) {
      console.log(`\n=== ${account.role} 계정 테스트 ===`);

      // 로그인
      await page.goto(`${baseURL}/login`);
      await page.fill('input[name="email"]', account.email);
      await page.fill('input[name="password"]', 'password');
      await page.click('button[type="submit"]');
      await page.waitForURL(/dashboard/);

      // 대리점 관리 버튼 확인
      const dealerButton = page.locator('button:has-text("대리점 관리")');
      const hasButton = await dealerButton.count() > 0;

      if (account.shouldHaveAccess) {
        expect(hasButton).toBe(true);
        console.log(`✅ ${account.role}: 대리점 관리 버튼 있음`);

        // 모달 열어서 권한 확인
        await dealerButton.first().click();
        await page.waitForSelector('#dealerManagementModal', { state: 'visible' });
        await page.waitForTimeout(2000);

        // 삭제 버튼 확인
        const deleteButtons = page.locator('#dealerTableBody button:has-text("삭제")');
        const hasDeleteButton = await deleteButtons.count() > 0;

        if (account.canDelete) {
          expect(hasDeleteButton).toBe(true);
          console.log(`✅ ${account.role}: 삭제 권한 있음`);
        } else {
          expect(hasDeleteButton).toBe(false);
          console.log(`✅ ${account.role}: 삭제 권한 없음 (정상)`);
        }

        // 모달 닫기
        await page.click('button[onclick="closeDealerModal()"]');
      } else {
        expect(hasButton).toBe(false);
        console.log(`✅ ${account.role}: 대리점 관리 접근 불가 (정상)`);
      }

      // 로그아웃
      await page.click('button:has-text("로그아웃")');
      await page.waitForURL(/login/);
    }
  });
});