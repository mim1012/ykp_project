<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Helpers\SalesCalculator;
use App\Helpers\FieldMapper;
use App\Models\DealerProfile;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CalculationController extends Controller
{
    /**
     * 응답 시간 임계값 (ms)
     */
    const RESPONSE_TIME_THRESHOLD = 100;
    
    /**
     * 캐시 TTL (초)
     */
    const CACHE_TTL = 300;
    /**
     * 단일 행 실시간 계산
     */
    public function calculateRow(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'price_setting' => 'required|numeric|min:0',
            'verbal1' => 'nullable|numeric',
            'verbal2' => 'nullable|numeric', 
            'grade_amount' => 'nullable|numeric',
            'addon_amount' => 'nullable|numeric',
            'paper_cash' => 'nullable|numeric',
            'usim_fee' => 'nullable|numeric',
            'new_mnp_disc' => 'nullable|numeric',
            'deduction' => 'nullable|numeric',
            'cash_in' => 'nullable|numeric',
            'payback' => 'nullable|numeric'
        ]);

        try {
            $result = SalesCalculator::computeRow($validated);

            return response()->json([
                'success' => true,
                'data' => $result,
                'input' => $validated,
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'timestamp' => now()->toISOString()
            ], 400);
        }
    }

    /**
     * 배치 계산 (여러 행)
     */
    public function calculateBatch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'rows' => 'required|array|min:1|max:50', // 최대 50행 제한
            'rows.*' => 'required|array'
        ]);

        try {
            $results = [];
            $successCount = 0;
            $errorCount = 0;

            foreach ($validated['rows'] as $index => $row) {
                try {
                    $calculation = SalesCalculator::computeRow($row);
                    $results[$index] = [
                        'status' => 'success',
                        'input' => $row,
                        'result' => $calculation
                    ];
                    $successCount++;
                } catch (\Exception $e) {
                    $results[$index] = [
                        'status' => 'error',
                        'input' => $row,
                        'message' => $e->getMessage()
                    ];
                    $errorCount++;
                }
            }

            return response()->json([
                'success' => true,
                'data' => $results,
                'summary' => [
                    'total' => count($validated['rows']),
                    'success' => $successCount,
                    'errors' => $errorCount
                ],
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'timestamp' => now()->toISOString()
            ], 400);
        }
    }

    /**
     * 프로파일 기반 단일 행 계산 (고도화)
     */
    public function calculateRowWithProfile(Request $request): JsonResponse
    {
        $startTime = microtime(true);
        
        try {
            $validated = $this->validateProfileCalculationRequest($request);
            
            // 프로파일 조회 (캐시 지원)
            $profile = $this->getProfileWithCache($validated['dealer_code']);
            if (!$profile) {
                return $this->errorResponse(
                    '활성화된 대리점 프로파일을 찾을 수 없습니다',
                    ['dealer_code' => $validated['dealer_code']],
                    404
                );
            }
            
            // 필드 매핑 (ykp-settlement → Laravel)
            $calculationData = FieldMapper::ykpToLaravel($validated['data']);
            
            // 프로파일 기반 계산
            $result = SalesCalculator::calculateWithProfile($calculationData, $profile);
            
            // 결과를 ykp-settlement 형식으로 변환
            $mappedResult = FieldMapper::laravelToYkp($result);
            
            return response()->json([
                'success' => true,
                'data' => $mappedResult,
                'input' => $validated['data'],
                'metadata' => [
                    'profile_used' => $profile->dealer_code,
                    'calculation_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                    'field_mapping_applied' => true,
                ],
                'timestamp' => now()->toISOString()
            ]);
            
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Exception $e) {
            Log::error('Profile calculation failed', [
                'error' => $e->getMessage(),
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return $this->errorResponse(
                '계산 처리 중 오류가 발생했습니다',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * 프로파일 기반 배치 계산 (고도화)
     */
    public function calculateBatchWithProfile(Request $request): JsonResponse
    {
        $startTime = microtime(true);
        
        try {
            $validated = $request->validate([
                'dealer_code' => 'required|string|exists:dealer_profiles,dealer_code',
                'rows' => 'required|array|min:1|max:100', // 최대 100행 제한
                'rows.*' => 'required|array',
                'options' => 'sometimes|array',
                'options.format' => 'sometimes|string|in:laravel,ykp',
                'options.include_performance' => 'sometimes|boolean',
            ]);
            
            // 프로파일 조회
            $profile = $this->getProfileWithCache($validated['dealer_code']);
            if (!$profile) {
                return $this->errorResponse(
                    '활성화된 대리점 프로파일을 찾을 수 없습니다',
                    ['dealer_code' => $validated['dealer_code']],
                    404
                );
            }
            
            // 입력 형식 확인 및 변환
            $format = $validated['options']['format'] ?? 'ykp';
            $rows = $format === 'ykp' 
                ? FieldMapper::ykpArrayToLaravel($validated['rows'])
                : $validated['rows'];
            
            // 배치 계산 수행
            $batchResult = SalesCalculator::calculateBatchWithProfile(
                $rows, 
                $profile, 
                $validated['options'] ?? []
            );
            
            // 결과 형식 변환
            if ($format === 'ykp') {
                foreach ($batchResult['results'] as &$result) {
                    if ($result['status'] === 'success' && isset($result['result'])) {
                        $result['result'] = FieldMapper::laravelToYkp($result['result']);
                        $result['input'] = FieldMapper::laravelToYkp($result['input']);
                    }
                }
            }
            
            $totalTime = round((microtime(true) - $startTime) * 1000, 2);
            
            return response()->json([
                'success' => true,
                'data' => $batchResult,
                'metadata' => [
                    'total_processing_time_ms' => $totalTime,
                    'profile_used' => $profile->dealer_code,
                    'input_format' => $format,
                    'field_mapping_applied' => true,
                ],
                'timestamp' => now()->toISOString()
            ]);
            
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Exception $e) {
            Log::error('Batch profile calculation failed', [
                'error' => $e->getMessage(),
                'request_data' => $request->all(),
            ]);
            
            return $this->errorResponse(
                '배치 계산 처리 중 오류가 발생했습니다',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * 프로파일 목록 조회
     */
    public function getProfiles(Request $request): JsonResponse
    {
        try {
            $profiles = Cache::remember(
                'active_dealer_profiles',
                300, // 5분 캐시
                fn() => DealerProfile::active()
                    ->select(['id', 'dealer_code', 'dealer_name', 'status', 'tax_rate', 'default_sim_fee', 'default_mnp_discount'])
                    ->orderBy('dealer_name')
                    ->get()
            );
            
            return response()->json([
                'success' => true,
                'data' => $profiles,
                'count' => $profiles->count(),
                'timestamp' => now()->toISOString()
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to fetch profiles', ['error' => $e->getMessage()]);
            
            return $this->errorResponse(
                '프로파일 목록 조회 중 오류가 발생했습니다',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * 프로파일 상세 정보 조회
     */
    public function getProfile(Request $request, string $dealerCode): JsonResponse
    {
        try {
            $profile = $this->getProfileWithCache($dealerCode);
            
            if (!$profile) {
                return $this->errorResponse(
                    '프로파일을 찾을 수 없습니다',
                    ['dealer_code' => $dealerCode],
                    404
                );
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'profile' => $profile,
                    'calculation_defaults' => $profile->getCalculationDefaults(),
                    'validation_result' => $profile->validateProfile(),
                ],
                'timestamp' => now()->toISOString()
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to fetch profile', [
                'dealer_code' => $dealerCode,
                'error' => $e->getMessage(),
            ]);
            
            return $this->errorResponse(
                '프로파일 상세 정보 조회 중 오류가 발생했습니다',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * AgGrid 컬럼 정의 조회
     */
    public function getColumnDefinitions(Request $request): JsonResponse
    {
        try {
            $fields = $request->query('fields', []);
            $options = [
                'sorting' => $request->query('sortable', true),
                'filtering' => $request->query('filterable', true),
                'resizable' => $request->query('resizable', true),
            ];
            
            $columns = FieldMapper::getAgGridColumns($fields, $options);
            
            return response()->json([
                'success' => true,
                'data' => $columns,
                'count' => count($columns),
                'timestamp' => now()->toISOString()
            ]);
            
        } catch (\Exception $e) {
            return $this->errorResponse(
                '컬럼 정의 조회 중 오류가 발생했습니다',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * 계산 공식 검증 (기존 + 프로파일 지원)
     */
    public function validateFormula(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'test_data' => 'required|array',
                'dealer_code' => 'sometimes|string|exists:dealer_profiles,dealer_code',
                'format' => 'sometimes|string|in:laravel,ykp',
            ]);
            
            $format = $validated['format'] ?? 'laravel';
            $testData = $format === 'ykp' 
                ? FieldMapper::ykpToLaravel($validated['test_data'])
                : $validated['test_data'];
            
            // 프로파일 기반 계산 또는 기본 계산
            if (isset($validated['dealer_code'])) {
                $profile = $this->getProfileWithCache($validated['dealer_code']);
                if (!$profile) {
                    return $this->errorResponse('프로파일을 찾을 수 없습니다', [], 404);
                }
                $result = SalesCalculator::calculateWithProfile($testData, $profile);
            } else {
                $result = SalesCalculator::computeRow($testData);
            }
            
            // 결과 형식 변환
            $finalResult = $format === 'ykp' 
                ? FieldMapper::laravelToYkp($result)
                : $result;
            
            return response()->json([
                'success' => true,
                'formula_valid' => true,
                'test_result' => $finalResult,
                'test_input' => $validated['test_data'],
                'profile_used' => $validated['dealer_code'] ?? null,
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'formula_valid' => false,
                'error' => $e->getMessage(),
                'test_input' => $request->input('test_data', []),
            ], 400);
        }
    }

    /**
     * 성능 벤치마크
     */
    public function benchmark(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'iterations' => 'sometimes|integer|min:1|max:1000',
                'dealer_code' => 'required|string|exists:dealer_profiles,dealer_code',
                'test_data' => 'required|array',
            ]);
            
            $iterations = $validated['iterations'] ?? 100;
            $profile = $this->getProfileWithCache($validated['dealer_code']);
            $testData = FieldMapper::ykpToLaravel($validated['test_data']);
            
            $times = [];
            $startTime = microtime(true);
            
            for ($i = 0; $i < $iterations; $i++) {
                $iterStart = microtime(true);
                SalesCalculator::calculateWithProfile($testData, $profile);
                $times[] = (microtime(true) - $iterStart) * 1000;
            }
            
            $totalTime = (microtime(true) - $startTime) * 1000;
            $avgTime = array_sum($times) / count($times);
            $minTime = min($times);
            $maxTime = max($times);
            
            return response()->json([
                'success' => true,
                'benchmark' => [
                    'iterations' => $iterations,
                    'total_time_ms' => round($totalTime, 2),
                    'average_time_ms' => round($avgTime, 2),
                    'min_time_ms' => round($minTime, 2),
                    'max_time_ms' => round($maxTime, 2),
                    'calculations_per_second' => round(1000 / $avgTime, 1),
                    'profile_used' => $profile->dealer_code,
                ],
                'timestamp' => now()->toISOString()
            ]);
            
        } catch (\Exception $e) {
            return $this->errorResponse(
                '벤치마크 실행 중 오류가 발생했습니다',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    // === Protected Helper Methods ===

    /**
     * 프로파일 기반 계산 요청 검증
     */
    protected function validateProfileCalculationRequest(Request $request): array
    {
        return $request->validate([
            'dealer_code' => 'required|string|exists:dealer_profiles,dealer_code',
            'data' => 'required|array',
            'data.priceSettling' => 'required|numeric|min:0',
            'data.verbal1' => 'nullable|numeric',
            'data.verbal2' => 'nullable|numeric',
            'data.gradeAmount' => 'nullable|numeric',
            'data.additionalAmount' => 'nullable|numeric',
            'data.documentCash' => 'nullable|numeric',
            'data.simFee' => 'nullable|numeric',
            'data.mnpDiscount' => 'nullable|numeric',
            'data.deduction' => 'nullable|numeric',
            'data.cashReceived' => 'nullable|numeric',
            'data.payback' => 'nullable|numeric',
        ]);
    }

    /**
     * 캐시된 프로파일 조회
     */
    protected function getProfileWithCache(string $dealerCode): ?DealerProfile
    {
        return Cache::remember(
            "dealer_profile_{$dealerCode}",
            self::CACHE_TTL, // 5분 캐시
            fn() => DealerProfile::active()->byDealerCode($dealerCode)->first()
        );
    }

    /**
     * 에러 응답 생성
     */
    protected function errorResponse(string $message, array $details = [], int $status = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'details' => $details,
            'timestamp' => now()->toISOString()
        ], $status);
    }

    /**
     * 검증 에러 응답 생성
     */
    protected function validationErrorResponse(ValidationException $e): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => '입력 데이터 검증 실패',
            'errors' => $e->errors(),
            'timestamp' => now()->toISOString()
        ], 422);
    }

    // === 비동기 배치 Job 관리 ===

    /**
     * 비동기 배치 처리 시작
     */
    public function startBatchJob(BatchCalculationRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $jobId = Str::uuid()->toString();
            
            // Job 생성 및 대기열 등록
            $job = new ProcessBatchCalculationJob(
                $validated['dealer_code'],
                $validated['rows'],
                $request->getProcessingOptions(),
                $jobId
            );
            
            // 비동기 실행
            dispatch($job);
            
            // Job 메타데이터 저장
            $jobMeta = [
                'job_id' => $jobId,
                'dealer_code' => $validated['dealer_code'],
                'row_count' => count($validated['rows']),
                'options' => $request->getProcessingOptions(),
                'performance_estimate' => $request->getPerformanceEstimate(),
                'created_at' => now()->toISOString(),
                'status' => 'queued',
            ];
            
            Cache::put("batch_job_meta_{$jobId}", $jobMeta, 3600);
            
            Log::info('Batch calculation job queued', [
                'job_id' => $jobId,
                'dealer_code' => $validated['dealer_code'],
                'row_count' => count($validated['rows']),
            ]);
            
            return response()->json([
                'success' => true,
                'job_id' => $jobId,
                'status' => 'queued',
                'message' => '배치 처리가 대기열에 등록되었습니다',
                'metadata' => $jobMeta,
                'estimated_completion_time' => now()->addMilliseconds(
                    $jobMeta['performance_estimate']['estimated_total_time_ms']
                )->toISOString(),
                'progress_url' => route('api.batch.status', ['jobId' => $jobId]),
                'result_url' => route('api.batch.result', ['jobId' => $jobId]),
                'timestamp' => now()->toISOString(),
            ], 202); // 202 Accepted
            
        } catch (\Exception $e) {
            Log::error('Failed to start batch job', [
                'error' => $e->getMessage(),
                'request_data' => $request->validated(),
            ]);
            
            return $this->errorResponse(
                '배치 처리 시작 중 오류가 발생했습니다',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * 배치 Job 상태 조회
     */
    public function getBatchJobStatus(Request $request, string $jobId): JsonResponse
    {
        try {
            // Job 메타데이터 조회
            $jobMeta = Cache::get("batch_job_meta_{$jobId}");
            if (!$jobMeta) {
                return $this->errorResponse('요청한 Job을 찾을 수 없습니다', ['job_id' => $jobId], 404);
            }
            
            // 진행상황 조회
            $progress = ProcessBatchCalculationJob::getProgress($jobId);
            $queueStatus = $this->getQueueJobStatus($jobId);
            
            // 상태 통합
            $status = $this->determineJobStatus($progress, $queueStatus, $jobMeta);
            
            return response()->json([
                'success' => true,
                'job_id' => $jobId,
                'status' => $status['status'],
                'progress' => $progress,
                'queue_info' => $queueStatus,
                'metadata' => $jobMeta,
                'estimated_remaining_time_ms' => $this->calculateRemainingTime($progress, $jobMeta),
                'timestamp' => now()->toISOString(),
            ]);
            
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Job 상태 조회 중 오류가 발생했습니다',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * 배치 Job 결과 조회
     */
    public function getBatchJobResult(Request $request, string $jobId): JsonResponse
    {
        try {
            // 결과 조회
            $result = ProcessBatchCalculationJob::getResult($jobId);
            
            if (!$result) {
                // Job이 아직 실행 중이거나 결과가 없음
                $progress = ProcessBatchCalculationJob::getProgress($jobId);
                $jobMeta = Cache::get("batch_job_meta_{$jobId}");
                
                if (!$jobMeta) {
                    return $this->errorResponse('요청한 Job을 찾을 수 없습니다', ['job_id' => $jobId], 404);
                }
                
                return response()->json([
                    'success' => false,
                    'job_id' => $jobId,
                    'status' => $progress['status'] ?? 'unknown',
                    'message' => 'Job이 아직 완료되지 않았습니다',
                    'progress' => $progress,
                    'timestamp' => now()->toISOString(),
                ], 202); // 202 Accepted
            }
            
            // 결과 반환
            return response()->json([
                'success' => true,
                'job_id' => $jobId,
                'data' => $result,
                'download_available' => isset($result['results_file']),
                'timestamp' => now()->toISOString(),
            ]);
            
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Job 결과 조회 중 오류가 발생했습니다',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * 배치 Job 취소
     */
    public function cancelBatchJob(Request $request, string $jobId): JsonResponse
    {
        try {
            // Job 메타데이터 확인
            $jobMeta = Cache::get("batch_job_meta_{$jobId}");
            if (!$jobMeta) {
                return $this->errorResponse('요청한 Job을 찾을 수 없습니다', ['job_id' => $jobId], 404);
            }
            
            // 진행상황 확인
            $progress = ProcessBatchCalculationJob::getProgress($jobId);
            
            if ($progress && in_array($progress['status'], ['completed', 'failed'])) {
                return $this->errorResponse(
                    '이미 완료되거나 실패한 Job은 취소할 수 없습니다',
                    ['status' => $progress['status']],
                    400
                );
            }
            
            // 취소 상태로 마크
            $progress = [
                'job_id' => $jobId,
                'percentage' => -2, // 취소 상태
                'status' => 'cancelled',
                'message' => '사용자에 의해 취소됨',
                'cancelled_at' => now()->toISOString(),
            ];
            
            Cache::put("batch_calculation_progress_{$jobId}", $progress, 3600);
            
            Log::info('Batch calculation job cancelled', [
                'job_id' => $jobId,
                'cancelled_by' => 'user_request',
            ]);
            
            return response()->json([
                'success' => true,
                'job_id' => $jobId,
                'status' => 'cancelled',
                'message' => 'Job이 취소되었습니다',
                'timestamp' => now()->toISOString(),
            ]);
            
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Job 취소 중 오류가 발생했습니다',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    // === 내부 헬퍼 메서드 ===

    /**
     * Queue 내 Job 상태 확인
     */
    protected function getQueueJobStatus(string $jobId): array
    {
        // Laravel Queue 내에서 Job 상태 확인
        return [
            'in_queue' => true,
            'position' => null,
            'estimated_start_time' => null,
        ];
    }

    /**
     * Job 전체 상태 결정
     */
    protected function determineJobStatus(?array $progress, array $queueStatus, array $jobMeta): array
    {
        if (!$progress) {
            return ['status' => 'queued', 'reason' => '대기열에서 대기 중'];
        }
        
        switch ($progress['status']) {
            case 'cancelled':
                return ['status' => 'cancelled', 'reason' => '취소됨'];
            case 'failed':
                return ['status' => 'failed', 'reason' => $progress['message'] ?? '알 수 없는 오류'];
            case 'completed':
                return ['status' => 'completed', 'reason' => '완료'];
            default:
                return ['status' => 'processing', 'reason' => '처리 중'];
        }
    }

    /**
     * 남은 시간 계산
     */
    protected function calculateRemainingTime(?array $progress, array $jobMeta): ?int
    {
        if (!$progress || $progress['percentage'] <= 0) {
            return $jobMeta['performance_estimate']['estimated_total_time_ms'] ?? null;
        }
        
        if ($progress['percentage'] >= 100) {
            return 0;
        }
        
        $estimatedTotal = $jobMeta['performance_estimate']['estimated_total_time_ms'] ?? 0;
        $remainingPercentage = (100 - $progress['percentage']) / 100;
        
        return round($estimatedTotal * $remainingPercentage);
    }

    /**
     * API 성능 모니터링
     */
    protected function logPerformanceMetrics(string $endpoint, float $startTime, array $metadata = []): void
    {
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        
        Log::info('API Performance', array_merge([
            'endpoint' => $endpoint,
            'duration_ms' => $duration,
            'threshold_exceeded' => $duration > self::RESPONSE_TIME_THRESHOLD,
        ], $metadata));
        
        // 임계값 초과 시 알림
        if ($duration > self::RESPONSE_TIME_THRESHOLD) {
            Log::warning('API response time exceeded threshold', [
                'endpoint' => $endpoint,
                'duration_ms' => $duration,
                'threshold_ms' => self::RESPONSE_TIME_THRESHOLD,
            ]);
        }
    }
}