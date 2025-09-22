<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

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

        // 인덱스 존재 여부 확인 후 생성 (PostgreSQL 호환)
        $existingIndexes = [];
        if (DB::getDriverName() === 'pgsql') {
            // PostgreSQL용 인덱스 조회
            $indexList = DB::select("
                SELECT indexname as name
                FROM pg_indexes
                WHERE tablename = 'stores' AND schemaname = 'public'
            ");
            $existingIndexes = array_column($indexList, 'name');
        } elseif (DB::getDriverName() === 'sqlite') {
            // SQLite용 인덱스 조회
            try {
                $indexList = DB::select("PRAGMA index_list('stores')");
                $existingIndexes = array_column($indexList, 'name');
            } catch (\Exception $e) {
                // SQLite가 아닌 경우 무시
            }
        }

        Schema::table('stores', function (Blueprint $table) use ($existingIndexes) {
            // 매장 관리 API 최적화용 인덱스
            if (!in_array('idx_stores_branch_status', $existingIndexes)) {
                $table->index(['branch_id', 'status'], 'idx_stores_branch_status');
            }
            if (!in_array('idx_stores_status_created', $existingIndexes)) {
                $table->index(['status', 'created_at'], 'idx_stores_status_created');
            }
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
