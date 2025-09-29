<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // SQLite에서는 직접 ALTER COLUMN이 제한적이므로
        // 새로운 테이블을 만들고 데이터를 복사하는 방식 사용
        if (DB::connection()->getDriverName() === 'sqlite') {
            // 1. 임시 테이블 생성 (carrier를 nullable로)
            DB::statement('CREATE TABLE sales_temp AS SELECT * FROM sales');

            // 2. 기존 sales 테이블 삭제
            Schema::drop('sales');

            // 3. sales 테이블 재생성 (carrier를 nullable로)
            Schema::create('sales', function (Blueprint $table) {
                $table->id();
                $table->foreignId('store_id')->constrained()->cascadeOnDelete();
                $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
                $table->date('sale_date');
                $table->string('agency', 20)->nullable();
                $table->string('carrier', 10)->nullable(); // nullable로 변경
                $table->string('activation_type', 10)->nullable(); // nullable로 변경
                $table->string('model_name', 50)->nullable(); // nullable로 변경
                $table->decimal('price_setting', 10, 2)->default(0);
                $table->decimal('verbal1', 10, 2)->default(0);
                $table->decimal('verbal2', 10, 2)->default(0);
                $table->decimal('grade_amount', 10, 2)->default(0);
                $table->decimal('addon_amount', 10, 2)->default(0);
                $table->decimal('paper_cash', 10, 2)->default(0);
                $table->decimal('usim_fee', 10, 2)->default(0);
                $table->decimal('new_mnp_disc', 10, 2)->default(0);
                $table->decimal('deduction', 10, 2)->default(0);
                $table->decimal('cash_in', 10, 2)->default(0);
                $table->decimal('payback', 10, 2)->default(0);
                $table->decimal('monthly_fee', 10, 2)->default(0);
                $table->string('phone_number', 20)->nullable();
                $table->string('salesperson', 50)->nullable();
                $table->string('memo')->nullable();
                $table->decimal('rebate_total', 10, 2)->default(0);
                $table->decimal('settlement_amount', 10, 2)->default(0);
                $table->decimal('tax', 10, 2)->default(0);
                $table->decimal('margin_before_tax', 10, 2)->default(0);
                $table->decimal('margin_after_tax', 10, 2)->default(0);
                $table->string('dealer_code', 50)->nullable();
                $table->string('dealer_name', 100)->nullable();
                $table->string('serial_number', 100)->nullable();
                $table->string('customer_name', 100)->nullable();
                $table->date('customer_birth_date')->nullable();
                $table->timestamps();

                // 인덱스 추가
                $table->index(['sale_date', 'store_id']);
                $table->index(['dealer_code']);
                $table->index(['agency']);
            });

            // 4. 데이터 복사
            DB::statement('INSERT INTO sales SELECT * FROM sales_temp');

            // 5. 임시 테이블 삭제
            DB::statement('DROP TABLE sales_temp');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 원상복구는 필요시 구현
    }
};