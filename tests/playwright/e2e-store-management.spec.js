/**
 * E2E Test: Store Management Page
 *
 * Tests:
 * - Pagination (30 stores per page)
 * - Search by store name
 * - Edit store owner name (all accounts)
 * - Edit branch assignment (headquarters only)
 * - RBAC validation (branch accounts cannot change branch)
 */

import { test, expect } from '@playwright/test';

// Test credentials
const CREDENTIALS = {
    headquarters: { email: 'admin@ykp.com', password: 'password' },
    branch: { email: 'branch@ykp.com', password: 'password' },
    store: { email: 'store@ykp.com', password: 'password' },
};

/**
 * Helper: Login to the application
 */
async function login(page, email, password) {
    await page.goto('http://localhost:8000/login');
    await page.fill('input[name="email"]', email);
    await page.fill('input[name="password"]', password);
    await page.click('button[type="submit"]');
    await page.waitForURL('**/dashboard', { timeout: 10000 });
}

/**
 * Helper: Navigate to store management page
 */
async function navigateToStoreManagement(page) {
    await page.goto('http://localhost:8000/management/stores');
    await page.waitForLoadState('networkidle');

    // Wait for stores to load
    await page.waitForSelector('#stores-grid', { timeout: 10000 });
}

test.describe('Store Management - Pagination', () => {
    test.beforeEach(async ({ page }) => {
        await login(page, CREDENTIALS.headquarters.email, CREDENTIALS.headquarters.password);
        await navigateToStoreManagement(page);
    });

    test('should load stores automatically on page load', async ({ page }) => {
        // Verify stores grid exists
        const storesGrid = await page.locator('#stores-grid');
        await expect(storesGrid).toBeVisible();

        // Verify at least one store card is visible
        const storeCards = await page.locator('.store-card');
        const count = await storeCards.count();
        expect(count).toBeGreaterThan(0);

        console.log(`✅ Loaded ${count} store cards on first page`);
    });

    test('should display pagination controls', async ({ page }) => {
        // Verify pagination container exists
        const paginationContainer = await page.locator('#pagination-container');
        await expect(paginationContainer).toBeVisible();

        // Verify "Previous" and "Next" buttons exist
        const prevButton = await page.locator('text=이전');
        const nextButton = await page.locator('text=다음');

        await expect(prevButton).toBeVisible();
        await expect(nextButton).toBeVisible();
    });

    test('should navigate to next page when clicking Next button', async ({ page }) => {
        // Get first store name on page 1
        const firstStoreOnPage1 = await page.locator('.store-card').first().locator('h3').textContent();

        // Click Next button
        await page.click('text=다음');
        await page.waitForTimeout(1000); // Wait for API response

        // Get first store name on page 2
        const firstStoreOnPage2 = await page.locator('.store-card').first().locator('h3').textContent();

        // Verify stores changed
        expect(firstStoreOnPage1).not.toBe(firstStoreOnPage2);
        console.log(`✅ Page 1 first store: ${firstStoreOnPage1}`);
        console.log(`✅ Page 2 first store: ${firstStoreOnPage2}`);
    });

    test('should show total store count', async ({ page }) => {
        // Look for text like "총 300개 매장"
        const paginationText = await page.locator('#pagination-container').textContent();
        expect(paginationText).toContain('총');
        expect(paginationText).toContain('매장');

        console.log(`✅ Pagination info: ${paginationText}`);
    });
});

