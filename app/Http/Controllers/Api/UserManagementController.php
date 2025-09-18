<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserManagementController extends Controller
{
    /**
     * Display a listing of users (role-based filtering)
     */
    public function index()
    {
        try {
            $currentUser = auth()->user();

            // 권한별 사용자 목록 필터링
            if ($currentUser->role === 'headquarters') {
                // 본사: 모든 사용자 조회 가능
                $users = User::with(['store', 'branch'])->get();
            } elseif ($currentUser->role === 'branch') {
                // 지사: 자신의 지사 소속 사용자만 조회 가능
                $users = User::with(['store', 'branch'])
                    ->where('branch_id', $currentUser->branch_id)
                    ->get();
            } else {
                // 매장: 자기 자신만 조회 가능
                $users = User::with(['store', 'branch'])
                    ->where('id', $currentUser->id)
                    ->get();
            }

            return response()->json(['success' => true, 'data' => $users]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update user information
     */
    public function update(Request $request, string $id)
    {
        try {
            $currentUser = auth()->user();
            $targetUser = User::findOrFail($id);

            // 권한 검증: 본사는 모든 계정 수정 가능, 지사는 소속 매장 계정만
            if ($currentUser->role === 'headquarters') {
                // 본사는 모든 계정 수정 가능 (단, 자기 자신 제외)
                if ($currentUser->id === $targetUser->id) {
                    return response()->json(['success' => false, 'error' => '본인 계정은 이 방법으로 수정할 수 없습니다.'], 403);
                }
            } elseif ($currentUser->role === 'branch') {
                // 지사는 자신의 소속 매장 계정만 수정 가능
                if ($targetUser->branch_id !== $currentUser->branch_id || $targetUser->role !== 'store') {
                    return response()->json(['success' => false, 'error' => '권한이 없습니다.'], 403);
                }
            } else {
                return response()->json(['success' => false, 'error' => '권한이 없습니다.'], 403);
            }

            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email,'.$id,
                'password' => 'nullable|string|min:6',
            ]);

            // 업데이트 데이터 준비
            $updateData = [
                'name' => $request->name,
                'email' => $request->email,
            ];

            // 비밀번호가 제공된 경우에만 업데이트
            if ($request->password) {
                $updateData['password'] = Hash::make($request->password);
            }

            $targetUser->update($updateData);

            return response()->json([
                'success' => true,
                'message' => '계정 정보가 업데이트되었습니다.',
                'data' => $targetUser->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Reset user password
     */
    public function resetPassword(Request $request, string $id)
    {
        try {
            $currentUser = auth()->user();
            $targetUser = User::findOrFail($id);

            // 권한 검증: 본사는 모든 계정, 지사는 소속 매장 계정만
            if ($currentUser->role === 'headquarters') {
                // 본사는 모든 계정 리셋 가능 (단, 자기 자신 제외)
                if ($currentUser->id === $targetUser->id) {
                    return response()->json(['success' => false, 'error' => '본인 계정은 리셋할 수 없습니다.'], 403);
                }
            } elseif ($currentUser->role === 'branch') {
                // 지사는 자신의 소속 매장 계정만 리셋 가능
                if ($targetUser->branch_id !== $currentUser->branch_id || $targetUser->role !== 'store') {
                    return response()->json(['success' => false, 'error' => '권한이 없습니다.'], 403);
                }
            } else {
                return response()->json(['success' => false, 'error' => '권한이 없습니다.'], 403);
            }

            $request->validate([
                'password' => 'required|string|min:6',
            ]);

            $targetUser->update([
                'password' => Hash::make($request->password),
            ]);

            return response()->json([
                'success' => true,
                'user' => $targetUser,
                'message' => '비밀번호가 성공적으로 리셋되었습니다.',
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
