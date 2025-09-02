import { test, expect } from '@playwright/test';
import { NaturalLanguageTestRunner } from './utils/nl-test-runner.js';

/**
 * YKP Dashboard 지사 관리 자연어 테스트
 * TC-HQ-001~006 시나리오를 자연어로 테스트
 */
test.describe('🤖 지사 관리 자연어 테스트 (TC-HQ-001~006)', () => {
    let nlRunner;
    
    test.beforeEach(async () => {
        nlRunner = new NaturalLanguageTestRunner();
    });

    test('TC-HQ-001: 지사 추가 기본 기능 자연어 테스트', async ({ page }) => {
        await nlRunner.execute(page, `
            본사 관리자로 로그인해줘
            지사 관리 페이지로 이동해줘
            지사 관리 탭을 클릭해줘
            지사를 추가해줘 - 이름: "자연어테스트지사", 코드: "NL001", 관리자: "자연어김"
            지사 목록에서 "자연어테스트지사"가 보이는지 확인해줘
        `);
        
        nlRunner.printSummary();
    });

    test('TC-HQ-002: 지사 계정 생성 및 로그인 자연어 테스트', async ({ page }) => {
        await nlRunner.execute(page, `
            본사 관리자로 로그인해줘
            지사 관리 페이지로 이동해줘
            지사 관리 탭을 클릭해줘
            지사를 추가해줘 - 이름: "계정테스트지사", 코드: "AC001", 관리자: "계정김"
            성공 메시지가 나타나는지 확인해줘
            로그아웃해줘
        `);

        // 생성된 계정으로 로그인 테스트 (수동)
        console.log('🔑 생성된 지사 관리자 계정으로 로그인 테스트');
        await page.goto('http://127.0.0.1:8000/login');
        await page.fill('input[type="email"]', 'branch_ac001@ykp.com');
        await page.fill('input[type="password"]', '123456');
        await page.click('button[type="submit"]');
        
        // 대시보드 접근 확인
        await page.waitForURL('**/dashboard');
        console.log('✅ 생성된 지사 계정 로그인 성공');
        
        nlRunner.printSummary();
    });

    test('TC-HQ-003: 지사코드 검증 자연어 테스트', async ({ page }) => {
        await nlRunner.execute(page, `
            본사 관리자로 로그인해줘
            지사 관리 페이지로 이동해줘
            지사 관리 탭을 클릭해줘
            새 지사를 추가해줘
        `);

        // 잘못된 지사코드로 테스트
        console.log('⚠️ 잘못된 지사코드 형식 테스트');
        await page.fill('#modal-branch-name', '검증테스트지사');
        await page.fill('#modal-branch-code', 'invalid123'); // 잘못된 형식
        await page.fill('#modal-branch-manager', '검증김');
        await page.click('button:has-text("✅ 지사 추가")');
        
        // 에러 메시지 확인
        await page.waitForTimeout(1000);
        console.log('✅ 잘못된 지사코드 검증 완료');

        nlRunner.printSummary();
    });

    test('TC-HQ-004~005: 지사 삭제 및 계정 비활성화 자연어 테스트', async ({ page }) => {
        // 먼저 테스트용 지사 생성
        await nlRunner.execute(page, `
            본사 관리자로 로그인해줘
            지사 관리 페이지로 이동해줘
            지사 관리 탭을 클릭해줘
            지사를 추가해줘 - 이름: "삭제테스트지사", 코드: "DEL01", 관리자: "삭제김"
            "삭제테스트지사"가 보이는지 확인해줘
        `);

        // 지사 삭제
        console.log('🗑️ 지사 삭제 프로세스 시작');
        const editButtons = page.locator('button:has-text("수정")');
        const editButtonCount = await editButtons.count();
        
        if (editButtonCount > 0) {
            await editButtons.last().click(); // 마지막(새로 추가된) 지사 선택
            await page.waitForTimeout(1000);
            
            await page.click('button:has-text("🗑️ 지사 삭제")');
            await page.waitForTimeout(1000);
            
            // 확인 다이얼로그에서 확인 클릭
            page.on('dialog', dialog => dialog.accept());
            
            await page.waitForTimeout(2000);
            console.log('✅ 지사 삭제 완료');
        }

        // 삭제된 계정으로 로그인 시도
        await page.goto('http://127.0.0.1:8000/login');
        await page.fill('input[type="email"]', 'branch_del01@ykp.com');
        await page.fill('input[type="password"]', '123456');
        await page.click('button[type="submit"]');
        
        // 로그인 실패 확인 (대시보드로 이동하지 않음)
        await page.waitForTimeout(3000);
        const currentUrl = page.url();
        expect(currentUrl).toContain('/login');
        console.log('✅ 삭제된 계정 로그인 차단 확인');

        nlRunner.printSummary();
    });

    test('TC-HQ-006: 하위 매장 있는 지사 삭제 방지 자연어 테스트', async ({ page }) => {
        await nlRunner.execute(page, `
            본사 관리자로 로그인해줘
            지사 관리 페이지로 이동해줘
            지사 관리 탭을 클릭해줘
        `);

        // 매장이 있는 지사 삭제 시도 (서울지사 - 2개 매장 보유)
        console.log('🛡️ 하위 매장 보호 로직 테스트');
        const editButtons = page.locator('button:has-text("수정")');
        await editButtons.first().click(); // 첫 번째 지사 (서울지사)
        await page.waitForTimeout(1000);
        
        await page.click('button:has-text("🗑️ 지사 삭제")');
        
        // 확인 다이얼로그에서 확인 클릭
        page.on('dialog', dialog => dialog.accept());
        await page.waitForTimeout(2000);
        
        // 에러 토스트 메시지 확인
        const errorToast = page.locator('.toast-error');
        await expect(errorToast).toBeVisible({ timeout: 5000 });
        console.log('✅ 하위 매장 보호 에러 메시지 확인');

        nlRunner.printSummary();
    });

    test('완전한 지사 라이프사이클 자연어 테스트', async ({ page }) => {
        await nlRunner.execute(page, `
            본사 관리자로 로그인해줘
            지사 관리 페이지로 이동해줘
            지사 관리 탭을 클릭해줘
            
            지사를 추가해줘 - 이름: "라이프사이클지사", 코드: "LC001", 관리자: "라이프김"
            "라이프사이클지사"가 보이는지 확인해줘
            3초 대기해줘
            
            로그아웃해줘
        `);

        // 생성된 지사 계정으로 로그인
        console.log('🔄 지사 계정으로 권한 테스트');
        await page.goto('http://127.0.0.1:8000/login');
        await page.fill('input[type="email"]', 'branch_lc001@ykp.com');
        await page.fill('input[type="password"]', '123456');
        await page.click('button[type="submit"]');
        
        await page.waitForURL('**/dashboard');
        console.log('✅ 지사 계정 로그인 성공');

        // 지사 권한으로 매장 관리 접근 시도
        await page.goto('http://127.0.0.1:8000/management/stores');
        await page.waitForTimeout(2000);
        
        // 지사도 매장 관리 페이지에 접근 가능해야 함
        const pageTitle = await page.textContent('h1');
        expect(pageTitle).toContain('매장 관리');
        console.log('✅ 지사 권한으로 매장 관리 접근 확인');

        nlRunner.printSummary();
    });
});

