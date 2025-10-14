<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // PostgreSQL의 ENUM 타입은 ALTER로 직접 수정이 어려우므로
        // 체크 제약조건을 사용하는 방식으로 변경

        // 기존 체크 제약조건 제거 (있다면)
        DB::statement("
            ALTER TABLE sales
            DROP CONSTRAINT IF EXISTS sales_activation_type_check
        ");

        // 새로운 체크 제약조건 추가 (유선, 2nd 포함)
        DB::statement("
            ALTER TABLE sales
            ADD CONSTRAINT sales_activation_type_check
            CHECK (activation_type IN ('신규', '기변', '번이', '유선', '2nd'))
        ");
    }

    public function down(): void
    {
        // 롤백 시 기존 제약조건으로 복원
        DB::statement("
            ALTER TABLE sales
            DROP CONSTRAINT IF EXISTS sales_activation_type_check
        ");

        DB::statement("
            ALTER TABLE sales
            ADD CONSTRAINT sales_activation_type_check
            CHECK (activation_type IN ('신규', '기변', '번이'))
        ");
    }
};
