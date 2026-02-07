<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DealerProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class DealerProfileController extends Controller
{
    public function index(Request $request)
    {
        try {
            // 권한 체크: 모든 사용자가 대리점 목록 조회 가능 (읽기 전용)

            $dealers = DealerProfile::query()
                ->when($request->status, function ($query, $status) {
                    return $query->where('status', $status);
                })
                ->orderBy('dealer_name', 'asc')
                ->get();

            return $this->jsonSuccess($dealers);
        } catch (\Exception $e) {
            return $this->handleException($e, '대리점 목록을 불러오는 중 오류가 발생했습니다.');
        }
    }

    public function store(Request $request)
    {
        try {
            // 권한 체크: 본사, 지사만 접근 가능
            if (!in_array(Auth::user()->role, ['headquarters', 'branch'])) {
                return $this->jsonError('권한이 없습니다.', 403);
            }

            $validator = Validator::make($request->all(), [
                'dealer_code' => 'required|string|max:50|unique:dealer_profiles,dealer_code',
                'dealer_name' => 'required|string|max:255',
                'contact_person' => 'nullable|string|max:100',
                'phone' => 'nullable|string|max:20',
                'address' => 'nullable|string|max:500',
                'default_sim_fee' => 'nullable|numeric|min:0',
                'default_mnp_discount' => 'nullable|numeric',
                'tax_rate' => 'nullable|numeric|min:0|max:1',
                'default_payback_rate' => 'nullable|numeric|min:0|max:100',
                'auto_calculate_tax' => 'boolean',
                'include_sim_fee_in_settlement' => 'boolean',
                'status' => 'in:active,inactive'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '입력값이 올바르지 않습니다.',
                    'errors' => $validator->errors()
                ], 422);
            }

            $dealer = DealerProfile::create(array_merge(
                $request->all(),
                [
                    'status' => $request->status ?? 'active',
                    'activated_at' => now(),
                    'tax_rate' => $request->tax_rate ?? 0.1,
                    'auto_calculate_tax' => $request->auto_calculate_tax ?? true,
                    'include_sim_fee_in_settlement' => $request->include_sim_fee_in_settlement ?? true
                ]
            ));

            Log::info('대리점 추가', [
                'dealer_id' => $dealer->id,
                'dealer_code' => $dealer->dealer_code,
                'user_id' => Auth::id()
            ]);

            return $this->jsonSuccess($dealer, '대리점이 추가되었습니다.', 201);
        } catch (\Exception $e) {
            return $this->handleException($e, '대리점 추가 중 오류가 발생했습니다.');
        }
    }

    public function update(Request $request, $id)
    {
        try {
            // 권한 체크: 본사, 지사만 접근 가능
            if (!in_array(Auth::user()->role, ['headquarters', 'branch'])) {
                return $this->jsonError('권한이 없습니다.', 403);
            }

            $dealer = DealerProfile::find($id);

            if (!$dealer) {
                return $this->jsonError('대리점을 찾을 수 없습니다.', 404);
            }

            $validator = Validator::make($request->all(), [
                'dealer_code' => 'required|string|max:50|unique:dealer_profiles,dealer_code,'.$id,
                'dealer_name' => 'required|string|max:255',
                'contact_person' => 'nullable|string|max:100',
                'phone' => 'nullable|string|max:20',
                'address' => 'nullable|string|max:500',
                'default_sim_fee' => 'nullable|numeric|min:0',
                'default_mnp_discount' => 'nullable|numeric',
                'tax_rate' => 'nullable|numeric|min:0|max:1',
                'default_payback_rate' => 'nullable|numeric|min:0|max:100',
                'auto_calculate_tax' => 'boolean',
                'include_sim_fee_in_settlement' => 'boolean',
                'status' => 'in:active,inactive'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '입력값이 올바르지 않습니다.',
                    'errors' => $validator->errors()
                ], 422);
            }

            $dealer->update($request->all());

            // 상태 변경 시 날짜 업데이트
            if ($request->has('status')) {
                if ($request->status === 'inactive' && !$dealer->deactivated_at) {
                    $dealer->deactivated_at = now();
                } elseif ($request->status === 'active' && $dealer->deactivated_at) {
                    $dealer->deactivated_at = null;
                    $dealer->activated_at = now();
                }
                $dealer->save();
            }

            Log::info('대리점 수정', [
                'dealer_id' => $dealer->id,
                'dealer_code' => $dealer->dealer_code,
                'user_id' => Auth::id()
            ]);

            return $this->jsonSuccess($dealer, '대리점 정보가 수정되었습니다.');
        } catch (\Exception $e) {
            return $this->handleException($e, '대리점 수정 중 오류가 발생했습니다.');
        }
    }

    public function destroy($id)
    {
        try {
            // 권한 체크: 본사만 삭제 가능
            if (Auth::user()->role !== 'headquarters') {
                return $this->jsonError('본사 관리자만 삭제할 수 있습니다.', 403);
            }

            $dealer = DealerProfile::find($id);

            if (!$dealer) {
                return $this->jsonError('대리점을 찾을 수 없습니다.', 404);
            }

            // 관련 판매 데이터가 있는지 확인
            if ($dealer->sales()->exists()) {
                // 소프트 삭제 (비활성화)
                $dealer->update([
                    'status' => 'inactive',
                    'deactivated_at' => now()
                ]);

                Log::info('대리점 비활성화 (판매 데이터 존재)', [
                    'dealer_id' => $dealer->id,
                    'dealer_code' => $dealer->dealer_code,
                    'user_id' => Auth::id()
                ]);

                return $this->jsonSuccess($dealer, '판매 데이터가 존재하여 비활성화 처리되었습니다.');
            } else {
                // 하드 삭제
                $dealerCode = $dealer->dealer_code;
                $dealer->delete();

                Log::info('대리점 삭제', [
                    'dealer_code' => $dealerCode,
                    'user_id' => Auth::id()
                ]);

                return $this->jsonSuccess(null, '대리점이 삭제되었습니다.');
            }
        } catch (\Exception $e) {
            return $this->handleException($e, '대리점 삭제 중 오류가 발생했습니다.');
        }
    }
}
