<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Supabase 연동을 위한 매장 테이블 확장
     */
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by')->nullable()->after('opened_at');
            $table->timestamp('last_sync_at')->nullable()->after('created_by');
            $table->json('sync_status')->nullable()->after('last_sync_at');
            $table->string('supabase_id')->nullable()->unique()->after('sync_status');
            $table->json('metadata')->nullable()->after('supabase_id');

            $table->index(['created_by']);
            $table->index(['last_sync_at']);
            $table->index(['supabase_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn([
                'created_by',
                'last_sync_at',
                'sync_status',
                'supabase_id',
                'metadata',
            ]);
        });
    }
};
