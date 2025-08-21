<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RBACMiddleware
{
    /**
     * 권한별 데이터 접근 제어 (API 및 웹 요청 모두 지원)
     */
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if (! $user) {
            // 요청 타입에 따른 다른 응답
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            return redirect()->route('auth.login')
                ->with('message', '로그인이 필요합니다.');
        }

        // 권한별 쿼리 필터 자동 주입
        switch ($user->role) {
            case 'headquarters':
                // 본사: 모든 데이터 접근 가능
                // 필터 없음
                break;

            case 'branch':
                // 지사: 해당 지사 데이터만
                if ($request->has('branch_id') && $request->branch_id != $user->branch_id) {
                    return $this->accessDeniedResponse($request, '권한이 없습니다. 해당 지사 데이터만 접근 가능합니다.');
                }
                $request->merge(['branch_id' => $user->branch_id]);

                // store_id가 있으면 해당 store가 branch에 속하는지 검증
                if ($request->has('store_id')) {
                    $store = \App\Models\Store::find($request->store_id);
                    if (! $store || $store->branch_id != $user->branch_id) {
                        return $this->accessDeniedResponse($request, '권한이 없습니다. 해당 지사 매장만 접근 가능합니다.');
                    }
                }
                break;

            case 'store':
                // 매장: 자기 매장 데이터만
                if ($request->has('store_id') && $request->store_id != $user->store_id) {
                    return $this->accessDeniedResponse($request, '권한이 없습니다. 자신의 매장 데이터만 접근 가능합니다.');
                }
                $request->merge([
                    'store_id' => $user->store_id,
                    'branch_id' => $user->branch_id,
                ]);
                break;

            default:
                return $this->accessDeniedResponse($request, '유효하지 않은 사용자 권한입니다.');
        }

        // 권한 정보를 request에 추가
        $request->merge([
            'user_role' => $user->role,
            'user_branch_id' => $user->branch_id,
            'user_store_id' => $user->store_id,
            'accessible_store_ids' => $user->getAccessibleStoreIds(),
        ]);

        // 로그 기록 (민감한 작업의 경우)
        if ($request->isMethod('post') || $request->isMethod('put') || $request->isMethod('delete')) {
            \Log::info('RBAC Action', [
                'user_id' => $user->id,
                'user_role' => $user->role,
                'method' => $request->method(),
                'url' => $request->url(),
                'ip' => $request->ip(),
            ]);
        }

        return $next($request);
    }

    /**
     * 접근 거부 응답 생성 (요청 타입에 따라 다른 응답)
     */
    private function accessDeniedResponse(Request $request, string $message)
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['error' => $message], 403);
        }

        return redirect()->route('dashboard')
            ->with('error', $message);
    }

    /**
     * 사용자가 특정 매장에 접근 권한이 있는지 확인
     */
    public static function canAccessStore($user, $storeId)
    {
        if (! $user || ! $storeId) {
            return false;
        }

        switch ($user->role) {
            case 'headquarters':
                return true;

            case 'branch':
                $store = \App\Models\Store::find($storeId);

                return $store && $store->branch_id == $user->branch_id;

            case 'store':
                return $user->store_id == $storeId;

            default:
                return false;
        }
    }

    /**
     * 사용자가 특정 지사에 접근 권한이 있는지 확인
     */
    public static function canAccessBranch($user, $branchId)
    {
        if (! $user || ! $branchId) {
            return false;
        }

        switch ($user->role) {
            case 'headquarters':
                return true;

            case 'branch':
            case 'store':
                return $user->branch_id == $branchId;

            default:
                return false;
        }
    }
}
