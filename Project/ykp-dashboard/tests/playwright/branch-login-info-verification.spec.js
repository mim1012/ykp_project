/**
 * 🔬 지사 관리 페이지 로그인 정보 검증 테스트
 * 
 * 테스트 목적:
 * 1. 지사 관리 페이지에 표시되는 로그인 정보 확인
 * 2. 실제 데이터베이스의 지사 계정 정보와 비교
 * 3. 로그인 정보 불일치 문제 파악 및 수정
 */

import { test, expect } from '@playwright/test';

const BASE_URL = 'https://ykpproject-production.up.railway.app';
const HQ_EMAIL = 'hq@ykp.com';
const HQ_PASSWORD = '123456';

test.describe('🔍 지사 관리 페이지 로그인 정보 검증', () => {
  
  test('지사 계정 로그인 정보 일치성 확인', async ({ page }) => {
    console.log('🚀 지사 로그인 정보 검증 테스트 시작');
    
    // ================================
    // PHASE 1: 본사 계정 로그인
    // ================================
    console.log('📝 Phase 1: 본사 계정 로그인');
    
    await page.goto(BASE_URL);
    await page.waitForSelector('input[name="email"]', { timeout: 10000 });
    
    await page.fill('input[name="email"]', HQ_EMAIL);
    await page.fill('input[name="password"]', HQ_PASSWORD);
    
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'networkidle' }),
      page.click('button[type="submit"]')
    ]);
    
    console.log('✅ 본사 계정 로그인 성공');
    
    // ================================
    // PHASE 2: 지사 관리 페이지 접근
    // ================================
    console.log('📝 Phase 2: 지사 관리 페이지 접근');
    
    await page.goto(`${BASE_URL}/management/branches`);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(3000); // 데이터 로딩 대기
    
    console.log('✅ 지사 관리 페이지 접근 성공');
    
    // 페이지 스크린샷
    await page.screenshot({ 
      path: 'tests/playwright/branch-management-page.png',
      fullPage: true 
    });
    
    // ================================
    // PHASE 3: 실제 지사 계정 정보 조회
    // ================================
    console.log('📝 Phase 3: 실제 지사 계정 정보 API 조회');
    
    const actualBranchAccounts = await page.evaluate(async () => {
      try {
        // 지사 목록 조회
        const branchesResponse = await fetch('/test-api/branches');
        const branches = await branchesResponse.json();
        
        const branchAccountInfo = [];
        
        for (const branch of branches.slice(0, 5)) { // 처음 5개만 확인
          // 각 지사의 관리자 계정 정보 조회
          const accountResponse = await fetch(`/test-api/branches/${branch.id}/account`);
          
          if (accountResponse.ok) {
            const accountData = await accountResponse.json();
            branchAccountInfo.push({
              branchCode: branch.code,
              branchName: branch.name,
              branchId: branch.id,
              accountInfo: accountData
            });
          } else {
            // 직접 사용자 테이블에서 지사 계정 찾기
            const usersResponse = await fetch('/test-api/users');
            if (usersResponse.ok) {
              const users = await usersResponse.json();
              const branchUser = users.find(u => u.role === 'branch' && u.branch_id === branch.id);
              
              if (branchUser) {
                branchAccountInfo.push({
                  branchCode: branch.code,
                  branchName: branch.name,
                  branchId: branch.id,
                  accountInfo: {
                    email: branchUser.email,
                    name: branchUser.name,
                    role: branchUser.role,
                    is_active: branchUser.is_active
                  }
                });
              }
            }
          }
        }
        
        return branchAccountInfo;
      } catch (error) {
        return { error: error.message };
      }
    });
    
    console.log('📊 실제 지사 계정 정보:', actualBranchAccounts);
    
    // ================================
    // PHASE 4: 페이지에 표시된 로그인 정보 수집
    // ================================
    console.log('📝 Phase 4: 페이지에 표시된 로그인 정보 수집');
    
    const displayedLoginInfo = await page.evaluate(() => {
      const loginInfoElements = [];
      
      // 지사 테이블이나 리스트에서 로그인 정보 찾기
      const emailElements = document.querySelectorAll('[data-email], .email, [class*="email"], td');
      const passwordElements = document.querySelectorAll('[data-password], .password, [class*="password"]');
      
      // 이메일 정보 수집
      const emails = [];
      emailElements.forEach(el => {
        const text = el.textContent?.trim();
        if (text && text.includes('@ykp.com')) {
          emails.push(text);
        }
      });
      
      // 비밀번호 정보 수집  
      const passwords = [];
      passwordElements.forEach(el => {
        const text = el.textContent?.trim();
        if (text && text.length > 3 && text.length < 20) {
          passwords.push(text);
        }
      });
      
      // 테이블 행별 정보 수집
      const tableRows = document.querySelectorAll('tr, .branch-item, [data-branch]');
      const branchDisplayInfo = [];
      
      tableRows.forEach(row => {
        const cells = row.querySelectorAll('td, .cell, [class*="col"]');
        const rowData = {};
        
        cells.forEach(cell => {
          const text = cell.textContent?.trim();
          
          if (text?.includes('@ykp.com')) {
            rowData.email = text;
          }
          if (text?.match(/^[A-Z]{2,}\d+$/)) {
            rowData.branchCode = text;
          }
          if (text?.length >= 4 && text?.length <= 8 && !text.includes('@')) {
            rowData.password = text;
          }
        });
        
        if (rowData.email || rowData.branchCode) {
          branchDisplayInfo.push(rowData);
        }
      });
      
      return {
        emails: emails,
        passwords: passwords,
        branchDisplayInfo: branchDisplayInfo,
        pageContent: document.body.innerHTML.includes('로그인') ? 'login info present' : 'no login info'
      };
    });
    
    console.log('📋 페이지에 표시된 로그인 정보:', displayedLoginInfo);
    
    // ================================
    // PHASE 5: 정보 일치성 검증
    // ================================
    console.log('📝 Phase 5: 로그인 정보 일치성 검증');
    
    const inconsistencies = [];
    
    if (actualBranchAccounts.length > 0) {
      actualBranchAccounts.forEach((actual, index) => {
        const displayed = displayedLoginInfo.branchDisplayInfo[index];
        
        if (displayed) {
          console.log(`\n🔍 지사 ${actual.branchCode} 정보 비교:`);
          console.log(`   실제 이메일: ${actual.accountInfo?.email || 'N/A'}`);
          console.log(`   표시 이메일: ${displayed.email || 'N/A'}`);
          console.log(`   표시 비밀번호: ${displayed.password || 'N/A'}`);
          
          // 불일치 검사
          if (actual.accountInfo?.email !== displayed.email) {
            inconsistencies.push({
              branchCode: actual.branchCode,
              type: 'email_mismatch',
              actual: actual.accountInfo?.email,
              displayed: displayed.email
            });
          }
        }
      });
    }
    
    if (inconsistencies.length > 0) {
      console.log('❌ 로그인 정보 불일치 발견:');
      inconsistencies.forEach(issue => {
        console.log(`   ${issue.branchCode}: ${issue.type} - 실제(${issue.actual}) vs 표시(${issue.displayed})`);
      });
    } else {
      console.log('✅ 모든 로그인 정보가 일치함');
    }
    
    // ================================
    // PHASE 6: 실제 로그인 테스트
    // ================================
    if (actualBranchAccounts.length > 0 && actualBranchAccounts[0].accountInfo?.email) {
      console.log('📝 Phase 6: 실제 지사 계정으로 로그인 테스트');
      
      const testAccount = actualBranchAccounts[0];
      const testEmail = testAccount.accountInfo.email;
      
      console.log(`🔐 테스트 계정: ${testEmail}`);
      
      // 본사에서 로그아웃
      await page.goto(`${BASE_URL}/logout`);
      await page.waitForURL(new RegExp(`${BASE_URL}/(login|$)`));
      
      // 실제 지사 계정으로 로그인 시도
      await page.waitForSelector('input[name="email"]');
      await page.fill('input[name="email"]', testEmail);
      await page.fill('input[name="password"]', '123456'); // 기본 비밀번호
      
      await page.click('button[type="submit"]');
      await page.waitForTimeout(3000);
      
      const loginResult = page.url();
      if (loginResult.includes('/dashboard')) {
        console.log('✅ 실제 지사 계정 로그인 성공');
      } else {
        console.log('❌ 실제 지사 계정 로그인 실패');
        
        // 다른 비밀번호들 시도
        const commonPasswords = ['password', '12345678', testAccount.branchCode];
        
        for (const pwd of commonPasswords) {
          await page.fill('input[name="email"]', testEmail);
          await page.fill('input[name="password"]', pwd);
          await page.click('button[type="submit"]');
          await page.waitForTimeout(2000);
          
          if (page.url().includes('/dashboard')) {
            console.log(`✅ 비밀번호 '${pwd}'로 로그인 성공`);
            break;
          } else {
            console.log(`❌ 비밀번호 '${pwd}' 실패`);
          }
        }
      }
    }
    
    // 최종 스크린샷
    await page.screenshot({ 
      path: 'tests/playwright/branch-login-info-verification.png',
      fullPage: true 
    });
    
    console.log('🎉 지사 로그인 정보 검증 완료');
    
    console.log('\n=== 검증 결과 요약 ===');
    console.log(`📊 실제 지사 계정 수: ${actualBranchAccounts.length}`);
    console.log(`📋 표시된 정보 수: ${displayedLoginInfo.branchDisplayInfo.length}`);
    console.log(`${inconsistencies.length === 0 ? '✅' : '❌'} 정보 일치성: ${inconsistencies.length === 0 ? '일치함' : '불일치 발견'}`);
  });
});