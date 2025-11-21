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
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();

            // 관계
            $table->foreignId('store_id')->constrained()->onDelete('cascade');

            // 지출 정보 (모두 필수)
            $table->date('expense_date'); // 날짜
            $table->string('description'); // 내용
            $table->decimal('amount', 12, 2); // 금액

            // 생성자
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');

            $table->timestamps();

            // 인덱스
            $table->index(['store_id', 'expense_date']); // 매장별 날짜 조회
            $table->index('expense_date'); // 날짜 범위 검색
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
