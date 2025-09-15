<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sales' => ['required', 'array', 'min:1', 'max:1000'],
            'sales.*.sale_date' => ['required', 'date'],
            'sales.*.carrier' => ['required', 'in:SK,KT,LG,MVNO'],
            'sales.*.activation_type' => ['required', 'in:신규,기변,MNP'],
            'sales.*.model_name' => ['required', 'string', 'max:100'],
            'sales.*.base_price' => ['nullable', 'numeric', 'min:0'],
            'sales.*.verbal1' => ['nullable', 'numeric'],
            'sales.*.verbal2' => ['nullable', 'numeric'],
            'sales.*.grade_amount' => ['nullable', 'numeric'],
            'sales.*.additional_amount' => ['nullable', 'numeric'],
            'sales.*.cash_activation' => ['nullable', 'numeric'],
            'sales.*.usim_fee' => ['nullable', 'numeric'],
            'sales.*.new_mnp_discount' => ['nullable', 'numeric'],
            'sales.*.deduction' => ['nullable', 'numeric'],
            'sales.*.cash_received' => ['nullable', 'numeric'],
            'sales.*.payback' => ['nullable', 'numeric'],
            'sales.*.monthly_fee' => ['nullable', 'numeric'],
            'sales.*.phone_number' => ['nullable', 'string', 'max:20'],
            'sales.*.salesperson' => ['nullable', 'string', 'max:50'],
            'sales.*.memo' => ['nullable', 'string', 'max:255'],

            // 신규 필드 검증 추가
            'sales.*.dealer_code' => ['nullable', 'string', 'max:50'],
            'sales.*.dealer_name' => ['nullable', 'string', 'max:100'],
            'sales.*.serial_number' => ['nullable', 'string', 'max:100'],
            'sales.*.customer_name' => ['nullable', 'string', 'max:100'],
            'sales.*.customer_birth_date' => ['nullable', 'date'],

            // 요청 레벨 dealer_code (전체 적용)
            'dealer_code' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'sales.required' => '판매 데이터가 필요합니다.',
            'sales.max' => '한 번에 최대 1000개까지만 저장할 수 있습니다.',
            'sales.*.carrier.in' => '유효하지 않은 통신사입니다.',
            'sales.*.activation_type.in' => '유효하지 않은 개통 유형입니다.',
        ];
    }
}
