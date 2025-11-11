<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class BranchController extends Controller
{
    /**
     * Display a listing of branches
     */
    public function index()
    {
        try {
            $branches = Branch::withCount('stores')->get();

            return response()->json(['success' => true, 'data' => $branches]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Store a newly created branch (headquarters only)
     */
    public function store(Request $request)
    {
        // 본사 관리자만 지사 추가 가능
        if (auth()->user()->role !== 'headquarters') {
            return response()->json(['success' => false, 'error' => '지사 추가는 본사 관리자만 가능합니다.'], 403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:branches,code',
            'manager_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
        ]);

        try {
            // 지사 생성
            $branch = Branch::create([
                'name' => $request->name,
                'code' => $request->code,
                'manager_name' => $request->manager_name ?? '',
                'phone' => $request->phone ?? '',
                'address' => $request->address ?? '',
                'status' => 'active',
            ]);

            // 지사 관리자 계정 자동 생성
            $managerEmail = 'branch_'.strtolower($request->code).'@ykp.com';
            $manager = User::create([
                'name' => ($request->manager_name ?? $request->name.' 관리자'),
                'email' => $managerEmail,
                'password' => Hash::make(config('app.default_password', '123456')),
                'role' => 'branch',
                'branch_id' => $branch->id,
                'store_id' => null,
                'is_active' => true,
            ]);

            return response()->json([
                'success' => true,
                'message' => '지사가 성공적으로 추가되었습니다.',
                'data' => [
                    'branch' => $branch->load('stores'),
                    'manager' => $manager,
                    'login_info' => [
                        'email' => $managerEmail,
                        'password' => '123456',
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified branch
     */
    public function show(string $id)
    {
        try {
            $branch = Branch::with(['stores'])->findOrFail($id);

            return response()->json(['success' => true, 'data' => $branch]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 404);
        }
    }

    /**
     * Update the specified branch
     */
    public function update(Request $request, string $id)
    {
        // 본사 관리자만 지사 수정 가능
        if (auth()->user()->role !== 'headquarters') {
            return response()->json(['success' => false, 'error' => '지사 수정은 본사 관리자만 가능합니다.'], 403);
        }

        try {
            $branch = Branch::findOrFail($id);

            $request->validate([
                'name' => 'required|string|max:255',
                'code' => 'required|string|max:50|unique:branches,code,'.$id,
                'manager_name' => 'nullable|string|max:255',
                'phone' => 'nullable|string|max:20',
                'address' => 'nullable|string|max:500',
                'status' => 'required|in:active,inactive',
            ]);

            $branch->update($request->only(['name', 'code', 'manager_name', 'phone', 'address', 'status']));

            return response()->json([
                'success' => true,
                'message' => '지사 정보가 수정되었습니다.',
                'data' => $branch->load('stores'),
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified branch
     */
    public function destroy(string $id)
    {
        // 본사 관리자만 지사 삭제 가능
        if (auth()->user()->role !== 'headquarters') {
            return response()->json(['success' => false, 'error' => '지사 삭제는 본사 관리자만 가능합니다.'], 403);
        }

        try {
            $branch = Branch::with('stores')->findOrFail($id);

            // 하위 매장이 있는 경우 경고
            if ($branch->stores->count() > 0) {
                return response()->json([
                    'success' => false,
                    'error' => '하위 매장이 있는 지사는 삭제할 수 없습니다.',
                    'stores_count' => $branch->stores->count(),
                ], 400);
            }

            // 지사 관리자 계정 비활성화 (PostgreSQL 호환)
            if (config('database.default') === 'pgsql') {
                User::where('branch_id', $id)->update(['is_active' => \DB::raw('false')]);
            } else {
                User::where('branch_id', $id)->update(['is_active' => false]);
            }

            $branch->delete();

            return response()->json([
                'success' => true,
                'message' => '지사가 성공적으로 삭제되었습니다.',
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get simple list of all branches for dropdown
     */
    public function list()
    {
        try {
            $branches = Branch::where('status', 'active')
                ->orderBy('name')
                ->select('id', 'name', 'code')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $branches
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
