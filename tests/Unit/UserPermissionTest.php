<?php

namespace Tests\Unit;

use App\Models\Branch;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected $branch1;
    protected $branch2;
    protected $store1;
    protected $store2;
    protected $store3;

    protected function setUp(): void
    {
        parent::setUp();

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
    }

    public function test_본사_사용자는_모든_권한을_가진다(): void
    {
        $user = User::factory()->create([
            'role' => 'headquarters',
            'branch_id' => null,
            'store_id' => null,
        ]);

        $this->assertTrue($user->isHeadquarters());
        $this->assertFalse($user->isBranch());
        $this->assertFalse($user->isStore());

        $accessibleStores = $user->getAccessibleStoreIds();
        $this->assertContains($this->store1->id, $accessibleStores);
        $this->assertContains($this->store2->id, $accessibleStores);
        $this->assertContains($this->store3->id, $accessibleStores);
    }

    public function test_지사_사용자는_소속_지사의_매장들에만_접근할_수_있다(): void
    {
        $user = User::factory()->create([
            'role' => 'branch',
            'branch_id' => $this->branch1->id,
            'store_id' => null,
        ]);

        $this->assertTrue($user->isBranch());
        $this->assertFalse($user->isHeadquarters());
        $this->assertFalse($user->isStore());

        $accessibleStores = $user->getAccessibleStoreIds();
        $this->assertContains($this->store1->id, $accessibleStores);
        $this->assertContains($this->store2->id, $accessibleStores);
        $this->assertNotContains($this->store3->id, $accessibleStores); // 다른 지사
    }

    public function test_매장_사용자는_자신의_매장에만_접근할_수_있다(): void
    {
        $user = User::factory()->create([
            'role' => 'store',
            'branch_id' => $this->branch1->id,
            'store_id' => $this->store1->id,
        ]);

        $this->assertTrue($user->isStore());
        $this->assertFalse($user->isBranch());
        $this->assertFalse($user->isHeadquarters());

        $accessibleStores = $user->getAccessibleStoreIds();
        $this->assertEquals([$this->store1->id], $accessibleStores);
        $this->assertNotContains($this->store2->id, $accessibleStores); // 같은 지사라도 다른 매장
        $this->assertNotContains($this->store3->id, $accessibleStores);
    }

    public function test_잘못된_역할을_가진_사용자는_빈_접근_권한을_가진다(): void
    {
        $user = User::factory()->create([
            'role' => 'invalid_role',
            'branch_id' => $this->branch1->id,
            'store_id' => $this->store1->id,
        ]);

        $this->assertFalse($user->isHeadquarters());
        $this->assertFalse($user->isBranch());
        $this->assertFalse($user->isStore());
    }

    public function test_매장이_없는_지사의_지사_관리자는_빈_접근_권한을_가진다(): void
    {
        $emptyBranch = Branch::factory()->create(['name' => '빈지사']);

        $user = User::factory()->create([
            'role' => 'branch',
            'branch_id' => $emptyBranch->id,
            'store_id' => null,
        ]);

        $accessibleStores = $user->getAccessibleStoreIds();
        $this->assertEmpty($accessibleStores);
    }

    public function test_사용자_역할별_권한_체크_메서드들이_정확히_동작한다(): void
    {
        $headquartersUser = User::factory()->create(['role' => 'headquarters']);
        $branchUser = User::factory()->create(['role' => 'branch']);
        $storeUser = User::factory()->create(['role' => 'store']);

        // 본사 사용자
        $this->assertTrue($headquartersUser->isHeadquarters());
        $this->assertFalse($headquartersUser->isBranch());
        $this->assertFalse($headquartersUser->isStore());

        // 지사 사용자
        $this->assertFalse($branchUser->isHeadquarters());
        $this->assertTrue($branchUser->isBranch());
        $this->assertFalse($branchUser->isStore());

        // 매장 사용자
        $this->assertFalse($storeUser->isHeadquarters());
        $this->assertFalse($storeUser->isBranch());
        $this->assertTrue($storeUser->isStore());
    }
}