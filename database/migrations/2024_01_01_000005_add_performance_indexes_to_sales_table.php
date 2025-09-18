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
            // 쿼리 성능 최적화를 위한 인덱스 추가

            // 1. 날짜별 조회 최적화 (가장 자주 사용되는 필터)
            $table->index('sale_date');

            // 2. 매장별 조회 최적화
            $table->index('store_id');

            // 3. 지사별 조회 최적화
            $table->index('branch_id');

            // 4. 통신사별 통계 최적화
            $table->index('carrier');

            // 5. 개통 유형별 통계 최적화
            $table->index('activation_type');

            // 6. 복합 인덱스: 날짜 + 매장 (가장 빈번한 조회 패턴)
            $table->index(['sale_date', 'store_id'], 'idx_sales_date_store');

            // 7. 복합 인덱스: 날짜 + 지사 (지사별 기간 조회)
            $table->index(['sale_date', 'branch_id'], 'idx_sales_date_branch');

            // 8. 복합 인덱스: 지사 + 매장 (권한 체크용)
            $table->index(['branch_id', 'store_id'], 'idx_sales_branch_store');

            // 9. 통계용 복합 인덱스: 날짜 + 통신사
            $table->index(['sale_date', 'carrier'], 'idx_sales_date_carrier');

            // 10. 통계용 복합 인덱스: 날짜 + 개통유형
            $table->index(['sale_date', 'activation_type'], 'idx_sales_date_activation');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            // 단일 인덱스 제거
            $table->dropIndex(['sale_date']);
            $table->dropIndex(['store_id']);
            $table->dropIndex(['branch_id']);
            $table->dropIndex(['carrier']);
            $table->dropIndex(['activation_type']);

            // 복합 인덱스 제거
            $table->dropIndex('idx_sales_date_store');
            $table->dropIndex('idx_sales_date_branch');
            $table->dropIndex('idx_sales_branch_store');
            $table->dropIndex('idx_sales_date_carrier');
            $table->dropIndex('idx_sales_date_activation');
        });
    }
};