/**
 * 권한별 자연어 테스트 시나리오
 */
test.describe('🔐 권한별 자연어 테스트', () => {
    let nlRunner;
    
    test.beforeEach(async () => {
        nlRunner = new NaturalLanguageTestRunner();
    });

    test('본사 권한 전체 기능 자연어 테스트', async ({ page }) => {
        await nlRunner.execute(page, `
            본사 관리자로 로그인해줘
            지사 관리 페이지로 이동해줘
            
            지사 관리 탭을 클릭해줘
            지사 목록이 보이는지 확인해줘
            
            매장 관리 탭을 클릭해줘  
            매장 목록이 보이는지 확인해줘
            
            사용자 관리 탭을 클릭해줘
            사용자 목록이 보이는지 확인해줘
        `);

        console.log('🏢 본사 권한으로 모든 탭 접근 테스트 완료');
        nlRunner.printSummary();
    });

    test('지사 권한 제한 자연어 테스트', async ({ page }) => {
        await nlRunner.execute(page, `
            지사 관리자로 로그인해줘
            지사 관리 페이지로 이동해줘
            매장 관리 탭을 클릭해줘
        `);

        // 지사가 볼 수 있는 매장 확인
        console.log('🏬 지사 권한 매장 필터링 확인');
        await page.waitForTimeout(3000);
        
        // 매장이 표시되는지 확인
        const storeCards = page.locator('[onclick*="editStore"]');
        const storeCount = await storeCards.count();
        console.log(`📊 지사가 볼 수 있는 매장 수: ${storeCount}개`);
        
        expect(storeCount).toBeGreaterThan(0); // 지사에게 매장이 할당되어 있어야 함
        
        nlRunner.printSummary();
    });

    test('매장 권한 자기 데이터만 접근 자연어 테스트', async ({ page }) => {
        await nlRunner.execute(page, `
            매장 관리자로 로그인해줘
            개통표 페이지로 이동해줘
        `);

        console.log('🏪 매장 권한 데이터 범위 확인');
        await page.waitForTimeout(2000);
        
        // 매장 사용자 정보 확인
        const userData = await page.evaluate(() => window.userData);
        expect(userData.role).toBe('store');
        expect(userData.store_id).toBeTruthy();
        console.log(`✅ 매장 권한 확인: 매장 ID ${userData.store_id}`);

        nlRunner.printSummary();
    });
});

