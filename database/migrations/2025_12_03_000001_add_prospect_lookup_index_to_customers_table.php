<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 가망고객 → 개통고객 전환 시 조회 최적화를 위한 복합 인덱스
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // 전환 쿼리 최적화: store_id + phone_number + customer_type
            // autoLinkProspectToSale() 쿼리에 최적화됨
            $table->index(
                ['store_id', 'phone_number', 'customer_type'],
                'customers_prospect_lookup_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('customers_prospect_lookup_index');
        });
    }
};
