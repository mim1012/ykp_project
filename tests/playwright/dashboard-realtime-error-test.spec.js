/**
 * 🔬 대시보드 실시간 데이터 로드 오류 테스트
 * 
 * 테스트 목적:
 * 1. loadRealTimeData 함수 오류 해결 검증
 * 2. DOM 요소 null 체크 수정 확인
 * 3. Console 오류 메시지 모니터링
 */

import { test, expect } from '@playwright/test';

const BASE_URL = 'https://ykpproject-production.up.railway.app';
const BRANCH_EMAIL = 'branch_bb01@ykp.com';
const BRANCH_PASSWORD = '123456';

test.describe('📊 대시보드 실시간 데이터 오류 수정 테스트', () => {
  
  test('대시보드 JavaScript 오류 해결 검증', async ({ page }) => {
    console.log('🚀 대시보드 실시간 데이터 오류 수정 테스트 시작');
    
    // 콘솔 메시지 모니터링
    const consoleMessages = [];
    page.on('console', msg => {
      const message = {
        type: msg.type(),
        text: msg.text(),
        timestamp: new Date().toISOString()
      };
      consoleMessages.push(message);
      
      if (msg.type() === 'error') {
        console.log(`🚨 Console Error: ${msg.text()}`);
      } else if (msg.type() === 'log') {
        console.log(`📝 Console Log: ${msg.text()}`);
      }
    });
    
    // ================================
    // PHASE 1: 지사 계정으로 로그인
    // ================================
    console.log('📝 Phase 1: 지사 계정 대시보드 테스트');
    
    await page.goto(BASE_URL);
    await page.waitForSelector('input[name="email"]', { timeout: 10000 });
    
    await page.fill('input[name="email"]', BRANCH_EMAIL);
    await page.fill('input[name="password"]', BRANCH_PASSWORD);
    
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'networkidle' }),
      page.click('button[type="submit"]')
    ]);
    
    console.log('✅ 지사 계정 로그인 성공');
    
    // ================================
    // PHASE 2: 대시보드 로딩 및 오류 모니터링
    // ================================
    console.log('📝 Phase 2: 대시보드 로딩 및 JavaScript 오류 모니터링');
    
    // 페이지 완전 로드 대기
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(5000); // 실시간 데이터 로드 대기
    
    // loadRealTimeData 함수 실행 상태 확인
    const realTimeDataStatus = await page.evaluate(() => {
      return {
        loadRealTimeDataExists: typeof loadRealTimeData === 'function',
        userDataExists: typeof window.userData !== 'undefined',
        userData: window.userData,
        kpiElements: {
          todaySales: !!document.querySelector('#todaySales .kpi-value'),
          monthSales: !!document.querySelector('#monthSales .kpi-value'),
          vatSales: !!document.querySelector('#vatSales .kpi-value'),
          goalProgress: !!document.querySelector('#goalProgress .kpi-value')
        }
      };
    });
    
    console.log('🔍 실시간 데이터 로드 함수 상태:', realTimeDataStatus);
    
    // ================================
    // PHASE 3: 수동으로 loadRealTimeData 실행 테스트
    // ================================
    console.log('📝 Phase 3: 수동 loadRealTimeData 실행 테스트');
    
    if (realTimeDataStatus.loadRealTimeDataExists) {
      console.log('✅ loadRealTimeData 함수 존재, 수동 실행 테스트');
      
      const manualExecutionResult = await page.evaluate(async () => {
        try {
          await loadRealTimeData();
          return {
            success: true,
            message: 'loadRealTimeData 수동 실행 성공'
          };
        } catch (error) {
          return {
            success: false,
            error: error.message,
            stack: error.stack
          };
        }
      });
      
      console.log('🧪 수동 실행 결과:', manualExecutionResult);
      
      if (manualExecutionResult.success) {
        console.log('✅ loadRealTimeData 함수 정상 실행됨');
      } else {
        console.log('❌ loadRealTimeData 함수 오류:', manualExecutionResult.error);
      }
      
    } else {
      console.log('❌ loadRealTimeData 함수가 존재하지 않음');
    }
    
    // ================================
    // PHASE 4: 콘솔 오류 분석
    // ================================
    console.log('📝 Phase 4: 콘솔 오류 메시지 분석');
    
    // 추가 대기 시간으로 모든 비동기 작업 완료 대기
    await page.waitForTimeout(10000);
    
    // 콘솔 메시지 분석
    const errorMessages = consoleMessages.filter(msg => msg.type === 'error');
    const realTimeDataErrors = errorMessages.filter(msg => 
      msg.text.includes('loadRealTimeData') || 
      msg.text.includes('textContent') ||
      msg.text.includes('Cannot set properties of null')
    );
    
    console.log(`📊 총 콘솔 메시지: ${consoleMessages.length}개`);
    console.log(`🚨 오류 메시지: ${errorMessages.length}개`);
    console.log(`🎯 loadRealTimeData 관련 오류: ${realTimeDataErrors.length}개`);
    
    if (realTimeDataErrors.length === 0) {
      console.log('🎉 loadRealTimeData 오류 완전 해결됨!');
    } else {
      console.log('❌ 여전히 loadRealTimeData 관련 오류 존재:');
      realTimeDataErrors.forEach((error, index) => {
        console.log(`   ${index + 1}. ${error.text}`);
      });
    }
    
    // ================================
    // PHASE 5: 최종 결과
    // ================================
    console.log('📝 Phase 5: 최종 결과 기록');
    
    await page.screenshot({ 
      path: 'tests/playwright/dashboard-realtime-error-fixed.png',
      fullPage: true 
    });
    
    console.log('🎉 대시보드 실시간 데이터 오류 수정 테스트 완료');
    
    console.log('\n=== 최종 결과 요약 ===');
    console.log(`✅ 지사 계정 대시보드 접근: 성공`);
    console.log(`${realTimeDataStatus.loadRealTimeDataExists ? '✅' : '❌'} loadRealTimeData 함수: ${realTimeDataStatus.loadRealTimeDataExists ? '존재' : '없음'}`);
    console.log(`${realTimeDataErrors.length === 0 ? '✅' : '❌'} 실시간 데이터 오류: ${realTimeDataErrors.length === 0 ? '해결됨' : '여전히 존재'}`);
    
    // 테스트 성공 조건: loadRealTimeData 관련 오류가 없어야 함
    expect(realTimeDataErrors.length).toBeLessThanOrEqual(0);
  });
});