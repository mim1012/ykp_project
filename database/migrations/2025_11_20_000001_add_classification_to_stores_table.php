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
        Schema::table('stores', function (Blueprint $table) {
            // 가맹점/직영점 분류
            $table->enum('store_type', ['franchise', 'direct'])->default('franchise')->after('status');

            // 사업자등록번호
            $table->string('business_registration_number', 20)->nullable()->after('store_type');

            // 이메일
            $table->string('email')->nullable()->after('business_registration_number');

            // 인덱스 추가
            $table->index('store_type');
            $table->index('business_registration_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropIndex(['store_type']);
            $table->dropIndex(['business_registration_number']);
            $table->dropColumn(['store_type', 'business_registration_number', 'email']);
        });
    }
};
