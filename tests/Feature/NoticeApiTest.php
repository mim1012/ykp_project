<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\NoticePost;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NoticeApiTest extends TestCase
{
    use RefreshDatabase;

    protected $storeUser;

    protected $branchUser;

    protected $hqUser;

    protected $store;

    protected $branch;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test branch
        $this->branch = Branch::factory()->create([
            'code' => 'TEST',
            'name' => '테스트지사',
        ]);

        // Create test store
        $this->store = Store::factory()->create([
            'branch_id' => $this->branch->id,
            'code' => 'TEST-001',
            'name' => '테스트매장',
        ]);

        // Create test users
        $this->storeUser = User::factory()->create([
            'role' => 'store',
            'store_id' => $this->store->id,
            'branch_id' => $this->branch->id,
            'email' => 'test@store.com',
        ]);

        $this->branchUser = User::factory()->create([
            'role' => 'branch',
            'branch_id' => $this->branch->id,
            'email' => 'test@branch.com',
        ]);

        $this->hqUser = User::factory()->create([
            'role' => 'headquarters',
            'email' => 'test@hq.com',
        ]);
    }

    #[Test]
    public function hq_user_can_create_notice_for_all()
    {
        $response = $this->actingAs($this->hqUser)
            ->postJson('/api/notices', [
                'title' => '전체 공지사항',
                'content' => '모든 사용자를 위한 공지사항입니다.',
                'target_audience' => 'all',
            ]);

        $response->assertStatus(201)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Notice created successfully',
                 ]);

        $this->assertDatabaseHas('notice_posts', [
            'title' => '전체 공지사항',
            'target_audience' => 'all',
            'author_user_id' => $this->hqUser->id,
        ]);

        echo "\n✅ Test passed: HQ user can create notice for all\n";
    }

    #[Test]
    public function branch_user_can_create_notice_for_own_branch()
    {
        $response = $this->actingAs($this->branchUser)
            ->postJson('/api/notices', [
                'title' => '지사 공지',
                'content' => '우리 지사를 위한 공지입니다.',
                'target_audience' => 'branches',
                'target_branch_ids' => [$this->branch->id],
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('notice_posts', [
            'title' => '지사 공지',
            'target_audience' => 'branches',
        ]);

        echo "\n✅ Test passed: Branch user can create notice for own branch\n";
    }

    #[Test]
    public function branch_user_cannot_create_notice_for_all()
    {
        $response = $this->actingAs($this->branchUser)
            ->postJson('/api/notices', [
                'title' => '전체 공지',
                'content' => '내용',
                'target_audience' => 'all',
            ]);

        $response->assertStatus(403)
                 ->assertJson([
                     'success' => false,
                     'message' => 'Branch users cannot create notices for all users',
                 ]);

        echo "\n✅ Test passed: Branch user cannot create notice for all\n";
    }

    #[Test]
    public function branch_user_cannot_target_other_branch()
    {
        $otherBranch = Branch::factory()->create(['code' => 'OTHER']);

        $response = $this->actingAs($this->branchUser)
            ->postJson('/api/notices', [
                'title' => '다른 지사 공지',
                'content' => '내용',
                'target_audience' => 'branches',
                'target_branch_ids' => [$otherBranch->id],
            ]);

        $response->assertStatus(403)
                 ->assertJson([
                     'success' => false,
                     'message' => 'Branch users can only target their own branch',
                 ]);

        echo "\n✅ Test passed: Branch user cannot target other branch\n";
    }

    #[Test]
    public function store_user_cannot_create_notice()
    {
        $response = $this->actingAs($this->storeUser)
            ->postJson('/api/notices', [
                'title' => '매장 공지',
                'content' => '내용',
                'target_audience' => 'all',
            ]);

        $response->assertStatus(403)
                 ->assertJson([
                     'success' => false,
                     'message' => 'Only headquarters and branch users can create notices',
                 ]);

        echo "\n✅ Test passed: Store user cannot create notice\n";
    }

    #[Test]
    public function all_users_can_see_notice_for_all()
    {
        $notice = NoticePost::factory()->forAll()->create([
            'author_user_id' => $this->hqUser->id,
        ]);

        // Store user can see
        $response1 = $this->actingAs($this->storeUser)->getJson('/api/notices');
        $response1->assertStatus(200);
        $this->assertGreaterThan(0, count($response1->json('data')));

        // Branch user can see
        $response2 = $this->actingAs($this->branchUser)->getJson('/api/notices');
        $response2->assertStatus(200);
        $this->assertGreaterThan(0, count($response2->json('data')));

        // HQ user can see
        $response3 = $this->actingAs($this->hqUser)->getJson('/api/notices');
        $response3->assertStatus(200);
        $this->assertGreaterThan(0, count($response3->json('data')));

        echo "\n✅ Test passed: All users can see notice for all\n";
    }

    #[Test]
    public function branch_user_can_see_branch_targeted_notice()
    {
        $notice = NoticePost::factory()->forBranches([$this->branch->id])->create([
            'author_user_id' => $this->hqUser->id,
        ]);

        // Branch user can see
        $response = $this->actingAs($this->branchUser)->getJson('/api/notices');
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertGreaterThan(0, count($data));

        echo "\n✅ Test passed: Branch user can see branch-targeted notice\n";
    }

    #[Test]
    public function store_user_can_see_store_targeted_notice()
    {
        $notice = NoticePost::factory()->forStores([$this->store->id])->create([
            'author_user_id' => $this->hqUser->id,
        ]);

        $response = $this->actingAs($this->storeUser)->getJson('/api/notices');
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertGreaterThan(0, count($data));

        echo "\n✅ Test passed: Store user can see store-targeted notice\n";
    }

    #[Test]
    public function store_user_cannot_see_other_store_targeted_notice()
    {
        $otherStore = Store::factory()->create(['branch_id' => $this->branch->id]);
        $notice = NoticePost::factory()->forStores([$otherStore->id])->create([
            'author_user_id' => $this->hqUser->id,
        ]);

        $response = $this->actingAs($this->storeUser)->getJson('/api/notices');
        $response->assertStatus(200);
        $data = $response->json('data');

        // Should not contain the notice targeted to other store
        $noticeIds = collect($data)->pluck('id')->toArray();
        $this->assertNotContains($notice->id, $noticeIds);

        echo "\n✅ Test passed: Store user cannot see other store's targeted notice\n";
    }

    #[Test]
    public function pinned_notices_appear_first()
    {
        $regularNotice = NoticePost::factory()->forAll()->create([
            'author_user_id' => $this->hqUser->id,
            'title' => '일반 공지',
            'is_pinned' => false,
            'priority' => 0,
        ]);

        $pinnedNotice = NoticePost::factory()->forAll()->pinned()->create([
            'author_user_id' => $this->hqUser->id,
            'title' => '고정 공지',
        ]);

        $response = $this->actingAs($this->storeUser)->getJson('/api/notices');
        $response->assertStatus(200);

        $data = $response->json('data');
        $firstNotice = $data[0];

        $this->assertEquals($pinnedNotice->id, $firstNotice['id']);
        $this->assertTrue($firstNotice['is_pinned']);

        echo "\n✅ Test passed: Pinned notices appear first\n";
    }

    #[Test]
    public function expired_notices_are_hidden_by_default()
    {
        $expiredNotice = NoticePost::factory()->forAll()->expired()->create([
            'author_user_id' => $this->hqUser->id,
        ]);

        $activeNotice = NoticePost::factory()->forAll()->create([
            'author_user_id' => $this->hqUser->id,
        ]);

        // Without include_expired parameter
        $response = $this->actingAs($this->storeUser)->getJson('/api/notices');
        $data = $response->json('data');
        $noticeIds = collect($data)->pluck('id')->toArray();

        $this->assertNotContains($expiredNotice->id, $noticeIds);
        $this->assertContains($activeNotice->id, $noticeIds);

        echo "\n✅ Test passed: Expired notices are hidden by default\n";
    }

    #[Test]
    public function expired_notices_can_be_included()
    {
        $expiredNotice = NoticePost::factory()->forAll()->expired()->create([
            'author_user_id' => $this->hqUser->id,
        ]);

        // With include_expired=true
        $response = $this->actingAs($this->storeUser)
            ->getJson('/api/notices?include_expired=true');

        $data = $response->json('data');
        $noticeIds = collect($data)->pluck('id')->toArray();

        $this->assertContains($expiredNotice->id, $noticeIds);

        echo "\n✅ Test passed: Expired notices can be included\n";
    }

    #[Test]
    public function author_can_update_notice()
    {
        $notice = NoticePost::factory()->forAll()->create([
            'author_user_id' => $this->hqUser->id,
            'title' => '원래 제목',
        ]);

        $response = $this->actingAs($this->hqUser)
            ->putJson("/api/notices/{$notice->id}", [
                'title' => '수정된 제목',
            ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Notice updated successfully',
                 ]);

        $this->assertDatabaseHas('notice_posts', [
            'id' => $notice->id,
            'title' => '수정된 제목',
        ]);

        echo "\n✅ Test passed: Author can update notice\n";
    }

    #[Test]
    public function non_author_cannot_update_notice()
    {
        $notice = NoticePost::factory()->forAll()->create([
            'author_user_id' => $this->hqUser->id,
        ]);

        $response = $this->actingAs($this->branchUser)
            ->putJson("/api/notices/{$notice->id}", [
                'title' => '수정 시도',
            ]);

        $response->assertStatus(403)
                 ->assertJson([
                     'success' => false,
                     'message' => 'Unauthorized to update this notice',
                 ]);

        echo "\n✅ Test passed: Non-author cannot update notice\n";
    }

    #[Test]
    public function hq_can_delete_any_notice()
    {
        $notice = NoticePost::factory()->forAll()->create([
            'author_user_id' => $this->branchUser->id,
        ]);

        $response = $this->actingAs($this->hqUser)
            ->deleteJson("/api/notices/{$notice->id}");

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Notice deleted successfully',
                 ]);

        $this->assertDatabaseMissing('notice_posts', ['id' => $notice->id]);

        echo "\n✅ Test passed: HQ can delete any notice\n";
    }

    #[Test]
    public function author_can_delete_own_notice()
    {
        $notice = NoticePost::factory()->forAll()->create([
            'author_user_id' => $this->branchUser->id,
        ]);

        $response = $this->actingAs($this->branchUser)
            ->deleteJson("/api/notices/{$notice->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('notice_posts', ['id' => $notice->id]);

        echo "\n✅ Test passed: Author can delete own notice\n";
    }

    #[Test]
    public function hq_can_pin_notice()
    {
        $notice = NoticePost::factory()->forAll()->create([
            'author_user_id' => $this->hqUser->id,
            'is_pinned' => false,
        ]);

        $response = $this->actingAs($this->hqUser)
            ->postJson("/api/notices/{$notice->id}/toggle-pin");

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Notice pinned',
                 ]);

        $notice->refresh();
        $this->assertTrue($notice->is_pinned);

        echo "\n✅ Test passed: HQ can pin notice\n";
    }

    #[Test]
    public function branch_user_cannot_pin_notice()
    {
        $notice = NoticePost::factory()->forAll()->create([
            'author_user_id' => $this->branchUser->id,
            'is_pinned' => false,
        ]);

        $response = $this->actingAs($this->branchUser)
            ->postJson("/api/notices/{$notice->id}/toggle-pin");

        $response->assertStatus(403)
                 ->assertJson([
                     'success' => false,
                     'message' => 'Only headquarters can pin/unpin notices',
                 ]);

        echo "\n✅ Test passed: Branch user cannot pin notice\n";
    }

    #[Test]
    public function view_count_increments_on_read()
    {
        $notice = NoticePost::factory()->forAll()->create([
            'author_user_id' => $this->hqUser->id,
            'view_count' => 0,
        ]);

        $response = $this->actingAs($this->storeUser)
            ->getJson("/api/notices/{$notice->id}");

        $response->assertStatus(200);

        $notice->refresh();
        $this->assertEquals(1, $notice->view_count);

        echo "\n✅ Test passed: View count increments on read\n";
    }

    #[Test]
    public function can_search_notices()
    {
        NoticePost::factory()->forAll()->create([
            'author_user_id' => $this->hqUser->id,
            'title' => '리베이트 변경 안내',
            'content' => '리베이트 정책이 변경되었습니다',
        ]);

        NoticePost::factory()->forAll()->create([
            'author_user_id' => $this->hqUser->id,
            'title' => '시스템 점검 안내',
            'content' => '시스템 점검 예정입니다',
        ]);

        $response = $this->actingAs($this->storeUser)
            ->getJson('/api/notices?search=리베이트');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertGreaterThan(0, count($data));

        foreach ($data as $notice) {
            $this->assertTrue(
                str_contains($notice['title'], '리베이트') ||
                str_contains($notice['content'], '리베이트')
            );
        }

        echo "\n✅ Test passed: Can search notices by keyword\n";
    }
}
