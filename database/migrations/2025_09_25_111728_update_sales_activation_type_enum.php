<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // PostgreSQL에서 체크 제약조건만 변경 (데이터는 그대로 유지)
        if (DB::connection()->getDriverName() === 'pgsql') {
            // 기존 체크 제약 조건 삭제
            DB::statement('ALTER TABLE sales DROP CONSTRAINT IF EXISTS sales_activation_type_check');

            // 새로운 체크 제약 조건 추가 (번이 포함)
            DB::statement("ALTER TABLE sales ADD CONSTRAINT sales_activation_type_check CHECK (activation_type IN ('신규', '기변', 'MNP', '번이'))");

            // carrier 체크 제약 조건도 업데이트
            DB::statement('ALTER TABLE sales DROP CONSTRAINT IF EXISTS sales_carrier_check');
            DB::statement("ALTER TABLE sales ADD CONSTRAINT sales_carrier_check CHECK (carrier IN ('SK', 'KT', 'LG', 'LG U+', 'MVNO', '알뜰'))");

            // nullable로 변경 (데이터 손실 없이)
            DB::statement('ALTER TABLE sales ALTER COLUMN activation_type DROP NOT NULL');
            DB::statement('ALTER TABLE sales ALTER COLUMN carrier DROP NOT NULL');
        } else {
            // SQLite/MySQL/MariaDB - 데이터 보존하면서 컬럼 타입 변경

            // ✅ SQLite: 인덱스가 있는 컬럼은 먼저 인덱스를 삭제해야 함
            try {
                Schema::table('sales', function (Blueprint $table) {
                    // activation_type 관련 인덱스 삭제
                    $table->dropIndex('idx_sales_date_activation');
                    $table->dropIndex(['activation_type']);

                    // carrier 관련 인덱스 삭제
                    $table->dropIndex('idx_sales_date_carrier');
                    $table->dropIndex('idx_sales_carrier_analysis');
                    $table->dropIndex(['carrier']);
                });
            } catch (\Exception $e) {
                // 인덱스가 없거나 이미 삭제되었으면 무시
            }

            // 임시 컬럼 생성
            DB::statement("ALTER TABLE sales ADD COLUMN activation_type_temp VARCHAR(10)");
            DB::statement("ALTER TABLE sales ADD COLUMN carrier_temp VARCHAR(10)");

            // 데이터 복사
            DB::statement("UPDATE sales SET activation_type_temp = activation_type");
            DB::statement("UPDATE sales SET carrier_temp = carrier");

            // 기존 컬럼 삭제 및 재생성
            DB::statement("ALTER TABLE sales DROP COLUMN activation_type");
            DB::statement("ALTER TABLE sales DROP COLUMN carrier");

            // ✅ SQLite는 ENUM을 지원하지 않으므로 VARCHAR 사용
            if (DB::connection()->getDriverName() === 'sqlite') {
                DB::statement("ALTER TABLE sales ADD COLUMN activation_type VARCHAR(10) NULL");
                DB::statement("ALTER TABLE sales ADD COLUMN carrier VARCHAR(10) NULL");
            } else {
                DB::statement("ALTER TABLE sales ADD COLUMN activation_type ENUM('신규', '기변', 'MNP', '번이') NULL");
                DB::statement("ALTER TABLE sales ADD COLUMN carrier ENUM('SK', 'KT', 'LG', 'LG U+', 'MVNO', '알뜰') NULL");
            }

            // 데이터 복원
            DB::statement("UPDATE sales SET activation_type = activation_type_temp");
            DB::statement("UPDATE sales SET carrier = carrier_temp");

            // 임시 컬럼 삭제
            DB::statement("ALTER TABLE sales DROP COLUMN activation_type_temp");
            DB::statement("ALTER TABLE sales DROP COLUMN carrier_temp");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // PostgreSQL
        if (DB::connection()->getDriverName() === 'pgsql') {
            // 기존 체크 제약 조건 삭제
            DB::statement('ALTER TABLE sales DROP CONSTRAINT IF EXISTS sales_activation_type_check');
            DB::statement('ALTER TABLE sales DROP CONSTRAINT IF EXISTS sales_carrier_check');

            // 원래 체크 제약 조건으로 복원 (번이를 MNP로 변환 필요)
            DB::statement("UPDATE sales SET activation_type = 'MNP' WHERE activation_type = '번이'");
            DB::statement("UPDATE sales SET carrier = 'LG' WHERE carrier = 'LG U+'");
            DB::statement("UPDATE sales SET carrier = 'MVNO' WHERE carrier = '알뜰'");

            DB::statement("ALTER TABLE sales ADD CONSTRAINT sales_activation_type_check CHECK (activation_type IN ('신규', '기변', 'MNP'))");
            DB::statement("ALTER TABLE sales ADD CONSTRAINT sales_carrier_check CHECK (carrier IN ('SK', 'KT', 'LG', 'MVNO'))");

            // NOT NULL로 복원
            DB::statement('ALTER TABLE sales ALTER COLUMN activation_type SET NOT NULL');
            DB::statement('ALTER TABLE sales ALTER COLUMN carrier SET NOT NULL');
        } else {
            // MySQL/MariaDB
            // 번이를 MNP로, LG U+를 LG로, 알뜰을 MVNO로 변환
            DB::statement("UPDATE sales SET activation_type = 'MNP' WHERE activation_type = '번이'");
            DB::statement("UPDATE sales SET carrier = 'LG' WHERE carrier = 'LG U+'");
            DB::statement("UPDATE sales SET carrier = 'MVNO' WHERE carrier = '알뜰'");

            // 컬럼 타입 변경
            DB::statement("ALTER TABLE sales MODIFY activation_type ENUM('신규', '기변', 'MNP') NOT NULL");
            DB::statement("ALTER TABLE sales MODIFY carrier ENUM('SK', 'KT', 'LG', 'MVNO') NOT NULL");
        }
    }
};