<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // PostgreSQL 전용 - HTTP 422 에러 해결을 위한 체크 제약조건 완전 재설정
        if (DB::connection()->getDriverName() === 'pgsql') {
            try {
                // 1. 모든 sales 관련 체크 제약조건 삭제
                $constraints = [
                    'sales_activation_type_check',
                    'sales_carrier_check',
                ];

                foreach ($constraints as $constraint) {
                    DB::statement("ALTER TABLE sales DROP CONSTRAINT IF EXISTS {$constraint}");
                    Log::info("Dropped constraint: {$constraint}");
                }

                // 2. 새로운 체크 제약조건 추가 - 빈 값('') 허용
                // activation_type: 신규, 기변, MNP, 번이, 빈 문자열, NULL 허용
                DB::statement("
                    ALTER TABLE sales ADD CONSTRAINT sales_activation_type_check
                    CHECK (
                        activation_type IS NULL OR
                        activation_type = '' OR
                        activation_type IN ('신규', '기변', 'MNP', '번이')
                    )
                ");
                Log::info("Added new activation_type constraint with empty string support");

                // carrier: SK, KT, LG, LG U+, MVNO, 알뜰, 빈 문자열, NULL 허용
                DB::statement("
                    ALTER TABLE sales ADD CONSTRAINT sales_carrier_check
                    CHECK (
                        carrier IS NULL OR
                        carrier = '' OR
                        carrier IN ('SK', 'KT', 'LG', 'LG U+', 'MVNO', '알뜰')
                    )
                ");
                Log::info("Added new carrier constraint with empty string support");

                // 3. 컬럼을 완전히 nullable로 설정
                DB::statement('ALTER TABLE sales ALTER COLUMN activation_type DROP NOT NULL');
                DB::statement('ALTER TABLE sales ALTER COLUMN carrier DROP NOT NULL');
                DB::statement('ALTER TABLE sales ALTER COLUMN model_name DROP NOT NULL');

                Log::info("Successfully updated sales table constraints for HTTP 422 fix");

            } catch (\Exception $e) {
                Log::error("Migration error: " . $e->getMessage());
                throw $e;
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            // 기존 체크 제약조건으로 복원
            DB::statement('ALTER TABLE sales DROP CONSTRAINT IF EXISTS sales_activation_type_check');
            DB::statement('ALTER TABLE sales DROP CONSTRAINT IF EXISTS sales_carrier_check');

            // 이전 버전의 제약조건 복원
            DB::statement("
                ALTER TABLE sales ADD CONSTRAINT sales_activation_type_check
                CHECK (activation_type IN ('신규', '기변', 'MNP', '번이'))
            ");

            DB::statement("
                ALTER TABLE sales ADD CONSTRAINT sales_carrier_check
                CHECK (carrier IN ('SK', 'KT', 'LG', 'LG U+', 'MVNO', '알뜰'))
            ");
        }
    }
};