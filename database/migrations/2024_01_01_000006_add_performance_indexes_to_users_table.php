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
        Schema::table('users', function (Blueprint $table) {
            // 사용자 권한 체크 최적화 인덱스

            // 1. 역할별 조회 최적화
            $table->index('role');

            // 2. 지사별 사용자 조회 최적화
            $table->index('branch_id');

            // 3. 매장별 사용자 조회 최적화
            $table->index('store_id');

            // 4. 복합 인덱스: 역할 + 지사 (권한 체크 최적화)
            $table->index(['role', 'branch_id'], 'idx_users_role_branch');

            // 5. 복합 인덱스: 역할 + 매장 (권한 체크 최적화)
            $table->index(['role', 'store_id'], 'idx_users_role_store');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // 단일 인덱스 제거
            $table->dropIndex(['role']);
            $table->dropIndex(['branch_id']);
            $table->dropIndex(['store_id']);

            // 복합 인덱스 제거
            $table->dropIndex('idx_users_role_branch');
            $table->dropIndex('idx_users_role_store');
        });
    }
};
