<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['headquarters', 'branch', 'store'])->default('store')->after('password');
            $table->foreignId('branch_id')->nullable()->after('role')->constrained();
            $table->foreignId('store_id')->nullable()->after('branch_id')->constrained();
            $table->index(['role', 'branch_id', 'store_id']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropForeign(['store_id']);
            $table->dropColumn(['role', 'branch_id', 'store_id']);
        });
    }
};
