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
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->date('refund_date')->index();
            $table->string('dealer_code', 20)->index();
            $table->foreignId('activation_id')->nullable()->constrained('sales')->onDelete('set null'); // 원래 개통 건
            $table->string('customer_name', 50);
            $table->string('customer_phone', 20);
            $table->string('refund_reason', 100); // 단순변심, 불만족 등
            $table->string('refund_type', 20); // 전액환불, 부분환불
            $table->decimal('original_amount', 12, 2); // 원래 금액
            $table->decimal('refund_amount', 12, 2); // 환불 금액
            $table->decimal('penalty_amount', 12, 2)->default(0); // 위약금
            $table->string('refund_method', 20)->nullable(); // 현금, 카드취소 등
            $table->string('processed_by', 50)->nullable(); // 처리자
            $table->text('memo')->nullable();
            $table->timestamps();

            // 인덱스
            $table->index(['refund_date', 'dealer_code']);
            $table->index(['activation_id']);
            $table->index(['customer_phone']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
