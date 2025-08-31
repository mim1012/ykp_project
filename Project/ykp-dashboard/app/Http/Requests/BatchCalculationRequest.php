<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\DealerProfile;

/**
 * 배치 계산 요청 검증
 */
class BatchCalculationRequest extends FormRequest
{
    /**
     * 최대 처리 가능한 행 수
     */
    const MAX_ROWS = 500;
    
    /**
     * 권장 배치 크기 (성능 최적화)
     */
    const RECOMMENDED_BATCH_SIZE = 100;

    /**
     * 요청 권한 확인
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 검증 규칙 정의
     */
    public function rules(): array
    {
        return [
            // 필수: 프로파일 정보
            'dealer_code' => [
                'required',
                'string',
                'max:50',
                Rule::exists('dealer_profiles', 'dealer_code')
                    ->where('status', 'active')
            ],
            
            // 필수: 배치 데이터
            'rows' => [
                'required',
                'array',
                'min:1',
                'max:' . self::MAX_ROWS
            ],
            'rows.*' => 'required|array',
            
            // 각 행의 필수 필드
            'rows.*.priceSettling' => 'required|numeric|min:0|max:999999999',
            
            // 각 행의 선택적 필드
            'rows.*.verbal1' => 'nullable|numeric|min:-999999999|max:999999999',
            'rows.*.verbal2' => 'nullable|numeric|min:-999999999|max:999999999',
            'rows.*.gradeAmount' => 'nullable|numeric|min:-999999999|max:999999999',
            'rows.*.additionalAmount' => 'nullable|numeric|min:-999999999|max:999999999',
            'rows.*.documentCash' => 'nullable|numeric|min:0|max:999999999',
            'rows.*.simFee' => 'nullable|numeric|min:0|max:999999999',
            'rows.*.mnpDiscount' => 'nullable|numeric|min:-999999999|max:0',
            'rows.*.deduction' => 'nullable|numeric|min:-999999999|max:999999999',
            'rows.*.cashReceived' => 'nullable|numeric|min:0|max:999999999',
            'rows.*.payback' => 'nullable|numeric|min:-999999999|max:0',
            
            // 메타데이터 (선택적)
            'rows.*.seller' => 'nullable|string|max:100',
            'rows.*.dealer' => 'nullable|string|max:100',
            'rows.*.carrier' => 'nullable|string|max:50',
            'rows.*.activationType' => 'nullable|string|in:신규,MNP,기변',
            'rows.*.modelName' => 'nullable|string|max:200',
            'rows.*.activationDate' => 'nullable|date',
            'rows.*.customerName' => 'nullable|string|max:100',
            'rows.*.memo' => 'nullable|string|max:1000',
            
            // 배치 옵션
            'options' => 'sometimes|array',
            'options.format' => 'sometimes|string|in:laravel,ykp',
            'options.validate_profile' => 'sometimes|boolean',
            'options.include_performance' => 'sometimes|boolean',
            'options.stop_on_error' => 'sometimes|boolean',
            'options.parallel_processing' => 'sometimes|boolean',
            'options.chunk_size' => 'sometimes|integer|min:1|max:100',
        ];
    }

    /**
     * 사용자 정의 검증 메시지
     */
    public function messages(): array
    {
        return [
            'dealer_code.required' => '대리점 코드는 필수입니다',
            'dealer_code.exists' => '활성화된 대리점 프로파일을 찾을 수 없습니다',
            'rows.required' => '계산할 행 데이터는 필수입니다',
            'rows.min' => '최소 1개 이상의 행이 필요합니다',
            'rows.max' => '최대 ' . self::MAX_ROWS . '개까지만 처리 가능합니다',
            'rows.*.priceSettling.required' => '각 행의 액면가/셋팅가는 필수입니다',
            'rows.*.priceSettling.min' => '액면가/셋팅가는 0 이상이어야 합니다',
            'options.chunk_size.max' => '청크 크기는 최대 100까지 설정 가능합니다',
        ];
    }

    /**
     * 검증 실패 시 속성명 사용자 정의
     */
    public function attributes(): array
    {
        return [
            'dealer_code' => '대리점 코드',
            'rows' => '계산 행 데이터',
            'rows.*.priceSettling' => '액면가/셋팅가',
            'rows.*.verbal1' => '구두1',
            'rows.*.verbal2' => '구두2',
            'rows.*.gradeAmount' => '그레이드',
            'rows.*.additionalAmount' => '부가추가',
            'rows.*.documentCash' => '서류상현금개통',
            'rows.*.simFee' => '유심비',
            'rows.*.mnpDiscount' => 'MNP 할인',
            'rows.*.deduction' => '차감',
            'rows.*.cashReceived' => '현금받음',
            'rows.*.payback' => '페이백',
        ];
    }

    /**
     * 검증 준비 (데이터 전처리)
     */
    protected function prepareForValidation(): void
    {
        $rows = $this->input('rows', []);
        $numericFields = [
            'priceSettling', 'verbal1', 'verbal2', 'gradeAmount', 'additionalAmount',
            'documentCash', 'simFee', 'mnpDiscount', 'deduction', 'cashReceived', 'payback'
        ];

        // 각 행의 숫자 필드 전처리
        foreach ($rows as $index => $row) {
            foreach ($numericFields as $field) {
                if (isset($row[$field]) && $row[$field] === '') {
                    $rows[$index][$field] = null;
                }
            }
        }

        $this->merge(['rows' => $rows]);
    }

