<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class StoreController extends Controller
{
    /**
     * 매장 목록 조회
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                return response()->json(['success' => false, 'error' => '인증이 필요합니다.'], 401);
            }
            
            $stores = Store::with('branch')->get();
            
            return response()->json([
                'success' => true,
                'data' => $stores
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * 매장 추가
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $branch = Branch::where('name', $request->branch_name)->first();
            if (!$branch) {
                return response()->json(['success' => false, 'error' => '지사를 찾을 수 없습니다.'], 404);
            }
            
            $storeCount = Store::where('branch_id', $branch->id)->count();
            $autoCode = $branch->code . '-' . str_pad($storeCount + 1, 3, '0', STR_PAD_LEFT);
            
            $store = Store::create([
                'name' => $request->name,
                'code' => $autoCode,
                'branch_id' => $branch->id,
                'manager_name' => $request->manager_name,
                'contact_number' => $request->contact_number,
                'status' => 'active'
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $store->load('branch'),
                'message' => '매장이 성공적으로 추가되었습니다.'
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * 매장 정보 조회
     */
    public function show($id): JsonResponse
    {
        try {
            $store = Store::with('branch')->findOrFail($id);
            return response()->json(['success' => true, 'data' => $store]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 404);
        }
    }
    
    /**
     * 매장 수정
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $store = Store::findOrFail($id);
            
            $store->update([
                'name' => $request->name,
                'manager_name' => $request->manager_name,
                'contact_number' => $request->contact_number
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $store->load('branch'),
                'message' => '매장 정보가 수정되었습니다.'
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * 매장 사용자 생성
     */
    public function createUser(Request $request, $id): JsonResponse
    {
        try {
            $store = Store::findOrFail($id);
            
            // PostgreSQL boolean 호환성을 위한 Raw SQL 사용
            DB::statement('INSERT INTO users (name, email, password, role, store_id, branch_id, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?::boolean, ?, ?)', [
                $request->name,
                $request->email,
                Hash::make($request->password),
                'store',
                $store->id,
                $store->branch_id,
                'true',  // PostgreSQL boolean 리터럴
                now(),
                now()
            ]);
            
            $user = User::where('email', $request->email)->first();
            
            return response()->json([
                'success' => true,
                'data' => $user,
                'message' => '매장 사용자 계정이 생성되었습니다.'
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}