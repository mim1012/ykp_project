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
        // Q&A 게시글 테이블
        Schema::create('qna_posts', function (Blueprint $table) {
            $table->id();

            // 게시글 정보
            $table->string('title');
            $table->text('content');

            // 작성자
            $table->foreignId('author_user_id')->constrained('users')->onDelete('cascade');
            $table->enum('author_role', ['headquarters', 'branch', 'store']);

            // 매장/지사 정보 (RBAC 필터링용)
            $table->foreignId('store_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('branch_id')->nullable()->constrained()->onDelete('cascade');

            // 비밀글 여부
            $table->boolean('is_private')->default(false);

            // 상태
            $table->enum('status', ['pending', 'answered', 'closed'])->default('pending');

            // 조회수
            $table->integer('view_count')->default(0);

            $table->timestamps();

            // 인덱스
            $table->index(['author_user_id', 'status']);
            $table->index(['is_private', 'author_role', 'status']);
            $table->index('store_id');
            $table->index('branch_id');
        });

        // Q&A 답변 테이블
        Schema::create('qna_replies', function (Blueprint $table) {
            $table->id();

            // 게시글 참조
            $table->foreignId('qna_post_id')->constrained()->onDelete('cascade');

            // 작성자
            $table->foreignId('author_user_id')->constrained('users')->onDelete('cascade');

            // 답변 내용
            $table->text('content');

            // 공식 답변 여부 (본사가 답변한 경우)
            $table->boolean('is_official_answer')->default(false);

            $table->timestamps();

            // 인덱스
            $table->index('qna_post_id');
            $table->index(['qna_post_id', 'created_at']); // 시간순 정렬
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('qna_replies');
        Schema::dropIfExists('qna_posts');
    }
};