    /**
     * 추가 검증 로직
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // 배치 크기 권장사항 체크
            $this->checkBatchSize($validator);
            
            // 프로파일 기반 검증
            if ($this->has('dealer_code') && !$validator->errors()->has('dealer_code')) {
                $this->validateWithProfile($validator);
            }
            
            // 데이터 일관성 검증
            $this->validateDataConsistency($validator);
        });
    }

    /**
     * 배치 크기 권장사항 체크
     */
    protected function checkBatchSize($validator): void
    {
        $rowCount = count($this->input('rows', []));
        
        if ($rowCount > self::RECOMMENDED_BATCH_SIZE) {
            // 경고 메시지 (검증 실패는 아님)
            $validator->warnings[] = "권장 배치 크기({자아}개)를 초과했습니다. 성능에 영향을 줄 수 있습니다.";
        }
    }

    /**
     * 프로파일 기반 검증
     */
    protected function validateWithProfile($validator): void
    {
        try {
            $profile = DealerProfile::active()
                ->byDealerCode($this->input('dealer_code'))
                ->first();

            if (!$profile) {
                $validator->errors()->add('dealer_code', '프로파일을 찾을 수 없습니다');
                return;
            }

            // 프로파일 검증
            $profileValidation = $profile->validateProfile();
            if (!$profileValidation['valid']) {
                $validator->errors()->add('dealer_code', 
                    '프로파일 설정 오류: ' . implode(', ', $profileValidation['errors'])
                );
            }

        } catch (\Exception $e) {
            $validator->errors()->add('dealer_code', '프로파일 검증 중 오류 발생');
        }
    }

    /**
     * 데이터 일관성 검증
     */
    protected function validateDataConsistency($validator): void
    {
        $rows = $this->input('rows', []);
        
        // 대리점 코드 일관성 체크
        $dealerCodes = array_filter(array_column($rows, 'dealer'));
        if (!empty($dealerCodes)) {
            $uniqueDealers = array_unique($dealerCodes);
            if (count($uniqueDealers) > 1) {
                $validator->errors()->add('rows', 
                    '배치 내 모든 행은 같은 대리점 코드를 가져야 합니다'
                );
            }
        }
        
        // 날짜 형식 일관성 체크
        foreach ($rows as $index => $row) {
            if (isset($row['activationDate']) && $row['activationDate']) {
                if (!strtotime($row['activationDate'])) {
                    $validator->errors()->add("rows.{$index}.activationDate", 
                        '올바른 날짜 형식이 아닙니다'
                    );
                }
            }
        }
    }

    /**
     * 처리 옵션 반환
     */
    public function getProcessingOptions(): array
    {
        $options = $this->input('options', []);
        
        return array_merge([
            'format' => 'ykp',
            'validate_profile' => true,
            'include_performance' => false,
            'stop_on_error' => false,
            'parallel_processing' => false,
            'chunk_size' => 50,
        ], $options);
    }

    /**
     * 청크 크기 계산 (성능 최적화)
     */
    public function getOptimalChunkSize(): int
    {
        $rowCount = count($this->input('rows', []));
        $chunkSize = $this->input('options.chunk_size', 50);
        
        // 행 수에 따른 최적 청크 크기 계산
        if ($rowCount <= 50) {
            return $rowCount;
        } elseif ($rowCount <= 200) {
            return min($chunkSize, 25);
        } else {
            return min($chunkSize, 50);
        }
    }

    /**
     * 성능 예상치 계산
     */
    public function getPerformanceEstimate(): array
    {
        $rowCount = count($this->input('rows', []));
        $chunkSize = $this->getOptimalChunkSize();
        
        // 평균 처리 시간 추정 (행당 ~2ms)
        $avgTimePerRow = 2; // ms
        $estimatedTotalTime = $rowCount * $avgTimePerRow;
        $estimatedChunks = ceil($rowCount / $chunkSize);
        
        return [
            'total_rows' => $rowCount,
            'chunk_size' => $chunkSize,
            'estimated_chunks' => $estimatedChunks,
            'estimated_total_time_ms' => $estimatedTotalTime,
            'estimated_time_per_chunk_ms' => $chunkSize * $avgTimePerRow,
            'performance_level' => $this->getPerformanceLevel($estimatedTotalTime),
        ];
    }

    /**
     * 성능 레벨 판단
     */
    protected function getPerformanceLevel(float $estimatedTime): string
    {
        if ($estimatedTime < 1000) { // 1초 미만
            return 'excellent';
        } elseif ($estimatedTime < 5000) { // 5초 미만
            return 'good';
        } elseif ($estimatedTime < 15000) { // 15초 미만
            return 'fair';
        } else {
            return 'slow';
        }
    }

    /**
     * 프로파일 객체 반환
     */
    public function getDealerProfile(): ?DealerProfile
    {
        return DealerProfile::active()
            ->byDealerCode($this->input('dealer_code'))
            ->first();
    }
}