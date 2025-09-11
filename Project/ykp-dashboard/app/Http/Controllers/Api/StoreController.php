<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StoreController extends Controller
{
    /**
     * 매장 목록 조회
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        $query = Store::with(['branch']);
        
        // 권한별 필터링
        if ($user->role === 'branch') {
            $query->where('branch_id', $user->branch_id);
        } elseif ($user->role === 'store') {
            $query->where('id', $user->store_id);
        }
        
        $stores = $query->orderBy('name')->get();
        
        return response()->json([
            'success' => true,
            'data' => $stores
        ]);
    }
    
    /**
     * 새 매장 추가 (본사만) - Supabase 실시간 동기화 지원
     */
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        // 본사와 지사만 매장 추가 가능
        if (!in_array($user->role, ['headquarters', 'branch'])) {
            return response()->json(['error' => '본사 또는 지사 관리자만 매장을 추가할 수 있습니다.'], 403);
        }
        
        $validationRules = [
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:stores,code',
            'owner_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500'
        ];
        
        // 본사는 지사 선택 가능, 지사는 자기 지사로 고정
        if ($user->role === 'headquarters') {
            $validationRules['branch_id'] = 'required|exists:branches,id';
        }
        
        $request->validate($validationRules);
        
        // 지사인 경우 자기 지사로 강제 설정
        $branchId = $user->role === 'branch' ? $user->branch_id : $request->branch_id;
        
        return DB::transaction(function () use ($request, $user, $branchId) {
            $store = Store::create([
                'name' => $request->name,
                'code' => $request->code,
                'branch_id' => $branchId,
                'owner_name' => $request->owner_name,
                'phone' => $request->phone,
                'address' => $request->address,
                'status' => 'active',
                'opened_at' => now(),
                'created_by' => Auth::id()
            ]);
            
            // 로깅 (Supabase에서 추적 가능)
            Log::info('Store created', [
                'store_id' => $store->id,
                'store_code' => $store->code,
                'branch_id' => $store->branch_id,
                'created_by' => Auth::id(),
                'timestamp' => now()->toISOString()
            ]);
            
            // Supabase 실시간 알림 트리거 (향후 구현)
            // $this->triggerRealtimeUpdate('store_created', $store);
            
            return response()->json([
                'success' => true,
                'message' => '매장이 성공적으로 추가되었습니다.',
                'data' => $store->load('branch')
            ], 201);
        });
    }
    
    /**
     * 매장용 계정 생성 (본사만)
     */
    public function createStoreUser(Request $request, Store $store): JsonResponse
    {
        if (Auth::user()->role !== 'headquarters') {
            return response()->json(['error' => '본사 관리자만 매장 계정을 생성할 수 있습니다.'], 403);
        }
        
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6'
        ]);
        
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'store',
            'store_id' => $store->id,
            'branch_id' => $store->branch_id,
            'created_by_user_id' => Auth::id()
        ]);
        
        return response()->json([
            'success' => true,
            'message' => '매장 계정이 성공적으로 생성되었습니다.',
            'data' => $user
        ], 201);
    }
    
    /**
     * 매장 정보 수정 (본사/지사)
     */
    public function update(Request $request, Store $store): JsonResponse
    {
        $user = Auth::user();
        
        // 권한 확인: 본사 또는 해당 지사 관리자만 수정 가능
        if ($user->role === 'branch' && $store->branch_id !== $user->branch_id) {
            return response()->json(['error' => '권한이 없습니다.'], 403);
        } elseif ($user->role === 'store') {
            return response()->json(['error' => '매장 사용자는 매장 정보를 수정할 수 없습니다.'], 403);
        }
        
        $request->validate([
            'name' => 'required|string|max:255',
            'owner_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'status' => 'nullable|in:active,inactive,maintenance'
        ]);
        
        $store->update($request->only([
            'name', 'owner_name', 'phone', 'address', 'status'
        ]));
        
        Log::info('Store updated', [
            'store_id' => $store->id,
            'updated_by' => Auth::id(),
            'changes' => $request->only(['name', 'owner_name', 'phone', 'address', 'status'])
        ]);
        
        return response()->json([
            'success' => true,
            'message' => '매장 정보가 성공적으로 수정되었습니다.',
            'data' => $store->fresh()->load('branch')
        ]);
    }
    
    /**
     * 매장 계정 생성 (본사/지사)
     */
    public function createAccount(Request $request, Store $store): JsonResponse
    {
        $user = Auth::user();
        
        // 권한 확인
        if ($user->role === 'branch' && $store->branch_id !== $user->branch_id) {
            return response()->json(['error' => '권한이 없습니다.'], 403);
        } elseif ($user->role === 'store') {
            return response()->json(['error' => '매장 사용자는 계정을 생성할 수 없습니다.'], 403);
        }
        
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'role' => 'required|in:store,branch'
        ]);
        
        // 지사 관리자는 매장 계정만 생성 가능
        if ($user->role === 'branch' && $request->role !== 'store') {
            return response()->json(['error' => '지사 관리자는 매장 계정만 생성할 수 있습니다.'], 403);
        }
        
        $newUser = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'store_id' => $request->role === 'store' ? $store->id : null,
            'branch_id' => $store->branch_id,
            'created_by_user_id' => Auth::id()
        ]);
        
        Log::info('Store account created', [
            'store_id' => $store->id,
            'new_user_id' => $newUser->id,
            'role' => $newUser->role,
            'created_by' => Auth::id()
        ]);
        
        return response()->json([
            'success' => true,
            'message' => '계정이 성공적으로 생성되었습니다.',
            'data' => $newUser
        ], 201);
    }
    
    /**
     * 매장 삭제/비활성화 (본사만)
     */
    public function destroy(Store $store): JsonResponse
    {
        if (Auth::user()->role !== 'headquarters') {
            return response()->json(['error' => '본사 관리자만 매장을 삭제할 수 있습니다.'], 403);
        }
        
        return DB::transaction(function () use ($store) {
            // 연관된 판매 데이터가 있는 경우 소프트 삭제 (상태를 비활성화)
            $hasSales = $store->sales()->exists();
            
            if ($hasSales) {
                $store->update([
                    'status' => 'deleted',
                    'deleted_at' => now()
                ]);
                
                // 매장 사용자들도 비활성화
                $store->users()->update(['is_active' => false]);
                
                $message = '판매 데이터가 있어 매장이 비활성화되었습니다.';
            } else {
                // 판매 데이터가 없으면 완전 삭제
                $store->users()->delete();
                $store->delete();
                
                $message = '매장이 완전히 삭제되었습니다.';
            }
            
            Log::info('Store deleted/deactivated', [
                'store_id' => $store->id,
                'store_name' => $store->name,
                'had_sales' => $hasSales,
                'deleted_by' => Auth::id(),
                'action' => $hasSales ? 'deactivated' : 'deleted'
            ]);
            
            return response()->json([
                'success' => true,
                'message' => $message
            ]);
        });
    }
    
    /**
     * 매장 성과 조회 (본사/지사/해당 매장)
     */
    public function performance(Store $store): JsonResponse
    {
        $user = Auth::user();
        
        // 권한 확인
        if ($user->role === 'store' && $store->id !== $user->store_id) {
            return response()->json(['error' => '권한이 없습니다.'], 403);
        } elseif ($user->role === 'branch' && $store->branch_id !== $user->branch_id) {
            return response()->json(['error' => '권한이 없습니다.'], 403);
        }
        
        // 이번 달 성과
        $thisMonth = $store->sales()
            ->whereYear('sale_date', now()->year)
            ->whereMonth('sale_date', now()->month)
            ->selectRaw('
                COUNT(*) as total_count,
                COALESCE(SUM(settlement_amount), 0) as total_settlement,
                COALESCE(SUM(margin_after_tax), 0) as total_margin,
                COALESCE(AVG(settlement_amount), 0) as avg_settlement
            ')
            ->first();
        
        // 지난달 성과 (비교용)
        $lastMonth = $store->sales()
            ->whereYear('sale_date', now()->subMonth()->year)
            ->whereMonth('sale_date', now()->subMonth()->month)
            ->selectRaw('
                COUNT(*) as total_count,
                COALESCE(SUM(settlement_amount), 0) as total_settlement,
                COALESCE(SUM(margin_after_tax), 0) as total_margin
            ')
            ->first();
        
        return response()->json([
            'success' => true,
            'data' => [
                'store' => $store->only(['id', 'name', 'status']),
                'this_month' => $thisMonth,
                'last_month' => $lastMonth,
                'growth_rate' => $lastMonth->total_settlement > 0 
                    ? (($thisMonth->total_settlement - $lastMonth->total_settlement) / $lastMonth->total_settlement) * 100 
                    : 0
            ]
        ]);
    }
    
    /**
     * 지사 목록 조회
     */
    public function branches(): JsonResponse
    {
        $branches = Branch::withCount('stores')->orderBy('name')->get();
        
        return response()->json([
            'success' => true,
            'data' => $branches
        ]);
    }
    
    /**
     * 매장 계정 정보 조회
     */
    public function getAccount(Store $store): JsonResponse
    {
        $user = Auth::user();
        
        // 권한 확인
        if ($user->role === 'store' && $store->id !== $user->store_id) {
            return response()->json(['error' => '권한이 없습니다.'], 403);
        } elseif ($user->role === 'branch' && $store->branch_id !== $user->branch_id) {
            return response()->json(['error' => '권한이 없습니다.'], 403);
        }
        
        $storeUser = User::where('store_id', $store->id)
                        ->where('role', 'store')
                        ->first();
        
        if (!$storeUser) {
            return response()->json([
                'success' => false,
                'error' => '매장 계정이 존재하지 않습니다.'
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'data' => [
                'account' => [
                    'id' => $storeUser->id,
                    'name' => $storeUser->name,
                    'email' => $storeUser->email,
                    'created_at' => $storeUser->created_at
                ]
            ]
        ]);
    }
    
    /**
     * 매장 계정 생성 (개선된 버전)
     */
    public function createStoreAccount(Request $request, Store $store): JsonResponse
    {
        $user = Auth::user();
        
        // 권한 확인: 본사 또는 해당 지사 관리자만 가능
        if ($user->role === 'branch' && $store->branch_id !== $user->branch_id) {
            return response()->json(['error' => '권한이 없습니다.'], 403);
        } elseif ($user->role === 'store') {
            return response()->json(['error' => '매장 사용자는 계정을 생성할 수 없습니다.'], 403);
        }
        
        // 기존 계정 확인
        $existingUser = User::where('store_id', $store->id)
                           ->where('role', 'store')
                           ->first();
        
        if ($existingUser) {
            return response()->json([
                'success' => false,
                'error' => '이미 매장 계정이 존재합니다.'
            ], 409);
        }
        
        return DB::transaction(function () use ($request, $user, $store) {
            // 계정 정보 설정
            if ($user->role === 'headquarters' && $request->filled(['name', 'email', 'password'])) {
                // 본사: 사용자 입력 정보 사용
                $name = $request->name;
                $email = $request->email;
                $password = $request->password;
            } else {
                // 지사: 자동 생성
                $cleanName = preg_replace('/[^가-힣a-zA-Z0-9]/', '', $store->owner_name ?: $store->name);
                $name = $store->owner_name ? "{$store->owner_name} ({$store->code} 매장장)" : "매장관리자 ({$store->code})";
                $email = strtolower($store->code) . "@ykp.com";
                $password = 'store' . substr(md5($store->code . time()), 0, 6); // 6자리 랜덤 비밀번호
            }
            
            // 이메일 중복 확인
            $emailExists = User::where('email', $email)->exists();
            if ($emailExists) {
                $email = strtolower($store->code) . time() . "@ykp.com"; // 타임스탬프 추가
            }
            
            // 사용자 생성
            $newUser = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'role' => 'store',
                'store_id' => $store->id,
                'branch_id' => $store->branch_id,
                'created_by_user_id' => Auth::id()
            ]);
            
            Log::info('Store account created via new endpoint', [
                'store_id' => $store->id,
                'store_code' => $store->code,
                'new_user_id' => $newUser->id,
                'new_user_email' => $email,
                'created_by' => Auth::id(),
                'created_by_role' => $user->role
            ]);
            
            return response()->json([
                'success' => true,
                'message' => '매장 계정이 성공적으로 생성되었습니다.',
                'data' => [
                    'store' => $store->only(['id', 'name', 'code']),
                    'account' => [
                        'id' => $newUser->id,
                        'name' => $name,
                        'email' => $email,
                        'password' => $password, // 1회성 반환
                        'created_at' => $newUser->created_at
                    ]
                ]
            ], 201);
        });
    }
}