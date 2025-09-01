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
        if (Auth::user()->role !== 'headquarters') {
            return response()->json(['error' => '본사 관리자만 매장을 추가할 수 있습니다.'], 403);
        }
        
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:stores,code',
            'branch_id' => 'required|exists:branches,id',
            'owner_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500'
        ]);
        
        return DB::transaction(function () use ($request) {
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
}