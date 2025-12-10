<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class StoreController extends Controller
{
    /**
     * 매장 목록 조회 (페이지네이션 + 검색 + 마지막 입력일)
     * Updated: 2025-12-10 - Added last_entry_at field
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();

        // 서브쿼리로 각 매장의 마지막 입력 시간 조회
        $lastSaleSubquery = DB::table('sales')
            ->select('store_id', DB::raw('MAX(created_at) as last_entry_at'))
            ->groupBy('store_id');

        $query = Store::with(['branch'])
            ->leftJoinSub($lastSaleSubquery, 'last_sales', function ($join) {
                $join->on('stores.id', '=', 'last_sales.store_id');
            })
            ->select('stores.*', 'last_sales.last_entry_at');

        // 권한별 필터링
        if ($user->role === 'branch') {
            $query->where('stores.branch_id', $user->branch_id);
        } elseif ($user->role === 'store') {
            $query->where('stores.id', $user->store_id);
        }

        // 검색 기능 (토큰 기반 - 매장명, 지사명, 점주명, 코드)
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            Log::info('🔍 Store search executed', ['search_term' => $searchTerm]);

            $query->where(function ($q) use ($searchTerm) {
                // 매장명 검색
                $q->where('stores.name', 'ILIKE', '%' . $searchTerm . '%')
                    // 점주명 검색
                    ->orWhere('stores.owner_name', 'ILIKE', '%' . $searchTerm . '%')
                    // 매장 코드 검색
                    ->orWhere('stores.code', 'ILIKE', '%' . $searchTerm . '%')
                    // 지사명 검색 (relation)
                    ->orWhereHas('branch', function ($branchQuery) use ($searchTerm) {
                        $branchQuery->where('name', 'ILIKE', '%' . $searchTerm . '%');
                    });
            });
        }

        // 페이지네이션 (기본 20개씩)
        $perPage = $request->get('per_page', 20);
        $stores = $query->orderBy('stores.name')->paginate($perPage);

        Log::info('📊 Store query result', [
            'total' => $stores->total(),
            'per_page' => $stores->perPage(),
            'has_search' => $request->has('search'),
            'search_value' => $request->get('search')
        ]);

        // 마지막 입력으로부터 경과 일수 계산
        $storesData = collect($stores->items())->map(function ($store) {
            $storeArray = $store->toArray();
            if ($store->last_entry_at) {
                $lastEntry = \Carbon\Carbon::parse($store->last_entry_at);
                $storeArray['last_entry_at'] = $lastEntry->format('Y-m-d H:i');
                $storeArray['days_since_entry'] = (int) abs(now()->diffInDays($lastEntry));
            } else {
                $storeArray['last_entry_at'] = null;
                $storeArray['days_since_entry'] = null;
            }
            return $storeArray;
        });

        return response()->json([
            'success' => true,
            'data' => $storesData->toArray(),
            'current_page' => $stores->currentPage(),
            'last_page' => $stores->lastPage(),
            'per_page' => $stores->perPage(),
            'total' => $stores->total(),
            'debug_version' => 'v3.0-with-last-entry',
            'debug_search_applied' => $request->has('search') && !empty($request->search),
        ]);
    }

    /**
     * 새 매장 추가 (본사만) - Supabase 실시간 동기화 지원
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            // 본사와 지사만 매장 추가 가능
            if (! in_array($user->role, ['headquarters', 'branch'])) {
                return response()->json(['error' => '본사 또는 지사 관리자만 매장을 추가할 수 있습니다.'], 403);
            }

            $validationRules = [
                'name' => 'required|string|max:255',
                'code' => 'nullable|string|unique:stores,code', // 자동 생성되므로 nullable
                'owner_name' => 'nullable|string|max:255',
                'phone' => 'nullable|string|max:20',
                'address' => 'nullable|string|max:500',
            ];

            // 본사는 지사 선택 가능, 지사는 자기 지사로 고정
            if ($user->role === 'headquarters') {
                $validationRules['branch_id'] = 'required|exists:branches,id';
            }

            $request->validate($validationRules);

            // 지사인 경우 자기 지사로 강제 설정
            $branchId = $user->role === 'branch' ? $user->branch_id : $request->branch_id;

            return DB::transaction(function () use ($request, $user, $branchId) {
            // 매장 코드 자동 생성 (PM 요구사항 반영)
            $branch = \App\Models\Branch::find($branchId);
            $branchCode = $branch ? $branch->code : 'BR'.str_pad($branchId, 3, '0', STR_PAD_LEFT);

            // 해당 지사의 마지막 매장 코드에서 번호 추출하여 다음 번호 계산
            $lastStore = Store::where('branch_id', $branchId)
                ->where('code', 'LIKE', $branchCode.'-%')
                ->orderBy('code', 'desc')
                ->first();

            if ($lastStore && preg_match('/.*-(\d+)$/', $lastStore->code, $matches)) {
                $nextNumber = intval($matches[1]) + 1;
            } else {
                // 해당 지사의 첫 매장인 경우
                $nextNumber = 1;
            }

            // 중복 방지를 위해 루프로 확인
            $attempts = 0;
            do {
                $storeCode = $request->code ?: $branchCode.'-'.str_pad($nextNumber + $attempts, 3, '0', STR_PAD_LEFT);
                $exists = Store::where('code', $storeCode)->exists();
                $attempts++;

                if ($attempts > 100) {
                    throw new \Exception('매장 코드 생성 실패: 사용 가능한 코드를 찾을 수 없습니다.');
                }
            } while ($exists);

            $store = Store::create([
                'name' => $request->name,
                'code' => $storeCode,
                'branch_id' => $branchId,
                'owner_name' => $request->owner_name,
                'phone' => $request->phone,
                'address' => $request->address,
                'status' => 'active',
                'opened_at' => now(),
                'created_by' => Auth::id(),  // created_by 필드 추가
            ]);

            // 로깅 (Supabase에서 추적 가능)
            Log::info('Store created', [
                'store_id' => $store->id,
                'store_code' => $store->code,
                'branch_id' => $store->branch_id,
                'created_by' => Auth::id(),
                'timestamp' => now()->toISOString(),
            ]);

            // Supabase 실시간 알림 트리거 (향후 구현)
            // $this->triggerRealtimeUpdate('store_created', $store);

            // 매장 계정 자동 생성
            $accountInfo = null;
            try {
                // 표준 양식으로 계정 정보 생성
                $standardAccount = $this->generateStandardAccountInfo($store, $user, $request);

                // 이메일 중복 확인
                $emailExists = User::where('email', $standardAccount['email'])->exists();
                if ($emailExists) {
                    // 중복 시 타임스탬프 추가
                    $timestamp = substr(time(), -4); // 마지막 4자리
                    $standardAccount['email'] = str_replace('@ykp.com', $timestamp.'@ykp.com', $standardAccount['email']);
                }

                // 사용자 생성 (PostgreSQL boolean 호환성을 위한 Raw SQL 사용)
                DB::statement('INSERT INTO users (name, email, password, role, store_id, branch_id, is_active, created_by_user_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?::boolean, ?, ?, ?)', [
                    $standardAccount['name'],
                    $standardAccount['email'],
                    Hash::make($standardAccount['password']),
                    'store',
                    $store->id,
                    $store->branch_id,
                    'true',  // PostgreSQL boolean 리터럴
                    Auth::id(),
                    now(),
                    now(),
                ]);

                // 생성된 사용자 가져오기
                $newUser = User::where('email', $standardAccount['email'])->first();

                // 계정 정보 반환용 (비밀번호 평문 포함)
                $accountInfo = [
                    'user_id' => $newUser->id,
                    'email' => $standardAccount['email'],
                    'password' => $standardAccount['password'], // 평문 비밀번호
                    'name' => $standardAccount['name'],
                ];

                Log::info('Store account auto-created', [
                    'store_id' => $store->id,
                    'user_id' => $newUser->id,
                    'created_by' => Auth::id(),
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to create store account', [
                    'store_id' => $store->id,
                    'error' => $e->getMessage(),
                ]);
                // 계정 생성 실패해도 매장은 이미 생성됨
                $accountInfo = [
                    'error' => '자동 계정 생성 실패 - 수동으로 생성 필요',
                    'email' => $standardAccount['email'] ?? '',
                    'password' => $standardAccount['password'] ?? '',
                ];
            }

            return response()->json([
                'success' => true,
                'message' => '매장이 성공적으로 추가되었습니다.',
                'data' => $store->load('branch'),
                'account' => $accountInfo, // 계정 정보 포함
            ], 201);
        });
        } catch (\Exception $e) {
            Log::error('Store creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'error' => '매장 생성 중 오류가 발생했습니다: ' . $e->getMessage()
            ], 500);
        }
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
            'password' => 'required|string|min:6',
        ]);

        // PostgreSQL boolean 호환성을 위한 Raw SQL 사용 (createAccount 메서드)
        DB::statement('INSERT INTO users (name, email, password, role, store_id, branch_id, is_active, created_by_user_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?::boolean, ?, ?, ?)', [
            $request->name,
            $request->email,
            Hash::make($request->password),
            'store',
            $store->id,
            $store->branch_id,
            'true',  // PostgreSQL boolean 리터럴
            Auth::id(),
            now(),
            now(),
        ]);

        // 생성된 사용자 가져오기
        $user = User::where('email', $request->email)->first();

        return response()->json([
            'success' => true,
            'message' => '매장 계정이 성공적으로 생성되었습니다.',
            'data' => $user,
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
            'status' => 'nullable|in:active,inactive',
        ]);

        $store->update($request->only([
            'name', 'owner_name', 'phone', 'address', 'status',
        ]));

        Log::info('Store updated', [
            'store_id' => $store->id,
            'updated_by' => Auth::id(),
            'changes' => $request->only(['name', 'owner_name', 'phone', 'address', 'status']),
        ]);

        return response()->json([
            'success' => true,
            'message' => '매장 정보가 성공적으로 수정되었습니다.',
            'data' => $store->fresh()->load('branch'),
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
            'role' => 'required|in:store,branch',
        ]);

        // 지사 관리자는 매장 계정만 생성 가능
        if ($user->role === 'branch' && $request->role !== 'store') {
            return response()->json(['error' => '지사 관리자는 매장 계정만 생성할 수 있습니다.'], 403);
        }

        // PostgreSQL boolean 호환성을 위한 Raw SQL 사용
        DB::statement('INSERT INTO users (name, email, password, role, store_id, branch_id, is_active, created_by_user_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?::boolean, ?, ?, ?)', [
            $request->name,
            $request->email,
            Hash::make($request->password),
            $request->role,
            $request->role === 'store' ? $store->id : null,
            $store->branch_id,
            'true',  // PostgreSQL boolean 리터럴
            Auth::id(),
            now(),
            now(),
        ]);

        // 생성된 사용자 가져오기
        $newUser = User::where('email', $request->email)->first();

        Log::info('Store account created', [
            'store_id' => $store->id,
            'new_user_id' => $newUser->id,
            'role' => $newUser->role,
            'created_by' => Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => '계정이 성공적으로 생성되었습니다.',
            'data' => $newUser,
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
                // DB 제약조건에 맞게 'inactive' 사용 (deleted 상태는 metadata에 저장)
                $store->update([
                    'status' => 'inactive',
                    'metadata' => [
                        'deleted_at' => now()->toISOString(),
                        'deleted_by' => Auth::id(),
                        'reason' => 'soft_delete_with_sales_data'
                    ]
                ]);

                // 매장 사용자들도 비활성화 (PostgreSQL 호환)
                if (config('database.default') === 'pgsql') {
                    $store->users()->update(['is_active' => \DB::raw('false')]);
                } else {
                    $store->users()->update(['is_active' => false]);
                }

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
                'action' => $hasSales ? 'deactivated' : 'deleted',
            ]);

            return response()->json([
                'success' => true,
                'message' => $message,
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
                    : 0,
            ],
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
            'data' => $branches,
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

        if (! $storeUser) {
            return response()->json([
                'success' => false,
                'error' => '매장 계정이 존재하지 않습니다.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'account' => [
                    'id' => $storeUser->id,
                    'name' => $storeUser->name,
                    'email' => $storeUser->email,
                    'created_at' => $storeUser->created_at,
                ],
            ],
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
                'error' => '이미 매장 계정이 존재합니다.',
            ], 409);
        }

        return DB::transaction(function () use ($request, $user, $store) {
            // 표준 양식으로 계정 정보 생성
            $standardAccount = $this->generateStandardAccountInfo($store, $user, $request);

            // 이메일 중복 확인
            $emailExists = User::where('email', $standardAccount['email'])->exists();
            if ($emailExists) {
                // 중복 시 타임스탬프 추가
                $timestamp = substr(time(), -4); // 마지막 4자리
                $standardAccount['email'] = str_replace('@ykp.com', $timestamp.'@ykp.com', $standardAccount['email']);
            }

            // 사용자 생성 (PostgreSQL boolean 호환성을 위한 Raw SQL 사용)
            // 문제: Laravel Eloquent가 boolean true를 integer 1로 변환하여 PostgreSQL 오류 발생
            // 해결: DB::statement()를 사용하여 PostgreSQL native boolean 값 직접 전달
            DB::statement('INSERT INTO users (name, email, password, role, store_id, branch_id, is_active, created_by_user_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?::boolean, ?, ?, ?)', [
                $standardAccount['name'],
                $standardAccount['email'],
                Hash::make($standardAccount['password']),
                'store',
                $store->id,
                $store->branch_id,
                'true',  // PostgreSQL boolean 리터럴
                Auth::id(),
                now(),
                now(),
            ]);

            // 생성된 사용자 가져오기
            $newUser = User::where('email', $standardAccount['email'])->first();

            Log::info('Store account created via new endpoint', [
                'store_id' => $store->id,
                'store_code' => $store->code,
                'new_user_id' => $newUser->id,
                'new_user_email' => $standardAccount['email'],
                'created_by' => Auth::id(),
                'created_by_role' => $user->role,
                'account_type' => $standardAccount['type'],
            ]);

            return response()->json([
                'success' => true,
                'message' => '매장 계정이 성공적으로 생성되었습니다.',
                'data' => [
                    'store' => $store->only(['id', 'name', 'code']),
                    'account' => [
                        'id' => $newUser->id,
                        'name' => $standardAccount['name'],
                        'email' => $standardAccount['email'],
                        'password' => $standardAccount['password'], // 1회성 반환
                        'created_at' => $newUser->created_at,
                        'type' => $standardAccount['type'],
                    ],
                ],
            ], 201);
        });
    }

    /**
     * 표준 양식으로 계정 정보 생성
     */
    private function generateStandardAccountInfo(Store $store, User $creator, Request $request): array
    {
        if ($creator->role === 'headquarters' && $request->filled(['name', 'email', 'password'])) {
            // 본사: 사용자 입력 정보 사용 (하지만 표준 양식 권장)
            return [
                'name' => $request->name,
                'email' => $request->email,
                'password' => $request->password,
                'type' => 'headquarters_manual',
            ];
        }

        // 표준 자동 생성 양식
        $branchCode = strtolower($store->branch->code ?? 'BR'.str_pad($store->branch_id, 3, '0', STR_PAD_LEFT));
        $storeNumber = $this->extractStoreNumber($store->code);

        // 이메일: {지사코드}-{매장번호}@ykp.com
        $email = $branchCode.'-'.str_pad($storeNumber, 3, '0', STR_PAD_LEFT).'@ykp.com';

        // 계정명: {사장님명} ({매장코드} 매장장)
        $ownerName = $store->owner_name ?: '매장관리자';
        $name = "{$ownerName} ({$store->code} 매장장)";

        // 비밀번호: store{6자리_영숫자}
        $password = 'store'.$this->generateSecureRandomString(6);

        return [
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'type' => 'auto_generated_standard',
        ];
    }

    /**
     * 매장 코드에서 매장 번호 추출
     */
    private function extractStoreNumber(string $storeCode): string
    {
        // BR001-001 → 001, DG001-003 → 003
        if (preg_match('/.*-(\d+)$/', $storeCode, $matches)) {
            return $matches[1];
        }

        // 패턴이 맞지 않으면 마지막 숫자들 추출
        if (preg_match('/(\d+)$/', $storeCode, $matches)) {
            return str_pad($matches[1], 3, '0', STR_PAD_LEFT);
        }

        // 기본값: 001
        return '001';
    }

    /**
     * 안전한 랜덤 문자열 생성 (영숫자 조합)
     */
    private function generateSecureRandomString(int $length): string
    {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $result = '';

        for ($i = 0; $i < $length; $i++) {
            $result .= $characters[random_int(0, strlen($characters) - 1)];
        }

        return $result;
    }

    /**
     * 3일 이상 미입력 매장 조회 (본사/지사 전용)
     */
    public function getUnmaintainedStores(Request $request): JsonResponse
    {
        $user = Auth::user();
        $daysThreshold = $request->get('days', 3);

        // 매장 역할은 조회 불가
        if ($user->role === 'store') {
            return response()->json(['success' => false, 'error' => '권한 없음'], 403);
        }

        // 각 매장의 마지막 판매 입력일 조회
        $query = Store::selectRaw('
            stores.*,
            MAX(sales.created_at) as last_sale_date
        ')
        ->leftJoin('sales', 'stores.id', '=', 'sales.store_id')
        ->where('stores.status', 'active')
        ->groupBy('stores.id');

        // 지사는 소속 매장만
        if ($user->role === 'branch') {
            $query->where('stores.branch_id', $user->branch_id);
        }

        $stores = $query->get();

        // 3일 이상 미입력 필터링
        $unmaintained = $stores->filter(function($store) use ($daysThreshold) {
            if (!$store->last_sale_date) return true; // 입력 기록 없음
            return now()->diffInDays($store->last_sale_date) >= $daysThreshold;
        })->sortByDesc(function($store) {
            return $store->last_sale_date ? now()->diffInDays($store->last_sale_date) : 9999;
        });

        // 지사 정보 로드
        $unmaintained->load('branch');

        return response()->json([
            'success' => true,
            'data' => $unmaintained->values()->map(fn($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'code' => $s->code,
                'branch_name' => $s->branch?->name,
                'last_sale_date' => $s->last_sale_date
                    ? \Carbon\Carbon::parse($s->last_sale_date)->format('Y-m-d H:i')
                    : null,
                'days_without_input' => $s->last_sale_date
                    ? now()->diffInDays($s->last_sale_date)
                    : null,
            ]),
            'count' => $unmaintained->count(),
            'threshold_days' => $daysThreshold,
        ]);
    }
}
