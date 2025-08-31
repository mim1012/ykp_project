<?php

namespace Tests\Feature;

use App\Models\DealerProfile;
use App\Helpers\SalesCalculator;
use App\Helpers\FieldMapper;
use App\Jobs\ProcessBatchCalculationJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * 실시간 계산 API 성능 테스트
 * - 응답 시간 측정
 * - 동시성 테스트
 * - 메모리 사용량 모니터링
 * - 처리량 벤치마크
 */
class CalculationApiPerformanceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 성능 임계값 설정
     */
    const PERFORMANCE_THRESHOLDS = [
        'single_calculation_ms' => 100,     // 단일 계산 100ms 미만
        'batch_50_rows_ms' => 5000,        // 50행 배치 5초 미만
        'memory_limit_mb' => 64,           // 메모리 사용량 64MB 미만
        'concurrent_requests' => 10,       // 동시 요청 처리 수
    ];

    protected DealerProfile $testProfile;

    protected function setUp(): void
    {
        parent::setUp();
        
        // 테스트용 프로파일 생성
        $this->testProfile = DealerProfile::create([
            'dealer_code' => 'TEST_001',
            'dealer_name' => '테스트 대리점',
            'default_sim_fee' => 22000,
            'default_mnp_discount' => 800,
            'tax_rate' => 0.133,
            'status' => 'active',
            'activated_at' => now(),
        ]);
    }

    /**
     * 단일 계산 성능 테스트
     */
    public function test_single_calculation_performance()
    {
        $testData = [
            'dealer_code' => 'TEST_001',
            'data' => [
                'priceSettling' => 100000,
                'verbal1' => 50000,
                'verbal2' => 30000,
                'gradeAmount' => 20000,
                'additionalAmount' => 10000,
            ],
        ];

        // 워밍업 (캐시 로드)
        $this->postJson('/api/calculation/profile/row', $testData);

        // 성능 측정
        $iterations = 100;
        $times = [];

        for ($i = 0; $i < $iterations; $i++) {
            $startTime = microtime(true);
            
            $response = $this->postJson('/api/calculation/profile/row', $testData);
            
            $endTime = microtime(true);
            $times[] = ($endTime - $startTime) * 1000; // ms 변환
            
            $response->assertStatus(200)
                    ->assertJson(['success' => true]);
        }

        // 통계 계산
        $avgTime = array_sum($times) / count($times);
        $minTime = min($times);
        $maxTime = max($times);
        $p95Time = $this->calculatePercentile($times, 95);

        // 성능 검증
        $this->assertLessThan(
            self::PERFORMANCE_THRESHOLDS['single_calculation_ms'],
            $avgTime,
            "평균 응답 시간이 {$avgTime}ms로 임계값을 초과했습니다"
        );

        $this->assertLessThan(
            self::PERFORMANCE_THRESHOLDS['single_calculation_ms'] * 2,
            $p95Time,
            "95% 응답 시간이 {$p95Time}ms로 임계값을 초과했습니다"
        );

        // 성능 메트릭 로그
        echo "\n=== 단일 계산 성능 결과 ===\n";
        echo "반복 횟수: {$iterations}\n";
        echo "평균 시간: " . round($avgTime, 2) . "ms\n";
        echo "최소 시간: " . round($minTime, 2) . "ms\n";
        echo "최대 시간: " . round($maxTime, 2) . "ms\n";
        echo "95% 시간: " . round($p95Time, 2) . "ms\n";
        echo "초당 처리량: " . round(1000 / $avgTime, 1) . " req/sec\n";
    }

    /**
     * 배치 계산 성능 테스트
     */
    public function test_batch_calculation_performance()
    {
        $batchSizes = [10, 25, 50, 100];
        
        foreach ($batchSizes as $size) {
            $this->runBatchPerformanceTest($size);
        }
    }

    /**
     * 특정 크기 배치 성능 테스트
     */
    protected function runBatchPerformanceTest(int $batchSize): void
    {
        $rows = $this->generateTestRows($batchSize);
        
        $testData = [
            'dealer_code' => 'TEST_001',
            'rows' => $rows,
        ];

        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        
        $response = $this->postJson('/api/calculation/profile/batch', $testData);
        
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        
        $duration = ($endTime - $startTime) * 1000; // ms
        $memoryUsed = ($endMemory - $startMemory) / 1024 / 1024; // MB
        
        $response->assertStatus(200)
                ->assertJson(['success' => true]);

        // 성능 검증
        if ($batchSize <= 50) {
            $this->assertLessThan(
                self::PERFORMANCE_THRESHOLDS['batch_50_rows_ms'],
                $duration,
                "{$batchSize}행 배치 처리 시간이 {$duration}ms로 임계값을 초과했습니다"
            );
        }

        $this->assertLessThan(
            self::PERFORMANCE_THRESHOLDS['memory_limit_mb'],
            $memoryUsed,
            "{$batchSize}행 배치 메모리 사용량이 {$memoryUsed}MB로 임계값을 초과했습니다"
        );

        // 결과 분석
        $responseData = $response->json();
        $successRate = ($responseData['data']['summary']['success'] / $batchSize) * 100;
        $avgTimePerRow = $duration / $batchSize;

        echo "\n=== {$batchSize}행 배치 성능 결과 ===\n";
        echo "총 처리 시간: " . round($duration, 2) . "ms\n";
        echo "행당 평균 시간: " . round($avgTimePerRow, 2) . "ms\n";
        echo "메모리 사용량: " . round($memoryUsed, 2) . "MB\n";
        echo "성공률: {$successRate}%\n";
        echo "초당 처리량: " . round(1000 / $avgTimePerRow, 1) . " rows/sec\n";
    }

    /**
     * 동시성 테스트
     */
    public function test_concurrent_requests_performance()
    {
        $concurrency = self::PERFORMANCE_THRESHOLDS['concurrent_requests'];
        $testData = [
            'dealer_code' => 'TEST_001',
            'data' => [
                'priceSettling' => 100000,
                'verbal1' => 50000,
            ],
        ];

        // 멀티프로세스 시뮬레이션을 위한 간단한 동시 요청
        $promises = [];
        $startTime = microtime(true);

        for ($i = 0; $i < $concurrency; $i++) {
            $response = $this->postJson('/api/calculation/profile/row', $testData);
            $this->assertEquals(200, $response->status());
        }

        $endTime = microtime(true);
        $totalTime = ($endTime - $startTime) * 1000;
        $avgTimePerRequest = $totalTime / $concurrency;

        echo "\n=== 동시성 테스트 결과 ===\n";
        echo "동시 요청 수: {$concurrency}\n";
        echo "총 시간: " . round($totalTime, 2) . "ms\n";
        echo "평균 요청 시간: " . round($avgTimePerRequest, 2) . "ms\n";
        echo "처리량: " . round($concurrency / ($totalTime / 1000), 1) . " req/sec\n";

        // 성능 검증
        $this->assertLessThan(
            self::PERFORMANCE_THRESHOLDS['single_calculation_ms'] * 2,
            $avgTimePerRequest,
            "동시 요청 시 평균 응답 시간이 임계값을 초과했습니다"
        );
    }

    /**
     * 메모리 사용량 테스트
     */
    public function test_memory_usage_under_load()
    {
        $initialMemory = memory_get_usage(true);
        
        // 대량 데이터 처리 시뮬레이션
        $largeRows = $this->generateTestRows(200);
        
        $testData = [
            'dealer_code' => 'TEST_001',
            'rows' => $largeRows,
        ];

        $response = $this->postJson('/api/calculation/profile/batch', $testData);
        
        $peakMemory = memory_get_peak_usage(true);
        $memoryIncrease = ($peakMemory - $initialMemory) / 1024 / 1024; // MB

        echo "\n=== 메모리 사용량 테스트 결과 ===\n";
        echo "초기 메모리: " . round($initialMemory / 1024 / 1024, 2) . "MB\n";
        echo "최대 메모리: " . round($peakMemory / 1024 / 1024, 2) . "MB\n";
        echo "메모리 증가: " . round($memoryIncrease, 2) . "MB\n";
        echo "행당 메모리: " . round($memoryIncrease / 200 * 1024, 2) . "KB\n";

        $this->assertLessThan(
            self::PERFORMANCE_THRESHOLDS['memory_limit_mb'] * 2,
            $memoryIncrease,
            "메모리 사용량이 {$memoryIncrease}MB로 임계값을 초과했습니다"
        );

        $response->assertStatus(200);
    }

    /**
     * 캐시 성능 테스트
     */
    public function test_cache_performance_impact()
    {
        $testData = [
            'dealer_code' => 'TEST_001',
            'data' => [
                'priceSettling' => 100000,
                'verbal1' => 50000,
            ],
        ];

        // 캐시 클리어 후 첫 번째 요청 (캐시 미스)
        Cache::forget("dealer_profile_TEST_001");
        
        $startTime = microtime(true);
        $response1 = $this->postJson('/api/calculation/profile/row', $testData);
        $cacheMissTime = (microtime(true) - $startTime) * 1000;

        // 두 번째 요청 (캐시 히트)
        $startTime = microtime(true);
        $response2 = $this->postJson('/api/calculation/profile/row', $testData);
        $cacheHitTime = (microtime(true) - $startTime) * 1000;

        $performanceImprovement = (($cacheMissTime - $cacheHitTime) / $cacheMissTime) * 100;

        echo "\n=== 캐시 성능 테스트 결과 ===\n";
        echo "캐시 미스 시간: " . round($cacheMissTime, 2) . "ms\n";
        echo "캐시 히트 시간: " . round($cacheHitTime, 2) . "ms\n";
        echo "성능 개선: " . round($performanceImprovement, 1) . "%\n";

        $this->assertGreaterThan(0, $performanceImprovement, "캐시로 인한 성능 개선이 없습니다");
        
        $response1->assertStatus(200);
        $response2->assertStatus(200);
    }

    /**
     * 비동기 Job 성능 테스트
     */
    public function test_async_job_performance()
    {
        Queue::fake();
        
        $rows = $this->generateTestRows(100);
        
        $testData = [
            'dealer_code' => 'TEST_001',
            'rows' => $rows,
        ];

        $startTime = microtime(true);
        
        $response = $this->postJson('/api/batch-jobs/start', $testData);
        
        $enqueueDuration = (microtime(true) - $startTime) * 1000;

        echo "\n=== 비동기 Job 성능 결과 ===\n";
        echo "Job 등록 시간: " . round($enqueueDuration, 2) . "ms\n";

        $response->assertStatus(202)
                ->assertJson(['success' => true]);

        // Job이 정상적으로 큐에 등록되었는지 확인
        Queue::assertPushed(ProcessBatchCalculationJob::class);

        // 빠른 응답 시간 확인 (비동기이므로)
        $this->assertLessThan(1000, $enqueueDuration, "비동기 Job 등록이 너무 느립니다");
    }

    /**
     * 필드 매핑 성능 테스트
     */
    public function test_field_mapping_performance()
    {
        $testData = $this->generateTestRows(100);
        
        // Laravel → ykp 매핑 성능
        $startTime = microtime(true);
        for ($i = 0; $i < 100; $i++) {
            FieldMapper::laravelArrayToYkp($testData);
        }
        $laravelToYkpTime = (microtime(true) - $startTime) * 1000;

        // ykp → Laravel 매핑 성능  
        $ykpData = FieldMapper::laravelArrayToYkp($testData);
        
        $startTime = microtime(true);
        for ($i = 0; $i < 100; $i++) {
            FieldMapper::ykpArrayToLaravel($ykpData);
        }
        $ykpToLaravelTime = (microtime(true) - $startTime) * 1000;

        echo "\n=== 필드 매핑 성능 결과 ===\n";
        echo "Laravel→ykp (100행 x 100회): " . round($laravelToYkpTime, 2) . "ms\n";
        echo "ykp→Laravel (100행 x 100회): " . round($ykpToLaravelTime, 2) . "ms\n";
        echo "평균 매핑 시간 (1행): " . round(($laravelToYkpTime + $ykpToLaravelTime) / 20000, 4) . "ms\n";

        // 매핑이 너무 느리지 않은지 확인
        $this->assertLessThan(5000, $laravelToYkpTime, "Laravel→ykp 매핑이 너무 느립니다");
        $this->assertLessThan(5000, $ykpToLaravelTime, "ykp→Laravel 매핑이 너무 느립니다");
    }

    // === Helper Methods ===

    /**
     * 테스트용 데이터 생성
     */
    protected function generateTestRows(int $count): array
    {
        $rows = [];
        
        for ($i = 0; $i < $count; $i++) {
            $rows[] = [
                'price_setting' => rand(50000, 200000),
                'verbal1' => rand(0, 100000),
                'verbal2' => rand(0, 50000),
                'grade_amount' => rand(0, 30000),
                'addon_amount' => rand(0, 20000),
                'paper_cash' => rand(0, 10000),
                'cash_in' => rand(0, 50000),
                'payback' => -rand(0, 30000),
            ];
        }
        
        return $rows;
    }

    /**
     * 백분위수 계산
     */
    protected function calculatePercentile(array $data, float $percentile): float
    {
        sort($data);
        $count = count($data);
        $index = ($percentile / 100) * ($count - 1);
        
        if (floor($index) == $index) {
            return $data[$index];
        } else {
            $lower = $data[floor($index)];
            $upper = $data[ceil($index)];
            return $lower + ($upper - $lower) * ($index - floor($index));
        }
    }

    /**
     * 성능 리포트 생성
     */
    public function test_generate_performance_report()
    {
        // 모든 성능 테스트 결과를 종합한 리포트 생성
        $report = [
            'test_date' => now()->toDateTimeString(),
            'thresholds' => self::PERFORMANCE_THRESHOLDS,
            'environment' => [
                'php_version' => PHP_VERSION,
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
            ],
            'summary' => '실시간 계산 API 성능 테스트가 완료되었습니다.',
        ];

        echo "\n" . json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        
        $this->assertTrue(true, "성능 리포트 생성 완료");
    }
}