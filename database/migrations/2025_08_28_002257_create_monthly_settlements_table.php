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
        Schema::create('monthly_settlements', function (Blueprint $table) {
            $table->id();
            $table->string('year_month', 7); // YYYY-MM
            $table->string('dealer_code', 20);
            $table->enum('settlement_status', ['draft', 'confirmed', 'closed'])->default('draft');

            // 수익 집계
            $table->decimal('total_sales_amount', 15, 2)->default(0);
            $table->integer('total_sales_count')->default(0);
            $table->decimal('average_margin_rate', 5, 2)->default(0);
            $table->decimal('total_vat_amount', 15, 2)->default(0);

            // 지출 집계
            $table->decimal('total_daily_expenses', 15, 2)->default(0);
            $table->decimal('total_fixed_expenses', 15, 2)->default(0);
            $table->decimal('total_payroll_amount', 15, 2)->default(0);
            $table->decimal('total_refund_amount', 15, 2)->default(0);
            $table->decimal('total_expense_amount', 15, 2)->default(0);

            // 최종 계산
            $table->decimal('gross_profit', 15, 2)->default(0);
            $table->decimal('net_profit', 15, 2)->default(0);
            $table->decimal('profit_rate', 5, 2)->default(0);

            // 전월 대비 분석
            $table->decimal('prev_month_comparison', 15, 2)->default(0);
            $table->decimal('growth_rate', 5, 2)->default(0);

            // 관리 정보
            $table->timestamp('calculated_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users');
            $table->text('notes')->nullable();

            $table->timestamps();

            // 인덱스 및 제약 조건
            $table->unique(['year_month', 'dealer_code']); // 월별 대리점별 유니크
            $table->foreign('dealer_code')->references('dealer_code')->on('dealer_profiles');
            $table->index(['year_month', 'settlement_status']);
            $table->index('calculated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monthly_settlements');
    }
};
