<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Exports\BranchTemplateExport;
use App\Exports\CreatedStoreAccountsExport;
use App\Exports\StoreAccountsExport;
use App\Exports\StoreTemplateExport;
use App\Helpers\RandomDataGenerator;
use App\Imports\StoresBulkImport;
use App\Jobs\ProcessBulkBranchCreationJob;
use App\Jobs\ProcessBulkStoreCreationJob;
use App\Models\Branch;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class StoreManagementController extends Controller
{
    /**
     * Display stores based on user role with pagination and search
     */
    public function index(Request $request)
    {
        try {
            $user = auth()->user();
            $query = Store::with('branch');

            // 권한별 매장 필터링 (RBAC)
            if ($user->role === 'headquarters') {
                // 본사: 모든 매장
            } elseif ($user->role === 'branch') {
                $query->where('branch_id', $user->branch_id); // 지사: 소속 매장만
            } elseif ($user->role === 'store') {
                $query->where('id', $user->store_id); // 매장: 자기 매장만
            } else {
                // 기타: 빈 결과
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'current_page' => 1,
                    'last_page' => 1,
                    'total' => 0,
                ]);
            }

            // 검색 기능 (매장명, 점주명, 코드, 지사명, 주소 ILIKE 검색)
            if ($search = $request->input('search')) {
                Log::info('🔍 Store search executed', ['search_term' => $search]);

                $query->where(function ($q) use ($search) {
                    $q->where('name', 'ILIKE', "%{$search}%")
                        ->orWhere('owner_name', 'ILIKE', "%{$search}%")
                        ->orWhere('code', 'ILIKE', "%{$search}%")
                        ->orWhere('address', 'ILIKE', "%{$search}%")
                        ->orWhereHas('branch', function ($branchQuery) use ($search) {
                            $branchQuery->where('name', 'ILIKE', "%{$search}%");
                        });
                });
            }

            // 페이지네이션 (기본 30개씩, 요청 시 변경 가능)
            $perPage = $request->get('per_page', 30);

            // 지사별 정렬 + 매장명 정렬
            $stores = $query->orderBy('branch_id')
                ->orderBy('name')
                ->paginate($perPage);

            Log::info('📊 Store query result', [
                'total' => $stores->total(),
                'per_page' => $stores->perPage(),
                'has_search' => $request->has('search'),
                'search_value' => $request->get('search')
            ]);

            return response()->json([
                'success' => true,
                'data' => $stores->items(),
                'current_page' => $stores->currentPage(),
                'last_page' => $stores->lastPage(),
                'per_page' => $stores->perPage(),
                'total' => $stores->total(),
                'debug_version' => 'v2.0-with-search',
                'debug_search_applied' => $request->has('search') && !empty($request->search),
            ]);
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
        if (! in_array($currentUser->role, ['headquarters', 'branch'])) {
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
            // 분류 필드
            'store_type' => ['nullable', Rule::in(['franchise', 'direct'])],
            'business_registration_number' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:100',
        ]);

        try {
            $branch = Branch::find($request->branch_id);
            $storeCount = Store::where('branch_id', $request->branch_id)->count();
            $autoCode = $branch->code.'-'.str_pad($storeCount + 1, 3, '0', STR_PAD_LEFT);

            // 매장 생성
            $store = Store::create([
                'name' => $request->name,
                'code' => $autoCode,
                'branch_id' => $request->branch_id,
                'owner_name' => $request->owner_name ?? '',
                'phone' => $request->phone ?? '',
                'address' => $request->address ?? '',
                'status' => 'active',
                'opened_at' => now()->toDateTimeString(), // PostgreSQL timestamp 호환 형식
                // 분류 필드
                'store_type' => $request->store_type,
                'business_registration_number' => $request->business_registration_number,
                'email' => $request->email,
            ]);

            // 매장 계정 자동 생성 (더 안전한 방식)
            $autoPassword = 'store'.str_pad($store->id, 4, '0', STR_PAD_LEFT); // store0001 형태
            $autoEmail = strtolower($autoCode).'@ykp.com'; // 지사코드-매장번호@ykp.com (소문자로 통일)

            $accountCreated = false;
            $accountData = null;

            try {
                \Log::info('User 생성 시작', ['email' => $autoEmail, 'store_id' => $store->id]);

                // 이메일 중복 체크
                $existingUser = User::where('email', $autoEmail)->first();
                if ($existingUser) {
                    // 이메일이 이미 존재하면 타임스탬프 추가
                    $autoEmail = strtolower($autoCode).'_'.time().'@ykp.com';
                    \Log::info('이메일 중복, 새 이메일 생성', ['new_email' => $autoEmail]);
                }

                $storeUser = User::create([
                    'name' => $request->name.' 매장',
                    'email' => $autoEmail,
                    'password' => Hash::make($autoPassword),
                    'role' => 'store',
                    'branch_id' => $request->branch_id,
                    'store_id' => $store->id,
                    'is_active' => true,  // boolean 타입 (모델에서 자동 캐스팅)
                    'created_by_user_id' => $currentUser ? $currentUser->id : null,
                ]);

                $accountCreated = true;
                $accountData = [
                    'email' => $autoEmail,
                    'password' => $autoPassword,
                    'user_id' => $storeUser->id,
                ];

                \Log::info('User 생성 성공', [
                    'user_id' => $storeUser->id,
                    'email' => $autoEmail,
                    'store_id' => $store->id,
                ]);

            } catch (\Exception $userException) {
                // User 생성 실패 시 로그 남기고 계속 진행
                \Log::error('매장 계정 생성 실패: '.$userException->getMessage(), [
                    'store_id' => $store->id,
                    'email' => $autoEmail,
                    'error' => $userException->getMessage(),
                    'trace' => $userException->getTraceAsString(),
                ]);

                // 실패해도 기본 계정 정보는 전달 (나중에 수동 생성 가능하도록)
                $accountData = [
                    'email' => $autoEmail,
                    'password' => $autoPassword,
                    'user_id' => null,
                    'error' => '계정 자동 생성 실패. 수동으로 생성해주세요.',
                ];
            }

            // 항상 account 키를 포함한 응답 반환
            return response()->json([
                'success' => true,
                'data' => $store,
                'account' => $accountData, // 항상 account 키 포함
                'message' => $accountCreated
                    ? '매장과 계정이 성공적으로 생성되었습니다.'
                    : '매장은 생성되었으나 계정 생성에 실패했습니다. 수동으로 계정을 생성해주세요.',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'account' => null, // 실패해도 account 키는 포함
            ], 500);
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
     * Update the specified store (supports partial updates)
     */
    public function update(Request $request, string $id)
    {
        // 권한 검증: 본사와 지사만 매장 수정 가능
        $currentUser = auth()->user();
        if (! in_array($currentUser->role, ['headquarters', 'branch'])) {
            return response()->json(['success' => false, 'error' => '매장 수정 권한이 없습니다.'], 403);
        }

        try {
            $store = Store::findOrFail($id);

            // 지사는 자신의 매장만 수정 가능
            if ($currentUser->role === 'branch' && $store->branch_id !== $currentUser->branch_id) {
                return response()->json(['success' => false, 'error' => '다른 지사 매장은 수정할 수 없습니다.'], 403);
            }

            // 부분 업데이트 지원 (제공된 필드만 검증)
            $validated = $request->validate([
                'name' => 'nullable|string|max:255',
                'owner_name' => 'nullable|string|max:255',
                'phone' => 'nullable|string|max:20',
                'address' => 'nullable|string|max:500',
                'status' => 'nullable|in:active,inactive',
                'branch_id' => 'nullable|exists:branches,id',
                // 분류 필드
                'store_type' => ['nullable', Rule::in(['franchise', 'direct', ''])],
                'business_registration_number' => 'nullable|string|max:20',
                'email' => 'nullable|email|max:100',
            ]);

            // 지사 변경은 본사만 가능
            if (isset($validated['branch_id']) && $currentUser->role !== 'headquarters') {
                return response()->json([
                    'success' => false,
                    'error' => '지사 배정은 본사 관리자만 변경할 수 있습니다.',
                ], 403);
            }

            // 제공된 필드만 업데이트
            $updateData = array_filter($validated, function ($value) {
                return $value !== null;
            });

            $store->update($updateData);

            return response()->json([
                'success' => true,
                'message' => '매장 정보가 수정되었습니다.',
                'store' => $store->load('branch'),
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update store classification (store_type)
     * PUT /api/stores/{id}/classification
     * Only Branch + HQ users can update
     */
    public function updateClassification(Request $request, string $id)
    {
        $user = auth()->user();

        // RBAC: Only Branch and HQ can update classification
        if ($user->role === 'store') {
            return response()->json([
                'success' => false,
                'message' => 'Store users cannot update store classification',
            ], 403);
        }

        // Validate request - this will automatically return 422 on validation failure
        $validated = $request->validate([
            'store_type' => ['required', Rule::in(['franchise', 'direct'])],
        ]);

        try {
            $store = Store::findOrFail($id);

            // Branch users can only update stores in their branch
            if ($user->role === 'branch' && $store->branch_id !== $user->branch_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only update stores in your branch',
                ], 403);
            }

            $store->update($validated);

            Log::info('Store classification updated', [
                'store_id' => $id,
                'store_type' => $validated['store_type'],
                'updated_by' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Store classification updated successfully',
                'data' => $store->load('branch'),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update store classification', [
                'store_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update store classification',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update store business information
     * PUT /api/stores/{id}/business-info
     * Only Branch + HQ users can update
     */
    public function updateBusinessInfo(Request $request, string $id)
    {
        $user = auth()->user();

        // RBAC: Only Branch and HQ can update business info
        if ($user->role === 'store') {
            return response()->json([
                'success' => false,
                'message' => 'Store users cannot update business information',
            ], 403);
        }

        // Validate request - this will automatically return 422 on validation failure
        $validated = $request->validate([
            'business_registration_number' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:100',
        ]);

        try {
            $store = Store::findOrFail($id);

            // Branch users can only update stores in their branch
            if ($user->role === 'branch' && $store->branch_id !== $user->branch_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only update stores in your branch',
                ], 403);
            }

            $store->update($validated);

            Log::info('Store business info updated', [
                'store_id' => $id,
                'updated_by' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Store business information updated successfully',
                'data' => $store->load('branch'),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update store business info', [
                'store_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update store business information',
                'error' => $e->getMessage(),
            ], 500);
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
                'message' => '매장이 삭제되었습니다.',
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
                    'account' => $storeAccount,
                ],
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
        if (! in_array($currentUser->role, ['headquarters', 'branch'])) {
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
                'is_active' => true,
            ]);

            return response()->json([
                'success' => true,
                'data' => $user,
                'message' => '매장 관리자 계정이 생성되었습니다.',
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Download Excel template for bulk branch creation
     * 본사 전용 기능 - 지사 대량 생성
     */
    public function downloadBranchTemplate()
    {
        $currentUser = auth()->user();

        if ($currentUser->role !== 'headquarters') {
            return response()->json([
                'success' => false,
                'error' => '권한 없음',
                'message' => '대량 생성은 본사 관리자만 사용할 수 있습니다.',
            ], 403);
        }

        return Excel::download(
            new BranchTemplateExport(),
            'branch-bulk-create-template-'.date('Y-m-d').'.xlsx'
        );
    }

    /**
     * Download Excel template for bulk store creation
     * 본사 전용 기능 - 매장 대량 생성
     */
    public function downloadStoreTemplate()
    {
        $currentUser = auth()->user();

        if ($currentUser->role !== 'headquarters') {
            return response()->json([
                'success' => false,
                'error' => '권한 없음',
                'message' => '대량 생성은 본사 관리자만 사용할 수 있습니다.',
            ], 403);
        }

        return Excel::download(
            new StoreTemplateExport(),
            'store-bulk-create-template-'.date('Y-m-d').'.xlsx'
        );
    }

    /**
     * Upload and validate Excel file for bulk branch creation
     * 본사 전용 기능 - 지사
     */
    public function uploadBulkBranchFile(Request $request)
    {
        $currentUser = auth()->user();

        if ($currentUser->role !== 'headquarters') {
            return response()->json([
                'success' => false,
                'error' => '권한 없음',
                'message' => '대량 생성은 본사 관리자만 사용할 수 있습니다.',
            ], 403);
        }

        $request->validate([
            'file' => 'required|mimes:xlsx,xls|max:10240',
        ]);

        try {
            $file = $request->file('file');
            $data = Excel::toArray([], $file)[0];
            array_shift($data); // 헤더 제거

            $branchesData = array_map(function ($row) {
                return [
                    'branch_name' => $row[0] ?? null,
                    'manager_name' => $row[1] ?? null,
                ];
            }, $data);

            // 빈 행 제거
            $branchesData = array_filter($branchesData, function ($row) {
                return ! empty($row['branch_name']);
            });

            if (empty($branchesData)) {
                return response()->json([
                    'success' => false,
                    'error' => '유효한 데이터가 없습니다.',
                ], 400);
            }

            // 기본 검증
            $validationErrors = $this->validateBulkBranchData($branchesData);

            return response()->json([
                'success' => true,
                'data' => [
                    'total_rows' => count($branchesData),
                    'validation_errors' => $validationErrors,
                    'can_proceed' => empty($validationErrors),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bulk create branches from uploaded Excel
     * 본사 전용 기능 - 지사
     */
    public function bulkCreateBranches(Request $request)
    {
        $currentUser = auth()->user();

        if ($currentUser->role !== 'headquarters') {
            return response()->json([
                'success' => false,
                'error' => '권한 없음',
                'message' => '대량 생성은 본사 관리자만 사용할 수 있습니다.',
            ], 403);
        }

        $request->validate([
            'file' => 'required|mimes:xlsx,xls|max:10240',
        ]);

        try {
            $file = $request->file('file');
            $data = Excel::toArray([], $file)[0];
            array_shift($data);

            $branchesData = array_map(function ($row) {
                return [
                    'branch_name' => $row[0] ?? null,
                    'manager_name' => $row[1] ?? null,
                ];
            }, $data);

            $branchesData = array_filter($branchesData, function ($row) {
                return ! empty($row['branch_name']);
            });

            if (empty($branchesData)) {
                return response()->json([
                    'success' => false,
                    'error' => '유효한 데이터가 없습니다.',
                ], 400);
            }

            // 50개 이상이면 큐로 처리
            if (count($branchesData) >= 50) {
                ProcessBulkBranchCreationJob::dispatch($branchesData, $currentUser->id);

                return response()->json([
                    'success' => true,
                    'status' => 'queued',
                    'message' => '대량 생성 작업이 백그라운드에서 처리됩니다.',
                    'total_branches' => count($branchesData),
                ]);
            }

            // 50개 미만이면 즉시 처리
            $job = new ProcessBulkBranchCreationJob($branchesData, $currentUser->id);
            $results = $job->handle();

            return response()->json([
                'success' => true,
                'status' => 'completed',
                'data' => $results,
                'summary' => [
                    'total' => count($branchesData),
                    'success' => count($results['success']),
                    'errors' => count($results['errors']),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload and validate Excel file for bulk store creation
     * 본사 전용 기능 - 매장
     */
    public function uploadBulkFile(Request $request)
    {
        $currentUser = auth()->user();

        // ✅ 본사 권한 체크
        if ($currentUser->role !== 'headquarters') {
            return response()->json([
                'success' => false,
                'error' => '권한 없음',
                'message' => '대량 생성은 본사 관리자만 사용할 수 있습니다.',
                'hint' => '개별 매장 추가 기능을 이용해주세요.',
            ], 403);
        }

        $request->validate([
            'file' => 'required|mimes:xlsx,xls|max:10240', // 10MB
            'validation_mode' => 'nullable|in:reject,skip,auto_create',
        ]);

        try {
            $file = $request->file('file');
            $validationMode = $request->input('validation_mode', 'reject');

            // Excel 파일 읽기
            $data = Excel::toArray([], $file)[0];

            // 헤더 제거
            $headers = array_shift($data);

            // 데이터 변환 (새 형식: 매장명, 소속 지사)
            $storesData = array_map(function ($row) {
                return [
                    'store_name' => $row[0] ?? null,
                    'branch_name' => $row[1] ?? null,
                ];
            }, $data);

            // 빈 행 제거
            $storesData = array_filter($storesData, function ($row) {
                return ! empty($row['store_name']);
            });

            if (empty($storesData)) {
                return response()->json([
                    'success' => false,
                    'error' => '유효한 데이터가 없습니다.',
                ], 400);
            }

            // 기본 검증
            $validationErrors = $this->validateBulkData($storesData, $validationMode);

            return response()->json([
                'success' => true,
                'data' => [
                    'total_rows' => count($storesData),
                    'validation_errors' => $validationErrors,
                    'can_proceed' => empty($validationErrors) || $validationMode !== 'reject',
                    'validation_mode' => $validationMode,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bulk create stores from uploaded Excel
     * 본사 전용 기능
     */
    public function bulkCreate(Request $request)
    {
        $currentUser = auth()->user();

        // ✅ 본사 권한 체크
        if ($currentUser->role !== 'headquarters') {
            return response()->json([
                'success' => false,
                'error' => '권한 없음',
                'message' => '대량 생성은 본사 관리자만 사용할 수 있습니다.',
                'hint' => '개별 매장 추가 기능을 이용해주세요.',
            ], 403);
        }

        $request->validate([
            'file' => 'required|mimes:xlsx,xls|max:10240',
            'validation_mode' => 'nullable|in:reject,skip,auto_create',
        ]);

        try {
            $file = $request->file('file');
            $validationMode = $request->input('validation_mode', 'reject');

            // Excel 파일 읽기
            $data = Excel::toArray([], $file)[0];
            array_shift($data); // 헤더 제거

            $storesData = array_map(function ($row) {
                return [
                    'store_name' => $row[0] ?? null,
                    'branch_name' => $row[1] ?? null,
                ];
            }, $data);

            // 빈 행 제거
            $storesData = array_filter($storesData, function ($row) {
                return ! empty($row['store_name']);
            });

            if (empty($storesData)) {
                return response()->json([
                    'success' => false,
                    'error' => '유효한 데이터가 없습니다.',
                ], 400);
            }

            // 100개 이상이면 큐로 처리
            if (count($storesData) >= 100) {
                ProcessBulkStoreCreationJob::dispatch($storesData, $currentUser->id, $validationMode);

                return response()->json([
                    'success' => true,
                    'status' => 'queued',
                    'message' => '대량 생성 작업이 백그라운드에서 처리됩니다.',
                    'total_stores' => count($storesData),
                ]);
            }

            // 100개 미만이면 즉시 처리
            $job = new ProcessBulkStoreCreationJob($storesData, $currentUser->id, $validationMode);
            $results = $job->handle();

            return response()->json([
                'success' => true,
                'status' => 'completed',
                'data' => $results,
                'summary' => [
                    'total' => count($storesData),
                    'success' => count($results['success']),
                    'errors' => count($results['errors']),
                    'skipped' => count($results['skipped']),
                    'auto_created_branches' => count($results['auto_created_branches']),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download created accounts as Excel
     * 본사 전용 기능
     */
    public function downloadAccounts(Request $request)
    {
        $currentUser = auth()->user();

        // ✅ 본사 권한 체크
        if ($currentUser->role !== 'headquarters') {
            return response()->json([
                'success' => false,
                'error' => '권한 없음',
            ], 403);
        }

        $accounts = $request->input('accounts', []);

        if (empty($accounts)) {
            return response()->json([
                'success' => false,
                'error' => '다운로드할 계정 정보가 없습니다.',
            ], 400);
        }

        return Excel::download(
            new StoreAccountsExport($accounts),
            'store-accounts-'.date('Y-m-d-His').'.xlsx'
        );
    }

    /**
     * Validate bulk branch data before creation
     */
    protected function validateBulkBranchData(array $branchesData): array
    {
        $errors = [];

        foreach ($branchesData as $index => $data) {
            $rowNumber = $index + 2;
            $rowErrors = [];

            // 필수 필드 체크
            if (empty($data['branch_name'])) {
                $rowErrors[] = '지사명이 누락되었습니다';
            }
            if (empty($data['manager_name'])) {
                $rowErrors[] = '지역장 이름이 누락되었습니다';
            }

            // 지사명 중복 체크
            if (! empty($data['branch_name'])) {
                $branchExists = Branch::where('name', $data['branch_name'])->exists();
                if ($branchExists) {
                    $rowErrors[] = "이미 존재하는 지사명: {$data['branch_name']}";
                }
            }

            if (! empty($rowErrors)) {
                $errors[] = [
                    'row' => $rowNumber,
                    'errors' => $rowErrors,
                    'data' => $data,
                ];
            }
        }

        return $errors;
    }

    /**
     * Validate bulk store data before creation
     */
    protected function validateBulkData(array $storesData, string $validationMode): array
    {
        $errors = [];

        foreach ($storesData as $index => $data) {
            $rowNumber = $index + 2;
            $rowErrors = [];

            // 필수 필드 체크
            if (empty($data['store_name'])) {
                $rowErrors[] = '매장명이 누락되었습니다';
            }
            if (empty($data['branch_name'])) {
                $rowErrors[] = '소속 지사가 누락되었습니다';
            }

            // 지사 존재 여부 체크
            if (! empty($data['branch_name'])) {
                $branchExists = Branch::where('name', $data['branch_name'])->exists();

                if (! $branchExists) {
                    if ($validationMode === 'reject') {
                        $rowErrors[] = "존재하지 않는 지사: {$data['branch_name']}";
                    } elseif ($validationMode === 'skip') {
                        $rowErrors[] = "존재하지 않는 지사 (스킵됨): {$data['branch_name']}";
                    } elseif ($validationMode === 'auto_create') {
                        $rowErrors[] = "지사가 자동 생성됩니다: {$data['branch_name']}";
                    }
                }
            }

            // 매장명 중복 체크 (같은 지사 내에서)
            if (! empty($data['store_name']) && ! empty($data['branch_name'])) {
                $branch = Branch::where('name', $data['branch_name'])->first();
                if ($branch) {
                    $storeExists = Store::where('name', $data['store_name'])
                        ->where('branch_id', $branch->id)
                        ->exists();
                    if ($storeExists) {
                        $rowErrors[] = "이미 존재하는 매장명: {$data['store_name']} ({$data['branch_name']})";
                    }
                }
            }

            if (! empty($rowErrors)) {
                $errors[] = [
                    'row' => $rowNumber,
                    'errors' => $rowErrors,
                    'data' => $data,
                ];
            }
        }

        return $errors;
    }

    /**
     * 지사별 시트로 구성된 엑셀 파일에서 매장 대량 생성
     *
     * 엑셀 형식:
     * - 각 시트는 지사명으로 구성 (예: "서울지사", "부산지사")
     * - 각 시트의 컬럼: 지사명, 매장명, 관리자명, 전화번호
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkCreateStoresFromMultiSheet(Request $request)
    {
        // 대량 생성 시 타임아웃 방지
        set_time_limit(300); // 5분

        Log::info('=== 매장 대량 생성 시작 ===', [
            'user_id' => auth()->id(),
            'request_method' => $request->method(),
            'request_url' => $request->fullUrl(),
            'has_file' => $request->hasFile('file'),
        ]);

        try {
            // 권한 체크 (본사와 지사만 가능)
            $currentUser = auth()->user();

            if (!$currentUser) {
                Log::error('❌ 인증 실패: 사용자 없음');
                return response()->json([
                    'success' => false,
                    'error' => '로그인이 필요합니다.',
                ], 401);
            }

            Log::info('✅ 사용자 인증 확인', [
                'user_id' => $currentUser->id,
                'email' => $currentUser->email,
                'role' => $currentUser->role,
            ]);

            if (! in_array($currentUser->role, ['headquarters', 'branch'])) {
                Log::warning('❌ 권한 부족', ['user_id' => $currentUser->id, 'role' => $currentUser->role]);
                return response()->json([
                    'success' => false,
                    'error' => '매장 생성 권한이 없습니다.',
                ], 403);
            }

            Log::info('📂 파일 검증 시작');

            // 파일 존재 확인
            if (!$request->hasFile('file')) {
                Log::error('❌ 파일 없음', [
                    'has_file' => $request->hasFile('file'),
                    'all_files' => $request->allFiles(),
                ]);
                return response()->json([
                    'success' => false,
                    'error' => '파일이 업로드되지 않았습니다.',
                ], 400);
            }

            // 파일 검증
            try {
                $request->validate([
                    'file' => 'required|file|mimes:xlsx,xls,csv|max:10240', // CSV도 추가, 최대 10MB
                ]);
            } catch (\Illuminate\Validation\ValidationException $e) {
                Log::error('❌ 파일 검증 실패', [
                    'errors' => $e->errors(),
                ]);
                return response()->json([
                    'success' => false,
                    'error' => '파일 검증 실패: ' . json_encode($e->errors()),
                ], 422);
            }

            $file = $request->file('file');
            Log::info('✅ 파일 업로드 확인', [
                'filename' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'extension' => $file->getClientOriginalExtension(),
            ]);

            // 파일을 임시 위치에 저장
            $filePath = $file->getRealPath();
            Log::info('📁 파일 경로', ['path' => $filePath, 'exists' => file_exists($filePath)]);

            // Import 처리
            Log::info('🚀 Import 처리 시작');
            $import = new StoresBulkImport($filePath);
            $import->processAllSheets();
            Log::info('✅ Import 처리 완료');

            $results = $import->getResults();
            $errors = $import->getErrors();

            // UTF-8 정리 함수 (재귀적으로 모든 문자열 정리)
            $cleanUtf8 = function($data) use (&$cleanUtf8) {
                if (is_string($data)) {
                    return mb_scrub($data, 'UTF-8');
                } elseif (is_array($data)) {
                    return array_map($cleanUtf8, $data);
                }
                return $data;
            };

            // 결과와 에러 데이터 정리
            $results = $cleanUtf8($results);
            $errors = $cleanUtf8($errors);

            // 결과 로깅
            Log::info('=== 매장 대량 생성 완료 ===', [
                'user_id' => $currentUser->id,
                'success_count' => count($results),
                'error_count' => count($errors),
                'sample_result' => !empty($results) ? $results[0] : null,
                'sample_error' => !empty($errors) ? $errors[0] : null,
            ]);

            return response()->json([
                'success' => true,
                'message' => '매장 생성이 완료되었습니다.',
                'data' => [
                    'created_count' => count($results),
                    'error_count' => count($errors),
                    'created_stores' => $results,
                    'errors' => $errors,
                ],
            ]);

        } catch (\Illuminate\Database\QueryException $e) {
            Log::error('❌ 데이터베이스 오류', [
                'error' => $e->getMessage(),
                'sql' => $e->getSql() ?? 'N/A',
                'bindings' => $e->getBindings() ?? [],
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => '데이터베이스 오류: ' . $e->getMessage(),
                'detail' => config('app.debug') ? $e->getTraceAsString() : null,
            ], 500);

        } catch (\Exception $e) {
            Log::error('❌ 매장 대량 생성 실패', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => '매장 생성 중 오류가 발생했습니다: ' . $e->getMessage(),
                'detail' => config('app.debug') ? [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ] : null,
            ], 500);
        }
    }

    /**
     * 생성된 매장 계정 정보를 엑셀로 다운로드
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function downloadCreatedAccounts(Request $request)
    {
        try {
            // 세션에 저장된 생성 결과 가져오기 (또는 요청 바디에서)
            $accounts = $request->input('accounts', []);

            if (empty($accounts)) {
                return response()->json([
                    'success' => false,
                    'error' => '다운로드할 계정 정보가 없습니다.',
                ], 400);
            }

            $filename = '생성된_매장_계정_'.date('Y-m-d_His').'.xlsx';

            return Excel::download(
                new CreatedStoreAccountsExport($accounts),
                $filename,
                \Maatwebsite\Excel\Excel::XLSX,
                [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'Content-Disposition' => 'attachment; filename="'.$filename.'"',
                ]
            );

        } catch (\Exception $e) {
            Log::error('계정 정보 다운로드 실패', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => '다운로드 중 오류가 발생했습니다: '.$e->getMessage(),
            ], 500);
        }
    }
}
