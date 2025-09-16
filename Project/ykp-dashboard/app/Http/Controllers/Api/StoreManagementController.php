<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class StoreManagementController extends Controller
{
    /**
     * Display stores based on user role
     */
    public function index(Request $request)
    {
        try {
            $user = auth()->user();
            
            // 권한별 매장 필터링
            if ($user->role === 'headquarters') {
                $stores = Store::with('branch')->get(); // 본사: 모든 매장
            } elseif ($user->role === 'branch') {
                $stores = Store::with('branch')
                         ->where('branch_id', $user->branch_id)
                         ->get(); // 지사: 소속 매장만
            } elseif ($user->role === 'store') {
                $stores = Store::with('branch')
                         ->where('id', $user->store_id)
                         ->get(); // 매장: 자기 매장만
            } else {
                $stores = collect(); // 기타: 빈 컬렉션
            }
            
            return response()->json(['success' => true, 'data' => $stores]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Store a newly created store
     */
    public function store(Request $request)
    {
        // 권한 검증: 본사와 지사만 매장 추가 가능
        $currentUser = auth()->user();
        if (!in_array($currentUser->role, ['headquarters', 'branch'])) {
            return response()->json(['success' => false, 'error' => '매장 추가 권한이 없습니다.'], 403);
        }
        
        // 지사 계정은 자기 지사에만 매장 추가 가능
        if ($currentUser->role === 'branch' && $request->branch_id != $currentUser->branch_id) {
            return response()->json(['success' => false, 'error' => '다른 지사에 매장을 추가할 권한이 없습니다.'], 403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'branch_id' => 'required|exists:branches,id',
            'owner_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
        ]);

        try {
            $branch = Branch::find($request->branch_id);
            $storeCount = Store::where('branch_id', $request->branch_id)->count();
            $autoCode = $branch->code . '-' . str_pad($storeCount + 1, 3, '0', STR_PAD_LEFT);

            // 매장 생성
            $store = Store::create([
                'name' => $request->name,
                'code' => $autoCode,
                'branch_id' => $request->branch_id,
                'owner_name' => $request->owner_name ?? '',
                'phone' => $request->phone ?? '',
                'address' => $request->address ?? '',
                'status' => 'active',
                'opened_at' => now()
            ]);

            // 매장 계정 자동 생성
            $autoPassword = 'store' . str_pad($store->id, 4, '0', STR_PAD_LEFT); // store0001 형태
            $autoEmail = $autoCode . '@ykp.com'; // 지사코드-매장번호@ykp.com

            $storeUser = User::create([
                'name' => $request->name . ' 매장',
                'email' => $autoEmail,
                'password' => Hash::make($autoPassword),
                'role' => 'store',
                'branch_id' => $request->branch_id,
                'store_id' => $store->id,
                'is_active' => true
            ]);

            return response()->json([
                'success' => true,
                'data' => $store,
                'account' => [
                    'email' => $autoEmail,
                    'password' => $autoPassword,
                    'user_id' => $storeUser->id
                ],
                'message' => '매장과 계정이 성공적으로 생성되었습니다.'
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified store
     */
    public function show(string $id)
    {
        try {
            $currentUser = auth()->user();
            $store = Store::with('branch')->findOrFail($id);
            
            // 권한 검증
            if ($currentUser->role === 'branch' && $store->branch_id !== $currentUser->branch_id) {
                return response()->json(['success' => false, 'error' => '접근 권한이 없습니다.'], 403);
            } elseif ($currentUser->role === 'store' && $store->id !== $currentUser->store_id) {
                return response()->json(['success' => false, 'error' => '접근 권한이 없습니다.'], 403);
            }
            
            return response()->json(['success' => true, 'data' => $store]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 404);
        }
    }

    /**
     * Update the specified store
     */
    public function update(Request $request, string $id)
    {
        // 권한 검증: 본사와 지사만 매장 수정 가능
        $currentUser = auth()->user();
        if (!in_array($currentUser->role, ['headquarters', 'branch'])) {
            return response()->json(['success' => false, 'error' => '매장 수정 권한이 없습니다.'], 403);
        }

        try {
            $store = Store::findOrFail($id);
            
            // 지사는 자신의 매장만 수정 가능
            if ($currentUser->role === 'branch' && $store->branch_id !== $currentUser->branch_id) {
                return response()->json(['success' => false, 'error' => '다른 지사 매장은 수정할 수 없습니다.'], 403);
            }

            $request->validate([
                'name' => 'required|string|max:255',
                'owner_name' => 'nullable|string|max:255',
                'phone' => 'nullable|string|max:20',
                'address' => 'nullable|string|max:500',
                'status' => 'required|in:active,inactive',
                'branch_id' => 'required|exists:branches,id'
            ]);

            $store->update($request->only(['name', 'owner_name', 'phone', 'address', 'status', 'branch_id']));

            return response()->json([
                'success' => true,
                'message' => '매장 정보가 수정되었습니다.',
                'data' => $store->load('branch')
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified store
     */
    public function destroy(string $id)
    {
        // 권한 검증: 본사만 매장 삭제 가능
        $currentUser = auth()->user();
        if ($currentUser->role !== 'headquarters') {
            return response()->json(['success' => false, 'error' => '매장 삭제는 본사 관리자만 가능합니다.'], 403);
        }

        try {
            $store = Store::findOrFail($id);
            
            // 매장 사용자들도 함께 삭제
            User::where('store_id', $id)->delete();
            
            $store->delete();

            return response()->json([
                'success' => true,
                'message' => '매장이 삭제되었습니다.'
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get store account information
     */
    public function getAccount(string $id)
    {
        try {
            $currentUser = auth()->user();
            $store = Store::with('branch')->findOrFail($id);
            
            // 권한 검증: 본사는 모든 매장, 지사는 소속 매장만
            if ($currentUser->role === 'branch' && $store->branch_id !== $currentUser->branch_id) {
                return response()->json(['success' => false, 'error' => '접근 권한이 없습니다.'], 403);
            } elseif ($currentUser->role === 'store' && $store->id !== $currentUser->store_id) {
                return response()->json(['success' => false, 'error' => '접근 권한이 없습니다.'], 403);
            }
            
            // 매장 계정 조회
            $storeAccount = User::where('store_id', $id)->where('role', 'store')->first();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'store' => $store,
                    'account' => $storeAccount
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Create store manager account
     */
    public function createAccount(Request $request, string $id)
    {
        // 권한 검증: 본사와 지사만 매장 계정 생성 가능
        $currentUser = auth()->user();
        if (!in_array($currentUser->role, ['headquarters', 'branch'])) {
            return response()->json(['success' => false, 'error' => '매장 계정 생성 권한이 없습니다.'], 403);
        }

        try {
            $store = Store::findOrFail($id);
            
            // 지사는 자신의 매장만 계정 생성 가능
            if ($currentUser->role === 'branch' && $store->branch_id !== $currentUser->branch_id) {
                return response()->json(['success' => false, 'error' => '다른 지사 매장의 계정은 생성할 수 없습니다.'], 403);
            }

            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|string|min:6',
            ]);

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'store',
                'store_id' => $store->id,
                'branch_id' => $store->branch_id,
                'is_active' => true
            ]);

            return response()->json([
                'success' => true,
                'data' => $user,
                'message' => '매장 관리자 계정이 생성되었습니다.'
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}