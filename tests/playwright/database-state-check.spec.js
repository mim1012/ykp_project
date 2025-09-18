import { test, expect } from '@playwright/test';

test.describe('🔍 Database State Check', () => {
  test('실제 사용자 계정 존재 여부 확인', async ({ page }) => {
    
    console.log('🔍 실제 데이터베이스 상태 확인 중...');
    
    try {
      // 1. 지사 목록 확인
      const branchResponse = await page.request.get('https://ykpproject-production.up.railway.app/test-api/branches');
      
      if (branchResponse.ok()) {
        const branchData = await branchResponse.json();
        console.log(`\\n🏢 지사 현황:`);
        console.log(`   총 지사 수: ${branchData.data?.length || 0}개`);
        
        branchData.data?.forEach((branch, index) => {
          console.log(`   ${index + 1}. ${branch.name} (${branch.code})`);
          console.log(`      - 관리자: ${branch.manager_name || '없음'}`);
          console.log(`      - 매장 수: ${branch.stores_count || 0}개`);
        });
      }
      
      // 2. 사용자 수 확인
      const userResponse = await page.request.get('https://ykpproject-production.up.railway.app/api/users/count');
      
      if (userResponse.ok()) {
        const userData = await userResponse.json();
        console.log(`\\n👥 사용자 현황:`);
        console.log(`   총 사용자 수: ${userData.count}명`);
      }
      
      // 3. 특정 지사 계정 존재 확인 (간접적)
      console.log(`\\n🔑 로그인 테스트:`);
      
      const testEmails = [
        'branch_gg001@ykp.com',
        'branch_test001@ykp.com'
      ];
      
      for (const email of testEmails) {
        // 로그인 시도해서 계정 존재 여부 확인
        const loginResponse = await page.request.post('https://ykpproject-production.up.railway.app/login', {
          form: {
            email: email,
            password: '123456'
          }
        });
        
        // 302는 성공 리다이렉트, 422는 validation error (계정 없음)
        const accountExists = loginResponse.status() === 302;
        console.log(`   ${email}: ${accountExists ? '✅ 존재' : '❌ 없음'} (HTTP ${loginResponse.status()})`);
      }
      
    } catch (error) {
      console.log(`❌ 확인 중 오류: ${error.message}`);
    }
    
  });
  
  // 지사 생성 로그 확인용 테스트
  test('지사 생성 API 오류 로그 확인', async ({ page }) => {
    
    console.log('🚨 지사 생성 API 오류 로그 확인...');
    
    // 본사 계정으로 로그인
    await page.goto('https://ykpproject-production.up.railway.app/login');
    await page.fill('input[name="email"]', 'admin@ykp.com');
    await page.fill('input[name="password"]', '123456');
    await page.click('button[type="submit"]');
    
    try {
      await page.waitForURL(/dashboard/, { timeout: 10000 });
      
      // 중복 지사 생성 시도로 오류 유발
      const response = await page.request.post('https://ykpproject-production.up.railway.app/test-api/branches/add', {
        form: {
          name: '중복테스트지사',
          code: 'GG001', // 이미 존재하는 코드
          manager_name: '중복테스트매니저',
          phone: '010-1111-1111'
        }
      });
      
      console.log(`\\n📡 중복 지사 생성 시도 결과:`);
      console.log(`   HTTP Status: ${response.status()}`);
      
      if (response.status() !== 200) {
        const errorBody = await response.text();
        console.log(`   응답: ${errorBody.substring(0, 200)}...`);
      }
      
    } catch (e) {
      console.log(`⚠️ 테스트 실행 실패: ${e.message}`);
    }
    
  });
  
});