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
            // 구두1/구두2 메모 필드 추가
            $table->text('verbal1_memo')->nullable()->after('verbal1');
            $table->text('verbal2_memo')->nullable()->after('verbal2');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['verbal1_memo', 'verbal2_memo']);
        });
    }
};
