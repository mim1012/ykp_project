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
        Schema::table('sales', function (Blueprint $table) {
            // API 성능 최적화용 복합 인덱스
            $table->index(['store_id', 'sale_date', 'settlement_amount'], 'idx_sales_store_date_amount');
            $table->index(['sale_date', 'settlement_amount'], 'idx_sales_date_amount');
            $table->index(['store_id', 'settlement_amount'], 'idx_sales_store_amount');

            // 통계 API 최적화용 인덱스
            $table->index(['created_at', 'settlement_amount'], 'idx_sales_created_amount');
        });

        Schema::table('stores', function (Blueprint $table) {
            // 매장 관리 API 최적화용 인덱스
            $table->index(['branch_id', 'status'], 'idx_stores_branch_status');
            $table->index(['status', 'created_at'], 'idx_stores_status_created');
        });

        Schema::table('users', function (Blueprint $table) {
            // 사용자 관리 API 최적화용 인덱스
            $table->index(['role', 'is_active'], 'idx_users_role_active');
            $table->index(['store_id', 'is_active'], 'idx_users_store_active');
            $table->index(['branch_id', 'is_active'], 'idx_users_branch_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex('idx_sales_store_date_amount');
            $table->dropIndex('idx_sales_date_amount');
            $table->dropIndex('idx_sales_store_amount');
            $table->dropIndex('idx_sales_created_amount');
        });

        Schema::table('stores', function (Blueprint $table) {
            $table->dropIndex('idx_stores_branch_status');
            $table->dropIndex('idx_stores_status_created');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_role_active');
            $table->dropIndex('idx_users_store_active');
            $table->dropIndex('idx_users_branch_active');
        });
    }
};
