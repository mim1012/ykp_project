<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id'); // 활동 수행자
            $table->enum('activity_type', [
                'login', 'logout',
                'sales_input', 'sales_update', 'sales_delete',
                'store_create', 'store_update', 'store_delete',
                'branch_create', 'branch_update', 'branch_delete',
                'account_create', 'account_update', 'account_delete',
                'goal_create', 'goal_update',
                'export_data', 'import_data',
            ]);

            $table->string('activity_title'); // 활동 제목 (예: "개통표 5건 입력")
            $table->text('activity_description')->nullable(); // 상세 설명
            $table->json('activity_data')->nullable(); // 관련 데이터 (JSON)

            // 관련 객체 정보
            $table->string('target_type')->nullable(); // 'store', 'branch', 'sale' 등
            $table->unsignedBigInteger('target_id')->nullable(); // 관련 객체 ID

            // 컨텍스트 정보
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('performed_at'); // 활동 수행 시간

            $table->timestamps();

            // 인덱스 (빠른 조회용)
            $table->index(['performed_at', 'activity_type']); // 최근 활동 조회
            $table->index(['user_id', 'performed_at']); // 사용자별 활동
            $table->index(['target_type', 'target_id']); // 객체별 활동
            $table->index(['activity_type', 'performed_at']); // 타입별 활동
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
