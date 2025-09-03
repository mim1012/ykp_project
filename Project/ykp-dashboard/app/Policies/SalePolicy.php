<?php

namespace App\Policies;

use App\Models\Sale;
use App\Models\Store;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SalePolicy
{
    use HandlesAuthorization;

    /**
     * 판매 데이터 생성 권한
     */
    public function create(User $user): bool
    {
        // 모든 역할은 자신의 권한 범위 안에서만 생성 가능
        return in_array($user->role, ['headquarters', 'branch', 'store']);
    }

    /**
     * 특정 매장에 대한 판매 생성 권한 확인
     * 컨트롤러에서 별도 검사 시 사용할 수 있음
     */
    public function createForStore(User $user, int $storeId): bool
    {
        if ($user->isHeadquarters() || $user->isDeveloper()) {
            return true;
        }

        if ($user->isBranch()) {
            $store = Store::find($storeId);
            return $store && (int) $store->branch_id === (int) $user->branch_id;
        }

        if ($user->isStore()) {
            return (int) $user->store_id === (int) $storeId;
        }

        return false;
    }
}

