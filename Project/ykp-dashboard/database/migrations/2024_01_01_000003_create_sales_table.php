<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->foreignId('branch_id')->constrained();
            $table->date('sale_date');
            $table->enum('carrier', ['SK', 'KT', 'LG', 'MVNO']);
            $table->enum('activation_type', ['신규', '기변', 'MNP']);
            $table->string('model_name')->nullable();

            // 금액 필드들
            $table->decimal('base_price', 12, 2)->default(0);
            $table->decimal('verbal1', 12, 2)->default(0);
            $table->decimal('verbal2', 12, 2)->default(0);
            $table->decimal('grade_amount', 12, 2)->default(0);
            $table->decimal('additional_amount', 12, 2)->default(0);
            $table->decimal('rebate_total', 12, 2)->default(0);
            $table->decimal('cash_activation', 12, 2)->default(0);
            $table->decimal('usim_fee', 12, 2)->default(0);
            $table->decimal('new_mnp_discount', 12, 2)->default(0);
            $table->decimal('deduction', 12, 2)->default(0);
            $table->decimal('settlement_amount', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('margin_before_tax', 12, 2)->default(0);
            $table->decimal('cash_received', 12, 2)->default(0);
            $table->decimal('payback', 12, 2)->default(0);
            $table->decimal('margin_after_tax', 12, 2)->default(0);
            $table->decimal('monthly_fee', 12, 2)->default(0);

            $table->string('phone_number')->nullable();
            $table->string('salesperson')->nullable();
            $table->text('memo')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'sale_date']);
            $table->index(['branch_id', 'sale_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
