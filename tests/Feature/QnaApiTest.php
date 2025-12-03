<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\QnaPost;
use App\Models\QnaReply;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class QnaApiTest extends TestCase
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
    public function store_user_can_create_public_post()
    {
        $response = $this->actingAs($this->storeUser)
            ->postJson('/api/qna/posts', [
                'title' => '테스트 질문',
                'content' => '이것은 테스트 질문입니다.',
                'is_private' => false,
            ]);

        $response->assertStatus(201)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Q&A post created successfully',
                 ])
                 ->assertJsonStructure([
                     'success',
                     'message',
                     'data' => [
                         'id',
                         'title',
                         'content',
                         'author_user_id',
                         'status',
                         'is_private',
                     ],
                 ]);

        $this->assertDatabaseHas('qna_posts', [
            'title' => '테스트 질문',
            'author_user_id' => $this->storeUser->id,
            'store_id' => $this->store->id,
            'is_private' => false,
            'status' => 'pending',
        ]);

        echo "\n✅ Test passed: Store user can create public post\n";
    }

    #[Test]
    public function store_user_can_create_private_post()
    {
        $response = $this->actingAs($this->storeUser)
            ->postJson('/api/qna/posts', [
                'title' => '비밀 질문',
                'content' => '이것은 비밀 질문입니다.',
                'is_private' => true,
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('qna_posts', [
            'title' => '비밀 질문',
            'is_private' => true,
        ]);

        echo "\n✅ Test passed: Store user can create private post\n";
    }

    #[Test]
    public function branch_user_can_create_post()
    {
        $response = $this->actingAs($this->branchUser)
            ->postJson('/api/qna/posts', [
                'title' => '지사 질문',
                'content' => '지사에서 작성한 질문입니다.',
                'is_private' => false,
            ]);

        $response->assertStatus(201)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Q&A post created successfully',
                 ]);

        $this->assertDatabaseHas('qna_posts', [
            'title' => '지사 질문',
            'author_user_id' => $this->branchUser->id,
            'branch_id' => $this->branch->id,
        ]);

        echo "\n✅ Test passed: Branch user can create post\n";
    }

    #[Test]
    public function hq_user_cannot_create_post()
    {
        $response = $this->actingAs($this->hqUser)
            ->postJson('/api/qna/posts', [
                'title' => '본사 질문',
                'content' => '내용',
            ]);

        $response->assertStatus(403)
                 ->assertJson([
                     'success' => false,
                     'message' => 'Only store or branch users can create Q&A posts',
                 ]);

        echo "\n✅ Test passed: HQ user cannot create post (they answer, not ask)\n";
    }

    #[Test]
    public function store_user_can_view_own_posts()
    {
        $post = QnaPost::factory()->create([
            'author_user_id' => $this->storeUser->id,
            'store_id' => $this->store->id,
            'branch_id' => $this->branch->id,
            'author_role' => 'store',
            'is_private' => true,
        ]);

        $response = $this->actingAs($this->storeUser)
            ->getJson('/api/qna/posts');

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                 ]);

        $data = $response->json('data');
        $this->assertGreaterThan(0, count($data));

        echo "\n✅ Test passed: Store user can view own posts\n";
    }

    #[Test]
    public function store_user_cannot_view_other_store_private_post()
    {
        $otherStore = Store::factory()->create(['branch_id' => $this->branch->id]);
        $otherUser = User::factory()->create([
            'role' => 'store',
            'store_id' => $otherStore->id,
            'branch_id' => $this->branch->id,
        ]);

        $privatePost = QnaPost::factory()->private()->create([
            'author_user_id' => $otherUser->id,
            'store_id' => $otherStore->id,
            'branch_id' => $this->branch->id,
            'author_role' => 'store',
        ]);

        $response = $this->actingAs($this->storeUser)
            ->getJson("/api/qna/posts/{$privatePost->id}");

        $response->assertStatus(403)
                 ->assertJson([
                     'success' => false,
                     'message' => 'Unauthorized to view this post',
                 ]);

        echo "\n✅ Test passed: Store user cannot view other store's private post\n";
    }

    #[Test]
    public function branch_user_can_view_branch_posts()
    {
        $publicPost = QnaPost::factory()->public()->create([
            'author_user_id' => $this->storeUser->id,
            'store_id' => $this->store->id,
            'branch_id' => $this->branch->id,
            'author_role' => 'store',
        ]);

        $privatePost = QnaPost::factory()->private()->create([
            'author_user_id' => $this->storeUser->id,
            'store_id' => $this->store->id,
            'branch_id' => $this->branch->id,
            'author_role' => 'store',
        ]);

        $response = $this->actingAs($this->branchUser)
            ->getJson('/api/qna/posts');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertGreaterThanOrEqual(2, count($data));

        echo "\n✅ Test passed: Branch user can view branch posts (public + private)\n";
    }

    #[Test]
    public function hq_user_can_view_all_posts()
    {
        $otherBranch = Branch::factory()->create(['code' => 'OTHER']);
        $otherStore = Store::factory()->create(['branch_id' => $otherBranch->id]);
        $otherUser = User::factory()->create([
            'role' => 'store',
            'store_id' => $otherStore->id,
            'branch_id' => $otherBranch->id,
        ]);

        QnaPost::factory()->private()->create([
            'author_user_id' => $otherUser->id,
            'store_id' => $otherStore->id,
            'branch_id' => $otherBranch->id,
            'author_role' => 'store',
        ]);

        $response = $this->actingAs($this->hqUser)
            ->getJson('/api/qna/posts');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertGreaterThan(0, count($data));

        echo "\n✅ Test passed: HQ user can view all posts\n";
    }

    #[Test]
    public function store_user_can_update_pending_post()
    {
        $post = QnaPost::factory()->pending()->create([
            'author_user_id' => $this->storeUser->id,
            'store_id' => $this->store->id,
            'branch_id' => $this->branch->id,
            'author_role' => 'store',
            'title' => '원래 제목',
        ]);

        $response = $this->actingAs($this->storeUser)
            ->putJson("/api/qna/posts/{$post->id}", [
                'title' => '수정된 제목',
                'content' => '수정된 내용',
            ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Q&A post updated successfully',
                 ]);

        $this->assertDatabaseHas('qna_posts', [
            'id' => $post->id,
            'title' => '수정된 제목',
            'content' => '수정된 내용',
        ]);

        echo "\n✅ Test passed: Store user can update pending post\n";
    }

    #[Test]
    public function store_user_cannot_update_answered_post()
    {
        $post = QnaPost::factory()->answered()->create([
            'author_user_id' => $this->storeUser->id,
            'store_id' => $this->store->id,
            'branch_id' => $this->branch->id,
            'author_role' => 'store',
        ]);

        $response = $this->actingAs($this->storeUser)
            ->putJson("/api/qna/posts/{$post->id}", [
                'title' => '수정 시도',
            ]);

        $response->assertStatus(400)
                 ->assertJson([
                     'success' => false,
                     'message' => 'Cannot update answered or closed posts',
                 ]);

        echo "\n✅ Test passed: Store user cannot update answered post\n";
    }

    #[Test]
    public function store_user_can_delete_pending_post()
    {
        $post = QnaPost::factory()->pending()->create([
            'author_user_id' => $this->storeUser->id,
            'store_id' => $this->store->id,
            'branch_id' => $this->branch->id,
            'author_role' => 'store',
        ]);

        $response = $this->actingAs($this->storeUser)
            ->deleteJson("/api/qna/posts/{$post->id}");

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Q&A post deleted successfully',
                 ]);

        $this->assertDatabaseMissing('qna_posts', ['id' => $post->id]);

        echo "\n✅ Test passed: Store user can delete pending post\n";
    }

    #[Test]
    public function hq_user_can_reply_to_post()
    {
        $post = QnaPost::factory()->pending()->create([
            'author_user_id' => $this->storeUser->id,
            'store_id' => $this->store->id,
            'branch_id' => $this->branch->id,
            'author_role' => 'store',
        ]);

        $response = $this->actingAs($this->hqUser)
            ->postJson("/api/qna/posts/{$post->id}/replies", [
                'content' => '본사 답변입니다.',
            ]);

        $response->assertStatus(201)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Reply added successfully',
                 ]);

        $this->assertDatabaseHas('qna_replies', [
            'qna_post_id' => $post->id,
            'author_user_id' => $this->hqUser->id,
            'content' => '본사 답변입니다.',
            'is_official_answer' => true,
        ]);

        // Check post status changed to 'answered'
        $post->refresh();
        $this->assertEquals('answered', $post->status);

        echo "\n✅ Test passed: HQ user can reply and post status changes to 'answered'\n";
    }

    #[Test]
    public function branch_user_can_reply_to_branch_post()
    {
        $post = QnaPost::factory()->pending()->create([
            'author_user_id' => $this->storeUser->id,
            'store_id' => $this->store->id,
            'branch_id' => $this->branch->id,
            'author_role' => 'store',
        ]);

        $response = $this->actingAs($this->branchUser)
            ->postJson("/api/qna/posts/{$post->id}/replies", [
                'content' => '지사 답변입니다.',
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('qna_replies', [
            'qna_post_id' => $post->id,
            'author_user_id' => $this->branchUser->id,
            'is_official_answer' => false, // Branch reply is not official
        ]);

        echo "\n✅ Test passed: Branch user can reply to branch post\n";
    }

    #[Test]
    public function store_user_cannot_reply_to_closed_post()
    {
        $post = QnaPost::factory()->closed()->create([
            'author_user_id' => $this->storeUser->id,
            'store_id' => $this->store->id,
            'branch_id' => $this->branch->id,
            'author_role' => 'store',
        ]);

        $response = $this->actingAs($this->storeUser)
            ->postJson("/api/qna/posts/{$post->id}/replies", [
                'content' => '추가 질문',
            ]);

        $response->assertStatus(403)
                 ->assertJson([
                     'success' => false,
                     'message' => 'Unauthorized to reply to this post',
                 ]);

        echo "\n✅ Test passed: Store user cannot reply to closed post\n";
    }

    #[Test]
    public function hq_user_can_close_post()
    {
        $post = QnaPost::factory()->answered()->create([
            'author_user_id' => $this->storeUser->id,
            'store_id' => $this->store->id,
            'branch_id' => $this->branch->id,
            'author_role' => 'store',
        ]);

        $response = $this->actingAs($this->hqUser)
            ->postJson("/api/qna/posts/{$post->id}/close");

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Q&A post closed successfully',
                 ]);

        $post->refresh();
        $this->assertEquals('closed', $post->status);

        echo "\n✅ Test passed: HQ user can close post\n";
    }

    #[Test]
    public function post_author_can_close_own_post()
    {
        $post = QnaPost::factory()->answered()->create([
            'author_user_id' => $this->storeUser->id,
            'store_id' => $this->store->id,
            'branch_id' => $this->branch->id,
            'author_role' => 'store',
        ]);

        $response = $this->actingAs($this->storeUser)
            ->postJson("/api/qna/posts/{$post->id}/close");

        $response->assertStatus(200);

        $post->refresh();
        $this->assertEquals('closed', $post->status);

        echo "\n✅ Test passed: Post author can close own post\n";
    }

    #[Test]
    public function view_count_increments_on_read()
    {
        $post = QnaPost::factory()->public()->create([
            'author_user_id' => $this->storeUser->id,
            'store_id' => $this->store->id,
            'branch_id' => $this->branch->id,
            'author_role' => 'store',
            'view_count' => 0,
        ]);

        // HQ user views the post
        $this->actingAs($this->hqUser)
            ->getJson("/api/qna/posts/{$post->id}");

        $post->refresh();
        $this->assertEquals(1, $post->view_count);

        // Author views own post - should not increment
        $this->actingAs($this->storeUser)
            ->getJson("/api/qna/posts/{$post->id}");

        $post->refresh();
        $this->assertEquals(1, $post->view_count); // Still 1

        echo "\n✅ Test passed: View count increments correctly\n";
    }

    #[Test]
    public function can_filter_posts_by_status()
    {
        QnaPost::factory()->pending()->create([
            'author_user_id' => $this->storeUser->id,
            'store_id' => $this->store->id,
            'branch_id' => $this->branch->id,
            'author_role' => 'store',
        ]);

        QnaPost::factory()->answered()->create([
            'author_user_id' => $this->storeUser->id,
            'store_id' => $this->store->id,
            'branch_id' => $this->branch->id,
            'author_role' => 'store',
        ]);

        $response = $this->actingAs($this->hqUser)
            ->getJson('/api/qna/posts?status=pending');

        $response->assertStatus(200);
        $data = $response->json('data');

        foreach ($data as $post) {
            $this->assertEquals('pending', $post['status']);
        }

        echo "\n✅ Test passed: Can filter posts by status\n";
    }

    #[Test]
    public function can_search_posts()
    {
        QnaPost::factory()->create([
            'author_user_id' => $this->storeUser->id,
            'store_id' => $this->store->id,
            'branch_id' => $this->branch->id,
            'author_role' => 'store',
            'title' => '리베이트 계산 방법',
            'content' => '리베이트 계산이 어렵습니다',
        ]);

        QnaPost::factory()->create([
            'author_user_id' => $this->storeUser->id,
            'store_id' => $this->store->id,
            'branch_id' => $this->branch->id,
            'author_role' => 'store',
            'title' => '판매 등록 문의',
            'content' => '판매 등록 방법',
        ]);

        $response = $this->actingAs($this->hqUser)
            ->getJson('/api/qna/posts?search=리베이트');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertGreaterThan(0, count($data));

        foreach ($data as $post) {
            $this->assertTrue(
                str_contains($post['title'], '리베이트') ||
                str_contains($post['content'], '리베이트')
            );
        }

        echo "\n✅ Test passed: Can search posts by keyword\n";
    }
}
