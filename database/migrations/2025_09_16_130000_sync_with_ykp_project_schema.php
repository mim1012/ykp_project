<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 프로젝트 스키마 동기화
     */
    public function up(): void
    {
        // 1. Sales 테이블 구조를 ykp-project와 완전히 동일하게 수정
        if (Schema::hasTable('sales')) {
            Schema::table('sales', function (Blueprint $table) {
                // 기존 컬럼 타입을 ykp-project 스펙으로 변경
                $table->string('carrier')->change();
                $table->string('activation_type')->change();
                $table->decimal('base_price', 12, 2)->default(0)->change();
                $table->decimal('verbal1', 12, 2)->default(0)->change();
                $table->decimal('verbal2', 12, 2)->default(0)->change();
                $table->decimal('grade_amount', 12, 2)->default(0)->change();
                $table->decimal('additional_amount', 12, 2)->default(0)->change();
                $table->decimal('rebate_total', 12, 2)->default(0)->change();
                $table->decimal('cash_activation', 12, 2)->default(0)->change();
                $table->decimal('usim_fee', 12, 2)->default(0)->change();
                $table->decimal('new_mnp_discount', 12, 2)->default(0)->change();
                $table->decimal('deduction', 12, 2)->default(0)->change();
                $table->decimal('settlement_amount', 12, 2)->default(0)->change();
                $table->decimal('tax', 12, 2)->default(0)->change();
                $table->decimal('margin_before_tax', 12, 2)->default(0)->change();
                $table->decimal('cash_received', 12, 2)->default(0)->change();
                $table->decimal('payback', 12, 2)->default(0)->change();
                $table->decimal('margin_after_tax', 12, 2)->default(0)->change();
                $table->decimal('monthly_fee', 12, 2)->default(0)->change();
            });
        }

        // 2. ykp-project에 있는 추가 테이블들 생성
        if (! Schema::hasTable('daily_expenses')) {
            Schema::create('daily_expenses', function (Blueprint $table) {
                $table->id();
                $table->foreignId('store_id')->constrained();
                $table->date('expense_date');
                $table->string('category');
                $table->string('description');
                $table->decimal('amount', 10, 2);
                $table->string('payment_method')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['store_id', 'expense_date']);
            });
        }

        if (! Schema::hasTable('fixed_expenses')) {
            Schema::create('fixed_expenses', function (Blueprint $table) {
                $table->id();
                $table->foreignId('store_id')->nullable()->constrained();
                $table->foreignId('branch_id')->nullable()->constrained();
                $table->string('expense_type');
                $table->string('description');
                $table->decimal('amount', 10, 2);
                $table->date('due_date');
                $table->boolean('is_paid')->default(false);
                $table->date('paid_date')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['due_date', 'is_paid']);
            });
        }

        if (! Schema::hasTable('refunds')) {
            Schema::create('refunds', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sale_id')->constrained();
                $table->string('refund_type');
                $table->decimal('refund_amount', 10, 2);
                $table->date('refund_date');
                $table->string('reason');
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['refund_date']);
            });
        }

        if (! Schema::hasTable('payrolls')) {
            Schema::create('payrolls', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained();
                $table->string('employee_name');
                $table->decimal('base_salary', 10, 2);
                $table->decimal('incentive', 10, 2)->default(0);
                $table->decimal('total_salary', 10, 2);
                $table->date('payment_date');
                $table->boolean('is_paid')->default(false);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['payment_date', 'is_paid']);
            });
        }

        if (! Schema::hasTable('monthly_settlements')) {
            Schema::create('monthly_settlements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('store_id')->constrained();
                $table->string('year_month');
                $table->decimal('total_sales', 15, 2);
                $table->decimal('total_margin', 15, 2);
                $table->decimal('total_expenses', 15, 2);
                $table->decimal('net_profit', 15, 2);
                $table->boolean('is_confirmed')->default(false);
                $table->date('confirmed_date')->nullable();
                $table->timestamps();

                $table->unique(['store_id', 'year_month']);
                $table->index(['year_month', 'is_confirmed']);
            });
        }

        if (! Schema::hasTable('store_requests')) {
            Schema::create('store_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('branch_id')->constrained();
                $table->string('store_name');
                $table->string('owner_name');
                $table->string('phone');
                $table->text('address');
                $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['status', 'created_at']);
            });
        }

        \Log::info('ykp-project schema synchronized to ykp-staging');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_requests');
        Schema::dropIfExists('monthly_settlements');
        Schema::dropIfExists('payrolls');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('fixed_expenses');
        Schema::dropIfExists('daily_expenses');
    }
};
