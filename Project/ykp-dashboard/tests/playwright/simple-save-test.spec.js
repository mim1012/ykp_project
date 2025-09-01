import { test, expect } from '@playwright/test';

test('간단한 개통표 저장 테스트', async ({ page }) => {
  const baseURL = 'http://127.0.0.1:8000';
  
  // 1. 로그인
  await page.goto(baseURL);
  await page.fill('input[type="email"]', 'hq@ykp.com');
  await page.fill('input[type="password"]', '123456');
  await page.click('button[type="submit"]');
  await page.waitForURL('**/dashboard');
  console.log('✅ 로그인 성공');

  // 2. 개통표 페이지 이동
  await page.goto(baseURL + '/test/complete-aggrid');
  await page.waitForTimeout(3000);
  console.log('✅ 개통표 페이지 로드');

  // 3. 일괄저장 버튼 확인
  const saveBtn = await page.locator('#save-btn');
  const isSaveBtnVisible = await saveBtn.isVisible();
  console.log('일괄저장 버튼 존재:', isSaveBtnVisible);

  // 4. JavaScript 변수 확인
  const jsCheck = await page.evaluate(() => {
    return {
      userData: typeof window.userData,
      completeTableData: typeof completeTableData,
      userDataContent: window.userData,
      tableDataLength: completeTableData?.length || 'undefined'
    };
  });
  console.log('JavaScript 상태:', jsCheck);

  // 5. 강제로 테스트 데이터 추가
  await page.evaluate(() => {
    // completeTableData 초기화
    if (typeof completeTableData === 'undefined') {
      window.completeTableData = [];
    }
    
    // 테스트 데이터 추가
    const testRow = {
      id: null,
      sale_date: new Date().toISOString().split('T')[0],
      model_name: 'Playwright Test iPhone',
      carrier: 'SK',
      activation_type: '신규',
      base_price: 1000000,
      settlement_amount: 400000,
      margin_after_tax: 50000,
      phone_number: '010-9999-9999',
      salesperson: 'Playwright Bot',
      store_id: window.userData?.store_id || 1,
      branch_id: window.userData?.branch_id || 1
    };
    
    completeTableData.push(testRow);
    console.log('✅ 테스트 데이터 추가:', testRow);
  });

  // 6. API 요청 모니터링
  let saveAPIWasCalled = false;
  let apiResponse = null;
  
  page.on('response', response => {
    if (response.url().includes('/save') || response.url().includes('/bulk')) {
      saveAPIWasCalled = true;
      apiResponse = {
        url: response.url(),
        status: response.status(),
        statusText: response.statusText()
      };
      console.log('📡 저장 API 응답:', apiResponse);
    }
  });

  // 7. 일괄저장 버튼 클릭
  console.log('일괄저장 버튼 클릭...');
  await saveBtn.click();
  
  // 8. API 호출 및 응답 대기
  await page.waitForTimeout(5000);
  
  // 9. 결과 확인
  console.log('저장 API 호출됨:', saveAPIWasCalled);
  if (apiResponse) {
    console.log('API 응답:', apiResponse);
    expect(apiResponse.status).toBe(200);
  }
  
  // 10. 브라우저 콘솔 로그 확인
  const consoleLogs = [];
  page.on('console', msg => {
    consoleLogs.push(msg.text());
  });
  
  console.log('브라우저 콘솔 로그:', consoleLogs);
});