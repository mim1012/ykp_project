<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\DealerProfile;

/**
 * 프로파일 기반 계산 요청 검증
 */
class ProfileCalculationRequest extends FormRequest
{
    /**
     * 요청 권한 확인
     */
    public function authorize(): bool
    {
        // API는 기본적으로 허용, 필요시 권한 로직 추가
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
            
            // 필수: 계산 데이터
            'data' => 'required|array',
            
            // 계산 입력 필드 (ykp-settlement 형식)
            'data.priceSettling' => 'required|numeric|min:0|max:999999999',
            'data.verbal1' => 'nullable|numeric|min:-999999999|max:999999999',
            'data.verbal2' => 'nullable|numeric|min:-999999999|max:999999999',
            'data.gradeAmount' => 'nullable|numeric|min:-999999999|max:999999999',
            'data.additionalAmount' => 'nullable|numeric|min:-999999999|max:999999999',
            'data.documentCash' => 'nullable|numeric|min:0|max:999999999',
            'data.simFee' => 'nullable|numeric|min:0|max:999999999',
            'data.mnpDiscount' => 'nullable|numeric|min:-999999999|max:0', // 할인은 음수
            'data.deduction' => 'nullable|numeric|min:-999999999|max:999999999',
            'data.cashReceived' => 'nullable|numeric|min:0|max:999999999',
            'data.payback' => 'nullable|numeric|min:-999999999|max:0', // 페이백은 음수
            
            // 선택적: 메타데이터
            'data.seller' => 'nullable|string|max:100',
            'data.dealer' => 'nullable|string|max:100',
            'data.carrier' => 'nullable|string|max:50',
            'data.activationType' => 'nullable|string|in:신규,MNP,기변',
            'data.modelName' => 'nullable|string|max:200',
            'data.activationDate' => 'nullable|date',
            'data.customerName' => 'nullable|string|max:100',
            'data.memo' => 'nullable|string|max:1000',
            
            // 선택적: 옵션
            'options' => 'sometimes|array',
            'options.format' => 'sometimes|string|in:laravel,ykp',
            'options.validate_profile' => 'sometimes|boolean',
            'options.include_performance' => 'sometimes|boolean',
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
            'data.required' => '계산 데이터는 필수입니다',
            'data.priceSettling.required' => '액면가/셋팅가는 필수입니다',
            'data.priceSettling.min' => '액면가/셋팅가는 0 이상이어야 합니다',
            'data.documentCash.min' => '서류상현금개통은 음수가 될 수 없습니다',
            'data.simFee.min' => '유심비는 음수가 될 수 없습니다',
            'data.mnpDiscount.max' => 'MNP 할인은 양수가 될 수 없습니다',
            'data.payback.max' => '페이백은 양수가 될 수 없습니다',
            'data.activationType.in' => '개통방식은 신규, MNP, 기변 중 하나여야 합니다',
        ];
    }

    /**
     * 검증 실패 시 속성명 사용자 정의
     */
    public function attributes(): array
    {
        return [
            'dealer_code' => '대리점 코드',
            'data.priceSettling' => '액면가/셋팅가',
            'data.verbal1' => '구두1',
            'data.verbal2' => '구두2',
            'data.gradeAmount' => '그레이드',
            'data.additionalAmount' => '부가추가',
            'data.documentCash' => '서류상현금개통',
            'data.simFee' => '유심비',
            'data.mnpDiscount' => 'MNP 할인',
            'data.deduction' => '차감',
            'data.cashReceived' => '현금받음',
            'data.payback' => '페이백',
            'data.seller' => '판매자',
            'data.dealer' => '대리점',
            'data.carrier' => '통신사',
            'data.activationType' => '개통방식',
            'data.modelName' => '모델명',
            'data.activationDate' => '개통일',
            'data.customerName' => '고객명',
            'data.memo' => '메모',
        ];
    }

    /**
     * 검증 준비 (데이터 전처리)
     */
    protected function prepareForValidation(): void
    {
        // 숫자 필드 전처리 (빈 문자열을 null로 변환)
        $numericFields = [
            'priceSettling', 'verbal1', 'verbal2', 'gradeAmount', 'additionalAmount',
            'documentCash', 'simFee', 'mnpDiscount', 'deduction', 'cashReceived', 'payback'
        ];

        $data = $this->input('data', []);
        
        foreach ($numericFields as $field) {
            if (isset($data[$field]) && $data[$field] === '') {
                $data[$field] = null;
            }
        }

        $this->merge(['data' => $data]);
    }

    /**
     * 추가 검증 로직 (프로파일 기반)
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // 프로파일 기반 커스텀 검증
            if ($this->has('dealer_code') && !$validator->errors()->has('dealer_code')) {
                $this->validateWithProfile($validator);
            }
        });
    }

    /**
     * 프로파일 기반 커스텀 검증
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

            // 프로파일 자체 검증
            $profileValidation = $profile->validateProfile();
            if (!$profileValidation['valid']) {
                $validator->errors()->add('dealer_code', 
                    '프로파일 설정에 오류가 있습니다: ' . implode(', ', $profileValidation['errors'])
                );
            }

            // 커스텀 규칙 검증
            if ($profile->custom_calculation_rules) {
                $this->validateCustomRules($validator, $profile);
            }

        } catch (\Exception $e) {
            $validator->errors()->add('dealer_code', '프로파일 검증 중 오류가 발생했습니다');
        }
    }

    /**
     * 커스텀 규칙 검증
     */
    protected function validateCustomRules($validator, DealerProfile $profile): void
    {
        $data = $this->input('data', []);
        
        foreach ($profile->custom_calculation_rules as $rule) {
            if (!isset($rule['validation'])) {
                continue;
            }

            $validation = $rule['validation'];

            // 필수 필드 검증
            if (isset($validation['required_fields'])) {
                foreach ($validation['required_fields'] as $field) {
                    if (empty($data[$field])) {
                        $validator->errors()->add("data.{$field}", 
                            "이 프로파일에서는 {$field}가 필수입니다"
                        );
                    }
                }
            }

            // 최소값 검증
            if (isset($validation['min_values'])) {
                foreach ($validation['min_values'] as $field => $minValue) {
                    if (isset($data[$field]) && $data[$field] < $minValue) {
                        $validator->errors()->add("data.{$field}", 
                            "{$field}는 최소 {$minValue} 이상이어야 합니다"
                        );
                    }
                }
            }

            // 최대값 검증
            if (isset($validation['max_values'])) {
                foreach ($validation['max_values'] as $field => $maxValue) {
                    if (isset($data[$field]) && $data[$field] > $maxValue) {
                        $validator->errors()->add("data.{$field}", 
                            "{$field}는 최대 {$maxValue} 이하여야 합니다"
                        );
                    }
                }
            }
        }
    }

    /**
     * 검증된 데이터 반환 (전처리된 형태)
     */
    public function getCalculationData(): array
    {
        $data = $this->validated()['data'];
        
        // null 값 제거 (기본값 적용을 위해)
        return array_filter($data, function($value) {
            return $value !== null && $value !== '';
        });
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