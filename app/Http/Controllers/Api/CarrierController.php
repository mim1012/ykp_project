<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Carrier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CarrierController extends Controller
{
    /**
     * 통신사 목록 조회
     */
    public function index()
    {
        try {
            $carriers = Carrier::orderBy('sort_order', 'asc')->get();

            return response()->json([
                'success' => true,
                'data' => $carriers
            ]);
        } catch (\Exception $e) {
            Log::error('Carrier index error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => '통신사 목록을 불러오는 중 오류가 발생했습니다.',
                'debug' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * 통신사 추가 (본사만 가능)
     */
    public function store(Request $request)
    {
        try {
            // 권한 체크: 본사만 추가 가능
            if (Auth::user()->role !== 'headquarters') {
                return response()->json([
                    'success' => false,
                    'message' => '본사 관리자만 통신사를 추가할 수 있습니다.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'code' => 'required|string|max:20|unique:carriers,code',
                'name' => 'required|string|max:50',
                'sort_order' => 'nullable|integer|min:0'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '입력값이 올바르지 않습니다.',
                    'errors' => $validator->errors()
                ], 422);
            }

            $carrier = Carrier::create([
                'code' => strtoupper($request->code),
                'name' => $request->name,
                'is_active' => true,
                'sort_order' => $request->sort_order ?? Carrier::max('sort_order') + 1
            ]);

            return response()->json([
                'success' => true,
                'message' => '통신사가 추가되었습니다.',
                'data' => $carrier
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '통신사 추가 중 오류가 발생했습니다.'
            ], 500);
        }
    }

    /**
     * 통신사 수정 (본사만 가능)
     */
    public function update(Request $request, $id)
    {
        try {
            // 권한 체크
            if (Auth::user()->role !== 'headquarters') {
                return response()->json([
                    'success' => false,
                    'message' => '본사 관리자만 통신사를 수정할 수 있습니다.'
                ], 403);
            }

            $carrier = Carrier::find($id);

            if (!$carrier) {
                return response()->json([
                    'success' => false,
                    'message' => '통신사를 찾을 수 없습니다.'
                ], 404);
            }

            // 기본 통신사는 수정 제한
            if (in_array($carrier->code, ['SK', 'KT', 'LG'])) {
                return response()->json([
                    'success' => false,
                    'message' => '기본 통신사는 수정할 수 없습니다.'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:50',
                'is_active' => 'boolean',
                'sort_order' => 'nullable|integer|min:0'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '입력값이 올바르지 않습니다.',
                    'errors' => $validator->errors()
                ], 422);
            }

            $carrier->update([
                'name' => $request->name,
                'is_active' => $request->is_active ?? $carrier->is_active,
                'sort_order' => $request->sort_order ?? $carrier->sort_order
            ]);

            return response()->json([
                'success' => true,
                'message' => '통신사 정보가 수정되었습니다.',
                'data' => $carrier
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '통신사 수정 중 오류가 발생했습니다.'
            ], 500);
        }
    }

    /**
     * 통신사 삭제 (본사만 가능)
     */
    public function destroy($id)
    {
        try {
            // 권한 체크
            if (Auth::user()->role !== 'headquarters') {
                return response()->json([
                    'success' => false,
                    'message' => '본사 관리자만 통신사를 삭제할 수 있습니다.'
                ], 403);
            }

            $carrier = Carrier::find($id);

            if (!$carrier) {
                return response()->json([
                    'success' => false,
                    'message' => '통신사를 찾을 수 없습니다.'
                ], 404);
            }

            // 기본 통신사는 삭제 불가
            if (in_array($carrier->code, ['SK', 'KT', 'LG', 'MVNO'])) {
                return response()->json([
                    'success' => false,
                    'message' => '기본 통신사는 삭제할 수 없습니다.'
                ], 400);
            }

            // 비활성화 처리 (완전 삭제 대신)
            $carrier->update(['is_active' => false]);

            return response()->json([
                'success' => true,
                'message' => '통신사가 비활성화되었습니다.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '통신사 삭제 중 오류가 발생했습니다.'
            ], 500);
        }
    }
}