<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dealer_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('dealer_code')->unique();
            $table->string('dealer_name');
            $table->string('contact_person')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            
            // 기본 설정값들 (ykp-settlement 기반)
            $table->decimal('default_sim_fee', 10, 2)->default(0);
            $table->decimal('default_mnp_discount', 10, 2)->default(800);
            $table->decimal('tax_rate', 5, 3)->default(0.10);
            $table->decimal('default_payback_rate', 5, 2)->default(0);
            
            // 계산 옵션들
            $table->boolean('auto_calculate_tax')->default(true);
            $table->boolean('include_sim_fee_in_settlement')->default(true);
            $table->json('custom_calculation_rules')->nullable();
            
            // 상태 관리
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('deactivated_at')->nullable();
            
            $table->timestamps();
            
            // 인덱스
            $table->index(['dealer_code', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dealer_profiles');
    }
};