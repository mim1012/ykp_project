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
            // Railway DB에 누락된 기본 필드들 추가 (SQLSTATE[42703] 에러 해결)
            if (! Schema::hasColumn('sales', 'salesperson')) {
                $table->string('salesperson')->nullable();
            }
            if (! Schema::hasColumn('sales', 'model_name')) {
                $table->string('model_name')->nullable();
            }
            if (! Schema::hasColumn('sales', 'phone_number')) {
                $table->string('phone_number')->nullable();
            }
            if (! Schema::hasColumn('sales', 'memo')) {
                $table->text('memo')->nullable();
            }

            // 검색 성능 향상을 위한 인덱스 추가
            $table->index('salesperson', 'idx_sales_salesperson');
            $table->index('model_name', 'idx_sales_model_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex('idx_sales_salesperson');
            $table->dropIndex('idx_sales_model_name');

            $table->dropColumn([
                'salesperson',
                'model_name',
                'phone_number',
                'memo',
            ]);
        });
    }
};
