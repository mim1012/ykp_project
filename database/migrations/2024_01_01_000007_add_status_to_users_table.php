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
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('store_id');
            $table->timestamp('last_login_at')->nullable()->after('is_active');
            $table->string('created_by_user_id')->nullable()->after('last_login_at');

            // 성능 최적화 인덱스
            $table->index('is_active');
            $table->index(['role', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
            $table->dropIndex(['role', 'is_active']);

            $table->dropColumn(['is_active', 'last_login_at', 'created_by_user_id']);
        });
    }
};
