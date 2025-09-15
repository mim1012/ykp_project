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
        // Railway 환경에서 테이블이 없을 때만 생성
        if (!Schema::hasTable('activity_logs')) {
            Schema::create('activity_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->enum('activity_type', [
                    'login', 'logout',
                    'sales_input', 'sales_update', 'sales_delete',
                    'store_create', 'store_update', 'store_delete',
                    'branch_create', 'branch_update', 'branch_delete',
                    'account_create', 'account_update', 'account_delete',
                    'goal_create', 'goal_update',
                    'export_data', 'import_data'
                ]);

                $table->string('activity_title');
                $table->text('activity_description')->nullable();
                $table->json('activity_data')->nullable();

                // 관련 객체 정보
                $table->string('target_type')->nullable();
                $table->unsignedBigInteger('target_id')->nullable();

                // 컨텍스트 정보
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamp('performed_at');

                $table->timestamps();

                // 인덱스
                $table->index(['performed_at', 'activity_type']);
                $table->index(['user_id', 'performed_at']);
                $table->index(['target_type', 'target_id']);
                $table->index(['activity_type', 'performed_at']);
            });

            \Log::info('activity_logs table created successfully on Railway');
        } else {
            \Log::info('activity_logs table already exists, skipping creation');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};