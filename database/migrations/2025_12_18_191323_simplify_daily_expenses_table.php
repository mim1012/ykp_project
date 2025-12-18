<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 지출 입력 폼 간소화: 지출일, 지출내용, 금액만 유지
     * 삭제: dealer_code, category, payment_method, receipt_number, approved_by
     */
    public function up(): void
    {
        Schema::table('daily_expenses', function (Blueprint $table) {
            // 인덱스 먼저 삭제 (PostgreSQL에서는 컬럼 삭제 전에 인덱스 삭제 필요)
            $table->dropIndex(['expense_date', 'dealer_code']);
            $table->dropIndex(['category']);
            $table->dropIndex(['dealer_code']);
        });

        Schema::table('daily_expenses', function (Blueprint $table) {
            // 불필요한 컬럼 삭제
            $table->dropColumn([
                'dealer_code',
                'category',
                'payment_method',
                'receipt_number',
                'approved_by',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_expenses', function (Blueprint $table) {
            // 삭제된 컬럼 복원
            $table->string('dealer_code', 20)->nullable()->after('expense_date');
            $table->string('category', 50)->nullable()->after('dealer_code');
            $table->string('payment_method', 20)->nullable()->after('amount');
            $table->string('receipt_number', 50)->nullable()->after('payment_method');
            $table->string('approved_by', 50)->nullable()->after('receipt_number');
        });

        Schema::table('daily_expenses', function (Blueprint $table) {
            // 인덱스 복원
            $table->index(['dealer_code']);
            $table->index(['expense_date', 'dealer_code']);
            $table->index(['category']);
        });
    }
};
