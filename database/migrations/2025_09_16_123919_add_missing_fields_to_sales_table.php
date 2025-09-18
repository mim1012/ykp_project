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
            // 누락된 필드들 추가 (IF NOT EXISTS 방식으로 안전하게)
            if (! Schema::hasColumn('sales', 'dealer_code')) {
                $table->string('dealer_code')->nullable()->after('memo');
            }
            if (! Schema::hasColumn('sales', 'dealer_name')) {
                $table->string('dealer_name')->nullable()->after('dealer_code');
            }
            if (! Schema::hasColumn('sales', 'serial_number')) {
                $table->string('serial_number')->nullable()->after('dealer_name');
            }
            if (! Schema::hasColumn('sales', 'customer_name')) {
                $table->string('customer_name')->nullable()->after('serial_number');
            }
            if (! Schema::hasColumn('sales', 'customer_birth_date')) {
                $table->date('customer_birth_date')->nullable()->after('customer_name');
            }

            // 성능 최적화 인덱스 추가
            $table->index('dealer_code', 'idx_sales_dealer_code');
            $table->index('dealer_name', 'idx_sales_dealer_name');
            $table->index('customer_name', 'idx_sales_customer_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex('idx_sales_dealer_code');
            $table->dropIndex('idx_sales_dealer_name');
            $table->dropIndex('idx_sales_customer_name');

            $table->dropColumn([
                'dealer_code',
                'dealer_name',
                'serial_number',
                'customer_name',
                'customer_birth_date',
            ]);
        });
    }
};
