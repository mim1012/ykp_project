/**
 * 🔬 지사 계정 매장 생성 테스트
 * 
 * 테스트 목적:
 * 1. 지사 계정으로 로그인
 * 2. 매장 생성 기능 테스트 
 * 3. 오류 원인 파악 및 로그 수집
 */

import { test, expect } from '@playwright/test';

// 테스트 설정
const BASE_URL = 'https://ykpproject-production.up.railway.app';
const BRANCH_EMAIL = 'branch_bb01@ykp.com';
const BRANCH_PASSWORD = '123456';
const TEST_STORE_CODE = 'ST001';
const TEST_STORE_NAME = '테스트매장01';

test.describe('🏪 지사 계정 매장 생성 테스트', () => {
  
  test('지사 계정 로그인 및 매장 생성 오류 확인', async ({ page }) => {
    console.log('🚀 지사 계정 매장 생성 테스트 시작');
    
    // API 응답 모니터링
    page.on('response', response => {
      if (response.url().includes('/api/') || response.url().includes('/test-api/')) {
        console.log(`📡 API Response: ${response.status()} ${response.url()}`);
      }
    });
    
    // 콘솔 에러 모니터링
    page.on('console', msg => {
      if (msg.type() === 'error') {
        console.log(`🚨 Console Error: ${msg.text()}`);
      }
    });
    
    // ================================
    // PHASE 1: 지사 계정 로그인
    // ================================
    console.log('📝 Phase 1: 지사 계정 로그인...');
    
    await page.goto(BASE_URL);
    
    // 로그인 폼 대기
    await page.waitForSelector('input[name="email"]', { timeout: 10000 });
    
    // 지사 계정으로 로그인
    await page.fill('input[name="email"]', BRANCH_EMAIL);
    await page.fill('input[name="password"]', BRANCH_PASSWORD);
    
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'networkidle' }),
      page.click('button[type="submit"]')
    ]);
    
    // 로그인 성공 확인
    const currentUrl = page.url();
    console.log('📍 로그인 후 현재 URL:', currentUrl);
    
    if (currentUrl.includes('/dashboard')) {
      console.log('✅ 지사 계정 로그인 성공');
      
      // 대시보드 스크린샷
      await page.screenshot({ 
        path: 'tests/playwright/branch-dashboard.png',
        fullPage: true 
      });
      
    } else {
      console.log('❌ 지사 계정 로그인 실패');
      await page.screenshot({ 
        path: 'tests/playwright/branch-login-failed.png',
        fullPage: true 
      });
      throw new Error('지사 계정 로그인에 실패했습니다');
    }
    
    // ================================
    // PHASE 2: 매장 관리 페이지 접근
    // ================================
    console.log('📝 Phase 2: 매장 관리 페이지 접근...');
    
    // 매장 관리 메뉴 찾기
    const storeManagementSelectors = [
      'a[href*="store"]',
      'a:has-text("매장")',
      'a:has-text("Store")',
      'a:has-text("매장관리")',
      '[data-testid="store-management"]'
    ];
    
    let storeManagementFound = false;
    for (const selector of storeManagementSelectors) {
      const element = await page.locator(selector).first();
      if (await element.isVisible()) {
        await element.click();
        storeManagementFound = true;
        console.log(`✅ 매장 관리 메뉴 발견 및 클릭: ${selector}`);
        break;
      }
    }
    
    // 직접 URL로 접근
    if (!storeManagementFound) {
      console.log('⚠️ 매장 관리 메뉴를 찾을 수 없음, 직접 URL 접근 시도...');
      await page.goto(`${BASE_URL}/management/store-management`);
    }
    
    // 페이지 로드 대기
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(3000);
    
    console.log('✅ 매장 관리 페이지 접근');
    
    // ================================
    // PHASE 3: 매장 생성 시도
    // ================================
    console.log('📝 Phase 3: 매장 생성 시도...');
    
    // 매장 추가 버튼 찾기
    const addStoreSelectors = [
      'button:has-text("매장 추가")',
      'button:has-text("Add Store")',
      'button:has-text("추가")',
      '[data-testid="add-store"]',
      '.btn-primary:has-text("추가")'
    ];
    
    let addStoreButtonFound = false;
    for (const selector of addStoreSelectors) {
      const button = await page.locator(selector).first();
      if (await button.isVisible()) {
        console.log(`✅ 매장 추가 버튼 발견: ${selector}`);
        await button.click();
        addStoreButtonFound = true;
        break;
      }
    }
    
    if (!addStoreButtonFound) {
      console.log('⚠️ 매장 추가 버튼을 찾을 수 없음, API 직접 호출로 테스트');
      
      // API 직접 호출로 매장 생성 테스트
      const storeCreationResponse = await page.evaluate(async ({ storeCode, storeName }) => {
        try {
          // CSRF 토큰 가져오기
          const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
                          document.querySelector('input[name="_token"]')?.value ||
                          window.Laravel?.csrfToken ||
                          '';
          
          console.log('CSRF Token found:', csrfToken ? 'Yes' : 'No');
          
          const headers = {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          };
          
          if (csrfToken) {
            headers['X-CSRF-TOKEN'] = csrfToken;
          }
          
          // 지사 계정의 매장 생성 API 호출
          const response = await fetch('/test-api/stores/add', {
            method: 'POST',
            headers: headers,
            body: JSON.stringify({
              code: storeCode,
              name: storeName,
              owner_name: '테스트 점주',
              phone: '010-1234-5678',
              address: '서울시 테스트구',
              _token: csrfToken
            })
          });
          
          const result = await response.json();
          
          return {
            success: response.ok,
            status: response.status,
            data: result,
            csrfToken: csrfToken ? 'found' : 'not found'
          };
        } catch (error) {
          return {
            success: false,
            status: 500,
            error: error.message
          };
        }
      }, { storeCode: TEST_STORE_CODE, storeName: TEST_STORE_NAME });
      
      console.log('🏪 매장 생성 API 호출 결과:', storeCreationResponse.status);
      console.log('📋 매장 생성 응답:', storeCreationResponse);
      
      if (storeCreationResponse.success) {
        console.log('✅ 매장 생성 API 성공');
      } else {
        console.log('❌ 매장 생성 API 실패:', storeCreationResponse.data);
        
        // 오류 스크린샷
        await page.screenshot({ 
          path: 'tests/playwright/store-creation-error.png',
          fullPage: true 
        });
      }
      
    } else {
      // UI를 통한 매장 생성
      console.log('📝 UI를 통한 매장 생성 시도...');
      
      await page.waitForTimeout(2000);
      
      // 매장 정보 입력
      const storeCodeInput = page.locator('input[name="code"], input[placeholder*="코드"]').first();
      const storeNameInput = page.locator('input[name="name"], input[placeholder*="이름"]').first();
      
      if (await storeCodeInput.isVisible()) {
        await storeCodeInput.fill(TEST_STORE_CODE);
        console.log('✅ 매장 코드 입력됨');
      }
      
      if (await storeNameInput.isVisible()) {
        await storeNameInput.fill(TEST_STORE_NAME);
        console.log('✅ 매장명 입력됨');
      }
      
      // 제출 버튼 클릭
      const submitSelectors = [
        'button[type="submit"]',
        'button:has-text("저장")',
        'button:has-text("Save")',
        'button:has-text("추가")'
      ];
      
      for (const selector of submitSelectors) {
        const button = await page.locator(selector).first();
        if (await button.isVisible()) {
          console.log('📤 매장 생성 폼 제출...');
          
          // API 응답 대기
          const responsePromise = page.waitForResponse(response => 
            response.url().includes('/api/stores') || 
            response.url().includes('/test-api/stores')
          );
          
          await button.click();
          
          try {
            const response = await responsePromise;
            console.log(`📡 매장 생성 응답: ${response.status()}`);
            
            if (response.ok()) {
              const responseBody = await response.json();
              console.log('✅ 매장 생성 성공:', responseBody);
            } else {
              const errorBody = await response.text();
              console.log('❌ 매장 생성 실패:', errorBody);
            }
          } catch (error) {
            console.log('⚠️ API 응답 캐처 실패:', error.message);
          }
          
          break;
        }
      }
    }
    
    // ================================
    // PHASE 4: 결과 확인 및 스크린샷
    // ================================
    console.log('📝 Phase 4: 최종 결과 확인...');
    
    await page.waitForTimeout(3000);
    
    // 최종 스크린샷
    await page.screenshot({ 
      path: 'tests/playwright/store-creation-final.png',
      fullPage: true 
    });
    
    // 에러 메시지 확인
    const errorSelectors = [
      '.alert-danger',
      '.error',
      '[class*="error"]',
      ':has-text("오류")',
      ':has-text("Error")',
      ':has-text("실패")'
    ];
    
    for (const selector of errorSelectors) {
      const errorElement = await page.locator(selector).first();
      if (await errorElement.isVisible()) {
        const errorText = await errorElement.textContent();
        console.log('🚨 발견된 오류 메시지:', errorText);
      }
    }
    
    console.log('🎉 지사 계정 매장 생성 테스트 완료');
    
    console.log('\n=== 테스트 요약 ===');
    console.log('✅ 지사 계정 로그인: 성공');
    console.log('📊 매장 생성 프로세스가 테스트되었습니다');
    console.log('🔍 발생한 모든 오류가 로그에 기록되었습니다');
  });
});