test.describe('Store Management - Search', () => {
    test.beforeEach(async ({ page }) => {
        await login(page, CREDENTIALS.headquarters.email, CREDENTIALS.headquarters.password);
        await navigateToStoreManagement(page);
    });

    test('should have search input visible', async ({ page }) => {
        const searchInput = await page.locator('#store-search-input');
        await expect(searchInput).toBeVisible();
        await expect(searchInput).toHaveAttribute('placeholder', /매장명으로 검색/);
    });

    test('should filter stores by name when typing in search box', async ({ page }) => {
        // Get initial store count
        const initialCount = await page.locator('.store-card').count();
        console.log(`✅ Initial store count: ${initialCount}`);

        // Type a search query
        const searchInput = await page.locator('#store-search-input');
        await searchInput.fill('세종');

        // Wait for debounce (300ms) + API response
        await page.waitForTimeout(500);

        // Get filtered store count
        const filteredCount = await page.locator('.store-card').count();
        console.log(`✅ Filtered store count (세종): ${filteredCount}`);

        // Verify filtered results
        expect(filteredCount).toBeLessThanOrEqual(initialCount);

        // Verify all visible stores contain search term
        const storeNames = await page.locator('.store-card h3').allTextContents();
        storeNames.forEach(name => {
            expect(name).toContain('세종');
        });
    });

    test('should reset pagination to page 1 when searching', async ({ page }) => {
        // Go to page 2
        await page.click('text=다음');
        await page.waitForTimeout(500);

        // Type search query
        const searchInput = await page.locator('#store-search-input');
        await searchInput.fill('대전');
        await page.waitForTimeout(500);

        // Verify pagination shows page 1 (active button)
        const activePage = await page.locator('.pagination-button.active');
        const pageNumber = await activePage.textContent();
        expect(pageNumber.trim()).toBe('1');
    });

    test('should show "no results" message for non-existent store', async ({ page }) => {
        // Type non-existent store name
        const searchInput = await page.locator('#store-search-input');
        await searchInput.fill('존재하지않는매장명12345');
        await page.waitForTimeout(500);

        // Verify "no results" message or empty container
        const storeCount = await page.locator('.store-card').count();
        expect(storeCount).toBe(0);

        // Look for "검색 결과가 없습니다" message
        const noResultsMsg = await page.locator('text=검색 결과가 없습니다');
        await expect(noResultsMsg).toBeVisible();
    });

    test('should clear search and show all stores', async ({ page }) => {
        // Type search query
        const searchInput = await page.locator('#store-search-input');
        await searchInput.fill('세종');
        await page.waitForTimeout(500);

        const filteredCount = await page.locator('.store-card').count();

        // Clear search
        await searchInput.fill('');
        await page.waitForTimeout(500);

        const allStoresCount = await page.locator('.store-card').count();

        expect(allStoresCount).toBeGreaterThan(filteredCount);
        console.log(`✅ Filtered: ${filteredCount}, All: ${allStoresCount}`);
    });
});

