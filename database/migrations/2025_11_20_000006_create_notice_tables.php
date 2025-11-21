<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 공지사항 테이블
        Schema::create('notice_posts', function (Blueprint $table) {
            $table->id();

            // 게시글 정보
            $table->string('title');
            $table->text('content');

            // 작성자 (본사/지사만)
            $table->foreignId('author_user_id')->constrained('users')->onDelete('cascade');

            // 대상 설정
            $table->enum('target_audience', ['all', 'branches', 'stores', 'specific'])->default('all');
            $table->json('target_branch_ids')->nullable(); // 특정 지사 대상
            $table->json('target_store_ids')->nullable(); // 특정 매장 대상

            // 고정 및 우선순위
            $table->boolean('is_pinned')->default(false);
            $table->integer('priority')->default(0);

            // 게시 기간
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            // 조회수
            $table->integer('view_count')->default(0);

            $table->timestamps();

            // 인덱스
            $table->index(['is_pinned', 'priority', 'published_at']); // 정렬용
            $table->index('target_audience');
            $table->index(['published_at', 'expires_at']); // 유효기간 검색
        });

        // 게시판 조회 기록 테이블 (Q&A + 공지사항 공용)
        Schema::create('board_views', function (Blueprint $table) {
            $table->id();

            // Polymorphic relation
            $table->string('viewable_type'); // 'App\Models\QnaPost' or 'App\Models\NoticePost'
            $table->unsignedBigInteger('viewable_id');

            // 조회한 사용자
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // 조회 시각
            $table->timestamp('viewed_at');

            // 인덱스
            $table->index(['viewable_type', 'viewable_id']);
            $table->index('user_id');

            // 중복 방지 (한 사용자가 같은 게시글을 여러 번 조회해도 1번만 기록)
            $table->unique(['viewable_type', 'viewable_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('board_views');
        Schema::dropIfExists('notice_posts');
    }
};
