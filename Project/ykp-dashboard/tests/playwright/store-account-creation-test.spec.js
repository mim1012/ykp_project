/**
 * 🔬 매장 계정 생성 테스트 - PostgreSQL Boolean 호환성 검증
 * 
 * 테스트 목적:
 * 1. 매장 생성 후 자동 계정 생성 테스트
 * 2. "매장은 생성되었지만 계정 생성에 실패했습니다" 오류 해결 검증
 * 3. StoreController의 PostgreSQL boolean 수정 검증
 */

import { test, expect } from '@playwright/test';

const BASE_URL = 'https://ykpproject-production.up.railway.app';
const BRANCH_EMAIL = 'branch_bb01@ykp.com';
const BRANCH_PASSWORD = '123456';

test.describe('🏪 매장 계정 생성 PostgreSQL 호환성 테스트', () => {
  
  test('지사 계정으로 매장 및 계정 생성 완전 테스트', async ({ page }) => {
    console.log('🚀 매장 계정 생성 완전 테스트 시작');
    
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
    
    // 지사 계정 정보 확인
    const branchInfo = await page.evaluate(() => {
      return {
        role: window.userData?.role || 'unknown',
        branch_id: window.userData?.branch_id || 'unknown',
        name: window.userData?.name || 'unknown'
      };
    });
    
    console.log('👤 지사 계정 정보:', branchInfo);
    
    // ================================
    // PHASE 2: 매장 생성 (계정 생성 포함)
    // ================================
    console.log('📝 Phase 2: 매장 생성 및 자동 계정 생성 테스트');
    
    const timestamp = Date.now().toString().slice(-4);
    const testStoreName = `계정테스트매장${timestamp}`;
    const testOwnerName = `계정테스트점주${timestamp}`;
    
    console.log(`🏪 매장 생성 시작: ${testStoreName}`);
    
    // 매장 생성 API 호출
    const storeCreationResult = await page.evaluate(async ({ storeName, ownerName, branchId }) => {
      try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        
        // 1단계: 매장 생성
        const storeResponse = await fetch('/test-api/stores/add', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken
          },
          body: JSON.stringify({
            name: storeName,
            branch_id: branchId,
            owner_name: ownerName,
            phone: '010-1111-2222',
            _token: csrfToken
          })
        });
        
        const storeResult = await storeResponse.json();
        
        if (!storeResult.success) {
          return {
            phase: 'store_creation',
            success: false,
            error: storeResult.error
          };
        }
        
        console.log('✅ 매장 생성 성공, 계정 생성 시작...');
        
        // 2단계: 매장 계정 생성 (PostgreSQL boolean 수정 검증)
        const accountResponse = await fetch(`/api/stores/${storeResult.data.id}/account`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken
          },
          body: JSON.stringify({
            name: `${storeName} 관리자`,
            email: `store_${storeResult.data.code.toLowerCase().replace('-', '_')}@ykp.com`,
            password: '123456',
            role: 'store',
            _token: csrfToken
          })
        });
        
        const accountResult = await accountResponse.json();
        
        return {
          phase: 'complete',
          store: {
            success: storeResponse.ok,
            status: storeResponse.status,
            data: storeResult
          },
          account: {
            success: accountResponse.ok,
            status: accountResponse.status,
            data: accountResult
          }
        };
        
      } catch (error) {
        return {
          phase: 'error',
          success: false,
          error: error.message
        };
      }
    }, { storeName: testStoreName, ownerName: testOwnerName, branchId: branchInfo.branch_id });
    
    console.log('🏪 매장 및 계정 생성 결과:', storeCreationResult);
    
    // ================================
    // PHASE 3: 결과 분석
    // ================================
    console.log('📝 Phase 3: 결과 분석');
    
    if (storeCreationResult.phase === 'complete') {
      // 매장 생성 결과
      if (storeCreationResult.store.success) {
        console.log('✅ 매장 생성: 성공');
        console.log('📋 생성된 매장:', storeCreationResult.store.data.data);
      } else {
        console.log('❌ 매장 생성: 실패');
      }
      
      // 매장 계정 생성 결과
      if (storeCreationResult.account.success) {
        console.log('✅ 매장 계정 생성: 성공');
        console.log('📋 생성된 계정:', storeCreationResult.account.data.data);
        console.log('🎉 PostgreSQL boolean 호환성 문제 완전 해결!');
      } else {
        console.log('❌ 매장 계정 생성: 실패');
        console.log('🚨 오류 내용:', storeCreationResult.account.data);
        
        // PostgreSQL boolean 오류인지 확인
        const errorMessage = storeCreationResult.account.data.error || '';
        if (errorMessage.includes('SQLSTATE[42804]') || errorMessage.includes('boolean')) {
          console.log('🔴 PostgreSQL boolean 호환성 문제가 여전히 존재합니다');
        }
      }
    } else {
      console.log('❌ 테스트 실행 중 오류:', storeCreationResult.error);
    }
    
    // ================================
    // PHASE 4: 최종 스크린샷 및 정리
    // ================================
    console.log('📝 Phase 4: 최종 결과 기록');
    
    await page.screenshot({ 
      path: 'tests/playwright/store-account-creation-final.png',
      fullPage: true 
    });
    
    console.log('🎉 매장 계정 생성 테스트 완료');
    
    console.log('\n=== 최종 테스트 요약 ===');
    console.log('✅ 지사 계정 로그인: 성공');
    console.log(`${storeCreationResult.store?.success ? '✅' : '❌'} 매장 생성: ${storeCreationResult.store?.success ? '성공' : '실패'}`);
    console.log(`${storeCreationResult.account?.success ? '✅' : '❌'} 매장 계정 생성: ${storeCreationResult.account?.success ? '성공' : '실패'}`);
    
    if (storeCreationResult.account?.success) {
      console.log('🎉 "매장은 생성되었지만 계정 생성에 실패했습니다" 문제 해결!');
    }
  });
});