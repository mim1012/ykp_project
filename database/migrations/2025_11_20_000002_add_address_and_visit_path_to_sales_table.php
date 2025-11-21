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
            // 고객 주소 (선택)
            $table->text('customer_address')->nullable()->after('customer_birth_date');

            // 방문 경로 (선택): '온라인', '지인소개', '매장방문', '기타'
            $table->string('visit_path', 50)->nullable()->after('customer_address');

            // 인덱스 추가 (통계용)
            $table->index('visit_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['visit_path']);
            $table->dropColumn(['customer_address', 'visit_path']);
        });
    }
};
