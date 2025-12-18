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
        Schema::table('daily_expenses', function (Blueprint $table) {
            // dealer_code를 nullable로 변경 (매장 기반 시스템에서는 store_id 사용)
            $table->string('dealer_code', 20)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_expenses', function (Blueprint $table) {
            $table->string('dealer_code', 20)->nullable(false)->change();
        });
    }
};
