<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        // 본사 사용자만 다른 사용자를 생성할 수 있음
        return auth()->user() && auth()->user()->isHeadquarters();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', 'in:headquarters,branch,store'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'store_id' => ['nullable', 'integer', 'exists:stores,id'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $role = $this->input('role');
            $branchId = $this->input('branch_id');
            $storeId = $this->input('store_id');

            // 역할별 필수 필드 검증
            switch ($role) {
                case 'headquarters':
                    if ($branchId || $storeId) {
                        $validator->errors()->add('role', '본사 계정은 지사나 매장 정보를 가질 수 없습니다.');
                    }
                    break;

                case 'branch':
                    if (!$branchId) {
                        $validator->errors()->add('branch_id', '지사 계정은 지사 정보가 필요합니다.');
                    }
                    if ($storeId) {
                        $validator->errors()->add('store_id', '지사 계정은 매장 정보를 가질 수 없습니다.');
                    }
                    break;

                case 'store':
                    if (!$branchId) {
                        $validator->errors()->add('branch_id', '매장 계정은 지사 정보가 필요합니다.');
                    }
                    if (!$storeId) {
                        $validator->errors()->add('store_id', '매장 계정은 매장 정보가 필요합니다.');
                    }
                    
                    // 매장이 지사에 속하는지 확인
                    if ($branchId && $storeId) {
                        $store = \App\Models\Store::find($storeId);
                        if ($store && $store->branch_id != $branchId) {
                            $validator->errors()->add('store_id', '선택한 매장이 해당 지사에 속하지 않습니다.');
                        }
                    }
                    break;
            }
        });
    }

    public function messages(): array
    {
        return [
            'name.required' => '이름은 필수입니다.',
            'email.required' => '이메일은 필수입니다.',
            'email.unique' => '이미 사용 중인 이메일입니다.',
            'password.required' => '비밀번호는 필수입니다.',
            'password.min' => '비밀번호는 최소 8자 이상이어야 합니다.',
            'password.confirmed' => '비밀번호 확인이 일치하지 않습니다.',
            'role.required' => '사용자 역할을 선택해주세요.',
            'role.in' => '유효하지 않은 사용자 역할입니다.',
            'branch_id.exists' => '존재하지 않는 지사입니다.',
            'store_id.exists' => '존재하지 않는 매장입니다.',
        ];
    }
}