test.describe('Store Management - Edit Store (Headquarters)', () => {
    test.beforeEach(async ({ page }) => {
        await login(page, CREDENTIALS.headquarters.email, CREDENTIALS.headquarters.password);
        await navigateToStoreManagement(page);
    });

    test('should open edit modal when clicking edit button', async ({ page }) => {
        // Click first store's edit button
        const firstEditButton = await page.locator('.store-card').first().locator('button:has-text("✏️")');
        await firstEditButton.click();

        // Verify modal is visible
        const modal = await page.locator('#edit-store-modal');
        await expect(modal).toBeVisible();

        // Verify modal title
        const modalTitle = await page.locator('#edit-store-modal h2');
        await expect(modalTitle).toHaveText(/매장 정보 수정/);
    });

    test('should pre-fill modal with current store data', async ({ page }) => {
        // Get first store's data
        const firstStoreCard = await page.locator('.store-card').first();
        const storeName = await firstStoreCard.locator('h3').textContent();
        const ownerName = await firstStoreCard.locator('text=점주:').locator('..').textContent();

        console.log(`✅ Store: ${storeName}, Owner: ${ownerName}`);

        // Click edit button
        await firstStoreCard.locator('button:has-text("✏️")').click();
        await page.waitForTimeout(300);

        // Verify modal inputs are pre-filled
        const storeNameInput = await page.locator('#edit-store-name');
        const ownerNameInput = await page.locator('#edit-owner-name');

        await expect(storeNameInput).toHaveValue(storeName.trim());
        // Owner name input should be filled (if exists)
        const currentOwner = await ownerNameInput.inputValue();
        console.log(`✅ Current owner in modal: ${currentOwner}`);
    });

    test('should allow editing owner name', async ({ page }) => {
        // Click first edit button
        await page.locator('.store-card').first().locator('button:has-text("✏️")').click();
        await page.waitForTimeout(300);

        // Change owner name
        const ownerNameInput = await page.locator('#edit-owner-name');
        const newOwnerName = '테스트점주_' + Date.now();
        await ownerNameInput.fill(newOwnerName);

        // Click Save button
        await page.click('button:has-text("💾 저장")');
        await page.waitForTimeout(1000); // Wait for API response

        // Verify success message (toast or alert)
        const successMsg = await page.locator('text=수정되었습니다').or(page.locator('text=성공'));
        await expect(successMsg).toBeVisible({ timeout: 5000 });

        console.log(`✅ Owner name changed to: ${newOwnerName}`);
    });

    test('should show branch dropdown for headquarters account', async ({ page }) => {
        // Click edit button
        await page.locator('.store-card').first().locator('button:has-text("✏️")').click();
        await page.waitForTimeout(300);

        // Verify branch dropdown is visible
        const branchSelect = await page.locator('#edit-branch-id');
        await expect(branchSelect).toBeVisible();

        // Verify dropdown has options
        const options = await branchSelect.locator('option').count();
        expect(options).toBeGreaterThan(1); // At least 2 options (placeholder + branches)

        console.log(`✅ Branch dropdown has ${options} options`);
    });

    test('should allow changing branch assignment (HQ only)', async ({ page }) => {
        // Click edit button
        await page.locator('.store-card').first().locator('button:has-text("✏️")').click();
        await page.waitForTimeout(300);

        // Get current branch
        const branchSelect = await page.locator('#edit-branch-id');
        const currentBranch = await branchSelect.inputValue();
        console.log(`✅ Current branch ID: ${currentBranch}`);

        // Select a different branch (option index 2)
        const options = await branchSelect.locator('option').all();
        if (options.length > 2) {
            await branchSelect.selectOption({ index: 2 });

            const newBranch = await branchSelect.inputValue();
            console.log(`✅ New branch ID: ${newBranch}`);

            // Save changes
            await page.click('button:has-text("💾 저장")');
            await page.waitForTimeout(1000);

            // Verify success
            const successMsg = await page.locator('text=수정되었습니다').or(page.locator('text=성공'));
            await expect(successMsg).toBeVisible({ timeout: 5000 });
        }
    });
});

test.describe('Store Management - Edit Store (Branch Account)', () => {
    test.beforeEach(async ({ page }) => {
        await login(page, CREDENTIALS.branch.email, CREDENTIALS.branch.password);
        await navigateToStoreManagement(page);
    });

    test('should NOT show branch dropdown for branch account', async ({ page }) => {
        // Wait for stores to load
        await page.waitForSelector('.store-card', { timeout: 5000 });

        // Click first edit button
        await page.locator('.store-card').first().locator('button:has-text("✏️")').click();
        await page.waitForTimeout(300);

        // Verify branch field is read-only or hidden
        const branchSelect = await page.locator('#edit-branch-id');
        const isDisabled = await branchSelect.isDisabled().catch(() => true);
        const isHidden = await branchSelect.isHidden().catch(() => true);

        expect(isDisabled || isHidden).toBeTruthy();
        console.log(`✅ Branch dropdown is disabled/hidden for branch account`);

        // Verify helper text exists
        const helperText = await page.locator('text=본사 계정만 변경할 수 있습니다');
        await expect(helperText).toBeVisible();
    });

    test('should allow editing owner name even as branch account', async ({ page }) => {
        // Wait for stores
        await page.waitForSelector('.store-card', { timeout: 5000 });

        // Click edit button
        await page.locator('.store-card').first().locator('button:has-text("✏️")').click();
        await page.waitForTimeout(300);

        // Change owner name
        const ownerNameInput = await page.locator('#edit-owner-name');
        const newOwnerName = '지사수정_' + Date.now();
        await ownerNameInput.fill(newOwnerName);

        // Save
        await page.click('button:has-text("💾 저장")');
        await page.waitForTimeout(1000);

        // Verify success
        const successMsg = await page.locator('text=수정되었습니다').or(page.locator('text=성공'));
        await expect(successMsg).toBeVisible({ timeout: 5000 });

        console.log(`✅ Branch account successfully edited owner name: ${newOwnerName}`);
    });
});

