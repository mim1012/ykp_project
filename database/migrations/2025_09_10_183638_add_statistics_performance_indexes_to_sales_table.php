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
            // 통계 API 최적화를 위한 복합 인덱스
            $table->index(['sale_date', 'store_id', 'branch_id'], 'idx_sales_statistics_performance');

            // 통신사별 분석용 인덱스
            $table->index(['carrier', 'sale_date'], 'idx_sales_carrier_analysis');

            // 매출 랭킹용 인덱스 (settlement_amount 내림차순)
            $table->index(['settlement_amount', 'sale_date'], 'idx_sales_revenue_ranking');

            // 지사별 집계용 인덱스
            $table->index(['branch_id', 'sale_date'], 'idx_sales_branch_aggregation');
        });

        Schema::table('stores', function (Blueprint $table) {
            // 매장 관리 최적화용 인덱스
            $table->index(['branch_id', 'status'], 'idx_stores_branch_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex('idx_sales_statistics_performance');
            $table->dropIndex('idx_sales_carrier_analysis');
            $table->dropIndex('idx_sales_revenue_ranking');
            $table->dropIndex('idx_sales_branch_aggregation');
        });

        Schema::table('stores', function (Blueprint $table) {
            $table->dropIndex('idx_stores_branch_status');
        });
    }
};
