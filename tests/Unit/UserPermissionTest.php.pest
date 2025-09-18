<?php

use App\Models\Branch;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->branch1 = Branch::factory()->create(['name' => '강남지사']);
    $this->branch2 = Branch::factory()->create(['name' => '판교지사']);

    $this->store1 = Store::factory()->create([
        'name' => '강남점',
        'branch_id' => $this->branch1->id,
    ]);

    $this->store2 = Store::factory()->create([
        'name' => '강남2점',
        'branch_id' => $this->branch1->id,
    ]);

    $this->store3 = Store::factory()->create([
        'name' => '판교점',
        'branch_id' => $this->branch2->id,
    ]);
});

it('본사 사용자는 모든 권한을 가진다', function () {
    $user = User::factory()->create([
        'role' => 'headquarters',
        'branch_id' => null,
        'store_id' => null,
    ]);

    expect($user->isHeadquarters())->toBeTrue();
    expect($user->isBranch())->toBeFalse();
    expect($user->isStore())->toBeFalse();

    $accessibleStores = $user->getAccessibleStoreIds();
    expect($accessibleStores)->toContain($this->store1->id);
    expect($accessibleStores)->toContain($this->store2->id);
    expect($accessibleStores)->toContain($this->store3->id);
});

it('지사 사용자는 소속 지사의 매장들에만 접근할 수 있다', function () {
    $user = User::factory()->create([
        'role' => 'branch',
        'branch_id' => $this->branch1->id,
        'store_id' => null,
    ]);

    expect($user->isBranch())->toBeTrue();
    expect($user->isHeadquarters())->toBeFalse();
    expect($user->isStore())->toBeFalse();

    $accessibleStores = $user->getAccessibleStoreIds();
    expect($accessibleStores)->toContain($this->store1->id);
    expect($accessibleStores)->toContain($this->store2->id);
    expect($accessibleStores)->not->toContain($this->store3->id); // 다른 지사
});

it('매장 사용자는 자신의 매장에만 접근할 수 있다', function () {
    $user = User::factory()->create([
        'role' => 'store',
        'branch_id' => $this->branch1->id,
        'store_id' => $this->store1->id,
    ]);

    expect($user->isStore())->toBeTrue();
    expect($user->isBranch())->toBeFalse();
    expect($user->isHeadquarters())->toBeFalse();

    $accessibleStores = $user->getAccessibleStoreIds();
    expect($accessibleStores)->toBe([$this->store1->id]);
    expect($accessibleStores)->not->toContain($this->store2->id); // 같은 지사라도 다른 매장
    expect($accessibleStores)->not->toContain($this->store3->id);
});

it('잘못된 역할을 가진 사용자는 빈 접근 권한을 가진다', function () {
    $user = User::factory()->create([
        'role' => 'invalid_role',
        'branch_id' => $this->branch1->id,
        'store_id' => $this->store1->id,
    ]);

    expect($user->isHeadquarters())->toBeFalse();
    expect($user->isBranch())->toBeFalse();
    expect($user->isStore())->toBeFalse();
});

it('매장이 없는 지사의 지사 관리자는 빈 접근 권한을 가진다', function () {
    $emptyBranch = Branch::factory()->create(['name' => '빈지사']);

    $user = User::factory()->create([
        'role' => 'branch',
        'branch_id' => $emptyBranch->id,
        'store_id' => null,
    ]);

    $accessibleStores = $user->getAccessibleStoreIds();
    expect($accessibleStores)->toBeEmpty();
});

it('사용자 역할별 권한 체크 메서드들이 정확히 동작한다', function () {
    $headquartersUser = User::factory()->create(['role' => 'headquarters']);
    $branchUser = User::factory()->create(['role' => 'branch']);
    $storeUser = User::factory()->create(['role' => 'store']);

    // 본사 사용자
    expect($headquartersUser->isHeadquarters())->toBeTrue();
    expect($headquartersUser->isBranch())->toBeFalse();
    expect($headquartersUser->isStore())->toBeFalse();

    // 지사 사용자
    expect($branchUser->isHeadquarters())->toBeFalse();
    expect($branchUser->isBranch())->toBeTrue();
    expect($branchUser->isStore())->toBeFalse();

    // 매장 사용자
    expect($storeUser->isHeadquarters())->toBeFalse();
    expect($storeUser->isBranch())->toBeFalse();
    expect($storeUser->isStore())->toBeTrue();
});
