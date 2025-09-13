/**
 * 🔬 지사 계정 매장 생성 상세 분석 테스트
 * 
 * 문제 해결을 위한 단계별 분석:
 * 1. 지사 대시보드의 "매장 추가" 버튼 정확히 찾기
 * 2. CSRF 토큰 정상 로드 확인
 * 3. 매장 생성 프로세스 완전 추적
 */

import { test, expect } from '@playwright/test';

const BASE_URL = 'https://ykpproject-production.up.railway.app';
const BRANCH_EMAIL = 'branch_bb01@ykp.com';
const BRANCH_PASSWORD = '123456';

test.describe('🏪 지사 계정 매장 생성 상세 분석', () => {
  
  test('매장 생성 프로세스 완전 분석', async ({ page }) => {
    console.log('🚀 지사 계정 매장 생성 상세 분석 시작');
    
    // ================================
    // PHASE 1: 지사 계정 로그인
    // ================================
    console.log('📝 Phase 1: 지사 계정 로그인');
    
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
    // PHASE 2: 대시보드 분석 및 매장 추가 버튼 찾기
    // ================================
    console.log('📝 Phase 2: 대시보드 UI 요소 분석');
    
    // 페이지 완전 로드 대기
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(5000); // 충분한 로딩 시간
    
    // CSRF 토큰 확인
    const csrfTokenCheck = await page.evaluate(() => {
      const metaToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
      const inputToken = document.querySelector('input[name="_token"]')?.value;
      const laravelToken = window.Laravel?.csrfToken;
      
      return {
        metaToken: metaToken || 'not found',
        inputToken: inputToken || 'not found', 
        laravelToken: laravelToken || 'not found',
        hasAnyToken: !!(metaToken || inputToken || laravelToken)
      };
    });
    
    console.log('🔒 CSRF 토큰 상태:', csrfTokenCheck);
    
    // 페이지의 모든 버튼 조사
    const allButtons = await page.locator('button, a[class*="btn"], [role="button"]').all();
    console.log(`🔍 페이지에서 발견된 버튼/링크 수: ${allButtons.length}`);
    
    const buttonTexts = [];
    for (let i = 0; i < Math.min(allButtons.length, 20); i++) { // 최대 20개만 확인
      try {
        const text = await allButtons[i].textContent();
        const isVisible = await allButtons[i].isVisible();
        buttonTexts.push({ text: text?.trim(), visible: isVisible });
      } catch (error) {
        buttonTexts.push({ text: 'error', visible: false });
      }
    }
    
    console.log('📋 발견된 버튼들:', buttonTexts);
    
    // "매장 추가" 버튼 정확히 찾기
    const storeAddButton = await page.locator(':has-text("매장 추가")').first();
    const storeAddButtonVisible = await storeAddButton.isVisible().catch(() => false);
    
    console.log(`🏪 "매장 추가" 버튼 발견: ${storeAddButtonVisible ? 'Yes' : 'No'}`);
    
    if (storeAddButtonVisible) {
      console.log('✅ 매장 추가 버튼 발견, 클릭 시도');
      
      // 버튼 클릭
      await storeAddButton.click();
      await page.waitForTimeout(3000);
      
      // 클릭 후 변화 확인
      const currentUrl = page.url();
      console.log('📍 버튼 클릭 후 URL:', currentUrl);
      
      // 폼이나 모달이 나타났는지 확인
      const formVisible = await page.locator('form, .modal, [role="dialog"]').isVisible().catch(() => false);
      console.log(`📝 폼/모달 표시됨: ${formVisible ? 'Yes' : 'No'}`);
      
      if (formVisible) {
        // 폼 요소 확인
        const formInputs = await page.locator('input, select, textarea').all();
        console.log(`📝 폼 입력 필드 수: ${formInputs.length}`);
        
        // 매장 생성에 필요한 필드들 확인
        const requiredFields = ['code', 'name', 'owner_name', 'phone', 'address'];
        const fieldStatus = {};
        
        for (const field of requiredFields) {
          const fieldElement = await page.locator(`input[name="${field}"], input[placeholder*="${field}"]`).first();
          const fieldExists = await fieldElement.isVisible().catch(() => false);
          fieldStatus[field] = fieldExists;
        }
        
        console.log('📋 필수 필드 상태:', fieldStatus);
        
        // 실제 매장 정보 입력 시도
        if (fieldStatus.code) {
          await page.fill('input[name="code"]', 'TEST001');
          console.log('✅ 매장 코드 입력');
        }
        
        if (fieldStatus.name) {
          await page.fill('input[name="name"]', '테스트매장');
          console.log('✅ 매장명 입력');
        }
        
        // 제출 버튼 찾기 및 클릭
        const submitButton = await page.locator('button[type="submit"], button:has-text("저장"), button:has-text("추가")').first();
        const submitButtonVisible = await submitButton.isVisible().catch(() => false);
        
        if (submitButtonVisible) {
          console.log('📤 매장 생성 폼 제출 중...');
          
          // API 응답 모니터링
          const responsePromise = page.waitForResponse(response => 
            response.url().includes('/api/') || response.url().includes('/test-api/')
          ).catch(() => null);
          
          await submitButton.click();
          
          // 응답 분석
          const response = await responsePromise;
          if (response) {
            console.log(`📡 API 응답: ${response.status()} ${response.url()}`);
            
            try {
              const responseBody = await response.json();
              console.log('📋 응답 데이터:', responseBody);
            } catch (error) {
              const responseText = await response.text();
              console.log('📋 응답 텍스트:', responseText.substring(0, 200));
            }
          }
        }
        
      } else {
        console.log('❌ 매장 추가 버튼 클릭 후 폼이 표시되지 않음');
      }
      
    } else {
      console.log('❌ 매장 추가 버튼을 찾을 수 없음');
      
      // 대체 방법: API 직접 테스트 (CSRF 토큰 포함)
      if (csrfTokenCheck.hasAnyToken) {
        console.log('🔄 CSRF 토큰이 있으므로 API 직접 호출 테스트');
        
        const directApiTest = await page.evaluate(async () => {
          try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
                            document.querySelector('input[name="_token"]')?.value ||
                            window.Laravel?.csrfToken ||
                            '';
            
            const response = await fetch('/test-api/stores/add', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken
              },
              body: JSON.stringify({
                code: 'DIRECT001',
                name: '직접API테스트매장',
                owner_name: '테스트점주',
                phone: '010-1234-5678',
                address: '테스트주소',
                _token: csrfToken
              })
            });
            
            const result = await response.json();
            
            return {
              status: response.status,
              success: response.ok,
              data: result
            };
          } catch (error) {
            return {
              status: 'error',
              success: false,
              error: error.message
            };
          }
        });
        
        console.log('🔗 직접 API 호출 결과:', directApiTest);
      }
    }
    
    // ================================
    // PHASE 3: 최종 스크린샷 및 분석
    // ================================
    console.log('📝 Phase 3: 최종 상태 기록');
    
    await page.screenshot({ 
      path: 'tests/playwright/branch-store-detailed-analysis.png',
      fullPage: true 
    });
    
    console.log('🎉 지사 계정 매장 생성 상세 분석 완료');
  });
});