<?php

namespace App\Jobs;

use App\Helpers\SalesCalculator;
use App\Helpers\FieldMapper;
use App\Models\DealerProfile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * 대량 계산 처리 Job
 * - 배치 계산을 백그라운드에서 비동기 처리
 * - 진행상황 추적 및 결과 캐싱
 * - 에러 복구 및 fallback 메커니즘
 */
class ProcessBatchCalculationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Job 처리 제한시간 (초)
     */
    public $timeout = 600; // 10분

    /**
     * 최대 재시도 횟수
     */
    public $tries = 3;

    /**
     * 재시도 간격 (초)
     */
    public $backoff = [10, 30, 60];

    protected string $jobId;
    protected string $dealerCode;
    protected array $rows;
    protected array $options;
    protected ?string $resultCacheKey;

    /**
     * Job 생성
     */
    public function __construct(
        string $dealerCode,
        array $rows,
        array $options = [],
        ?string $jobId = null
    ) {
        $this->jobId = $jobId ?: Str::uuid()->toString();
        $this->dealerCode = $dealerCode;
        $this->rows = $rows;
        $this->options = array_merge([
            'format' => 'ykp',
            'chunk_size' => 50,
            'store_results' => true,
            'include_performance' => true,
            'stop_on_error' => false,
        ], $options);
        
        $this->resultCacheKey = "batch_calculation_result_{$this->jobId}";
        
        // Queue 설정
        $this->onQueue('calculations');
    }

    /**
     * Job 실행
     */
    public function handle(): void
    {
        $startTime = microtime(true);
        
        try {
            Log::info('Batch calculation job started', [
                'job_id' => $this->jobId,
                'dealer_code' => $this->dealerCode,
                'row_count' => count($this->rows),
                'options' => $this->options,
            ]);

            // 진행상황 초기화
            $this->updateProgress(0, 'started', 'Job 시작');

            // 프로파일 로드
            $profile = $this->loadProfile();
            if (!$profile) {
                throw new \RuntimeException("대리점 프로파일을 찾을 수 없습니다: {$this->dealerCode}");
            }

            // 진행상황 업데이트
            $this->updateProgress(10, 'profile_loaded', '프로파일 로드 완료');

            // 데이터 전처리
            $processedRows = $this->preprocessRows();
            $this->updateProgress(20, 'data_preprocessed', '데이터 전처리 완료');

            // 청크 단위로 배치 처리
            $results = $this->processInChunks($processedRows, $profile);
            $this->updateProgress(90, 'calculation_completed', '계산 완료');

            // 결과 후처리 및 저장
            $finalResult = $this->postProcessResults($results, $startTime);
            $this->storeResults($finalResult);
            
            $this->updateProgress(100, 'completed', 'Job 완료');

            Log::info('Batch calculation job completed successfully', [
                'job_id' => $this->jobId,
                'total_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'success_count' => $finalResult['summary']['success'],
                'error_count' => $finalResult['summary']['errors'],
            ]);

        } catch (\Exception $e) {
            $this->handleJobFailure($e, $startTime);
            throw $e;
        }
    }

    /**
     * Job 실패 시 처리
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Batch calculation job failed permanently', [
            'job_id' => $this->jobId,
            'dealer_code' => $this->dealerCode,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        $this->updateProgress(-1, 'failed', '처리 실패: ' . $exception->getMessage());

        // 실패 결과 저장
        $failureResult = [
            'success' => false,
            'error' => $exception->getMessage(),
            'job_id' => $this->jobId,
            'failed_at' => now()->toISOString(),
            'attempts' => $this->attempts(),
        ];

        Cache::put($this->resultCacheKey, $failureResult, 3600); // 1시간 보관
    }

    /**
     * 프로파일 로드
     */
    protected function loadProfile(): ?DealerProfile
    {
        return Cache::remember(
            "dealer_profile_{$this->dealerCode}",
            300,
            fn() => DealerProfile::active()->byDealerCode($this->dealerCode)->first()
        );
    }

    /**
     * 데이터 전처리
     */
    protected function preprocessRows(): array
    {
        $format = $this->options['format'] ?? 'ykp';
        
        return $format === 'ykp' 
            ? FieldMapper::ykpArrayToLaravel($this->rows)
            : $this->rows;
    }

    /**
     * 청크 단위 배치 처리
     */
    protected function processInChunks(array $processedRows, DealerProfile $profile): array
    {
        $chunkSize = $this->options['chunk_size'] ?? 50;
        $chunks = array_chunk($processedRows, $chunkSize, true);
        $allResults = [];
        $totalChunks = count($chunks);
        
        foreach ($chunks as $chunkIndex => $chunk) {
            try {
                Log::debug('Processing chunk', [
                    'job_id' => $this->jobId,
                    'chunk' => $chunkIndex + 1,
                    'total_chunks' => $totalChunks,
                    'chunk_size' => count($chunk),
                ]);

                $chunkResults = $this->processChunk($chunk, $profile);
                $allResults = array_merge($allResults, $chunkResults);

                // 진행률 업데이트 (20% ~ 90% 구간)
                $progress = 20 + (70 * ($chunkIndex + 1) / $totalChunks);
                $this->updateProgress(
                    $progress,
                    'processing',
                    "청크 처리 중 ({$chunkIndex + 1}/{$totalChunks})"
                );

            } catch (\Exception $e) {
                Log::error('Chunk processing failed', [
                    'job_id' => $this->jobId,
                    'chunk_index' => $chunkIndex,
                    'error' => $e->getMessage(),
                ]);

                if ($this->options['stop_on_error']) {
                    throw $e;
                }

                // 에러 발생한 청크의 각 행을 실패로 처리
                foreach ($chunk as $originalIndex => $row) {
                    $allResults[$originalIndex] = [
                        'status' => 'error',
                        'input' => $row,
                        'message' => "청크 처리 실패: {$e->getMessage()}",
                        'chunk_error' => true,
                    ];
                }
            }
        }

        return $allResults;
    }

    /**
     * 개별 청크 처리
     */
    protected function processChunk(array $chunk, DealerProfile $profile): array
    {
        $chunkResults = [];

        foreach ($chunk as $originalIndex => $row) {
            try {
                $calculation = SalesCalculator::calculateWithProfile($row, $profile);
                
                $chunkResults[$originalIndex] = [
                    'status' => 'success',
                    'input' => $row,
                    'result' => $calculation,
                ];

            } catch (\Exception $e) {
                Log::warning('Row calculation failed', [
                    'job_id' => $this->jobId,
                    'row_index' => $originalIndex,
                    'error' => $e->getMessage(),
                ]);

                // Fallback 계산 시도
                $fallbackResult = $this->attemptFallbackCalculation($row);
                
                $chunkResults[$originalIndex] = [
                    'status' => $fallbackResult ? 'success_with_fallback' : 'error',
                    'input' => $row,
                    'result' => $fallbackResult,
                    'message' => $fallbackResult ? '기본 계산으로 fallback' : $e->getMessage(),
                    'original_error' => $e->getMessage(),
                ];
            }
        }

        return $chunkResults;
    }

    /**
     * Fallback 계산 시도
     */
    protected function attemptFallbackCalculation(array $row): ?array
    {
        try {
            Log::info('Attempting fallback calculation', [
                'job_id' => $this->jobId,
                'row' => array_keys($row),
            ]);

            $result = SalesCalculator::computeRow($row);
            $result['fallback_used'] = true;
            
            return $result;

        } catch (\Exception $e) {
            Log::error('Fallback calculation also failed', [
                'job_id' => $this->jobId,
                'fallback_error' => $e->getMessage(),
            ]);
            
            return null;
        }
    }

    /**
     * 결과 후처리
     */
    protected function postProcessResults(array $results, float $startTime): array
    {
        $successCount = 0;
        $errorCount = 0;
        $fallbackCount = 0;

        // 결과 형식 변환 및 통계 계산
        $format = $this->options['format'] ?? 'ykp';
        
        foreach ($results as &$result) {
            if ($result['status'] === 'success' || $result['status'] === 'success_with_fallback') {
                $successCount++;
                
                if ($result['status'] === 'success_with_fallback') {
                    $fallbackCount++;
                }
                
                // 결과 형식 변환
                if ($format === 'ykp' && isset($result['result'])) {
                    $result['result'] = FieldMapper::laravelToYkp($result['result']);
                    $result['input'] = FieldMapper::laravelToYkp($result['input']);
                }
            } else {
                $errorCount++;
            }
        }

        $totalTime = round((microtime(true) - $startTime) * 1000, 2);
        $avgTime = count($results) > 0 ? round($totalTime / count($results), 2) : 0;

        return [
            'job_id' => $this->jobId,
            'success' => true,
            'results' => $results,
            'summary' => [
                'total' => count($results),
                'success' => $successCount,
                'errors' => $errorCount,
                'fallbacks' => $fallbackCount,
                'success_rate' => count($results) > 0 ? round(($successCount / count($results)) * 100, 1) : 0,
            ],
            'performance' => [
                'total_time_ms' => $totalTime,
                'avg_time_per_row_ms' => $avgTime,
                'rows_per_second' => $avgTime > 0 ? round(1000 / $avgTime, 1) : 0,
            ],
            'metadata' => [
                'dealer_code' => $this->dealerCode,
                'processed_at' => now()->toISOString(),
                'format_used' => $format,
                'chunk_size' => $this->options['chunk_size'],
                'options' => $this->options,
            ],
        ];
    }

    /**
     * 결과 저장
     */
    protected function storeResults(array $finalResult): void
    {
        if (!$this->options['store_results']) {
            return;
        }

        // 메모리 캐시 저장 (빠른 조회용)
        Cache::put($this->resultCacheKey, $finalResult, 3600); // 1시간

        // 대용량 결과는 파일로 저장
        if (count($finalResult['results']) > 100) {
            $filename = "batch_calculations/{$this->jobId}.json";
            Storage::disk('local')->put($filename, json_encode($finalResult, JSON_PRETTY_PRINT));
            
            // 캐시에는 파일 참조만 저장
            $summaryResult = $finalResult;
            unset($summaryResult['results']); // 상세 결과 제거
            $summaryResult['results_file'] = $filename;
            
            Cache::put($this->resultCacheKey, $summaryResult, 3600);
        }
    }

    /**
     * 진행상황 업데이트
     */
    protected function updateProgress(float $percentage, string $status, string $message = ''): void
    {
        $progressKey = "batch_calculation_progress_{$this->jobId}";
        
        $progress = [
            'job_id' => $this->jobId,
            'percentage' => max(0, min(100, $percentage)),
            'status' => $status,
            'message' => $message,
            'updated_at' => now()->toISOString(),
        ];

        Cache::put($progressKey, $progress, 3600); // 1시간 보관
    }

    /**
     * Job 실패 처리
     */
    protected function handleJobFailure(\Exception $e, float $startTime): void
    {
        $this->updateProgress(-1, 'failed', $e->getMessage());

        Log::error('Batch calculation job failed', [
            'job_id' => $this->jobId,
            'dealer_code' => $this->dealerCode,
            'error' => $e->getMessage(),
            'processing_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
            'attempt' => $this->attempts(),
        ]);
    }

    /**
     * Job ID 반환
     */
    public function getJobId(): string
    {
        return $this->jobId;
    }

    /**
     * 결과 캐시 키 반환
     */
    public function getResultCacheKey(): string
    {
        return $this->resultCacheKey;
    }

    /**
     * 진행상황 조회 (정적 메서드)
     */
    public static function getProgress(string $jobId): ?array
    {
        $progressKey = "batch_calculation_progress_{$jobId}";
        return Cache::get($progressKey);
    }

    /**
     * 결과 조회 (정적 메서드)
     */
    public static function getResult(string $jobId): ?array
    {
        $resultKey = "batch_calculation_result_{$jobId}";
        $result = Cache::get($resultKey);
        
        // 파일로 저장된 결과 로드
        if ($result && isset($result['results_file'])) {
            $fullResult = Storage::disk('local')->get($result['results_file']);
            return json_decode($fullResult, true);
        }
        
        return $result;
    }
}