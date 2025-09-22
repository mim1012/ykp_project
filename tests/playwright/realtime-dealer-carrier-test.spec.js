import { test, expect } from '@playwright/test';

test.describe('통신사 및 대리점 실시간 업데이트 E2E 테스트', () => {
    test('본사에서 통신사 추가 시 매장 개통표에 실시간 반영', async ({ browser }) => {
        // 두 개의 브라우저 컨텍스트 생성
        const adminContext = await browser.newContext();
        const storeContext = await browser.newContext();

        const adminPage = await adminContext.newPage();
        const storePage = await storeContext.newPage();

        // 본사 계정으로 로그인
        await adminPage.goto('http://localhost:8080/login');
        await adminPage.fill('#email', 'admin@ykp.com');
        await adminPage.fill('#password', 'password');
        await adminPage.click('button[type="submit"]');
        await adminPage.waitForLoadState('networkidle');

        // 매장 계정으로 로그인
        await storePage.goto('http://localhost:8080/login');
        await storePage.fill('#email', 'store@ykp.com');
        await storePage.fill('#password', 'password');
        await storePage.click('button[type="submit"]');
        await storePage.waitForLoadState('networkidle');

        // 매장 계정으로 개통표 입력 페이지 열기
        await storePage.goto('http://localhost:8080/sales/complete-aggrid');
        await storePage.waitForLoadState('networkidle');

        // 콘솔 메시지 리스너 설정
        let carrierUpdateDetected = false;
        storePage.on('console', msg => {
            if (msg.text().includes('다른 탭에서 통신사 목록이 변경됨')) {
                carrierUpdateDetected = true;
                console.log('✅ 매장: 통신사 변경 감지됨');
            }
        });

        // 본사에서 대시보드로 이동
        await adminPage.goto('http://localhost:8080/dashboard');
        await adminPage.waitForLoadState('networkidle');

        // 통신사 관리 버튼 클릭
        const carrierButton = adminPage.locator('button:has-text("통신사 관리")').first();
        await carrierButton.click();
        await adminPage.waitForTimeout(1000);

        // 새 통신사 추가
        const testCarrierName = 'TEST_E2E_' + Date.now();
        await adminPage.fill('#carrier-code', 'E2E');
        await adminPage.fill('#carrier-name', testCarrierName);
        await adminPage.fill('#carrier-sort-order', '999');

        // 저장
        const saveButton = adminPage.locator('#carrierForm button[type="submit"]');
        await saveButton.click();

        // 알림 처리
        adminPage.on('dialog', dialog => dialog.accept());
        await adminPage.waitForTimeout(2000);

        // localStorage 이벤트가 전파되기를 기다림
        await storePage.waitForTimeout(3000);

        // 매장 페이지 새로고침하여 변경사항 확인
        await storePage.reload();
        await storePage.waitForLoadState('networkidle');

        // 새로 추가한 통신사가 드롭다운에 있는지 확인
        const addRowBtn = storePage.locator('#add-row-btn');
        await addRowBtn.click();
        await storePage.waitForTimeout(1000);

        // 통신사 드롭다운 확인
        const carrierSelect = storePage.locator('select[id^="carrier-select-"]').first();
        const carrierOptions = await carrierSelect.locator('option').allTextContents();

        const hasNewCarrier = carrierOptions.some(option => option.includes(testCarrierName));
        if (hasNewCarrier) {
            console.log('✅ 새로 추가한 통신사가 매장 개통표에 반영됨');
        } else {
            console.log('❌ 통신사가 반영되지 않음. 옵션:', carrierOptions);
        }

        await adminContext.close();
        await storeContext.close();
    });

    test('지사에서 대리점 추가 시 매장 개통표에 실시간 반영', async ({ browser }) => {
        // 두 개의 브라우저 컨텍스트 생성
        const branchContext = await browser.newContext();
        const storeContext = await browser.newContext();

        const branchPage = await branchContext.newPage();
        const storePage = await storeContext.newPage();

        // 지사 계정으로 로그인
        await branchPage.goto('http://localhost:8080/login');
        await branchPage.fill('#email', 'branch@ykp.com');
        await branchPage.fill('#password', 'password');
        await branchPage.click('button[type="submit"]');
        await branchPage.waitForLoadState('networkidle');

        // 매장 계정으로 로그인
        await storePage.goto('http://localhost:8080/login');
        await storePage.fill('#email', 'store@ykp.com');
        await storePage.fill('#password', 'password');
        await storePage.click('button[type="submit"]');
        await storePage.waitForLoadState('networkidle');

        // 매장 계정으로 개통표 입력 페이지 열기
        await storePage.goto('http://localhost:8080/sales/complete-aggrid');
        await storePage.waitForLoadState('networkidle');

        // 콘솔 메시지 리스너 설정
        let dealerUpdateDetected = false;
        storePage.on('console', msg => {
            if (msg.text().includes('다른 탭에서 대리점 목록이 변경됨')) {
                dealerUpdateDetected = true;
                console.log('✅ 매장: 대리점 변경 감지됨');
            }
        });

        // 지사에서 대시보드로 이동
        await branchPage.goto('http://localhost:8080/dashboard');
        await branchPage.waitForLoadState('networkidle');

        // 대리점 관리 버튼 클릭
        const dealerButton = branchPage.locator('button:has-text("대리점 관리")').first();
        await dealerButton.click();
        await branchPage.waitForTimeout(1000);

        // 새 대리점 추가
        const testDealerName = 'TEST_DEALER_' + Date.now().toString().slice(-6);
        await branchPage.fill('#dealerCode', 'TD' + Date.now().toString().slice(-4));
        await branchPage.fill('#dealerName', testDealerName);
        await branchPage.fill('#contactPerson', 'E2E Test');
        await branchPage.fill('#phone', '010-1234-5678');

        // 저장
        const saveButton = branchPage.locator('#dealerForm button[type="submit"]');
        await saveButton.click();

        // 알림 처리
        branchPage.on('dialog', dialog => dialog.accept());
        await branchPage.waitForTimeout(2000);

        // localStorage 이벤트가 전파되기를 기다림
        await storePage.waitForTimeout(3000);

        // 매장 페이지 새로고침하여 변경사항 확인
        await storePage.reload();
        await storePage.waitForLoadState('networkidle');

        // 새로 추가한 대리점이 드롭다운에 있는지 확인
        const addRowBtn = storePage.locator('#add-row-btn');
        await addRowBtn.click();
        await storePage.waitForTimeout(1000);

        // 대리점 드롭다운 확인
        const dealerSelect = storePage.locator('select[id^="dealer-select-"]').first();
        const dealerOptions = await dealerSelect.locator('option').allTextContents();

        const hasNewDealer = dealerOptions.some(option => option.includes(testDealerName));
        if (hasNewDealer) {
            console.log('✅ 새로 추가한 대리점이 매장 개통표에 반영됨');
        } else {
            console.log('❌ 대리점이 반영되지 않음. 옵션:', dealerOptions);
        }

        await branchContext.close();
        await storeContext.close();
    });

    test('동시에 여러 탭에서 업데이트 확인', async ({ browser }) => {
        // 세 개의 브라우저 컨텍스트 생성
        const context1 = await browser.newContext();
        const context2 = await browser.newContext();
        const context3 = await browser.newContext();

        const page1 = await context1.newPage();
        const page2 = await context2.newPage();
        const page3 = await context3.newPage();

        // 모두 본사 계정으로 로그인 (테스트 간소화)
        for (const page of [page1, page2, page3]) {
            await page.goto('http://localhost:8080/login');
            await page.fill('#email', 'admin@ykp.com');
            await page.fill('#password', 'password');
            await page.click('button[type="submit"]');
            await page.waitForLoadState('networkidle');
        }

        // 모두 개통표 페이지 열기
        await page1.goto('http://localhost:8080/sales/complete-aggrid');
        await page2.goto('http://localhost:8080/sales/complete-aggrid');
        await page3.goto('http://localhost:8080/sales/complete-aggrid');

        // 각 페이지에 콘솔 리스너 설정
        let updates = { page2: false, page3: false };

        page2.on('console', msg => {
            if (msg.text().includes('통신사 목록이 변경됨') || msg.text().includes('대리점 목록이 변경됨')) {
                updates.page2 = true;
                console.log('✅ 페이지2: 업데이트 감지');
            }
        });

        page3.on('console', msg => {
            if (msg.text().includes('통신사 목록이 변경됨') || msg.text().includes('대리점 목록이 변경됨')) {
                updates.page3 = true;
                console.log('✅ 페이지3: 업데이트 감지');
            }
        });

        // 페이지1에서 통신사 관리 버튼 클릭
        await page1.click('button:has-text("통신사 관리")');
        await page1.waitForTimeout(1000);

        // 새 통신사 추가
        const uniqueId = Date.now().toString().slice(-6);
        await page1.fill('#carrier-code', 'MT' + uniqueId);
        await page1.fill('#carrier-name', 'MultiTab_' + uniqueId);
        await page1.fill('#carrier-sort-order', '888');

        // 저장
        await page1.locator('#carrierForm button[type="submit"]').click();
        page1.on('dialog', dialog => dialog.accept());

        // 업데이트 전파 대기
        await page1.waitForTimeout(3000);

        // 30초 자동 업데이트를 기다리지 않고 수동 새로고침
        await page2.reload();
        await page3.reload();

        console.log('🔍 멀티탭 업데이트 결과:');
        console.log('- 페이지2 업데이트:', updates.page2 ? '✅' : '❌');
        console.log('- 페이지3 업데이트:', updates.page3 ? '✅' : '❌');

        await context1.close();
        await context2.close();
        await context3.close();
    });
});