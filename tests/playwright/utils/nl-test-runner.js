import { expect } from '@playwright/test';

/**
 * YKP Dashboard 자연어 테스트 러너
 * 한국어 명령어를 Playwright 액션으로 변환하여 실행
 */
export class NaturalLanguageTestRunner {
    constructor() {
        this.executedCommands = [];
        this.baseURL = 'http://127.0.0.1:8000';
        
        // YKP 시스템 특화 사용자 계정
        this.userCredentials = {
            '본사': { email: 'hq@ykp.com', password: '123456', role: 'headquarters' },
            '본사 관리자': { email: 'hq@ykp.com', password: '123456', role: 'headquarters' },
            '지사': { email: 'branch@ykp.com', password: '123456', role: 'branch' },
            '지사 관리자': { email: 'branch@ykp.com', password: '123456', role: 'branch' },
            '매장': { email: 'store@ykp.com', password: '123456', role: 'store' },
            '매장 관리자': { email: 'store@ykp.com', password: '123456', role: 'store' }
        };

        // YKP 페이지 URL 매핑
        this.urlMap = {
            '지사 관리': '/management/stores',
            '매장 관리': '/management/stores', 
            '개통표 입력': '/test/complete-aggrid',
            '개통표': '/test/complete-aggrid',
            '통계': '/dashboard',
            '대시보드': '/dashboard',
            '매출 통계': '/dashboard'
        };

        // YKP 시스템 셀렉터 매핑
        this.selectorMap = {
            // 지사 관리
            '지사 추가 버튼': 'button:has-text("➕ 지사 추가")',
            '지사명 입력': '#modal-branch-name',
            '지사코드 입력': '#modal-branch-code', 
            '관리자명 입력': '#modal-branch-manager',
            '연락처 입력': '#modal-branch-phone',
            '주소 입력': '#modal-branch-address',
            '지사 저장 버튼': 'button:has-text("✅ 지사 추가")',
            '지사 목록': '#branches-grid',
            
            // 매장 관리
            '매장 추가 버튼': 'button:has-text("➕ 매장 추가")',
            '매장명 입력': '#modal-store-name',
            '점주명 입력': '#modal-owner-name',
            '매장 저장 버튼': 'button:has-text("✅ 매장 추가")',
            '매장 목록': '#stores-grid',
            
            // 개통표
            '기종 입력': '#model-name',
            '금액 입력': '#settlement-amount',
            '통신사 선택': '#carrier',
            '개통유형 선택': '#activation-type',
            '개통표 저장': 'button:has-text("저장")',
            
            // 공통
            '저장 버튼': 'button:has-text("저장")',
            '확인 버튼': 'button:has-text("확인")',
            '취소 버튼': 'button:has-text("취소")',
            '삭제 버튼': 'button:has-text("삭제")',
            '성공 메시지': 'div:has-text("성공")',
            '에러 메시지': 'div:has-text("오류"), div:has-text("실패"), div:has-text("에러")',
            
            // 탭
            '지사 관리 탭': '#branches-tab',
            '매장 관리 탭': '#stores-tab',
            '사용자 관리 탭': '#users-tab'
        };
    }

    /**
     * 자연어 시나리오 실행 메인 함수
     */
    async execute(page, scenario) {
        console.log('🤖 자연어 테스트 시나리오 시작');
        
        const commands = this.parseScenario(scenario);
        console.log(`📝 총 ${commands.length}개 명령 감지`);

        for (const [index, command] of commands.entries()) {
            console.log(`\n${index + 1}. ${command.text}`);
            
            try {
                await this.executeCommand(page, command);
                command.status = '✅ 성공';
                await page.waitForTimeout(1000); // 각 명령 간 대기
            } catch (error) {
                command.status = `❌ 실패: ${error.message}`;
                console.error(`명령 실행 실패:`, error.message);
                throw error;
            }
            
            this.executedCommands.push(command);
        }

        console.log('\n🎉 자연어 테스트 시나리오 완료');
        return this.executedCommands;
    }

