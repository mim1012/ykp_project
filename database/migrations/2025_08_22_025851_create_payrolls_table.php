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
        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();
            $table->string('year_month', 7)->index(); // 2024-01
            $table->string('dealer_code', 20)->index();
            $table->string('employee_id', 20)->index();
            $table->string('employee_name', 50);
            $table->string('position', 30)->nullable(); // 점장, 직원 등
            $table->decimal('base_salary', 12, 2)->default(0); // 기본급
            $table->decimal('incentive_amount', 12, 2)->default(0); // 인센티브
            $table->decimal('bonus_amount', 12, 2)->default(0); // 보너스
            $table->decimal('deduction_amount', 12, 2)->default(0); // 공제액
            $table->decimal('total_salary', 12, 2); // 총 급여
            $table->date('payment_date')->nullable(); // 지급일
            $table->enum('payment_status', ['pending', 'paid'])->default('pending');
            $table->text('memo')->nullable();
            $table->timestamps();

            // 복합 인덱스
            $table->index(['year_month', 'dealer_code'], 'payrolls_year_month_dealer_index');
            $table->index(['payment_status'], 'payrolls_payment_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payrolls');
    }
};
