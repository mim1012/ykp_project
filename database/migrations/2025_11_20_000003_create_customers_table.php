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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();

            // 관계
            $table->foreignId('store_id')->constrained()->onDelete('cascade');
            $table->foreignId('branch_id')->constrained()->onDelete('cascade');

            // 기본 정보 (필수)
            $table->string('phone_number', 20); // 필수
            $table->string('customer_name', 100); // 필수

            // 추가 정보 (선택)
            $table->date('birth_date')->nullable();
            $table->string('current_device')->nullable(); // 현재 사용 기기

            // 고객 유형
            $table->enum('customer_type', ['prospect', 'activated'])->default('prospect');

            // 개통 연결 (activated 인 경우)
            $table->foreignId('activated_sale_id')->nullable()->constrained('sales')->onDelete('set null');

            // 날짜 정보
            $table->date('first_visit_date')->nullable(); // 방문날짜
            $table->date('last_contact_date')->nullable(); // 마지막 연락 날짜

            // 메모
            $table->text('notes')->nullable();

            // 상태
            $table->enum('status', ['active', 'inactive', 'converted'])->default('active');

            // 생성자
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');

            $table->timestamps();

            // 인덱스
            $table->index(['store_id', 'phone_number']); // 매장별 전화번호 조회
            $table->index(['customer_type', 'status']); // 유형별 상태 조회
            $table->index('activated_sale_id'); // 개통표 역참조
            $table->unique(['store_id', 'phone_number']); // 매장 내 전화번호 중복 방지
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
