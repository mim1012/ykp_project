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
        Schema::create('fixed_expenses', function (Blueprint $table) {
            $table->id();
            $table->string('year_month', 7)->index(); // 2024-01
            $table->string('dealer_code', 20)->index();
            $table->string('expense_type', 50); // 임대료, 인건비, 통신비 등
            $table->string('description', 200)->nullable();
            $table->decimal('amount', 12, 2);
            $table->date('due_date')->nullable(); // 지급 예정일
            $table->date('payment_date')->nullable(); // 실제 지급일
            $table->enum('payment_status', ['pending', 'paid', 'overdue'])->default('pending');
            $table->timestamps();
            
            // 복합 인덱스
            $table->index(['year_month', 'dealer_code']);
            $table->index(['expense_type']);
            $table->index(['payment_status']);
            
            // 월별 대리점별 고유 제약
            $table->unique(['year_month', 'dealer_code', 'expense_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fixed_expenses');
    }
};
