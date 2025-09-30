<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('sales', 'destination_region')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->string('destination_region', 100)
                    ->nullable()
                    ->after('customer_name')
                    ->comment('배송 도착지 또는 지역');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('sales', 'destination_region')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->dropColumn('destination_region');
            });
        }
    }
};
