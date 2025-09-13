<?php
/**
 * 🚨 Supabase 데이터베이스 수정 스크립트
 * 누락된 테이블들을 직접 생성하여 500 오류 해결
 * 
 * 사용법: https://ykpproject-production.up.railway.app/fix-database.php
 */

// Laravel 환경 로드
require_once __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "<!DOCTYPE html><html><head><title>🔧 DB 수정 스크립트</title></head><body>";
echo "<h1>🚨 Supabase 데이터베이스 수정</h1>";

try {
    $pdo = DB::connection()->getPdo();
    echo "<p>✅ 데이터베이스 연결 성공</p>";
    
    // 현재 테이블 확인
    echo "<h2>📋 현재 테이블 상태</h2>";
    $tables = DB::select("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' ORDER BY table_name");
    
    $existingTables = array_column($tables, 'table_name');
    $requiredTables = ['dealer_profiles', 'monthly_settlements', 'daily_expenses', 'payrolls', 'refunds', 'fixed_expenses'];
    
    echo "<ul>";
    foreach ($requiredTables as $table) {
        $exists = in_array($table, $existingTables);
        $status = $exists ? "✅ 존재" : "❌ 누락";
        echo "<li><strong>{$table}</strong>: {$status}</li>";
    }
    echo "</ul>";
    
    // 누락된 테이블 생성
    echo "<h2>🔧 누락된 테이블 생성</h2>";
    
    // 1. dealer_profiles 테이블
    if (!in_array('dealer_profiles', $existingTables)) {
        echo "<p>🔄 dealer_profiles 테이블 생성 중...</p>";
        
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
        
        echo "<p>✅ dealer_profiles 테이블 생성 완료</p>";
        
        // 기본 대리점 데이터 삽입
        DB::table('dealer_profiles')->insert([
            ['dealer_code' => 'ENT', 'dealer_name' => '이앤티', 'contact_person' => '김대리', 'status' => 'active'],
            ['dealer_code' => 'WIN', 'dealer_name' => '앤투윈', 'contact_person' => '박과장', 'status' => 'active'],
            ['dealer_code' => '초시대', 'dealer_name' => '초시대', 'contact_person' => '관리자', 'status' => 'active'],
            ['dealer_code' => '아엠티', 'dealer_name' => '아엠티', 'contact_person' => '관리자', 'status' => 'active'],
        ]);
        
        echo "<p>✅ 기본 대리점 4개 추가 완료</p>";
    }
    
    // 2. daily_expenses 테이블
    if (!in_array('daily_expenses', $existingTables)) {
        echo "<p>🔄 daily_expenses 테이블 생성 중...</p>";
        
        DB::statement("
            CREATE TABLE daily_expenses (
                id BIGSERIAL PRIMARY KEY,
                store_id BIGINT REFERENCES stores(id) ON DELETE CASCADE,
                branch_id BIGINT REFERENCES branches(id) ON DELETE CASCADE,
                expense_date DATE NOT NULL,
                category VARCHAR(100) NOT NULL,
                amount DECIMAL(10, 2) NOT NULL,
                description TEXT,
                receipt_number VARCHAR(100),
                payment_method VARCHAR(50) DEFAULT 'cash',
                status VARCHAR(20) DEFAULT 'pending',
                
                created_by BIGINT REFERENCES users(id),
                approved_by BIGINT REFERENCES users(id),
                approved_at TIMESTAMP,
                
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        echo "<p>✅ daily_expenses 테이블 생성 완료</p>";
    }
    
    // 3. payrolls 테이블
    if (!in_array('payrolls', $existingTables)) {
        echo "<p>🔄 payrolls 테이블 생성 중...</p>";
        
        DB::statement("
            CREATE TABLE payrolls (
                id BIGSERIAL PRIMARY KEY,
                employee_name VARCHAR(255) NOT NULL,
                store_id BIGINT REFERENCES stores(id) ON DELETE CASCADE,
                branch_id BIGINT REFERENCES branches(id) ON DELETE CASCADE,
                year_month VARCHAR(7) NOT NULL,
                
                base_salary DECIMAL(10, 2) DEFAULT 0,
                performance_bonus DECIMAL(10, 2) DEFAULT 0,
                overtime_pay DECIMAL(10, 2) DEFAULT 0,
                allowances DECIMAL(10, 2) DEFAULT 0,
                total_gross_pay DECIMAL(10, 2) NOT NULL,
                
                income_tax DECIMAL(10, 2) DEFAULT 0,
                insurance DECIMAL(10, 2) DEFAULT 0,
                other_deductions DECIMAL(10, 2) DEFAULT 0,
                total_deductions DECIMAL(10, 2) DEFAULT 0,
                
                net_pay DECIMAL(10, 2) NOT NULL,
                
                payment_status VARCHAR(20) DEFAULT 'pending',
                payment_date DATE,
                payment_method VARCHAR(50) DEFAULT 'bank_transfer',
                
                notes TEXT,
                
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        echo "<p>✅ payrolls 테이블 생성 완료</p>";
    }
    
    // 4. refunds 테이블
    if (!in_array('refunds', $existingTables)) {
        echo "<p>🔄 refunds 테이블 생성 중...</p>";
        
        DB::statement("
            CREATE TABLE refunds (
                id BIGSERIAL PRIMARY KEY,
                store_id BIGINT REFERENCES stores(id) ON DELETE CASCADE,
                branch_id BIGINT REFERENCES branches(id) ON DELETE CASCADE,
                
                refund_date DATE NOT NULL,
                customer_name VARCHAR(255),
                phone_number VARCHAR(20),
                model_name VARCHAR(255),
                carrier VARCHAR(20) NOT NULL,
                
                original_settlement_amount DECIMAL(10, 2) NOT NULL,
                refund_amount DECIMAL(10, 2) NOT NULL,
                penalty_amount DECIMAL(10, 2) DEFAULT 0,
                total_refund_amount DECIMAL(10, 2) NOT NULL,
                
                refund_reason VARCHAR(255) NOT NULL,
                detailed_reason TEXT,
                
                status VARCHAR(20) DEFAULT 'pending',
                processed_date DATE,
                processed_by BIGINT REFERENCES users(id),
                
                notes TEXT,
                
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        echo "<p>✅ refunds 테이블 생성 완료</p>";
    }
    
    // 5. fixed_expenses 테이블
    if (!in_array('fixed_expenses', $existingTables)) {
        echo "<p>🔄 fixed_expenses 테이블 생성 중...</p>";
        
        DB::statement("
            CREATE TABLE fixed_expenses (
                id BIGSERIAL PRIMARY KEY,
                store_id BIGINT REFERENCES stores(id) ON DELETE CASCADE,
                branch_id BIGINT REFERENCES branches(id) ON DELETE CASCADE,
                
                year_month VARCHAR(7) NOT NULL,
                category VARCHAR(100) NOT NULL,
                amount DECIMAL(10, 2) NOT NULL,
                description TEXT,
                
                due_date DATE,
                payment_status VARCHAR(20) DEFAULT 'pending',
                payment_date DATE,
                payment_method VARCHAR(50),
                
                notes TEXT,
                
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        echo "<p>✅ fixed_expenses 테이블 생성 완료</p>";
    }
    
    // 6. monthly_settlements 테이블  
    if (!in_array('monthly_settlements', $existingTables)) {
        echo "<p>🔄 monthly_settlements 테이블 생성 중...</p>";
        
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
                confirmed_by BIGINT REFERENCES users(id),
                notes TEXT,
                
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                
                UNIQUE(year_month, dealer_code)
            )
        ");
        
        echo "<p>✅ monthly_settlements 테이블 생성 완료</p>";
    }
    
    echo "<h2>🎉 데이터베이스 수정 완료!</h2>";
    echo "<p><strong>이제 500 오류가 해결되어 모든 API가 정상 작동할 것입니다.</strong></p>";
    echo "<p><a href='/management/stores'>매장 관리로 이동</a> | <a href='/monthly-settlement'>월마감정산으로 이동</a></p>";
    
} catch (Exception $e) {
    echo "<h2>❌ 오류 발생</h2>";
    echo "<p>오류: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "</body></html>";
?>