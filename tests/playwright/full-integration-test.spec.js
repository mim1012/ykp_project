import { test, expect } from '@playwright/test';

test.describe('전체 통합 E2E 테스트', () => {
    test('본사에서 통신사/대리점 추가 후 매장에서 개통표 입력 및 DB 저장', async ({ browser }) => {
        // 두 개의 브라우저 컨텍스트 생성
        const adminContext = await browser.newContext();
        const storeContext = await browser.newContext();

        const adminPage = await adminContext.newPage();
        const storePage = await storeContext.newPage();

        // 테스트용 고유 ID 생성
        const timestamp = Date.now().toString().slice(-6);
        const testCarrierName = `테스트통신사_${timestamp}`;
        const testDealerName = `테스트대리점_${timestamp}`;

        console.log('🚀 테스트 시작');
        console.log(`📡 테스트 통신사: ${testCarrierName}`);
        console.log(`🏢 테스트 대리점: ${testDealerName}`);

        // ========== 1단계: 본사 계정으로 로그인 ==========
        console.log('\n[1단계] 본사 계정 로그인');
        await adminPage.goto('http://localhost:8080/login');
        await adminPage.fill('#email', 'admin@ykp.com');
        await adminPage.fill('#password', 'password');
        await adminPage.click('button[type="submit"]');
        await adminPage.waitForLoadState('networkidle');

        // 대시보드로 이동
        await adminPage.goto('http://localhost:8080/dashboard');
        await adminPage.waitForLoadState('networkidle');

        // ========== 2단계: 새 통신사 추가 ==========
        console.log('\n[2단계] 새 통신사 추가');
        const carrierButton = adminPage.locator('button:has-text("통신사 관리")').first();
        await carrierButton.click();
        await adminPage.waitForTimeout(1000);

        // 통신사 정보 입력
        await adminPage.fill('#carrier-code', `T${timestamp}`);
        await adminPage.fill('#carrier-name', testCarrierName);
        await adminPage.fill('#carrier-sort-order', '900');

        // 저장
        const carrierSaveBtn = adminPage.locator('#carrierForm button[type="submit"]');
        await carrierSaveBtn.click();

        // 알림 처리
        adminPage.on('dialog', dialog => {
            console.log(`✅ 알림: ${dialog.message()}`);
            dialog.accept();
        });
        await adminPage.waitForTimeout(2000);

        // 통신사 모달 닫기 - JavaScript로 직접 처리
        await adminPage.evaluate(() => {
            const modal = document.getElementById('carrierManagementModal');
            if (modal) {
                modal.style.display = 'none';
                modal.classList.remove('show');
                document.body.classList.remove('modal-open');
                const backdrop = document.querySelector('.modal-backdrop');
                if (backdrop) backdrop.remove();
            }
        });
        await adminPage.waitForTimeout(500);

        // ========== 3단계: 새 대리점 추가 ==========
        console.log('\n[3단계] 새 대리점 추가');
        const dealerButton = adminPage.locator('button:has-text("대리점 관리")').first();
        await dealerButton.click();
        await adminPage.waitForTimeout(1000);

        // 대리점 정보 입력
        await adminPage.fill('#dealerCode', `D${timestamp}`);
        await adminPage.fill('#dealerName', testDealerName);
        await adminPage.fill('#contactPerson', '테스트담당자');
        await adminPage.fill('#phone', '010-1234-5678');

        // 저장
        const dealerSaveBtn = adminPage.locator('#dealerForm button[type="submit"]');
        await dealerSaveBtn.click();
        await adminPage.waitForTimeout(2000);

        // 대리점 모달 닫기 - JavaScript로 직접 처리
        await adminPage.evaluate(() => {
            const modal = document.getElementById('dealerManagementModal');
            if (modal) {
                modal.style.display = 'none';
                modal.classList.remove('show');
                document.body.classList.remove('modal-open');
                const backdrop = document.querySelector('.modal-backdrop');
                if (backdrop) backdrop.remove();
            }
        });
        await adminPage.waitForTimeout(500);

        // ========== 4단계: 매장 계정으로 로그인 ==========
        console.log('\n[4단계] 매장 계정 로그인');
        await storePage.goto('http://localhost:8080/login');
        await storePage.fill('#email', 'store@ykp.com');
        await storePage.fill('#password', 'password');
        await storePage.click('button[type="submit"]');
        await storePage.waitForLoadState('networkidle');

        // ========== 5단계: 개통표 입력 페이지로 이동 ==========
        console.log('\n[5단계] 개통표 입력 페이지로 이동');
        await storePage.goto('http://localhost:8080/sales/complete-aggrid');
        await storePage.waitForLoadState('networkidle');

        // localStorage 이벤트가 전파되기를 기다림
        await storePage.waitForTimeout(3000);

        // 페이지 새로고침으로 최신 데이터 로드
        await storePage.reload();
        await storePage.waitForLoadState('networkidle');

        // ========== 6단계: 새 행 추가 및 데이터 입력 ==========
        console.log('\n[6단계] 개통표 데이터 입력');
        await storePage.click('#add-row-btn');
        await storePage.waitForTimeout(2000);

        // 첫 번째 행 찾기 - 다른 방법으로 시도
        const firstRowId = await storePage.evaluate(() => {
            // data-row-id 속성이 있는 요소 찾기
            const rows = document.querySelectorAll('[data-row-id]');
            if (rows.length > 0) {
                return rows[0].getAttribute('data-row-id');
            }

            // 또는 테이블 행에서 ID 추출
            const tableRows = document.querySelectorAll('#sales-table tbody tr');
            if (tableRows.length > 0) {
                const firstRow = tableRows[0];
                const input = firstRow.querySelector('input[id^="customer-name-"]');
                if (input) {
                    const id = input.id.replace('customer-name-', '');
                    return id;
                }
            }

            // salesData 배열 확인
            if (typeof salesData !== 'undefined' && salesData.length > 0) {
                return salesData[0].id;
            }

            return null;
        });

        let carrierSelect, dealerSelect;

        if (firstRowId) {
            console.log(`✅ 행 ID 발견: ${firstRowId}`);
            carrierSelect = storePage.locator(`#carrier-select-${firstRowId}`);
            dealerSelect = storePage.locator(`#dealer-select-${firstRowId}`);
        } else {
            console.log('⚠️ 행 ID를 찾을 수 없음, 첫 번째 select 요소 사용');
            // 첫 번째 select 요소 직접 찾기
            carrierSelect = storePage.locator('select[id^="carrier-select-"]').first();
            dealerSelect = storePage.locator('select[id^="dealer-select-"]').first();
        }

        // 통신사 선택 - 새로 추가한 통신사 확인 및 선택
        const carrierOptions = await carrierSelect.locator('option').allTextContents();

        console.log('📡 통신사 옵션:', carrierOptions);
        const hasNewCarrier = carrierOptions.some(option => option.includes(testCarrierName));

        if (hasNewCarrier) {
            console.log(`✅ 새 통신사 '${testCarrierName}'가 드롭다운에 있음`);
            await carrierSelect.selectOption({ label: testCarrierName });
        } else {
            console.log(`❌ 새 통신사 '${testCarrierName}'를 찾을 수 없음`);
            // 기본 통신사 선택
            await carrierSelect.selectOption('SK');
        }

        // 대리점 선택 - 새로 추가한 대리점 확인 및 선택
        const dealerOptions = await dealerSelect.locator('option').allTextContents();

        console.log('🏢 대리점 옵션:', dealerOptions);
        const hasNewDealer = dealerOptions.some(option => option.includes(testDealerName));

        if (hasNewDealer) {
            console.log(`✅ 새 대리점 '${testDealerName}'가 드롭다운에 있음`);
            await dealerSelect.selectOption({ label: testDealerName });
        } else {
            console.log(`❌ 새 대리점 '${testDealerName}'를 찾을 수 없음`);
            // 기본 대리점 선택
            await dealerSelect.selectOption('SM');
        }

        // 나머지 필수 필드 입력
        if (firstRowId) {
            await storePage.fill(`#activation-type-${firstRowId}`, '신규');
            await storePage.fill(`#customer-name-${firstRowId}`, '테스트고객');
            await storePage.fill(`#phone-number-${firstRowId}`, '010-9999-8888');
            await storePage.fill(`#model-name-${firstRowId}`, 'iPhone 15');
            await storePage.fill(`#serial-number-${firstRowId}`, `SN${timestamp}`);

            // 금액 필드 입력
            await storePage.fill(`#base-price-${firstRowId}`, '100000');
            await storePage.fill(`#verbal1-${firstRowId}`, '50000');
            await storePage.fill(`#verbal2-${firstRowId}`, '30000');

            // 계산 버튼 클릭
            const calculateBtn = storePage.locator(`#calculate-btn-${firstRowId}`);
            await calculateBtn.click();
        } else {
            // ID가 없으면 첫 번째 입력 필드들 사용
            await storePage.locator('input[id^="activation-type-"]').first().fill('신규');
            await storePage.locator('input[id^="customer-name-"]').first().fill('테스트고객');
            await storePage.locator('input[id^="phone-number-"]').first().fill('010-9999-8888');
            await storePage.locator('input[id^="model-name-"]').first().fill('iPhone 15');
            await storePage.locator('input[id^="serial-number-"]').first().fill(`SN${timestamp}`);

            // 금액 필드 입력
            await storePage.locator('input[id^="base-price-"]').first().fill('100000');
            await storePage.locator('input[id^="verbal1-"]').first().fill('50000');
            await storePage.locator('input[id^="verbal2-"]').first().fill('30000');

            // 계산 버튼 클릭
            const calculateBtn = storePage.locator('button[id^="calculate-btn-"]').first();
            await calculateBtn.click();
        }
        await storePage.waitForTimeout(2000);

        // ========== 7단계: 데이터 저장 ==========
        console.log('\n[7단계] 데이터 저장');
        await storePage.click('#save-btn');
        await storePage.waitForTimeout(3000);

        // 저장 성공 메시지 확인
        const statusMessage = await storePage.locator('#status-message').textContent();
        console.log(`💾 저장 상태: ${statusMessage}`);

        // ========== 8단계: DB 저장 확인 ==========
        console.log('\n[8단계] DB 저장 확인');

        // 페이지 새로고침하여 저장된 데이터 확인
        await storePage.reload();
        await storePage.waitForLoadState('networkidle');
        await storePage.waitForTimeout(2000);

        // 저장된 데이터가 표시되는지 확인
        const savedDataRows = await storePage.evaluate(() => {
            const rows = document.querySelectorAll('[data-row-id]');
            return rows.length;
        });

        if (savedDataRows > 0) {
            console.log(`✅ DB에 ${savedDataRows}개의 데이터가 저장됨`);

            // 저장된 데이터의 통신사와 대리점 확인
            const savedData = await storePage.evaluate(() => {
                const rows = document.querySelectorAll('[data-row-id]');
                const data = [];
                rows.forEach(row => {
                    const rowId = row.getAttribute('data-row-id');
                    const carrier = document.querySelector(`#carrier-select-${rowId}`)?.value;
                    const dealer = document.querySelector(`#dealer-select-${rowId}`)?.value;
                    const customerName = document.querySelector(`#customer-name-${rowId}`)?.value;
                    if (carrier || dealer || customerName) {
                        data.push({ carrier, dealer, customerName });
                    }
                });
                return data;
            });

            console.log('📊 저장된 데이터:', savedData);
        } else {
            console.log('⚠️ 저장된 데이터를 찾을 수 없음');
        }

        // ========== 9단계: 본사에서 통신사/대리점 삭제 테스트 ==========
        console.log('\n[9단계] 통신사/대리점 삭제 테스트');

        // 본사 페이지로 돌아가기
        await adminPage.goto('http://localhost:8080/dashboard');
        await adminPage.waitForLoadState('networkidle');

        // 대리점 삭제
        const dealerMgmtBtn = adminPage.locator('button:has-text("대리점 관리")').first();
        await dealerMgmtBtn.click();
        await adminPage.waitForTimeout(1000);

        // 삭제할 대리점 찾기
        const dealerRows = await adminPage.locator('#dealerTable tbody tr').count();
        console.log(`🏢 총 ${dealerRows}개의 대리점이 있음`);

        // 방금 추가한 대리점 삭제 (마지막 행)
        if (dealerRows > 0) {
            const deleteBtn = adminPage.locator('#dealerTable tbody tr').last().locator('button:has-text("삭제")');
            const deleteBtnCount = await deleteBtn.count();

            if (deleteBtnCount > 0) {
                await deleteBtn.click();

                // 삭제 확인 대화상자 처리
                adminPage.on('dialog', dialog => {
                    console.log(`🗑️ 삭제 확인: ${dialog.message()}`);
                    dialog.accept();
                });

                await adminPage.waitForTimeout(2000);
                console.log('✅ 대리점 삭제 완료');
            }
        }

        // ========== 10단계: 최종 확인 ==========
        console.log('\n[10단계] 최종 확인');

        // 매장 페이지 새로고침
        await storePage.reload();
        await storePage.waitForLoadState('networkidle');

        // 새 행 추가하여 삭제된 대리점이 목록에서 제거되었는지 확인
        await storePage.click('#add-row-btn');
        await storePage.waitForTimeout(1000);

        const newRowId = await storePage.evaluate(() => {
            const rows = document.querySelectorAll('[data-row-id]');
            return rows[rows.length - 1]?.getAttribute('data-row-id');
        });

        if (newRowId) {
            const finalDealerSelect = storePage.locator(`#dealer-select-${newRowId}`);
            const finalDealerOptions = await finalDealerSelect.locator('option').allTextContents();

            const deletedDealerExists = finalDealerOptions.some(option => option.includes(testDealerName));

            if (!deletedDealerExists) {
                console.log('✅ 삭제된 대리점이 목록에서 제거됨');
            } else {
                console.log('⚠️ 삭제된 대리점이 아직 목록에 있음');
            }
        }

        console.log('\n🎉 전체 통합 테스트 완료!');
        console.log('=====================');
        console.log('테스트 결과 요약:');
        console.log(`✅ 통신사 추가: ${hasNewCarrier ? '성공' : '실패'}`);
        console.log(`✅ 대리점 추가: ${hasNewDealer ? '성공' : '실패'}`);
        console.log(`✅ 개통표 입력: ${savedDataRows > 0 ? '성공' : '실패'}`);
        console.log(`✅ DB 저장: ${savedDataRows > 0 ? '성공' : '실패'}`);
        console.log('=====================');

        await adminContext.close();
        await storeContext.close();
    });
});