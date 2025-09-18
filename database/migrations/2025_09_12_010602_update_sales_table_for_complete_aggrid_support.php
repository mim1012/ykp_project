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
            // PM 요구사항: 누락된 개통표 필드들 추가
            $table->string('dealer_name')->nullable()->after('carrier')->comment('대리점명 (이앤티, 앤투윈 등)');
            $table->string('serial_number')->nullable()->after('model_name')->comment('기기 일련번호');
            $table->string('customer_name')->nullable()->after('phone_number')->comment('고객명');
            $table->date('customer_birth_date')->nullable()->after('customer_name')->comment('고객 생년월일');

            // 기존 필드명 정리 및 인덱스 추가
            $table->index(['store_id', 'sale_date'], 'idx_store_date');
            $table->index(['branch_id', 'sale_date'], 'idx_branch_date');
            $table->index('salesperson', 'idx_salesperson');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            // 추가된 필드들 제거
            $table->dropColumn(['dealer_name', 'serial_number', 'customer_name', 'customer_birth_date']);

            // 인덱스 제거
            $table->dropIndex(['idx_store_date', 'idx_branch_date', 'idx_salesperson']);
        });
    }
};