    /**
     * 자연어 시나리오를 개별 명령으로 파싱
     */
    parseScenario(scenario) {
        return scenario
            .split('\n')
            .map(line => line.trim())
            .filter(line => line.length > 0 && !line.startsWith('//'))
            .map(line => this.parseCommand(line));
    }

    /**
     * 개별 명령을 구조화된 객체로 파싱
     */
    parseCommand(commandText) {
        const patterns = {
            // 로그인/로그아웃
            login: /(.*?)(으로|로)\s*로그인/,
            logout: /로그아웃/,
            
            // 페이지 이동
            navigate: /(.*?)\s*(페이지로|으로|에)\s*(이동|가|접근)/,
            clickTab: /(.*?)\s*탭\s*(클릭|선택)/,
            
            // 지사 관리 특화
            addBranch: /지사.*?추가.*?이름:\s*"([^"]+)".*?코드:\s*"([^"]+)".*?관리자:\s*"([^"]+)"/,
            addBranchSimple: /새?\s*지사\s*(를|을)?\s*추가/,
            
            // 매장 관리
            addStore: /매장.*?추가.*?이름:\s*"([^"]+)"/,
            addStoreSimple: /새?\s*매장\s*(를|을)?\s*추가/,
            
            // 개통표 입력
            inputSales: /개통.*?데이터.*?입력.*?기종:\s*"([^"]+)".*?금액:\s*(\d+)/,
            inputSalesSimple: /(데이터|개통표).*?입력/,
            
            // 클릭 액션
            click: /(.*?)\s*(버튼을|을|를)\s*(눌러|클릭)/,
            
            // 입력 액션  
            input: /(.*?)에\s*(.*?)\s*(입력|넣어)/,
            select: /(.*?)에서\s*(.*?)\s*(선택|고르)/,
            
            // 검증
            verify: /(.*?)\s*(가|이)\s*(보이는지|사라졌는지|나타나는지|있는지|없는지)\s*확인/,
            
            // 삭제
            delete: /"([^"]+)"\s*(을|를)?\s*삭제/,
            
            // 대기
            wait: /(\d+)초\s*(대기|기다려)/,
        };

        for (const [type, pattern] of Object.entries(patterns)) {
            const match = commandText.match(pattern);
            if (match) {
                return { 
                    type, 
                    text: commandText, 
                    matches: match,
                    raw: commandText 
                };
            }
        }

        return { type: 'unknown', text: commandText, raw: commandText };
    }

    /**
     * 개별 명령 실행
     */
    async executeCommand(page, command) {
        switch (command.type) {
            case 'login':
                await this.handleLogin(page, command);
                break;
            case 'logout':
                await this.handleLogout(page, command);
                break;
            case 'navigate':
                await this.handleNavigation(page, command);
                break;
            case 'clickTab':
                await this.handleClickTab(page, command);
                break;
            case 'addBranch':
                await this.handleAddBranch(page, command);
                break;
            case 'addBranchSimple':
                await this.handleAddBranchSimple(page, command);
                break;
            case 'addStore':
                await this.handleAddStore(page, command);
                break;
            case 'inputSales':
                await this.handleInputSales(page, command);
                break;
            case 'click':
                await this.handleClick(page, command);
                break;
            case 'input':
                await this.handleInput(page, command);
                break;
            case 'select':
                await this.handleSelect(page, command);
                break;
            case 'verify':
                await this.handleVerify(page, command);
                break;
            case 'delete':
                await this.handleDelete(page, command);
                break;
            case 'wait':
                await this.handleWait(page, command);
                break;
            default:
                console.log(`⚠️ 알 수 없는 명령: ${command.text}`);
        }
    }

    /**
     * 로그인 처리
     */
    async handleLogin(page, command) {
        const userType = command.matches[1].trim();
        const credentials = this.userCredentials[userType];
        
        if (!credentials) {
            throw new Error(`알 수 없는 사용자 유형: ${userType}`);
        }

        console.log(`🔑 ${userType} 계정으로 로그인 중...`);
        
        // 빠른 로그인 사용
        await page.goto(`${this.baseURL}/quick-login/${credentials.role}`);
        await page.waitForURL('**/dashboard');
        
        console.log(`✅ ${userType} 로그인 완료`);
    }

    /**
     * 로그아웃 처리  
     */
    async handleLogout(page, command) {
        await page.click('button:has-text("로그아웃")');
        console.log('✅ 로그아웃 완료');
    }

    /**
     * 페이지 네비게이션 처리
     */
    async handleNavigation(page, command) {
        const destination = command.matches[1].trim();
        const url = this.urlMap[destination];
        
        if (!url) {
            throw new Error(`알 수 없는 페이지: ${destination}`);
        }

        console.log(`🧭 ${destination}로 이동 중...`);
        await page.goto(`${this.baseURL}${url}`);
        await page.waitForTimeout(2000);
        console.log(`✅ ${destination} 페이지 이동 완료`);
    }

    /**
     * 탭 클릭 처리
     */
    async handleClickTab(page, command) {
        const tabName = command.matches[1].trim();
        const selector = this.selectorMap[`${tabName} 탭`];
        
        if (!selector) {
            throw new Error(`알 수 없는 탭: ${tabName}`);
        }

        console.log(`📑 ${tabName} 탭 클릭 중...`);
        await page.click(selector);
        await page.waitForTimeout(1000);
        console.log(`✅ ${tabName} 탭 활성화 완료`);
    }

    /**
     * 지사 추가 처리 (상세 정보 포함)
     */
    async handleAddBranch(page, command) {
        const [, name, code, manager] = command.matches;
        
        console.log(`🏢 지사 추가 중: ${name} (${code})`);
        
        // 지사 관리 탭으로 이동
        await page.click('#branches-tab');
        await page.waitForTimeout(1000);
        
        // 지사 추가 버튼 클릭
        await page.click('button:has-text("➕ 지사 추가")');
        await page.waitForTimeout(500);
        
        // 정보 입력
        await page.fill('#modal-branch-name', name);
        await page.fill('#modal-branch-code', code.toUpperCase());
        await page.fill('#modal-branch-manager', manager);
        
        // 저장
        await page.click('button:has-text("✅ 지사 추가")');
        await page.waitForTimeout(2000);
        
        console.log(`✅ 지사 "${name}" 추가 완료`);
    }

    /**
     * 지사 추가 처리 (간단)
     */
    async handleAddBranchSimple(page, command) {
        console.log('🏢 지사 추가 모달 열기');
        
        await page.click('#branches-tab');
        await page.waitForTimeout(1000);
        await page.click('button:has-text("➕ 지사 추가")');
        await page.waitForTimeout(500);
        
        console.log('✅ 지사 추가 모달 열림');
    }

    /**
     * 클릭 액션 처리
     */
    async handleClick(page, command) {
        const element = command.matches[1].trim();
        const selector = this.selectorMap[element] || `button:has-text("${element}")`;
        
        console.log(`👆 "${element}" 클릭 중...`);
        await page.click(selector);
        await page.waitForTimeout(1000);
        console.log(`✅ "${element}" 클릭 완료`);
    }

    /**
     * 입력 액션 처리
     */
    async handleInput(page, command) {
        const field = command.matches[1].trim();
        const value = command.matches[2].trim();
        
        const selector = this.selectorMap[`${field} 입력`] || `input[placeholder*="${field}"]`;
        
        console.log(`⌨️ ${field}에 "${value}" 입력 중...`);
        await page.fill(selector, value);
        console.log(`✅ ${field} 입력 완료`);
    }

    /**
     * 선택 액션 처리
     */
    async handleSelect(page, command) {
        const field = command.matches[1].trim();
        const value = command.matches[2].trim();
        
        const selector = this.selectorMap[`${field} 선택`] || `select[name*="${field}"]`;
        
        console.log(`🎯 ${field}에서 "${value}" 선택 중...`);
        await page.selectOption(selector, value);
        console.log(`✅ ${field} 선택 완료`);
    }

    /**
     * 검증 처리
     */
    async handleVerify(page, command) {
        const target = command.matches[1].trim();
        const condition = command.matches[3].trim();
        
        // 동적 셀렉터 생성 (strict mode 해결)
        let selector = this.selectorMap[target];
        if (!selector) {
            if (target.includes('"')) {
                const itemName = target.match(/"([^"]+)"/)[1];
                // 더 구체적인 셀렉터 사용하여 strict mode 해결
                if (target.includes('지사 목록')) {
                    selector = `#branches-grid .text-gray-900:has-text("${itemName}")`;
                } else if (target.includes('매장 목록')) {
                    selector = `#stores-grid h4:has-text("${itemName}")`;
                } else {
                    selector = `[data-testid*="${itemName}"], .text-gray-900:has-text("${itemName}")`;
                }
            } else {
                selector = `text=${target}`;
            }
        }

        console.log(`🔍 "${target}" ${condition} 검증 중...`);

        if (condition.includes('보이는지') || condition.includes('나타나는지') || condition.includes('있는지')) {
            try {
                // first()로 첫 번째 요소만 선택하여 strict mode 해결
                await expect(page.locator(selector).first()).toBeVisible({ timeout: 10000 });
                console.log(`✅ "${target}" 표시 확인 완료`);
            } catch (error) {
                // 대체 셀렉터로 재시도
                const fallbackSelector = target.includes('"') ? 
                    `text=${target.match(/"([^"]+)"/)[1]}` : 
                    `text=${target}`;
                await expect(page.locator(fallbackSelector).first()).toBeVisible({ timeout: 5000 });
                console.log(`✅ "${target}" 표시 확인 완료 (대체 셀렉터)`);
            }
        } else if (condition.includes('사라졌는지') || condition.includes('없는지')) {
            await expect(page.locator(selector).first()).not.toBeVisible({ timeout: 10000 });
            console.log(`✅ "${target}" 숨김 확인 완료`);
        }
    }

    /**
     * 삭제 처리
     */
    async handleDelete(page, command) {
        const itemName = command.matches[1];
        
        console.log(`🗑️ "${itemName}" 삭제 중...`);
        
        // 수정 버튼 클릭 후 삭제 버튼 찾기
        const editButton = page.locator('button:has-text("수정")').first();
        await editButton.click();
        await page.waitForTimeout(1000);
        
        const deleteButton = page.locator('button:has-text("삭제")');
        await deleteButton.click();
        await page.waitForTimeout(1000);
        
        console.log(`✅ "${itemName}" 삭제 시도 완료`);
    }

    /**
     * 대기 처리
     */
    async handleWait(page, command) {
        const seconds = parseInt(command.matches[1]);
        console.log(`⏰ ${seconds}초 대기 중...`);
        await page.waitForTimeout(seconds * 1000);
        console.log(`✅ ${seconds}초 대기 완료`);
    }

    /**
     * 실행된 명령 목록 반환
     */
    getExecutedCommands() {
        return this.executedCommands;
    }

    /**
     * 테스트 결과 요약 출력
     */
    printSummary() {
        console.log('\n📊 자연어 테스트 실행 결과');
        console.log('='.repeat(50));
        
        this.executedCommands.forEach((cmd, index) => {
            console.log(`${index + 1}. ${cmd.status} ${cmd.text}`);
        });
        
        const successCount = this.executedCommands.filter(cmd => cmd.status.includes('성공')).length;
        const totalCount = this.executedCommands.length;
        
        console.log('='.repeat(50));
        console.log(`총 ${totalCount}개 명령 중 ${successCount}개 성공 (${Math.round(successCount/totalCount*100)}%)`);
    }
}

export default NaturalLanguageTestRunner;