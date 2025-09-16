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
        Schema::table('sales', function (Blueprint $table) {
            // Add missing columns that exist in Model but not in DB
            if (!Schema::hasColumn('sales', 'dealer_code')) {
                $table->string('dealer_code', 50)->nullable()->after('id');
            }
            if (!Schema::hasColumn('sales', 'dealer_name')) {
                $table->string('dealer_name', 100)->nullable()->after('dealer_code');
            }
            if (!Schema::hasColumn('sales', 'agency')) {
                $table->string('agency', 50)->nullable()->after('carrier');
            }
            if (!Schema::hasColumn('sales', 'model_name')) {
                $table->string('model_name', 100)->nullable()->after('activation_type');
            }
            if (!Schema::hasColumn('sales', 'serial_number')) {
                $table->string('serial_number', 100)->nullable()->after('model_name');
            }
            if (!Schema::hasColumn('sales', 'phone_number')) {
                $table->string('phone_number', 20)->nullable()->after('serial_number');
            }
            if (!Schema::hasColumn('sales', 'customer_name')) {
                $table->string('customer_name', 100)->nullable()->after('phone_number');
            }
            if (!Schema::hasColumn('sales', 'customer_birth_date')) {
                $table->date('customer_birth_date')->nullable()->after('customer_name');
            }
            if (!Schema::hasColumn('sales', 'salesperson')) {
                $table->string('salesperson', 100)->nullable()->after('customer_birth_date');
            }
            if (!Schema::hasColumn('sales', 'monthly_fee')) {
                $table->decimal('monthly_fee', 10, 2)->nullable()->default(0)->after('margin_after_tax');
            }
            if (!Schema::hasColumn('sales', 'memo')) {
                $table->text('memo')->nullable()->after('monthly_fee');
            }

            // Rename columns to match Model expectations
            // price_setting -> base_price (already exists as base_price in model)
            // paper_cash -> cash_activation
            // cash_in -> cash_received
            // addon_amount -> additional_amount
            // new_mnp_disc -> new_mnp_discount

            // First check if columns exist before renaming
            if (Schema::hasColumn('sales', 'price_setting')) {
                $table->renameColumn('price_setting', 'base_price');
            }
            if (Schema::hasColumn('sales', 'paper_cash')) {
                $table->renameColumn('paper_cash', 'cash_activation');
            }
            if (Schema::hasColumn('sales', 'cash_in')) {
                $table->renameColumn('cash_in', 'cash_received');
            }
            if (Schema::hasColumn('sales', 'addon_amount')) {
                $table->renameColumn('addon_amount', 'additional_amount');
            }
            if (Schema::hasColumn('sales', 'new_mnp_disc')) {
                $table->renameColumn('new_mnp_disc', 'new_mnp_discount');
            }

            // Add missing columns that don't exist in either
            if (!Schema::hasColumn('sales', 'base_price')) {
                $table->decimal('base_price', 10, 2)->nullable()->default(0)->after('serial_number');
            }
            if (!Schema::hasColumn('sales', 'cash_activation')) {
                $table->decimal('cash_activation', 10, 2)->nullable()->default(0)->after('rebate_total');
            }
            if (!Schema::hasColumn('sales', 'cash_received')) {
                $table->decimal('cash_received', 10, 2)->nullable()->default(0)->after('margin_before_tax');
            }
            if (!Schema::hasColumn('sales', 'additional_amount')) {
                $table->decimal('additional_amount', 10, 2)->nullable()->default(0)->after('grade_amount');
            }
            if (!Schema::hasColumn('sales', 'new_mnp_discount')) {
                $table->decimal('new_mnp_discount', 10, 2)->nullable()->default(0)->after('usim_fee');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            // Remove added columns
            $table->dropColumn([
                'dealer_code',
                'dealer_name',
                'agency',
                'model_name',
                'serial_number',
                'phone_number',
                'customer_name',
                'customer_birth_date',
                'salesperson',
                'monthly_fee',
                'memo'
            ]);

            // Rename back if they were renamed
            if (Schema::hasColumn('sales', 'base_price') && !Schema::hasColumn('sales', 'price_setting')) {
                $table->renameColumn('base_price', 'price_setting');
            }
            if (Schema::hasColumn('sales', 'cash_activation') && !Schema::hasColumn('sales', 'paper_cash')) {
                $table->renameColumn('cash_activation', 'paper_cash');
            }
            if (Schema::hasColumn('sales', 'cash_received') && !Schema::hasColumn('sales', 'cash_in')) {
                $table->renameColumn('cash_received', 'cash_in');
            }
            if (Schema::hasColumn('sales', 'additional_amount') && !Schema::hasColumn('sales', 'addon_amount')) {
                $table->renameColumn('additional_amount', 'addon_amount');
            }
            if (Schema::hasColumn('sales', 'new_mnp_discount') && !Schema::hasColumn('sales', 'new_mnp_disc')) {
                $table->renameColumn('new_mnp_discount', 'new_mnp_disc');
            }
        });
    }
};
