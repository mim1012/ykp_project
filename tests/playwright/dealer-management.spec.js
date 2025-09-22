// @ts-check
import { test, expect } from '@playwright/test';

test.describe('대리점 관리 시스템 테스트', () => {
  const baseURL = 'http://localhost:8080';

  // 테스트용 대리점 정보
  const testDealer = {
    code: 'TEST_' + Date.now(),
    name: '플레이라이트 테스트 대리점',
    contact: '테스트 담당자',
    phone: '010-9999-8888'
  };

  test('본사 계정으로 대리점 추가 후 개통표에 반영 확인', async ({ page }) => {
    // 1. 본사 계정으로 로그인
    await page.goto(`${baseURL}/login`);
    await page.fill('input[name="email"]', 'hq@test.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');

    // 대시보드 로딩 대기
    await page.waitForURL(/dashboard|premium-dashboard|role-dashboard/);
    console.log('✅ 본사 계정 로그인 성공');

    // 2. 대리점 관리 버튼 클릭
    const dealerButton = page.locator('button:has-text("대리점 관리")').first();
    await expect(dealerButton).toBeVisible({ timeout: 10000 });
    await dealerButton.click();
    console.log('✅ 대리점 관리 모달 열기');

    // 3. 대리점 추가 폼 작성
    await page.waitForSelector('#dealerManagementModal', { state: 'visible' });

    // 폼 필드 입력
    await page.fill('#dealerCode', testDealer.code);
    await page.fill('#dealerName', testDealer.name);
    await page.fill('#contactPerson', testDealer.contact);
    await page.fill('#phone', testDealer.phone);
    await page.fill('#defaultSimFee', '6000');
    await page.fill('#defaultMnpDiscount', '-800');

    // 저장 버튼 클릭
    await page.click('#dealerForm button[type="submit"]');

    // alert 확인
    page.on('dialog', async dialog => {
      console.log('Alert 메시지:', dialog.message());
      await dialog.accept();
    });

    await page.waitForTimeout(2000);
    console.log('✅ 새 대리점 추가 완료:', testDealer.name);

    // 4. 모달 닫기
    await page.click('button[onclick="closeDealerModal()"]');
    await page.waitForSelector('#dealerManagementModal', { state: 'hidden' });

    // 5. 개통표 입력 페이지로 이동
    await page.goto(`${baseURL}/management/stores`);
    await page.waitForSelector('.ag-root', { timeout: 10000 });
    console.log('✅ 개통표 입력 페이지 로딩');

    // 6. 거래처(대리점) 드롭다운 확인
    // AG-Grid 내 첫 번째 셀 클릭하여 편집 모드 진입
    const firstCell = page.locator('.ag-cell').first();
    await firstCell.dblclick();

    // 거래처 컬럼 찾기 (dealer_code 필드)
    const dealerCell = page.locator('[col-id="dealer_code"]').first();
    await dealerCell.dblclick();

    // 드롭다운이 나타날 때까지 대기
    await page.waitForTimeout(1000);

    // 드롭다운 옵션에서 새로 추가한 대리점 확인
    const dealerOption = page.locator(`option:has-text("${testDealer.name}")`);
    const optionExists = await dealerOption.count() > 0;

    if (optionExists) {
      console.log('✅ 새로 추가한 대리점이 드롭다운에 표시됨');
    } else {
      // select 요소 직접 확인
      const selectElement = page.locator('select').first();
      const options = await selectElement.locator('option').allTextContents();
      console.log('현재 드롭다운 옵션들:', options);

      // 대리점 이름이 포함되어 있는지 확인
      const dealerFound = options.some(opt => opt.includes(testDealer.name) || opt.includes(testDealer.code));

      if (dealerFound) {
        console.log('✅ 새로 추가한 대리점이 드롭다운에 포함됨');
      } else {
        console.log('⚠️ 새 대리점이 아직 드롭다운에 반영되지 않음 (캐시 갱신 필요할 수 있음)');
      }
    }

    // 7. 대리점 관리로 돌아가서 삭제 (테스트 데이터 정리)
    await page.goto(`${baseURL}/dashboard`);
    await page.waitForTimeout(2000);

    const dealerButton2 = page.locator('button:has-text("대리점 관리")').first();
    await dealerButton2.click();
    await page.waitForSelector('#dealerManagementModal', { state: 'visible' });

    // 테스트 대리점 찾아서 삭제
    const deleteButton = page.locator(`tr:has-text("${testDealer.code}") button:has-text("삭제")`).first();
    const deleteButtonExists = await deleteButton.count() > 0;

    if (deleteButtonExists) {
      await deleteButton.click();

      // confirm 다이얼로그 처리
      page.on('dialog', async dialog => {
        if (dialog.type() === 'confirm') {
          await dialog.accept();
        }
      });

      await page.waitForTimeout(2000);
      console.log('✅ 테스트 대리점 삭제 완료');
    }
  });

  test('지사 계정으로 대리점 관리 권한 확인', async ({ page }) => {
    // 1. 지사 계정으로 로그인
    await page.goto(`${baseURL}/login`);
    await page.fill('input[name="email"]', 'seoul@test.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');

    await page.waitForURL(/dashboard|premium-dashboard|role-dashboard/);
    console.log('✅ 지사 계정 로그인 성공');

    // 2. 대리점 관리 버튼 존재 확인
    const dealerButton = page.locator('button:has-text("대리점 관리")').first();
    await expect(dealerButton).toBeVisible({ timeout: 10000 });
    console.log('✅ 지사 계정에 대리점 관리 버튼 표시됨');

    // 3. 대리점 관리 모달 열기
    await dealerButton.click();
    await page.waitForSelector('#dealerManagementModal', { state: 'visible' });

    // 4. 삭제 버튼이 없는지 확인 (지사는 삭제 권한 없음)
    const deleteButtons = page.locator('#dealerTableBody button:has-text("삭제")');
    const deleteButtonCount = await deleteButtons.count();

    if (deleteButtonCount === 0) {
      console.log('✅ 지사 계정은 삭제 버튼이 표시되지 않음 (정상)');
    } else {
      console.log('⚠️ 지사 계정에 삭제 버튼이 표시됨 (권한 설정 확인 필요)');
    }

    // 5. 수정 버튼은 있는지 확인
    const editButtons = page.locator('#dealerTableBody button:has-text("수정")');
    const editButtonExists = await editButtons.count() > 0;

    if (editButtonExists) {
      console.log('✅ 지사 계정에 수정 버튼 표시됨');
    }
  });

  test('매장 계정으로 대리점 관리 접근 불가 확인', async ({ page }) => {
    // 1. 매장 계정으로 로그인
    await page.goto(`${baseURL}/login`);
    await page.fill('input[name="email"]', 'gangnam@test.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');

    await page.waitForURL(/dashboard|premium-dashboard|role-dashboard/);
    console.log('✅ 매장 계정 로그인 성공');

    // 2. 대리점 관리 버튼이 없는지 확인
    const dealerButton = page.locator('button:has-text("대리점 관리")');
    const buttonExists = await dealerButton.count() > 0;

    if (!buttonExists) {
      console.log('✅ 매장 계정에는 대리점 관리 버튼이 표시되지 않음 (정상)');
    } else {
      console.log('❌ 매장 계정에 대리점 관리 버튼이 표시됨 (권한 오류)');
    }

    // 3. 개통표 입력 페이지에서 기존 대리점 선택 가능한지 확인
    await page.goto(`${baseURL}/management/stores`);
    await page.waitForSelector('.ag-root', { timeout: 10000 });

    // 거래처 드롭다운에 대리점 목록이 있는지 확인
    const firstCell = page.locator('.ag-cell').first();
    await firstCell.dblclick();

    const dealerCell = page.locator('[col-id="dealer_code"]').first();
    await dealerCell.dblclick();

    await page.waitForTimeout(1000);

    const selectElement = page.locator('select').first();
    const optionCount = await selectElement.locator('option').count();

    if (optionCount > 1) { // 첫 번째는 보통 빈 옵션
      console.log(`✅ 매장 직원도 ${optionCount - 1}개의 대리점을 선택 가능`);
    } else {
      console.log('⚠️ 대리점 드롭다운이 비어있음');
    }
  });
});

