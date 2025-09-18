/**
 * 🔬 Users API 응답 디버깅 테스트
 * 
 * 목적: /test-api/users API가 제대로 지사 계정들을 반환하는지 확인
 */

import { test, expect } from '@playwright/test';

const BASE_URL = 'https://ykpproject-production.up.railway.app';
const HQ_EMAIL = 'hq@ykp.com';
const HQ_PASSWORD = '123456';

test.describe('🔍 Users API 디버깅', () => {
  
  test('지사 계정 조회 API 응답 분석', async ({ page }) => {
    console.log('🚀 Users API 디버깅 테스트 시작');
    
    // 본사 로그인
    await page.goto(BASE_URL);
    await page.waitForSelector('input[name="email"]');
    
    await page.fill('input[name="email"]', HQ_EMAIL);
    await page.fill('input[name="password"]', HQ_PASSWORD);
    
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'networkidle' }),
      page.click('button[type="submit"]')
    ]);
    
    console.log('✅ 본사 계정 로그인 성공');
    
    // ================================
    // PHASE 1: /test-api/users API 직접 호출
    // ================================
    console.log('📝 Phase 1: /test-api/users API 직접 호출');
    
    const usersApiResult = await page.evaluate(async () => {
      try {
        const response = await fetch('/test-api/users');
        const data = await response.json();
        
        return {
          success: response.ok,
          status: response.status,
          data: data,
          dataType: typeof data,
          isArray: Array.isArray(data),
          hasSuccessProperty: data.hasOwnProperty('success'),
          userCount: Array.isArray(data) ? data.length : 
                    (data.data && Array.isArray(data.data)) ? data.data.length : 'unknown'
        };
      } catch (error) {
        return {
          success: false,
          error: error.message
        };
      }
    });
    
    console.log('📊 /test-api/users API 응답:', usersApiResult);
    
    // ================================
    // PHASE 2: 지사 계정들만 필터링
    // ================================
    console.log('📝 Phase 2: 지사 계정들 필터링 분석');
    
    const branchAccountsAnalysis = await page.evaluate(async () => {
      try {
        const response = await fetch('/test-api/users');
        const userData = await response.json();
        const users = userData.success ? userData.data : userData;
        
        if (!Array.isArray(users)) {
          return { error: 'users is not an array', receivedType: typeof users, receivedData: users };
        }
        
        // 모든 사용자 역할 분석
        const roleDistribution = {};
        users.forEach(user => {
          const role = user.role || 'unknown';
          roleDistribution[role] = (roleDistribution[role] || 0) + 1;
        });
        
        // 지사 계정들만 필터링
        const branchUsers = users.filter(u => u.role === 'branch');
        
        // 지사별 계정 정보
        const branchAccountDetails = branchUsers.map(user => ({
          id: user.id,
          email: user.email,
          name: user.name,
          branch_id: user.branch_id,
          branch_name: user.branch?.name || 'unknown',
          is_active: user.is_active,
          created_at: user.created_at
        }));
        
        return {
          totalUsers: users.length,
          roleDistribution: roleDistribution,
          branchUserCount: branchUsers.length,
          branchAccounts: branchAccountDetails
        };
        
      } catch (error) {
        return {
          error: error.message,
          stack: error.stack
        };
      }
    });
    
    console.log('📊 지사 계정 분석 결과:', branchAccountsAnalysis);
    
    if (branchAccountsAnalysis.branchAccounts) {
      console.log('\n📋 발견된 지사 계정들:');
      branchAccountsAnalysis.branchAccounts.forEach((account, index) => {
        console.log(`   ${index + 1}. ${account.email}`);
        console.log(`      이름: ${account.name}`);
        console.log(`      지사 ID: ${account.branch_id}`);
        console.log(`      지사명: ${account.branch_name}`);
        console.log(`      활성: ${account.is_active ? '✅' : '❌'}`);
        console.log('');
      });
    }
    
    // ================================
    // PHASE 3: 지사 목록과 매칭 테스트
    // ================================
    console.log('📝 Phase 3: 지사 목록과 계정 매칭 테스트');
    
    const branchMatchingResult = await page.evaluate(async () => {
      try {
        // 지사 목록 조회
        const branchesResponse = await fetch('/test-api/branches');
        const branchesData = await branchesResponse.json();
        const branches = branchesData.success ? branchesData.data : branchesData;
        
        // 사용자 목록 조회
        const usersResponse = await fetch('/test-api/users');
        const usersData = await usersResponse.json();
        const users = usersData.success ? usersData.data : usersData;
        
        const matchingResults = [];
        
        if (Array.isArray(branches) && Array.isArray(users)) {
          branches.forEach(branch => {
            const branchAccount = users.find(u => 
              u.role === 'branch' && 
              u.branch_id === branch.id &&
              u.is_active
            );
            
            matchingResults.push({
              branchId: branch.id,
              branchCode: branch.code,
              branchName: branch.name,
              hasAccount: !!branchAccount,
              accountEmail: branchAccount?.email || null,
              accountName: branchAccount?.name || null
            });
          });
        }
        
        return {
          success: true,
          matchingResults: matchingResults,
          branchCount: branches?.length || 0,
          userCount: users?.length || 0
        };
        
      } catch (error) {
        return {
          success: false,
          error: error.message
        };
      }
    });
    
    console.log('📊 지사-계정 매칭 결과:', branchMatchingResult);
    
    if (branchMatchingResult.success) {
      console.log('\n📋 지사별 계정 매칭 상황:');
      branchMatchingResult.matchingResults.forEach(result => {
        const status = result.hasAccount ? '✅' : '❌';
        console.log(`   ${status} ${result.branchCode} (${result.branchName}): ${result.accountEmail || '계정 없음'}`);
      });
    }
    
    console.log('🎉 Users API 디버깅 완료');
  });
});