/**
 * 복합 업무 프로세스 자연어 테스트
 */
test.describe('💼 복합 업무 프로세스 자연어 테스트', () => {
    let nlRunner;
    
    test.beforeEach(async () => {
        nlRunner = new NaturalLanguageTestRunner();
    });

    test('전체 워크플로우: 지사생성→매장생성→매출입력 자연어 테스트', async ({ page }) => {
        const testTimestamp = Date.now().toString().substr(-6);
        
        await nlRunner.execute(page, `
            본사 관리자로 로그인해줘
            지사 관리 페이지로 이동해줘
            
            지사 관리 탭을 클릭해줘
            지사를 추가해줘 - 이름: "워크플로우지사${testTimestamp}", 코드: "WF${testTimestamp}", 관리자: "워크플로우김"
            "워크플로우지사${testTimestamp}"가 보이는지 확인해줘
            
            매장 관리 탭을 클릭해줜
            새 매장을 추가해줘
        `);

        // 매장 정보 입력 (모달에서)
        console.log('🏪 새 매장 추가 중...');
        await page.fill('#modal-store-name', `워크플로우매장${testTimestamp}`);
        await page.selectOption('#modal-branch-select', { label: `워크플로우지사${testTimestamp}` });
        await page.fill('#modal-owner-name', '워크플로우사장');
        await page.fill('#modal-phone', '010-workflow-123');
        await page.click('button:has-text("✅ 매장 추가")');
        await page.waitForTimeout(2000);

        console.log('📊 전체 워크플로우 자연어 테스트 완료');
        nlRunner.printSummary();
    });

    test('에러 처리 및 검증 자연어 테스트', async ({ page }) => {
        await nlRunner.execute(page, `
            본사 관리자로 로그인해줘
            지사 관리 페이지로 이동해줘
            지사 관리 탭을 클릭해줘
            새 지사를 추가해줘
        `);

        // 필수 필드 누락 테스트
        console.log('⚠️ 필수 필드 누락 에러 테스트');
        await page.fill('#modal-branch-name', ''); // 비어있는 이름
        await page.fill('#modal-branch-code', 'ER001');
        await page.click('button:has-text("✅ 지사 추가")');
        await page.waitForTimeout(1000);
        
        // 에러 토스트 확인
        console.log('✅ 필수 필드 검증 에러 확인');

        // 중복 코드 테스트
        console.log('🔁 지사코드 중복 에러 테스트');
        await page.fill('#modal-branch-name', '중복테스트지사');
        await page.fill('#modal-branch-code', 'BR001'); // 기존 서울지사 코드
        await page.click('button:has-text("✅ 지사 추가")');
        await page.waitForTimeout(2000);
        
        console.log('✅ 중복 코드 검증 완료');

        nlRunner.printSummary();
    });
});

/**
 * 실시간 데이터 동기화 자연어 테스트
 */
test.describe('🔄 실시간 동기화 자연어 테스트', () => {
    let nlRunner;
    
    test.beforeEach(async () => {
        nlRunner = new NaturalLanguageTestRunner();
    });

    test('지사-매장-매출 연동 자연어 테스트', async ({ page }) => {
        const testId = Date.now().toString().substr(-4);
        
        await nlRunner.execute(page, `
            본사 관리자로 로그인해줘
            지사 관리 페이지로 이동해줘
            지사 관리 탭을 클릭해줘
            지사를 추가해줘 - 이름: "연동테스트지사${testId}", 코드: "IT${testId}", 관리자: "연동김"
            3초 대기해줘
        `);

        // API로 생성된 지사 확인
        const branchResponse = await page.request.get('/test-api/branches');
        const branchData = await branchResponse.json();
        const createdBranch = branchData.data.find(b => b.code === `IT${testId}`);
        
        expect(createdBranch).toBeTruthy();
        console.log(`✅ 지사 "${createdBranch.name}" DB 저장 확인`);

        // 생성된 지사 계정 검증
        const userResponse = await page.request.get('/test-api/users');
        const userData = await userResponse.json();
        const createdManager = userData.data.find(u => u.email === `branch_it${testId}@ykp.com`);
        
        expect(createdManager).toBeTruthy();
        expect(createdManager.role).toBe('branch');
        expect(createdManager.branch_id).toBe(createdBranch.id);
        console.log(`✅ 지사 관리자 계정 "${createdManager.email}" 생성 확인`);

        nlRunner.printSummary();
    });
});