test.describe('Store Management - Modal Functionality', () => {
    test.beforeEach(async ({ page }) => {
        await login(page, CREDENTIALS.headquarters.email, CREDENTIALS.headquarters.password);
        await navigateToStoreManagement(page);
    });

    test('should close modal when clicking Cancel button', async ({ page }) => {
        // Open modal
        await page.locator('.store-card').first().locator('button:has-text("✏️")').click();
        await page.waitForTimeout(300);

        // Verify modal is open
        const modal = await page.locator('#edit-store-modal');
        await expect(modal).toBeVisible();

        // Click Cancel
        await page.click('button:has-text("취소")');
        await page.waitForTimeout(300);

        // Verify modal is closed
        await expect(modal).toBeHidden();
    });

    test('should require owner name field', async ({ page }) => {
        // Open modal
        await page.locator('.store-card').first().locator('button:has-text("✏️")').click();
        await page.waitForTimeout(300);

        // Clear owner name
        const ownerNameInput = await page.locator('#edit-owner-name');
        await ownerNameInput.fill('');

        // Try to save
        await page.click('button:has-text("💾 저장")');
        await page.waitForTimeout(500);

        // Verify validation error or field highlight
        // (Implementation may vary - check for required attribute or error message)
        const isRequired = await ownerNameInput.getAttribute('required');
        expect(isRequired).not.toBeNull();
    });

    test('should refresh store list after successful edit', async ({ page }) => {
        // Get first store's owner name before edit
        const firstStoreCard = await page.locator('.store-card').first();
        const originalOwner = await firstStoreCard.locator('text=점주:').locator('..').textContent();

        // Open modal
        await firstStoreCard.locator('button:has-text("✏️")').click();
        await page.waitForTimeout(300);

        // Change owner name
        const newOwnerName = 'E2E테스트_' + Date.now();
        await page.locator('#edit-owner-name').fill(newOwnerName);

        // Save
        await page.click('button:has-text("💾 저장")');
        await page.waitForTimeout(1500); // Wait for API + refresh

        // Verify store card updated with new owner name
        const updatedOwner = await page.locator('.store-card').first().locator('text=점주:').locator('..').textContent();
        expect(updatedOwner).toContain(newOwnerName);

        console.log(`✅ Owner updated from "${originalOwner}" to "${updatedOwner}"`);
    });
});

test.describe('Store Management - RBAC Validation', () => {
    test('headquarters can access all stores', async ({ page }) => {
        await login(page, CREDENTIALS.headquarters.email, CREDENTIALS.headquarters.password);
        await navigateToStoreManagement(page);

        const storeCount = await page.locator('.store-card').count();
        expect(storeCount).toBeGreaterThan(0);

        console.log(`✅ Headquarters sees ${storeCount} stores`);
    });

    test('branch account can only see their branch stores', async ({ page }) => {
        await login(page, CREDENTIALS.branch.email, CREDENTIALS.branch.password);
        await navigateToStoreManagement(page);

        // Wait for stores
        await page.waitForSelector('.store-card', { timeout: 5000 }).catch(() => {});

        const storeCount = await page.locator('.store-card').count();
        console.log(`✅ Branch account sees ${storeCount} stores`);

        // Verify all stores belong to same branch
        const branchNames = await page.locator('.store-card').locator('text=지사:').locator('..').allTextContents();
        const uniqueBranches = [...new Set(branchNames)];

        expect(uniqueBranches.length).toBeLessThanOrEqual(1); // Should only see one branch
        console.log(`✅ Branch account sees stores from: ${uniqueBranches.join(', ')}`);
    });

    test('store account should not access store management page', async ({ page }) => {
        await login(page, CREDENTIALS.store.email, CREDENTIALS.store.password);

        // Try to navigate to store management
        await page.goto('http://localhost:8000/management/stores');
        await page.waitForLoadState('networkidle');

        // Should be redirected or see access denied
        const url = page.url();
        const isAccessDenied = url.includes('/dashboard') || url.includes('/403');

        expect(isAccessDenied).toBeTruthy();
        console.log(`✅ Store account redirected to: ${url}`);
    });
});
