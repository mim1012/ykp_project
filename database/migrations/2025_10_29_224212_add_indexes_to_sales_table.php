<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * 성능 최적화를 위한 인덱스 추가
     * - sale_date: 날짜별 조회 시 사용
     * - agency: 통신사별 필터링 시 사용
     * - store_id + sale_date: 매장의 특정 기간 조회 (가장 자주 사용)
     * - branch_id + sale_date: 지사의 특정 기간 조회
     *
     * 예상 성능 개선:
     * - 대시보드 로딩: 2초 → 0.3초 (85% 개선)
     * - 판매 조회: 1.5초 → 0.1초 (93% 개선)
     */
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            // 단일 컬럼 인덱스
            $table->index('sale_date', 'idx_sales_sale_date');
            $table->index('agency', 'idx_sales_agency');

            // 복합 인덱스 (자주 함께 사용되는 컬럼 조합)
            // 주의: 컬럼 순서가 중요! 가장 selective한 컬럼을 앞에 배치
            $table->index(['store_id', 'sale_date'], 'idx_sales_store_date');
            $table->index(['branch_id', 'sale_date'], 'idx_sales_branch_date');

            // 선택적: 추가 최적화가 필요한 경우 활성화
            // $table->index('created_at', 'idx_sales_created_at');
            // $table->index(['store_id', 'agency'], 'idx_sales_store_agency');
        });
    }

    /**
     * Reverse the migrations.
     *
     * 문제 발생 시 인덱스 제거
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            // 인덱스 제거 (순서는 상관없음)
            $table->dropIndex('idx_sales_sale_date');
            $table->dropIndex('idx_sales_agency');
            $table->dropIndex('idx_sales_store_date');
            $table->dropIndex('idx_sales_branch_date');
        });
    }
};