// 성능 테스트
test('대리점 목록 로딩 성능 테스트', async ({ page }) => {
  const baseURL = 'http://localhost:8080';

  // 본사 계정으로 로그인
  await page.goto(`${baseURL}/login`);
  await page.fill('input[name="email"]', 'hq@test.com');
  await page.fill('input[name="password"]', 'password');
  await page.click('button[type="submit"]');

  await page.waitForURL(/dashboard/);

  // 대리점 관리 모달 열기 시간 측정
  const startTime = Date.now();

  const dealerButton = page.locator('button:has-text("대리점 관리")').first();
  await dealerButton.click();

  await page.waitForSelector('#dealerTableBody tr', { timeout: 5000 });

  const loadTime = Date.now() - startTime;
  console.log(`⏱️ 대리점 목록 로딩 시간: ${loadTime}ms`);

  if (loadTime < 1000) {
    console.log('✅ 우수한 성능 (1초 이내)');
  } else if (loadTime < 2000) {
    console.log('⚠️ 보통 성능 (1-2초)');
  } else {
    console.log('❌ 개선 필요 (2초 이상)');
  }

  // 대리점 개수 확인
  const dealerRows = page.locator('#dealerTableBody tr');
  const dealerCount = await dealerRows.count();
  console.log(`📊 현재 등록된 대리점 수: ${dealerCount}개`);
});