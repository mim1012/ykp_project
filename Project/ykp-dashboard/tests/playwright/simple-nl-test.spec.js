import { test } from '@playwright/test';
import { NaturalLanguageTestRunner } from './utils/nl-test-runner.js';

/**
 * 간단한 자연어 테스트 데모
 */
test.describe('🚀 간단한 자연어 테스트 데모', () => {
    let nlRunner;
    
    test.beforeEach(async () => {
        nlRunner = new NaturalLanguageTestRunner();
    });

    test('기본 로그인 및 페이지 이동 자연어 테스트', async ({ page }) => {
        await nlRunner.execute(page, `
            본사 관리자로 로그인해줘
            지사 관리 페이지로 이동해줘
            지사 관리 탭을 클릭해줘
        `);
        
        console.log('\n🎉 기본 자연어 테스트 완료!');
        nlRunner.printSummary();
    });

    test('지사 추가 모달 열기 자연어 테스트', async ({ page }) => {
        await nlRunner.execute(page, `
            본사 관리자로 로그인해줘
            지사 관리 페이지로 이동해줘
            지사 관리 탭을 클릭해줘
            새 지사를 추가해줘
            3초 대기해줘
        `);
        
        console.log('\n🎉 지사 추가 모달 자연어 테스트 완료!');
        nlRunner.printSummary();
    });

    test('로그인 -> 지사관리 -> 매장관리 탭 이동 자연어 테스트', async ({ page }) => {
        await nlRunner.execute(page, `
            본사 관리자로 로그인해줘
            지사 관리 페이지로 이동해줘
            
            지사 관리 탭을 클릭해줘
            2초 대기해줘
            
            매장 관리 탭을 클릭해줘
            2초 대기해줘
            
            사용자 관리 탭을 클릭해줘
            2초 대기해줘
        `);
        
        console.log('\n🎉 전체 탭 이동 자연어 테스트 완료!');
        nlRunner.printSummary();
    });
});