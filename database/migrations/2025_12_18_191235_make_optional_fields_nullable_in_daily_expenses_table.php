<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 간소화된 지출 입력 폼을 위해 선택 필드들을 nullable로 변경
     */
    public function up(): void
    {
        Schema::table('daily_expenses', function (Blueprint $table) {
            // 지출 입력 폼 간소화에 따라 선택 필드들을 nullable로 변경
            $table->string('category', 50)->nullable()->change();
            $table->string('payment_method', 20)->nullable()->change();
            $table->string('receipt_number', 50)->nullable()->change();
            $table->string('approved_by', 50)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_expenses', function (Blueprint $table) {
            $table->string('category', 50)->nullable(false)->change();
            $table->string('payment_method', 20)->nullable(false)->change();
            $table->string('receipt_number', 50)->nullable(false)->change();
            $table->string('approved_by', 50)->nullable(false)->change();
        });
    }
};
