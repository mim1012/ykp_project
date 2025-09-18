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
        Schema::create('daily_expenses', function (Blueprint $table) {
            $table->id();
            $table->date('expense_date')->index();
            $table->string('dealer_code', 20)->index();
            $table->string('category', 50); // 상담비, 메일접수비, 기타
            $table->string('description', 200)->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('payment_method', 20)->nullable(); // 현금, 카드, 계좌이체
            $table->string('receipt_number', 50)->nullable();
            $table->string('approved_by', 50)->nullable();
            $table->timestamps();

            // 복합 인덱스
            $table->index(['expense_date', 'dealer_code']);
            $table->index(['category']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_expenses');
    }
};
