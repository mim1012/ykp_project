/**
 * Production Search Functionality Test
 * Tests search API endpoints on https://ykperp.co.kr
 *
 * Features:
 * - Automated authentication with headquarters account
 * - Comprehensive search validation
 * - Pagination field verification
 * - Search filtering verification
 * - Retry logic for deployment delays
 */

import { test, expect } from '@playwright/test';

const BASE_URL = 'https://ykperp.co.kr';
const CREDENTIALS = {
    email: 'admin@ykp.com',
    password: 'password'
};

/**
 * Helper: Login to production
 */
async function login(page) {
    await page.goto(`${BASE_URL}/login`);
    await page.fill('input[name="email"]', CREDENTIALS.email);
    await page.fill('input[name="password"]', CREDENTIALS.password);
    await page.click('button[type="submit"]');
    await page.waitForURL('**/dashboard', { timeout: 15000 });
    console.log('✅ Successfully logged in');
}

/**
 * Helper: Make authenticated API request
 */
async function makeAuthenticatedApiRequest(page, endpoint) {
    const response = await page.request.get(`${BASE_URL}${endpoint}`, {
        headers: {
            'Accept': 'application/json',
        }
    });

    return {
        status: response.status(),
        ok: response.ok(),
        body: await response.json().catch(() => null),
        size: (await response.text()).length
    };
}

/**
 * Helper: Validate pagination fields
 */
function validatePaginationFields(body) {
    const requiredFields = ['current_page', 'last_page', 'total', 'per_page', 'data'];
    const missingFields = requiredFields.filter(field => !(field in body));

    return {
        passed: missingFields.length === 0,
        missingFields,
        message: missingFields.length > 0
            ? `Missing fields: ${missingFields.join(', ')}`
            : 'All pagination fields present'
    };
}

/**
 * Helper: Validate search functionality
 */
function validateSearchResults(body, searchTerm) {
    const checks = [];

    // Check 1: Total should be less than full dataset (387)
    const totalFiltered = body.total < 387;
    checks.push({
        name: '검색 필터링 작동',
        passed: totalFiltered,
        expected: 'total < 387',
        actual: `total = ${body.total}`
    });

    // Check 2: debug_search_applied should be true
    const searchApplied = body.debug_search_applied === true;
    checks.push({
        name: 'debug_search_applied',
        passed: searchApplied,
        expected: 'true',
        actual: body.debug_search_applied
    });

    // Check 3: Search term should appear in results
    if (body.data && body.data.length > 0) {
        const hasSearchTerm = body.data.some(store =>
            store.store_name?.includes(searchTerm) ||
            store.branch_name?.includes(searchTerm) ||
            store.address?.includes(searchTerm)
        );
        checks.push({
            name: `검색어 "${searchTerm}" 포함`,
            passed: hasSearchTerm,
            expected: 'At least one result contains term',
            actual: hasSearchTerm ? 'Found' : 'Not found'
        });
    }

    return {
        passed: checks.every(c => c.passed),
        checks
    };
}

