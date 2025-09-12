<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Branch;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class UserManagementController extends Controller
{
    /**
     * 사용자 목록 조회 (본사만 가능)
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);
        
        $query = User::with(['branch', 'store']);
        
        // 필터링
        if ($request->has('role')) {
            $query->where('role', $request->role);
        }
        
        if ($request->has('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }
        
        if ($request->has('store_id')) {
            $query->where('store_id', $request->store_id);
        }
        
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }
        
        $users = $query->orderBy('created_at', 'desc')
                      ->paginate($request->per_page ?? 20);
        
        return response()->json($users);
    }
    
    /**
     * 새 사용자 생성 (본사만 가능)
     */
    public function store(CreateUserRequest $request): JsonResponse
    {
        $this->authorize('create', User::class);
        
        try {
            $userData = $request->validated();
            $userData['password'] = Hash::make($userData['password']);
            $userData['is_active'] = true; // PostgreSQL boolean compatibility
            
            // 역할별 유효성 검증
            $this->validateRoleAssignment($userData);
            
            $user = User::create($userData);
            
            Log::info('User created by headquarters', [
                'created_user_id' => $user->id,
                'created_user_email' => $user->email,
                'created_user_role' => $user->role,
                'creator_id' => Auth::id(),
                'creator_email' => Auth::user()->email
            ]);
            
            return response()->json([
                'success' => true,
                'message' => '사용자가 성공적으로 생성되었습니다.',
                'user' => $user->load(['branch', 'store'])
            ], 201);
            
        } catch (\Exception $e) {
            Log::error('User creation failed', [
                'error' => $e->getMessage(),
                'request_data' => $request->except('password'),
                'creator_id' => Auth::id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '사용자 생성 중 오류가 발생했습니다.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 사용자 정보 수정 (본사만 가능)
     */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);
        
        try {
            $userData = $request->validated();
            
            // 비밀번호가 제공된 경우만 해시
            if (isset($userData['password'])) {
                $userData['password'] = Hash::make($userData['password']);
            } else {
                unset($userData['password']);
            }
            
            // 역할 변경 시 유효성 검증
            if (isset($userData['role']) && $userData['role'] !== $user->role) {
                $this->validateRoleAssignment($userData);
            }
            
            $user->update($userData);
            
            Log::info('User updated by headquarters', [
                'updated_user_id' => $user->id,
                'updated_user_email' => $user->email,
                'changes' => $request->only(['role', 'branch_id', 'store_id']),
                'updater_id' => Auth::id()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => '사용자 정보가 성공적으로 수정되었습니다.',
                'user' => $user->fresh(['branch', 'store'])
            ]);
            
        } catch (\Exception $e) {
            Log::error('User update failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'request_data' => $request->except('password'),
                'updater_id' => Auth::id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '사용자 정보 수정 중 오류가 발생했습니다.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 사용자 삭제 (본사만 가능)
     */
    public function destroy(User $user): JsonResponse
    {
        $this->authorize('delete', $user);
        
        // 자기 자신은 삭제할 수 없음
        if ($user->id === Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => '자기 자신의 계정은 삭제할 수 없습니다.'
            ], 422);
        }
        
        try {
            $userInfo = [
                'id' => $user->id,
                'email' => $user->email,
                'role' => $user->role
            ];
            
            $user->delete();
            
            Log::info('User deleted by headquarters', [
                'deleted_user' => $userInfo,
                'deleter_id' => Auth::id(),
                'deleter_email' => Auth::user()->email
            ]);
            
            return response()->json([
                'success' => true,
                'message' => '사용자가 성공적으로 삭제되었습니다.'
            ]);
            
        } catch (\Exception $e) {
            Log::error('User deletion failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'deleter_id' => Auth::id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '사용자 삭제 중 오류가 발생했습니다.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 지사 목록 조회 (사용자 생성 시 필요)
     */
    public function getBranches(): JsonResponse
    {
        $this->authorize('viewAny', User::class);
        
        $branches = Branch::orderBy('name')->get();
        
        return response()->json($branches);
    }
    
    /**
     * 매장 목록 조회 (특정 지사의 매장들)
     */
    public function getStores(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);
        
        $query = Store::with('branch')->orderBy('name');
        
        if ($request->has('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }
        
        $stores = $query->get();
        
        return response()->json($stores);
    }
    
    /**
     * 역할별 유효성 검증
     */
    private function validateRoleAssignment(array $userData): void
    {
        $role = $userData['role'];
        $branchId = $userData['branch_id'] ?? null;
        $storeId = $userData['store_id'] ?? null;
        
        switch ($role) {
            case 'headquarters':
                // 본사는 지사/매장 정보 없어야 함
                if ($branchId || $storeId) {
                    throw new \InvalidArgumentException('본사 계정은 지사나 매장 정보를 가질 수 없습니다.');
                }
                break;
                
            case 'branch':
                // 지사는 지사 정보 필수, 매장 정보 없어야 함
                if (!$branchId) {
                    throw new \InvalidArgumentException('지사 계정은 지사 정보가 필요합니다.');
                }
                if ($storeId) {
                    throw new \InvalidArgumentException('지사 계정은 매장 정보를 가질 수 없습니다.');
                }
                // 지사 존재 확인
                if (!Branch::find($branchId)) {
                    throw new \InvalidArgumentException('존재하지 않는 지사입니다.');
                }
                break;
                
            case 'store':
                // 매장은 지사와 매장 정보 모두 필수
                if (!$branchId || !$storeId) {
                    throw new \InvalidArgumentException('매장 계정은 지사와 매장 정보가 모두 필요합니다.');
                }
                // 매장 존재 및 지사 일치 확인
                $store = Store::find($storeId);
                if (!$store) {
                    throw new \InvalidArgumentException('존재하지 않는 매장입니다.');
                }
                if ($store->branch_id != $branchId) {
                    throw new \InvalidArgumentException('매장과 지사 정보가 일치하지 않습니다.');
                }
                break;
                
            default:
                throw new \InvalidArgumentException('유효하지 않은 사용자 역할입니다.');
        }
    }
}