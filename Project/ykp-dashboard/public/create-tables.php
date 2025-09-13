<?php
/**
 * 🚨 긴급 테이블 생성 스크립트 
 * Railway에서 즉시 실행 가능
 */

// Laravel 환경 로드
require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "<!DOCTYPE html><html><head><title>🚨 긴급 테이블 생성</title></head><body>";
echo "<h1>🚨 누락된 테이블 즉시 생성</h1>";

try {
    echo "<h2>📋 현재 테이블 확인</h2>";
    
    $tables = DB::select("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'");
    $existingTables = array_column($tables, 'table_name');
    
    $requiredTables = [
        'dealer_profiles' => false,
        'monthly_settlements' => false,
        'daily_expenses' => false,
        'payrolls' => false,
        'refunds' => false,
        'fixed_expenses' => false
    ];
    
    foreach ($requiredTables as $table => &$exists) {
        $exists = in_array($table, $existingTables);
        $status = $exists ? "✅" : "❌";
        echo "<p><strong>{$table}</strong>: {$status}</p>";
    }
    
    echo "<h2>🔧 누락 테이블 생성</h2>";
    
    // 1. dealer_profiles 생성
    if (!$requiredTables['dealer_profiles']) {
        echo "<p>🔄 dealer_profiles 테이블 생성...</p>";
        
        DB::statement("
            CREATE TABLE dealer_profiles (
                id BIGSERIAL PRIMARY KEY,
                dealer_code VARCHAR(255) UNIQUE NOT NULL,
                dealer_name VARCHAR(255) NOT NULL,
                contact_person VARCHAR(255),
                phone VARCHAR(255),
                address TEXT,
                default_sim_fee DECIMAL(10, 2) DEFAULT 0,
                default_mnp_discount DECIMAL(10, 2) DEFAULT 800,
                tax_rate DECIMAL(5, 3) DEFAULT 0.10,
                default_payback_rate DECIMAL(5, 2) DEFAULT 0,
                auto_calculate_tax BOOLEAN DEFAULT true,
                include_sim_fee_in_settlement BOOLEAN DEFAULT true,
                custom_calculation_rules JSON,
                status VARCHAR(20) DEFAULT 'active',
                activated_at TIMESTAMP,
                deactivated_at TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // 기본 대리점 추가
        DB::table('dealer_profiles')->insert([
            ['dealer_code' => 'ENT', 'dealer_name' => '이앤티', 'contact_person' => '김대리', 'status' => 'active'],
            ['dealer_code' => 'WIN', 'dealer_name' => '앤투윈', 'contact_person' => '박과장', 'status' => 'active'],
            ['dealer_code' => '초시대', 'dealer_name' => '초시대', 'contact_person' => '관리자', 'status' => 'active'],
            ['dealer_code' => '아엠티', 'dealer_name' => '아엠티', 'contact_person' => '관리자', 'status' => 'active'],
        ]);
        
        echo "<p>✅ dealer_profiles + 4개 대리점 생성 완료</p>";
    }
    
    // 2. monthly_settlements 생성  
    if (!$requiredTables['monthly_settlements']) {
        echo "<p>🔄 monthly_settlements 테이블 생성...</p>";
        
        DB::statement("
            CREATE TABLE monthly_settlements (
                id BIGSERIAL PRIMARY KEY,
                year_month VARCHAR(7) NOT NULL,
                dealer_code VARCHAR(20) NOT NULL,
                settlement_status VARCHAR(20) DEFAULT 'draft',
                total_sales_amount DECIMAL(15, 2) DEFAULT 0,
                total_sales_count INTEGER DEFAULT 0,
                average_margin_rate DECIMAL(5, 2) DEFAULT 0,
                total_vat_amount DECIMAL(15, 2) DEFAULT 0,
                total_daily_expenses DECIMAL(15, 2) DEFAULT 0,
                total_fixed_expenses DECIMAL(15, 2) DEFAULT 0,
                total_payroll_amount DECIMAL(15, 2) DEFAULT 0,
                total_refund_amount DECIMAL(15, 2) DEFAULT 0,
                total_expense_amount DECIMAL(15, 2) DEFAULT 0,
                gross_profit DECIMAL(15, 2) DEFAULT 0,
                net_profit DECIMAL(15, 2) DEFAULT 0,
                profit_rate DECIMAL(5, 2) DEFAULT 0,
                prev_month_comparison DECIMAL(15, 2) DEFAULT 0,
                growth_rate DECIMAL(5, 2) DEFAULT 0,
                calculated_at TIMESTAMP,
                confirmed_at TIMESTAMP,
                confirmed_by BIGINT,
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(year_month, dealer_code)
            )
        ");
        
        echo "<p>✅ monthly_settlements 테이블 생성 완료</p>";
    }
    
    // 3. daily_expenses 생성
    if (!$requiredTables['daily_expenses']) {
        echo "<p>🔄 daily_expenses 테이블 생성...</p>";
        
        DB::statement("
            CREATE TABLE daily_expenses (
                id BIGSERIAL PRIMARY KEY,
                store_id BIGINT,
                branch_id BIGINT,
                expense_date DATE NOT NULL,
                category VARCHAR(100) NOT NULL,
                amount DECIMAL(10, 2) NOT NULL,
                description TEXT,
                receipt_number VARCHAR(100),
                payment_method VARCHAR(50) DEFAULT 'cash',
                status VARCHAR(20) DEFAULT 'pending',
                created_by BIGINT,
                approved_by BIGINT,
                approved_at TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        echo "<p>✅ daily_expenses 테이블 생성 완료</p>";
    }
    
    // 4. payrolls, refunds, fixed_expenses 생성 (간략화)
    $remainingTables = ['payrolls', 'refunds', 'fixed_expenses'];
    foreach ($remainingTables as $table) {
        if (!$requiredTables[$table]) {
            echo "<p>🔄 {$table} 테이블 생성...</p>";
            
            switch($table) {
                case 'payrolls':
                    DB::statement("
                        CREATE TABLE payrolls (
                            id BIGSERIAL PRIMARY KEY,
                            employee_name VARCHAR(255) NOT NULL,
                            store_id BIGINT, branch_id BIGINT,
                            year_month VARCHAR(7) NOT NULL,
                            total_gross_pay DECIMAL(10, 2) NOT NULL,
                            net_pay DECIMAL(10, 2) NOT NULL,
                            payment_status VARCHAR(20) DEFAULT 'pending',
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                        )
                    ");
                    break;
                    
                case 'refunds':
                    DB::statement("
                        CREATE TABLE refunds (
                            id BIGSERIAL PRIMARY KEY,
                            store_id BIGINT, branch_id BIGINT,
                            refund_date DATE NOT NULL,
                            customer_name VARCHAR(255),
                            refund_amount DECIMAL(10, 2) NOT NULL,
                            refund_reason VARCHAR(255) NOT NULL,
                            status VARCHAR(20) DEFAULT 'pending',
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                        )
                    ");
                    break;
                    
                case 'fixed_expenses':
                    DB::statement("
                        CREATE TABLE fixed_expenses (
                            id BIGSERIAL PRIMARY KEY,
                            store_id BIGINT, branch_id BIGINT,
                            year_month VARCHAR(7) NOT NULL,
                            category VARCHAR(100) NOT NULL,
                            amount DECIMAL(10, 2) NOT NULL,
                            payment_status VARCHAR(20) DEFAULT 'pending',
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                        )
                    ");
                    break;
            }
            
            echo "<p>✅ {$table} 테이블 생성 완료</p>";
        }
    }
    
    echo "<h2>🎉 데이터베이스 수정 완료!</h2>";
    echo "<p><strong style='color:green; font-size:18px;'>모든 API가 이제 정상 작동할 것입니다!</strong></p>";
    echo "<p><a href='/management/stores' style='background:blue;color:white;padding:10px;text-decoration:none;border-radius:5px;'>매장 관리 테스트</a>";
    echo " | <a href='/monthly-settlement' style='background:green;color:white;padding:10px;text-decoration:none;border-radius:5px;'>월마감정산 테스트</a></p>";
    
} catch (Exception $e) {
    echo "<h2 style='color:red;'>❌ 오류 발생</h2>";
    echo "<p>오류: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "</body></html>";
?>