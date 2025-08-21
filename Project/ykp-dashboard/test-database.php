<?php

/**
 * Simple Database Test Script
 * This script tests database connectivity and provides sample data if needed
 */

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Branch;
use App\Models\Sale;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

echo "=== YKP Dashboard Database Test ===\n";

try {
    // Test database connection
    echo "Testing database connection...\n";
    $userCount = User::count();
    echo "✓ Database connected successfully. Users count: {$userCount}\n";

    // Check if we have basic data
    $branchCount = Branch::count();
    $storeCount = Store::count();
    $saleCount = Sale::count();

    echo "Current database status:\n";
    echo "- Branches: {$branchCount}\n";
    echo "- Stores: {$storeCount}\n";
    echo "- Sales: {$saleCount}\n";

    // If no data exists, create sample data
    if ($userCount == 0) {
        echo "\nNo users found. Creating sample data...\n";

        // Create admin user
        $admin = User::create([
            'name' => '테스트 관리자',
            'email' => 'admin@test.com',
            'password' => Hash::make('password'),
            'role' => 'headquarters',
        ]);
        echo "✓ Admin user created: admin@test.com / password\n";

        // Create branch
        $branch = Branch::create([
            'code' => 'TEST01',
            'name' => '테스트지점',
            'manager_name' => '테스트매니저',
            'phone' => '010-1234-5678',
            'address' => '서울시 테스트구',
            'status' => 'active',
        ]);

        // Create store
        $store = Store::create([
            'branch_id' => $branch->id,
            'code' => 'TEST001',
            'name' => '테스트매장',
            'owner_name' => '테스트점주',
            'phone' => '010-1234-5678',
            'address' => '서울시 테스트구 테스트동',
            'status' => 'active',
            'opened_at' => now(),
        ]);

        // Create store user
        $storeUser = User::create([
            'name' => '테스트점주',
            'email' => 'store@test.com',
            'password' => Hash::make('password'),
            'role' => 'store',
            'branch_id' => $branch->id,
            'store_id' => $store->id,
        ]);

        echo "✓ Test data created successfully\n";
        echo "Login credentials: store@test.com / password\n";
    }

    // Create sample sales data for today
    if ($saleCount == 0) {
        echo "\nCreating sample sales data...\n";

        $store = Store::first();
        if ($store) {
            // Create 10 sample sales for today
            for ($i = 1; $i <= 10; $i++) {
                Sale::create([
                    'store_id' => $store->id,
                    'branch_id' => $store->branch_id,
                    'sale_date' => now()->format('Y-m-d'),
                    'carrier' => ['SK', 'KT', 'LG'][rand(0, 2)],
                    'activation_type' => ['신규', '기변', 'MNP'][rand(0, 2)],
                    'model_name' => 'iPhone 15',
                    'base_price' => 100000,
                    'verbal1' => 30000,
                    'verbal2' => 20000,
                    'grade_amount' => 10000,
                    'additional_amount' => 5000,
                    'rebate_total' => 165000,
                    'cash_activation' => 10000,
                    'usim_fee' => 5000,
                    'new_mnp_discount' => 20000,
                    'deduction' => 5000,
                    'settlement_amount' => 175000,
                    'tax' => 23275,
                    'margin_before_tax' => 151725,
                    'cash_received' => 30000,
                    'payback' => 10000,
                    'margin_after_tax' => 191725,
                    'monthly_fee' => 55000,
                    'phone_number' => '010-1234-'.sprintf('%04d', $i),
                    'salesperson' => '영업사원'.$i,
                    'memo' => '테스트 데이터',
                ]);
            }
            echo "✓ Created 10 sample sales records for today\n";
        }
    }

    echo "\n=== Test completed successfully ===\n";
    echo "You can now access the dashboard at: http://127.0.0.1:8000\n";

} catch (Exception $e) {
    echo '❌ Error: '.$e->getMessage()."\n";
    echo "Stack trace:\n".$e->getTraceAsString()."\n";
}
