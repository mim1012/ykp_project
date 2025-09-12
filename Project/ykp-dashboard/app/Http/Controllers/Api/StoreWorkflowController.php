<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\Branch;
use App\Models\User;
use App\Models\StoreRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class StoreWorkflowController extends Controller
{
    /**
     * 본사: 즉시 승인형 매장 추가
     */
    public function createStoreImmediate(Request $request): JsonResponse
    {
        if (Auth::user()->role !== 'headquarters') {
            return response()->json(['error' => '본사 관리자만 즉시 매장을 추가할 수 있습니다.'], 403);
        }
        
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:stores,code',
            'branch_id' => 'required|exists:branches,id',
            'owner_name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'address' => 'required|string|max:500',
            'create_account' => 'boolean',
            'user_name' => 'required_if:create_account,true',
            'user_email' => 'required_if:create_account,true|email|unique:users,email',
            'user_password' => 'required_if:create_account,true|min:6'
        ]);
        
        return DB::transaction(function () use ($request) {
            // 1. 매장 생성
            $store = Store::create([
                'name' => $request->name,
                'code' => $request->code,
                'branch_id' => $request->branch_id,
                'owner_name' => $request->owner_name,
                'phone' => $request->phone,
                'address' => $request->address,
                'status' => 'active',
                'opened_at' => now(),
                'created_by' => Auth::id()
            ]);
            
            // 2. 계정 생성 (옵션)
            $user = null;
            if ($request->create_account) {
                $user = User::create([
                    'name' => $request->user_name,
                    'email' => $request->user_email,
                    'password' => Hash::make($request->user_password),
                    'role' => 'store',
                    'store_id' => $store->id,
                    'branch_id' => $store->branch_id,
                    'is_active' => true,
                    'created_by_user_id' => Auth::id()
                ]);
            }
            
            // 3. 로그 기록
            Log::info('Store created immediately by HQ', [
                'store_id' => $store->id,
                'store_code' => $store->code,
                'branch_id' => $store->branch_id,
                'user_created' => $user ? true : false,
                'created_by' => Auth::id()
            ]);
            
            // 4. 지사에 알림 (Supabase 실시간)
            $this->notifyBranchManagers($store->branch_id, 'new_store_added', $store);
            
            return response()->json([
                'success' => true,
                'message' => '매장이 즉시 생성되었습니다.',
                'data' => [
                    'store' => $store->load('branch'),
                    'user' => $user
                ]
            ], 201);
        });
    }
    
    /**
     * 지사: 매장 추가 요청 (본사 승인 필요)
     */
    public function requestStore(Request $request): JsonResponse
    {
        if (Auth::user()->role !== 'branch') {
            return response()->json(['error' => '지사 관리자만 매장 추가를 요청할 수 있습니다.'], 403);
        }
        
        $request->validate([
            'store_name' => 'required|string|max:255',
            'store_code' => 'required|string|unique:stores,code|unique:store_requests,store_code',
            'owner_name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'address' => 'required|string|max:500',
            'business_license' => 'nullable|string',
            'request_reason' => 'required|string|max:1000'
        ]);
        
        $storeRequest = StoreRequest::create([
            'requested_by' => Auth::id(),
            'branch_id' => Auth::user()->branch_id,
            'store_name' => $request->store_name,
            'store_code' => $request->store_code,
            'owner_name' => $request->owner_name,
            'phone' => $request->phone,
            'address' => $request->address,
            'business_license' => $request->business_license,
            'request_reason' => $request->request_reason,
            'status' => StoreRequest::STATUS_PENDING
        ]);
        
        // 본사에 알림
        $this->notifyHeadquarters('store_request_submitted', $storeRequest);
        
        return response()->json([
            'success' => true,
            'message' => '매장 추가 요청이 제출되었습니다. 본사 검토 후 승인됩니다.',
            'data' => $storeRequest
        ], 201);
    }
    
    /**
     * 본사: 매장 요청 승인/반려
     */
    public function reviewStoreRequest(Request $request, StoreRequest $storeRequest): JsonResponse
    {
        if (Auth::user()->role !== 'headquarters') {
            return response()->json(['error' => '본사 관리자만 요청을 검토할 수 있습니다.'], 403);
        }
        
        $request->validate([
            'action' => 'required|in:approve,reject',
            'comment' => 'nullable|string|max:500'
        ]);
        
        return DB::transaction(function () use ($request, $storeRequest) {
            if ($request->action === 'approve') {
                // 승인: 실제 매장 생성
                $store = Store::create([
                    'name' => $storeRequest->store_name,
                    'code' => $storeRequest->store_code,
                    'branch_id' => $storeRequest->branch_id,
                    'owner_name' => $storeRequest->owner_name,
                    'phone' => $storeRequest->phone,
                    'address' => $storeRequest->address,
                    'status' => 'active',
                    'opened_at' => now(),
                    'created_by' => Auth::id()
                ]);
                
                $storeRequest->update([
                    'status' => StoreRequest::STATUS_APPROVED,
                    'reviewed_by' => Auth::id(),
                    'reviewed_at' => now(),
                    'review_comment' => $request->comment
                ]);
                
                // 지사에 승인 알림
                $this->notifyBranchManagers($storeRequest->branch_id, 'store_approved', $store);
                
                return response()->json([
                    'success' => true,
                    'message' => '매장 요청이 승인되어 생성되었습니다.',
                    'data' => $store
                ]);
                
            } else {
                // 반려
                $storeRequest->update([
                    'status' => StoreRequest::STATUS_REJECTED,
                    'reviewed_by' => Auth::id(),
                    'reviewed_at' => now(),
                    'review_comment' => $request->comment
                ]);
                
                // 지사에 반려 알림
                $this->notifyBranchManagers($storeRequest->branch_id, 'store_rejected', $storeRequest);
                
                return response()->json([
                    'success' => true,
                    'message' => '매장 요청이 반려되었습니다.',
                    'data' => $storeRequest
                ]);
            }
        });
    }
    
    /**
     * 지사: 본인 지사 매장 관리
     */
    public function getMyStores(Request $request): JsonResponse
    {
        if (!in_array(Auth::user()->role, ['branch', 'headquarters'])) {
            return response()->json(['error' => '지사 또는 본사 관리자만 접근 가능합니다.'], 403);
        }
        
        $query = Store::with(['branch', 'users']);
        
        if (Auth::user()->role === 'branch') {
            $query->where('branch_id', Auth::user()->branch_id);
        }
        
        $stores = $query->get()->map(function ($store) {
            return [
                'id' => $store->id,
                'name' => $store->name,
                'code' => $store->code,
                'owner_name' => $store->owner_name,
                'phone' => $store->phone,
                'status' => $store->status,
                'users_count' => $store->users->count(),
                'last_sale_date' => $store->sales()->latest('sale_date')->first()?->sale_date,
                'monthly_sales' => $store->sales()->whereYear('sale_date', now()->year)
                    ->whereMonth('sale_date', now()->month)
                    ->sum('settlement_amount'),
                'can_manage' => true // 지사는 소속 매장 관리 가능
            ];
        });
        
        return response()->json([
            'success' => true,
            'data' => $stores
        ]);
    }
    
    /**
     * 알림 발송 (Supabase 실시간)
     */
    private function notifyHeadquarters(string $event, $data): void
    {
        // Supabase 실시간 알림 (향후 구현)
        Log::info('Notification to HQ', [
            'event' => $event,
            'data' => $data,
            'timestamp' => now()
        ]);
    }
    
    private function notifyBranchManagers(int $branchId, string $event, $data): void
    {
        // Supabase 실시간 알림 (향후 구현)  
        Log::info('Notification to Branch', [
            'branch_id' => $branchId,
            'event' => $event,
            'data' => $data,
            'timestamp' => now()
        ]);
    }
}