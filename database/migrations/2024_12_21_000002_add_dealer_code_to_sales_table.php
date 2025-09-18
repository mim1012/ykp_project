<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('dealer_code')->nullable()->after('id');
            $table->index('dealer_code');
        });

        // 기존 데이터에 기본 dealer_code 할당
        DB::table('sales')->whereNull('dealer_code')->update([
            'dealer_code' => 'DEFAULT',
        ]);
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['dealer_code']);
            $table->dropColumn('dealer_code');
        });
    }
};
