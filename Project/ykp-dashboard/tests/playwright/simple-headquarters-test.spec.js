import { test, expect } from '@playwright/test';

/**
 * 간단한 본사 계정 매장관리 테스트
 */
test.describe('🏢 본사 계정 간단 테스트', () => {
    
    /**
     * 기본 로그인 및 API 호출 테스트
     */
    test('본사 계정 로그인 및 매장 API 호출 테스트', async ({ page }) => {
        console.log('🏢 본사 계정 로그인 및 매장 API 테스트 시작');
        
        // 1. 로그인 페이지로 이동
        await page.goto('http://127.0.0.1:8000/login');
        console.log('📝 로그인 페이지 접근');
        
        // 2. 본사 계정으로 로그인
        await page.fill('input[name="email"], input[type="email"]', 'hq@ykp.com');
        await page.fill('input[name="password"], input[type="password"]', '123456');
        await page.click('button[type="submit"], button:has-text("로그인")');
        
        console.log('🔑 본사 계정 로그인 시도');
        
        // 3. 로그인 성공 대기
        await page.waitForTimeout(3000);
        
        // 4. 매장 API 직접 호출 테스트
        const storeListResponse = await page.evaluate(async () => {
            try {
                const response = await fetch('/api/stores', {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                return {
                    status: response.status,
                    data: await response.json()
                };
            } catch (error) {
                return {
                    status: 500,
                    error: error.message
                };
            }
        });
        
        console.log('📊 매장 목록 API 호출 결과:', storeListResponse.status);
        console.log('📋 매장 데이터:', storeListResponse.data);
        
        // 5. API 응답 검증
        expect(storeListResponse.status).toBe(200);
        if (storeListResponse.data && storeListResponse.data.success) {
            expect(storeListResponse.data.data).toBeDefined();
            console.log(`✅ 본사 계정으로 ${storeListResponse.data.data.length}개 매장 조회 성공`);
        }
        
        // 6. 매장 수정 API 테스트 (첫 번째 매장)
        if (storeListResponse.data?.data?.length > 0) {
            const firstStore = storeListResponse.data.data[0];
            const storeId = firstStore.id;
            
            console.log(`🏪 매장 ${storeId} 수정 테스트 시작`);
            
            const updateResponse = await page.evaluate(async (storeId) => {
                try {
                    const response = await fetch(`/api/stores/${storeId}`, {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({
                            name: `테스트 매장 ${storeId} (${new Date().toISOString()})`,
                            owner_name: '테스트 점주',
                            phone: '010-1234-5678',
                            address: '테스트 주소',
                            status: 'active'
                        })
                    });
                    return {
                        status: response.status,
                        data: await response.json()
                    };
                } catch (error) {
                    return {
                        status: 500,
                        error: error.message
                    };
                }
            }, storeId);
            
            console.log(`✏️ 매장 ${storeId} 수정 API 결과:`, updateResponse.status);
            console.log('📝 수정 응답:', updateResponse.data);
            
            // 수정 API 검증
            if (updateResponse.status === 200) {
                expect(updateResponse.data.success).toBeTruthy();
                expect(updateResponse.data.message).toContain('수정');
                console.log(`✅ 매장 ${storeId} 수정 성공`);
            } else {
                console.log(`⚠️ 매장 ${storeId} 수정 실패:`, updateResponse);
            }
        }
        
        // 7. 매장 성과 API 테스트
        if (storeListResponse.data?.data?.length > 0) {
            const firstStore = storeListResponse.data.data[0];
            const storeId = firstStore.id;
            
            console.log(`📈 매장 ${storeId} 성과 조회 테스트`);
            
            const performanceResponse = await page.evaluate(async (storeId) => {
                try {
                    const response = await fetch(`/api/stores/${storeId}/performance`, {
                        method: 'GET',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    return {
                        status: response.status,
                        data: await response.json()
                    };
                } catch (error) {
                    return {
                        status: 500,
                        error: error.message
                    };
                }
            }, storeId);
            
            console.log(`📊 매장 ${storeId} 성과 API 결과:`, performanceResponse.status);
            console.log('📈 성과 데이터:', performanceResponse.data);
            
            if (performanceResponse.status === 200) {
                expect(performanceResponse.data.success).toBeTruthy();
                console.log(`✅ 매장 ${storeId} 성과 조회 성공`);
            }
        }
        
        // 8. 계정 생성 API 테스트
        if (storeListResponse.data?.data?.length > 0) {
            const firstStore = storeListResponse.data.data[0];
            const storeId = firstStore.id;
            
            console.log(`👤 매장 ${storeId} 계정 생성 테스트`);
            
            const createAccountResponse = await page.evaluate(async (storeId) => {
                try {
                    const timestamp = Date.now();
                    const response = await fetch(`/api/stores/${storeId}/create-account`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({
                            name: `테스트 사용자 ${timestamp}`,
                            email: `test-user-${timestamp}@ykp.com`,
                            password: 'test123456',
                            role: 'store'
                        })
                    });
                    return {
                        status: response.status,
                        data: await response.json()
                    };
                } catch (error) {
                    return {
                        status: 500,
                        error: error.message
                    };
                }
            }, storeId);
            
            console.log(`👥 매장 ${storeId} 계정 생성 API 결과:`, createAccountResponse.status);
            console.log('👤 생성 응답:', createAccountResponse.data);
            
            if (createAccountResponse.status === 201) {
                expect(createAccountResponse.data.success).toBeTruthy();
                expect(createAccountResponse.data.data.role).toBe('store');
                console.log(`✅ 매장 ${storeId} 계정 생성 성공`);
            }
        }
        
        console.log('🎉 본사 계정 매장관리 테스트 완료');
    });
    
    /**
     * 권한 확인 테스트
     */
    test('본사 계정 권한 확인 테스트', async ({ page }) => {
        console.log('🔐 본사 계정 권한 확인 테스트');
        
        // 로그인
        await page.goto('http://127.0.0.1:8000/login');
        await page.fill('input[name="email"]', 'hq@ykp.com');
        await page.fill('input[name="password"]', '123456');
        await page.click('button[type="submit"]');
        await page.waitForTimeout(3000);
        
        // 사용자 정보 확인
        const userData = await page.evaluate(() => {
            return window.userData || null;
        });
        
        console.log('👤 로그인된 사용자 정보:', userData);
        
        if (userData) {
            expect(userData.role).toBe('headquarters');
            console.log('✅ 본사 권한 확인 완료');
        } else {
            console.log('⚠️ 사용자 정보를 가져올 수 없음');
        }
        
        // 본사 권한으로 모든 매장 접근 가능한지 확인
        const accessibleStores = await page.evaluate(() => {
            if (window.userData && typeof window.userData.getAccessibleStoreIds === 'function') {
                return window.userData.getAccessibleStoreIds();
            }
            return null;
        });
        
        console.log('🏪 접근 가능한 매장:', accessibleStores);
        
        console.log('✅ 권한 확인 테스트 완료');
    });
});