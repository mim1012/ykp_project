/**
 * 🔬 Complete Branch Creation and Login E2E Test
 * 
 * This test covers the full workflow:
 * 1. Headquarters login
 * 2. Navigate to branch management  
 * 3. Create new branch with account
 * 4. Verify branch account creation
 * 5. Logout and login as branch user
 * 6. Verify branch dashboard access
 */

import { test, expect } from '@playwright/test';

// Test configuration
const BASE_URL = 'https://ykpproject-production.up.railway.app';
const HQ_EMAIL = 'hq@ykp.com';
const HQ_PASSWORD = '123456';
const TEST_BRANCH_CODE = 'E2E001';
const TEST_BRANCH_NAME = 'E2E테스트지점';
const TEST_BRANCH_EMAIL = 'branch_e2e001@ykp.com';
const TEST_BRANCH_PASSWORD = '123456';

test.describe('🏢 Branch Creation and Login E2E Test', () => {
  
  test.beforeEach(async ({ page }) => {
    // Enable slow motion for better debugging
    await page.setViewportSize({ width: 1280, height: 720 });
    
    // Intercept network requests to monitor API calls
    page.on('response', response => {
      if (response.url().includes('/api/') || response.url().includes('/test-api/')) {
        console.log(`📡 API Response: ${response.status()} ${response.url()}`);
      }
    });
    
    // Intercept console errors
    page.on('console', msg => {
      if (msg.type() === 'error') {
        console.log(`🚨 Console Error: ${msg.text()}`);
      }
    });
  });

  test('🔄 Complete branch creation and login workflow', async ({ page }) => {
    console.log('🚀 Starting complete branch creation and login E2E test...');
    
    // ================================
    // PHASE 1: HEADQUARTERS LOGIN
    // ================================
    console.log('📝 Phase 1: Logging in as headquarters...');
    
    await page.goto(BASE_URL);
    
    // Wait for login page to load
    await page.waitForSelector('input[name="email"]', { timeout: 10000 });
    
    // Fill login form
    await page.fill('input[name="email"]', HQ_EMAIL);
    await page.fill('input[name="password"]', HQ_PASSWORD);
    
    // Click login button and wait for navigation
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'networkidle' }),
      page.click('button[type="submit"]')
    ]);
    
    // Verify headquarters dashboard loaded
    await expect(page).toHaveURL(new RegExp(`${BASE_URL}/dashboard`));
    console.log('✅ Headquarters login successful');
    
    // ================================ 
    // PHASE 2: LOCATE BRANCH CREATION BUTTON
    // ================================
    console.log('📝 Phase 2: Looking for branch creation button...');
    
    // Wait for dashboard to fully load
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(3000); // Wait for dynamic content to load
    
    // Look for the "새 지사 추가" (Add New Branch) button directly on dashboard
    const addBranchButtonFound = await page.isVisible(':has-text("새 지사 추가")');
    if (addBranchButtonFound) {
      console.log('✅ Found "새 지사 추가" button on dashboard');
    } else {
      console.log('⚠️ "새 지사 추가" button not visible, checking page content...');
      await page.screenshot({ 
        path: `tests/playwright/dashboard-debug-${Date.now()}.png`,
        fullPage: true 
      });
    }
    
    // ================================
    // PHASE 3: CREATE NEW BRANCH
    // ================================
    console.log('📝 Phase 3: Creating new branch...');
    
    // Clean up any existing test branch first
    console.log('🧹 Cleaning up existing test branch...');
    await page.evaluate(async (branchCode) => {
      try {
        const response = await fetch(`/api/branches?search=${branchCode}`);
        const branches = await response.json();
        if (branches.data && branches.data.length > 0) {
          for (const branch of branches.data) {
            if (branch.code === branchCode) {
              await fetch(`/api/branches/${branch.id}`, { method: 'DELETE' });
              console.log(`🗑️ Deleted existing branch: ${branchCode}`);
            }
          }
        }
      } catch (error) {
        console.log('ℹ️ No existing branch to clean up');
      }
    }, TEST_BRANCH_CODE);
    
    // Click the "새 지사 추가" button that we can see on the dashboard
    console.log('🖱️ Clicking "새 지사 추가" button...');
    await page.click(':has-text("새 지사 추가")');
    console.log('✅ Clicked add branch button');
    
    // Wait for any modal or navigation to complete
    await page.waitForTimeout(2000);
    
    // Wait for form to appear
    await page.waitForSelector('input', { timeout: 5000 });
    await page.waitForTimeout(1000); // Wait for form to fully load
    
    // Fill branch creation form
    console.log('📝 Filling branch creation form...');
    
    // Find and fill branch code field
    const codeInputSelectors = [
      'input[name="code"]',
      'input[placeholder*="코드"]',
      'input[placeholder*="Code"]'
    ];
    
    for (const selector of codeInputSelectors) {
      if (await page.isVisible(selector)) {
        await page.fill(selector, TEST_BRANCH_CODE);
        console.log(`✅ Filled branch code: ${selector}`);
        break;
      }
    }
    
    // Find and fill branch name field  
    const nameInputSelectors = [
      'input[name="name"]',
      'input[placeholder*="이름"]',
      'input[placeholder*="Name"]'
    ];
    
    for (const selector of nameInputSelectors) {
      if (await page.isVisible(selector)) {
        await page.fill(selector, TEST_BRANCH_NAME);
        console.log(`✅ Filled branch name: ${selector}`);
        break;
      }
    }
    
    // Take screenshot before submission
    await page.screenshot({ 
      path: `tests/playwright/branch-creation-form-${Date.now()}.png`,
      fullPage: true 
    });
    
    // Submit the form
    const submitSelectors = [
      'button[type="submit"]',
      'button:has-text("저장")',
      'button:has-text("Save")',
      'button:has-text("추가")',
      '.btn-primary:has-text("저장")'
    ];
    
    let formSubmitted = false;
    for (const selector of submitSelectors) {
      const button = await page.locator(selector).first();
      if (await button.isVisible()) {
        console.log('📤 Submitting branch creation form...');
        
        // Wait for the API response
        const responsePromise = page.waitForResponse(response => 
          response.url().includes('/api/branches') || 
          response.url().includes('/test-api/branches/add')
        );
        
        await button.click();
        
        try {
          const response = await responsePromise;
          console.log(`📡 Form submission response: ${response.status()}`);
          
          if (response.ok()) {
            const responseBody = await response.json();
            console.log('✅ Branch creation response:', responseBody);
          } else {
            const errorBody = await response.text();
            console.log('❌ Branch creation failed:', errorBody);
          }
        } catch (error) {
          console.log('⚠️ Could not capture API response:', error.message);
        }
        
        formSubmitted = true;
        break;
      }
    }
    
    if (!formSubmitted) {
      throw new Error('Could not find or click submit button');
    }
    
    // Wait for form submission to complete
    await page.waitForTimeout(3000);
    
    // ================================
    // PHASE 4: VERIFY BRANCH CREATION
    // ================================
    console.log('📝 Phase 4: Verifying branch and account creation...');
    
    // Check if success message appeared
    const successIndicators = [
      '.alert-success',
      '.toast-success', 
      ':has-text("성공")',
      ':has-text("생성")',
      ':has-text("추가")'
    ];
    
    let successFound = false;
    for (const indicator of successIndicators) {
      if (await page.isVisible(indicator)) {
        console.log(`✅ Success indicator found: ${indicator}`);
        successFound = true;
        break;
      }
    }
    
    // Verify branch appears in the list
    await page.waitForTimeout(2000); // Wait for list to refresh
    
    // Look for the new branch in the table/list
    const branchInList = await page.isVisible(`:has-text("${TEST_BRANCH_CODE}")`);
    if (branchInList) {
      console.log('✅ New branch appears in the list');
    } else {
      console.log('⚠️ New branch not visible in list, but may have been created');
    }
    
    // Take screenshot after creation
    await page.screenshot({ 
      path: `tests/playwright/after-branch-creation-${Date.now()}.png`,
      fullPage: true 
    });
    
    // ================================
    // PHASE 5: LOGOUT FROM HEADQUARTERS
    // ================================
    console.log('📝 Phase 5: Logging out from headquarters...');
    
    const logoutSelectors = [
      'button:has-text("로그아웃")',
      'button:has-text("Logout")', 
      'a:has-text("로그아웃")',
      'a:has-text("Logout")',
      '[data-testid="logout"]'
    ];
    
    let loggedOut = false;
    for (const selector of logoutSelectors) {
      if (await page.isVisible(selector)) {
        await page.click(selector);
        loggedOut = true;
        console.log('✅ Clicked logout button');
        break;
      }
    }
    
    // If logout button not found, go directly to logout URL
    if (!loggedOut) {
      await page.goto(`${BASE_URL}/logout`);
      console.log('✅ Logout via direct URL');
    }
    
    // Wait for redirect to login page
    await page.waitForURL(new RegExp(`${BASE_URL}/(login|$)`));
    console.log('✅ Redirected to login page');
    
    // ================================
    // PHASE 6: LOGIN AS BRANCH USER
    // ================================
    console.log('📝 Phase 6: Attempting branch user login...');
    
    // Wait for login form
    await page.waitForSelector('input[name="email"]', { timeout: 10000 });
    
    // Clear and fill branch user credentials
    await page.fill('input[name="email"]', '');
    await page.fill('input[name="password"]', '');
    
    await page.fill('input[name="email"]', TEST_BRANCH_EMAIL);
    await page.fill('input[name="password"]', TEST_BRANCH_PASSWORD);
    
    console.log(`🔐 Attempting login with: ${TEST_BRANCH_EMAIL}`);
    
    // Submit login form
    const loginResponsePromise = page.waitForResponse(response => 
      response.url().includes('/login') && response.request().method() === 'POST'
    );
    
    await page.click('button[type="submit"]');
    
    try {
      const loginResponse = await loginResponsePromise;
      console.log(`📡 Branch login response: ${loginResponse.status()}`);
      
      if (loginResponse.ok()) {
        console.log('✅ Branch login successful');
        
        // Wait for dashboard redirect
        await page.waitForURL(new RegExp(`${BASE_URL}/dashboard`), { timeout: 10000 });
        console.log('✅ Branch dashboard loaded');
        
        // Take screenshot of branch dashboard
        await page.screenshot({ 
          path: `tests/playwright/branch-dashboard-${Date.now()}.png`,
          fullPage: true 
        });
        
        // Verify branch-specific content
        const pageContent = await page.content();
        const hasBranchContent = pageContent.includes(TEST_BRANCH_NAME) || 
                               pageContent.includes('지사') || 
                               pageContent.includes('Branch');
        
        if (hasBranchContent) {
          console.log('✅ Branch-specific content verified');
        }
        
      } else {
        const errorText = await loginResponse.text();
        console.log('❌ Branch login failed:', errorText);
        
        // Take screenshot of error
        await page.screenshot({ 
          path: `tests/playwright/branch-login-error-${Date.now()}.png`,
          fullPage: true 
        });
        
        // Check for error messages on page
        const errorMessage = await page.locator('.alert-danger, .error, :has-text("오류"), :has-text("Error")').first();
        if (await errorMessage.isVisible()) {
          const errorText = await errorMessage.textContent();
          console.log('🚨 Login error message:', errorText);
        }
      }
    } catch (error) {
      console.log('⚠️ Could not capture login response:', error.message);
      
      // Check if we were redirected anyway
      await page.waitForTimeout(3000);
      const currentUrl = page.url();
      
      if (currentUrl.includes('/dashboard')) {
        console.log('✅ Login appears successful despite error capturing response');
      } else {
        console.log('❌ Login failed - still on login page');
        
        // Take screenshot for debugging
        await page.screenshot({ 
          path: `tests/playwright/branch-login-failed-${Date.now()}.png`,
          fullPage: true 
        });
      }
    }
    
    // ================================
    // PHASE 7: VERIFY BRANCH ACCESS
    // ================================
    console.log('📝 Phase 7: Verifying branch dashboard access...');
    
    // Verify we're on dashboard
    await expect(page).toHaveURL(new RegExp(`${BASE_URL}/dashboard`));
    
    // Verify branch-specific elements
    await page.waitForSelector('body', { timeout: 5000 });
    
    // Check for branch-specific navigation or content
    const dashboardElements = [
      'nav',
      '.dashboard',
      'h1, h2, h3',
      ':has-text("대시보드")',
      ':has-text("Dashboard")'
    ];
    
    for (const selector of dashboardElements) {
      if (await page.isVisible(selector)) {
        console.log(`✅ Dashboard element found: ${selector}`);
        break;
      }
    }
    
    // Final screenshot
    await page.screenshot({ 
      path: `tests/playwright/E2E-final-branch-dashboard-${Date.now()}.png`,
      fullPage: true 
    });
    
    console.log('🎉 Complete E2E test finished successfully!');
  });
  
  test.afterEach(async ({ page }) => {
    // Clean up test data
    console.log('🧹 Cleaning up test data...');
    
    try {
      await page.evaluate(async (branchCode) => {
        // Clean up test branch and user
        const response = await fetch(`/api/branches?search=${branchCode}`);
        const branches = await response.json();
        
        if (branches.data && branches.data.length > 0) {
          for (const branch of branches.data) {
            if (branch.code === branchCode) {
              await fetch(`/api/branches/${branch.id}`, { method: 'DELETE' });
              console.log(`🗑️ Cleaned up test branch: ${branchCode}`);
            }
          }
        }
      }, TEST_BRANCH_CODE);
    } catch (error) {
      console.log('ℹ️ Cleanup completed or no cleanup needed');
    }
  });
});