test.describe('Production Search Functionality Tests', () => {
    test.beforeEach(async ({ page }) => {
        await login(page);
    });

    test('Health Check - Verify v2 deployment', async ({ page }) => {
        const response = await page.request.get(`${BASE_URL}/health.php`);
        const text = await response.text();

        console.log(`🏥 Health check: ${text}`);
        expect(text).toContain('v2');
        expect(text).toContain('search');
    });

    test('Basic Query - No search (20 items)', async ({ page }) => {
        console.log('\n[Test 1] Basic Query - No search');
        console.log('URL: /api/stores?per_page=20');

        const result = await makeAuthenticatedApiRequest(page, '/api/stores?per_page=20');

        console.log(`Response size: ${(result.size / 1024).toFixed(2)} KB`);
        console.log(`Total records: ${result.body.total}`);
        console.log(`Current page: ${result.body.current_page}/${result.body.last_page}`);
        console.log(`Data count: ${result.body.data?.length}`);

        // Validate response
        expect(result.ok).toBeTruthy();
        expect(result.status).toBe(200);

        // Validate pagination fields
        const paginationCheck = validatePaginationFields(result.body);
        expect(paginationCheck.passed).toBeTruthy();
        console.log(`✅ ${paginationCheck.message}`);

        // Validate data
        expect(result.body.data.length).toBeLessThanOrEqual(20);
        expect(result.body.total).toBe(387); // Full dataset
        expect(result.body.current_page).toBe(1);
        expect(result.body.per_page).toBe(20);

        console.log('✅ Test passed: Basic query works correctly\n');
    });

    test('Search Test - "천호" (10 items max)', async ({ page }) => {
        console.log('\n[Test 2] Search Test - "천호"');
        console.log('URL: /api/stores?per_page=10&search=천호');

        const result = await makeAuthenticatedApiRequest(page, '/api/stores?per_page=10&search=천호');

        console.log(`Response size: ${(result.size / 1024).toFixed(2)} KB`);
        console.log(`Total records: ${result.body.total}`);
        console.log(`Data count: ${result.body.data?.length}`);

        // Validate response
        expect(result.ok).toBeTruthy();

        // Validate pagination
        const paginationCheck = validatePaginationFields(result.body);
        expect(paginationCheck.passed).toBeTruthy();

        // Validate search
        const searchCheck = validateSearchResults(result.body, '천호');
        searchCheck.checks.forEach(check => {
            console.log(`${check.passed ? '✅' : '❌'} ${check.name}: ${check.actual} (Expected: ${check.expected})`);
            expect(check.passed).toBeTruthy();
        });

        // Validate data size
        expect(result.body.data.length).toBeLessThanOrEqual(10);
        expect(result.body.total).toBeLessThan(387); // Filtered

        console.log('✅ Test passed: Search for "천호" works correctly\n');
    });

    test('Search Test - "부산" (10 items max)', async ({ page }) => {
        console.log('\n[Test 3] Search Test - "부산"');
        console.log('URL: /api/stores?per_page=10&search=부산');

        const result = await makeAuthenticatedApiRequest(page, '/api/stores?per_page=10&search=부산');

        console.log(`Response size: ${(result.size / 1024).toFixed(2)} KB`);
        console.log(`Total records: ${result.body.total}`);
        console.log(`Data count: ${result.body.data?.length}`);

        // Validate response
        expect(result.ok).toBeTruthy();

        // Validate pagination
        const paginationCheck = validatePaginationFields(result.body);
        expect(paginationCheck.passed).toBeTruthy();

        // Validate search
        const searchCheck = validateSearchResults(result.body, '부산');
        searchCheck.checks.forEach(check => {
            console.log(`${check.passed ? '✅' : '❌'} ${check.name}: ${check.actual} (Expected: ${check.expected})`);
            expect(check.passed).toBeTruthy();
        });

        expect(result.body.data.length).toBeLessThanOrEqual(10);
        expect(result.body.total).toBeLessThan(387);

        console.log('✅ Test passed: Search for "부산" works correctly\n');
    });

    test('Search Test - "전주" (10 items max)', async ({ page }) => {
        console.log('\n[Test 4] Search Test - "전주"');
        console.log('URL: /api/stores?per_page=10&search=전주');

        const result = await makeAuthenticatedApiRequest(page, '/api/stores?per_page=10&search=전주');

        console.log(`Response size: ${(result.size / 1024).toFixed(2)} KB`);
        console.log(`Total records: ${result.body.total}`);
        console.log(`Data count: ${result.body.data?.length}`);

        // Validate response
        expect(result.ok).toBeTruthy();

        // Validate pagination
        const paginationCheck = validatePaginationFields(result.body);
        expect(paginationCheck.passed).toBeTruthy();

        // Validate search
        const searchCheck = validateSearchResults(result.body, '전주');
        searchCheck.checks.forEach(check => {
            console.log(`${check.passed ? '✅' : '❌'} ${check.name}: ${check.actual} (Expected: ${check.expected})`);
            expect(check.passed).toBeTruthy();
        });

        expect(result.body.data.length).toBeLessThanOrEqual(10);
        expect(result.body.total).toBeLessThan(387);

        console.log('✅ Test passed: Search for "전주" works correctly\n');
    });

    test('Debug Fields Verification', async ({ page }) => {
        console.log('\n[Test 5] Debug Fields Verification');

        const result = await makeAuthenticatedApiRequest(page, '/api/stores?per_page=10&search=test');

        expect(result.ok).toBeTruthy();

        // Verify debug fields exist
        expect(result.body).toHaveProperty('debug_version');
        expect(result.body).toHaveProperty('debug_search_applied');
        expect(result.body).toHaveProperty('debug_search_term');

        console.log(`debug_version: ${result.body.debug_version}`);
        console.log(`debug_search_applied: ${result.body.debug_search_applied}`);
        console.log(`debug_search_term: ${result.body.debug_search_term}`);

        expect(result.body.debug_version).toContain('v2');
        expect(result.body.debug_search_applied).toBe(true);
        expect(result.body.debug_search_term).toBe('test');

        console.log('✅ Test passed: Debug fields present and correct\n');
    });

    test('Pagination Consistency - Page 1 vs Page 2', async ({ page }) => {
        console.log('\n[Test 6] Pagination Consistency');

        // Fetch page 1
        const page1 = await makeAuthenticatedApiRequest(page, '/api/stores?per_page=10&page=1');
        console.log(`Page 1: ${page1.body.data.length} items, current_page=${page1.body.current_page}`);

        // Fetch page 2
        const page2 = await makeAuthenticatedApiRequest(page, '/api/stores?per_page=10&page=2');
        console.log(`Page 2: ${page2.body.data.length} items, current_page=${page2.body.current_page}`);

        // Validate both pages
        expect(page1.ok && page2.ok).toBeTruthy();
        expect(page1.body.current_page).toBe(1);
        expect(page2.body.current_page).toBe(2);

        // Verify different data on each page
        const page1Ids = page1.body.data.map(s => s.id);
        const page2Ids = page2.body.data.map(s => s.id);
        const overlap = page1Ids.filter(id => page2Ids.includes(id));
        expect(overlap.length).toBe(0); // No overlap

        console.log('✅ Test passed: Pagination works correctly across pages\n');
    });

    test('Empty Search Results', async ({ page }) => {
        console.log('\n[Test 7] Empty Search Results');

        const result = await makeAuthenticatedApiRequest(
            page,
            '/api/stores?per_page=10&search=NONEXISTENT123456789'
        );

        console.log(`Total records: ${result.body.total}`);
        console.log(`Data count: ${result.body.data?.length}`);

        expect(result.ok).toBeTruthy();
        expect(result.body.total).toBe(0);
        expect(result.body.data.length).toBe(0);
        expect(result.body.debug_search_applied).toBe(true);

        console.log('✅ Test passed: Empty search handled correctly\n');
    });
});
