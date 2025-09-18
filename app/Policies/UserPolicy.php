<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    /**
     * 사용자 목록 조회 권한 (본사만 가능)
     */
    public function viewAny(User $user): bool
    {
        return $user->isHeadquarters();
    }

    /**
     * 특정 사용자 정보 조회 권한
     */
    public function view(User $user, User $model): bool
    {
        // 본사는 모든 사용자 정보 조회 가능
        if ($user->isHeadquarters()) {
            return true;
        }

        // 지사는 같은 지사의 사용자만 조회 가능
        if ($user->isBranch()) {
            return $model->branch_id === $user->branch_id;
        }

        // 매장은 자기 자신만 조회 가능
        if ($user->isStore()) {
            return $model->id === $user->id;
        }

        return false;
    }

    /**
     * 사용자 생성 권한 (본사만 가능)
     */
    public function create(User $user): bool
    {
        return $user->isHeadquarters();
    }

    /**
     * 사용자 정보 수정 권한 (본사만 가능)
     */
    public function update(User $user, User $model): bool
    {
        // 본사만 다른 사용자 정보 수정 가능
        return $user->isHeadquarters();
    }

    /**
     * 사용자 삭제 권한 (본사만 가능)
     */
    public function delete(User $user, User $model): bool
    {
        // 본사만 다른 사용자 삭제 가능
        // 단, 자기 자신은 삭제 불가
        return $user->isHeadquarters() && $user->id !== $model->id;
    }

    /**
     * 사용자 역할 변경 권한 (본사만 가능)
     */
    public function changeRole(User $user, User $model): bool
    {
        return $user->isHeadquarters();
    }

    /**
     * 비밀번호 재설정 권한
     */
    public function resetPassword(User $user, User $model): bool
    {
        // 본사는 모든 사용자 비밀번호 재설정 가능
        if ($user->isHeadquarters()) {
            return true;
        }

        // 사용자는 자기 자신의 비밀번호만 변경 가능
        return $user->id === $model->id;
    }

    /**
     * 사용자 활성화/비활성화 권한 (본사만 가능)
     */
    public function toggleStatus(User $user, User $model): bool
    {
        return $user->isHeadquarters() && $user->id !== $model->id;
    }
}
