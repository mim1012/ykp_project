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
        Schema::table('goals', function (Blueprint $table) {
            $table->foreignId('store_id')->nullable()->after('id')->constrained()->onDelete('cascade');
            $table->date('target_month')->nullable()->after('store_id');
            $table->index(['store_id', 'target_month'], 'goals_store_month_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('goals', function (Blueprint $table) {
            $table->dropIndex('goals_store_month_index');
            $table->dropForeign(['store_id']);
            $table->dropColumn(['store_id', 'target_month']);
        });
    }
};
