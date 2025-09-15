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
        if (!Schema::hasTable('goals')) {
            Schema::create('goals', function (Blueprint $table) {
                $table->id();
                $table->enum('target_type', ['system', 'branch', 'store']);
                $table->foreignId('target_id')->nullable();
                $table->enum('period_type', ['daily', 'weekly', 'monthly', 'quarterly', 'yearly']);
                $table->date('period_start');
                $table->date('period_end');

                // 목표 지표들
                $table->decimal('sales_target', 15, 2)->default(0);
                $table->integer('activation_target')->default(0);
                $table->decimal('margin_target', 15, 2)->default(0);

                // 목표 설정 정보
                $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
                $table->text('notes')->nullable();
                $table->boolean('is_active')->default(true);

                $table->timestamps();

                // 인덱스
                $table->index(['target_type', 'target_id', 'period_type']);
                $table->index(['period_start', 'period_end']);
                $table->index(['is_active', 'target_type']);
            });

            \Log::info('goals table created successfully on Railway');
        } else {
            \Log::info('goals table already exists, skipping creation');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('goals');
    }
};