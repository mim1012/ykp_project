/**
 * Production Search Functionality Test
 * Tests search API endpoints on https://ykperp.co.kr
 *
 * Features:
 * - Automated retry (max 10 attempts, 30s interval)
 * - Health check verification (v2 deployment)
 * - Comprehensive test cases
 * - Detailed reporting
 */

const https = require('https');

const BASE_URL = 'https://ykperp.co.kr';
const MAX_RETRIES = 10;
const RETRY_INTERVAL = 30000; // 30 seconds
const TIMEOUT = 10000; // 10 seconds per request

// Test cases configuration
const TEST_CASES = [
  {
    name: '기본 조회 (검색 없음)',
    path: '/api/stores?per_page=20',
    expectations: {
      itemCount: 20,
      totalRecords: 387,
      currentPage: 1,
      lastPage: 20,
      hasSearch: false
    }
  },
  {
    name: '검색 테스트 - "천호"',
    path: '/api/stores?per_page=10&search=천호',
    expectations: {
      maxItemCount: 10,
      totalLessThan: 387,
      hasSearch: true,
      searchTerm: '천호'
    }
  },
  {
    name: '검색 테스트 - "부산"',
    path: '/api/stores?per_page=10&search=부산',
    expectations: {
      maxItemCount: 10,
      totalLessThan: 387,
      hasSearch: true,
      searchTerm: '부산'
    }
  },
  {
    name: '검색 테스트 - "전주"',
    path: '/api/stores?per_page=10&search=전주',
    expectations: {
      maxItemCount: 10,
      totalLessThan: 387,
      hasSearch: true,
      searchTerm: '전주'
    }
  }
];

/**
 * Make HTTPS GET request
 */
function makeRequest(url, timeout = TIMEOUT) {
  return new Promise((resolve, reject) => {
    const timeoutId = setTimeout(() => {
      reject(new Error(`Request timeout after ${timeout}ms`));
    }, timeout);

    https.get(url, (res) => {
      let data = '';

      res.on('data', (chunk) => {
        data += chunk;
      });

      res.on('end', () => {
        clearTimeout(timeoutId);
        try {
          const json = JSON.parse(data);
          resolve({
            statusCode: res.statusCode,
            headers: res.headers,
            body: json,
            size: Buffer.byteLength(data, 'utf8')
          });
        } catch (error) {
          reject(new Error(`JSON parse error: ${error.message}`));
        }
      });
    }).on('error', (error) => {
      clearTimeout(timeoutId);
      reject(error);
    });
  });
}

/**
 * Check deployment version via health.php
 */
async function checkHealthVersion() {
  try {
    const response = await makeRequest(`${BASE_URL}/health.php`);
    return {
      success: true,
      version: response.body.version || 'unknown',
      deployedAt: response.body.deployed_at || 'unknown',
      hasSearch: response.body.version?.includes('v2') || false
    };
  } catch (error) {
    return {
      success: false,
      error: error.message
    };
  }
}

/**
 * Validate test case response
 */
function validateResponse(testCase, response) {
  const results = {
    passed: true,
    checks: []
  };

  const { body } = response;
  const { expectations } = testCase;

  // Check 1: Pagination fields exist
  const paginationFields = ['current_page', 'last_page', 'total', 'per_page'];
  const hasPagination = paginationFields.every(field => field in body);
  results.checks.push({
    name: '페이지네이션 필드 존재',
    passed: hasPagination,
    expected: 'current_page, last_page, total, per_page',
    actual: hasPagination ? 'All present' : 'Missing fields'
  });
  if (!hasPagination) results.passed = false;

  // Check 2: Debug version field
  const hasDebugVersion = 'debug_version' in body;
  results.checks.push({
    name: 'debug_version 필드 존재',
    passed: hasDebugVersion,
    expected: 'v2.0-with-search',
    actual: body.debug_version || 'Missing'
  });
  if (!hasDebugVersion) results.passed = false;

  // Check 3: Data array size
  const dataCount = body.data?.length || 0;
  const expectedMax = expectations.maxItemCount || expectations.itemCount;
  const sizeCheck = dataCount <= expectedMax;
  results.checks.push({
    name: '데이터 배열 크기',
    passed: sizeCheck,
    expected: `<= ${expectedMax}`,
    actual: dataCount
  });
  if (!sizeCheck) results.passed = false;

  // Check 4: Total records (search filtering)
  if (expectations.hasSearch) {
    const totalFiltered = body.total < expectations.totalLessThan;
    results.checks.push({
      name: '검색 필터링 작동',
      passed: totalFiltered,
      expected: `total < ${expectations.totalLessThan}`,
      actual: body.total
    });
    if (!totalFiltered) results.passed = false;

    // Check 5: debug_search_applied
    const searchApplied = body.debug_search_applied === true;
    results.checks.push({
      name: 'debug_search_applied',
      passed: searchApplied,
      expected: 'true',
      actual: body.debug_search_applied
    });
    if (!searchApplied) results.passed = false;

    // Check 6: Search term in results
    if (body.data && body.data.length > 0) {
      const searchTerm = expectations.searchTerm;
      const hasSearchTerm = body.data.some(store =>
        store.store_name?.includes(searchTerm) ||
        store.branch_name?.includes(searchTerm) ||
        store.address?.includes(searchTerm)
      );
      results.checks.push({
        name: `검색어 "${searchTerm}" 포함`,
        passed: hasSearchTerm,
        expected: `At least one result contains "${searchTerm}"`,
        actual: hasSearchTerm ? 'Found' : 'Not found'
      });
      if (!hasSearchTerm) results.passed = false;
    }
  } else {
    // No search - check exact counts
    if (expectations.totalRecords) {
      const totalMatch = body.total === expectations.totalRecords;
      results.checks.push({
        name: '전체 레코드 수',
        passed: totalMatch,
        expected: expectations.totalRecords,
        actual: body.total
      });
      if (!totalMatch) results.passed = false;
    }
  }

  return results;
}

