<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goals', function (Blueprint $table) {
            $table->id();
            $table->enum('target_type', ['system', 'branch', 'store']); // 목표 대상
            $table->foreignId('target_id')->nullable(); // 지사 ID 또는 매장 ID (system은 null)
            $table->enum('period_type', ['daily', 'weekly', 'monthly', 'quarterly', 'yearly']);
            $table->date('period_start'); // 목표 기간 시작
            $table->date('period_end'); // 목표 기간 종료

            // 목표 지표들
            $table->decimal('sales_target', 15, 2)->default(0); // 매출 목표
            $table->integer('activation_target')->default(0); // 개통 건수 목표
            $table->decimal('margin_target', 15, 2)->default(0); // 마진 목표

            // 목표 설정 정보
            $table->foreignId('created_by'); // 목표 설정자
            $table->text('notes')->nullable(); // 목표 설정 사유/메모
            $table->boolean('is_active')->default(true); // 활성 목표인지

            $table->timestamps();

            // 인덱스
            $table->index(['target_type', 'target_id', 'period_type']);
            $table->index(['period_start', 'period_end']);
            $table->index(['is_active', 'target_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goals');
    }
};