/**
 * Run single test case
 */
async function runTestCase(testCase, attemptNumber) {
  console.log(`\n[${testCase.name}] - Attempt ${attemptNumber}`);
  console.log(`URL: ${BASE_URL}${testCase.path}`);

  try {
    const response = await makeRequest(`${BASE_URL}${testCase.path}`);

    console.log(`응답 크기: ${(response.size / 1024).toFixed(2)} KB`);
    console.log(`총 개수: ${response.body.total}`);
    console.log(`현재 페이지: ${response.body.current_page}/${response.body.last_page}`);
    console.log(`Per Page: ${response.body.per_page}`);
    console.log(`실제 반환: ${response.body.data?.length || 0}개`);

    const validation = validateResponse(testCase, response);

    console.log('\n검증 결과:');
    validation.checks.forEach(check => {
      const icon = check.passed ? '✅' : '❌';
      console.log(`  ${icon} ${check.name}: ${check.actual} (기대: ${check.expected})`);
    });

    return {
      testCase: testCase.name,
      success: validation.passed,
      response: response.body,
      validation,
      size: response.size
    };
  } catch (error) {
    console.log(`❌ 실패: ${error.message}`);
    return {
      testCase: testCase.name,
      success: false,
      error: error.message
    };
  }
}

/**
 * Run all test cases with retry logic
 */
async function runAllTests() {
  console.log('=== 프로덕션 검색 기능 테스트 시작 ===');
  console.log(`테스트 시간: ${new Date().toISOString()}`);
  console.log(`재시도 설정: 최대 ${MAX_RETRIES}번, ${RETRY_INTERVAL/1000}초 간격\n`);

  for (let attempt = 1; attempt <= MAX_RETRIES; attempt++) {
    console.log(`\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━`);
    console.log(`시도 ${attempt}/${MAX_RETRIES}`);
    console.log(`━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━`);

    // Health check
    console.log('\n[헬스체크] 배포 버전 확인...');
    const health = await checkHealthVersion();
    if (health.success) {
      console.log(`✅ 버전: ${health.version}`);
      console.log(`   배포 시간: ${health.deployedAt}`);
      console.log(`   검색 기능: ${health.hasSearch ? 'v2 배포됨' : 'v2 미배포'}`);
    } else {
      console.log(`❌ 헬스체크 실패: ${health.error}`);
    }

    // Run test cases
    const results = [];
    for (const testCase of TEST_CASES) {
      const result = await runTestCase(testCase, attempt);
      results.push(result);

      // Wait 1 second between tests
      await new Promise(resolve => setTimeout(resolve, 1000));
    }

    // Check if all tests passed
    const allPassed = results.every(r => r.success);

    console.log('\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    console.log(`시도 ${attempt} 결과 요약:`);
    console.log(`━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━`);
    results.forEach(r => {
      const icon = r.success ? '✅' : '❌';
      console.log(`${icon} ${r.testCase}`);
    });

    if (allPassed) {
      console.log('\n🎉 모든 테스트 통과! 검색 기능이 정상 작동합니다.');

      // Final summary
      console.log('\n=== 최종 테스트 결과 ===');
      console.log(`배포 버전: ${health.version || 'unknown'}`);
      console.log(`테스트 시간: ${new Date().toISOString()}`);
      console.log(`총 시도 횟수: ${attempt}/${MAX_RETRIES}`);
      console.log(`성공 여부: ✅ 모든 테스트 통과`);

      return {
        success: true,
        attempts: attempt,
        results
      };
    }

    // If not all passed and not last attempt, wait and retry
    if (attempt < MAX_RETRIES) {
      console.log(`\n⏳ ${RETRY_INTERVAL/1000}초 후 재시도...`);
      await new Promise(resolve => setTimeout(resolve, RETRY_INTERVAL));
    }
  }

  // All retries exhausted
  console.log('\n❌ 최대 재시도 횟수 초과. 테스트 실패.');
  console.log(`\n=== 최종 테스트 결과 ===`);
  console.log(`배포 버전: 확인 필요`);
  console.log(`테스트 시간: ${new Date().toISOString()}`);
  console.log(`총 시도 횟수: ${MAX_RETRIES}/${MAX_RETRIES}`);
  console.log(`성공 여부: ❌ 일부 테스트 실패`);

  return {
    success: false,
    attempts: MAX_RETRIES,
    message: 'Max retries exceeded'
  };
}

// Run the test suite
runAllTests().then(result => {
  process.exit(result.success ? 0 : 1);
}).catch(error => {
  console.error('Fatal error:', error);
  process.exit(1);
});
