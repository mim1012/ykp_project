<?php

/**
 * Supabase 마이그레이션 실행
 */
echo '<h1>🗄️ Supabase 마이그레이션 실행</h1>';

function runArtisan($command)
{
    $output = [];
    $returnCode = 0;
    exec("cd .. && php artisan $command 2>&1", $output, $returnCode);

    return [
        'success' => $returnCode === 0,
        'output' => implode("\n", $output),
        'code' => $returnCode,
    ];
}

try {
    // 1. 마이그레이션 상태 확인
    echo '<h2>1️⃣ 현재 마이그레이션 상태</h2>';
    $status = runArtisan('migrate:status');
    echo "<pre style='background: #f8f9fa; padding: 10px; font-size: 12px;'>";
    echo htmlspecialchars($status['output']);
    echo '</pre>';

    // 2. 모든 테이블 생성
    echo '<h2>2️⃣ 데이터베이스 마이그레이션 실행</h2>';
    $migrate = runArtisan('migrate --force');

    if ($migrate['success']) {
        echo '<p>✅ 마이그레이션 완료</p>';
    } else {
        echo '<p>❌ 마이그레이션 실패</p>';
        echo "<pre style='color: red;'>".htmlspecialchars($migrate['output']).'</pre>';
    }

    // 2.5. 필수 테이블 수동 생성 (마이그레이션 실패 시 보완)
    echo '<h2>2️⃣🛠️ 누락 테이블 수동 생성</h2>';

    // Laravel 어플리케이션 초기화
    require_once __DIR__.'/../vendor/autoload.php';
    $app = require_once __DIR__.'/../bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

    $pdo = DB::connection()->getPdo();

    // 현재 테이블 확인
    $tables = DB::select("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' ORDER BY table_name");
    $existingTables = array_column($tables, 'table_name');
    $requiredTables = ['dealer_profiles', 'monthly_settlements', 'daily_expenses', 'payrolls', 'refunds', 'fixed_expenses'];

    echo '<ul>';
    foreach ($requiredTables as $table) {
        $exists = in_array($table, $existingTables);
        $status = $exists ? '✅ 존재' : '❌ 누락';
        echo "<li><strong>{$table}</strong>: {$status}</li>";

        // 누락된 테이블 생성
        if (! $exists) {
            echo "<p style='margin-left:20px; color:blue;'>🔄 {$table} 생성 중...</p>";

            switch ($table) {
                case 'dealer_profiles':
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

                    // 기본 데이터 삽입
                    DB::table('dealer_profiles')->insert([
                        ['dealer_code' => 'ENT', 'dealer_name' => '이앤티', 'contact_person' => '김대리', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
                        ['dealer_code' => 'WIN', 'dealer_name' => '앤투윈', 'contact_person' => '박과장', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
                        ['dealer_code' => '초시대', 'dealer_name' => '초시대', 'contact_person' => '관리자', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
                        ['dealer_code' => '아엠티', 'dealer_name' => '아엠티', 'contact_person' => '관리자', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
                    ]);
                    echo "<p style='margin-left:40px; color:green;'>✅ dealer_profiles + 4개 대리점 생성 완료</p>";
                    break;

                case 'monthly_settlements':
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
                    echo "<p style='margin-left:40px; color:green;'>✅ monthly_settlements 생성 완료</p>";
                    break;

                case 'daily_expenses':
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
                    echo "<p style='margin-left:40px; color:green;'>✅ daily_expenses 생성 완료</p>";
                    break;

                case 'payrolls':
                    DB::statement("
                        CREATE TABLE payrolls (
                            id BIGSERIAL PRIMARY KEY,
                            employee_name VARCHAR(255) NOT NULL,
                            store_id BIGINT,
                            branch_id BIGINT,
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
                    echo "<p style='margin-left:40px; color:green;'>✅ payrolls 생성 완룼</p>";
                    break;

                case 'refunds':
                    DB::statement("
                        CREATE TABLE refunds (
                            id BIGSERIAL PRIMARY KEY,
                            store_id BIGINT,
                            branch_id BIGINT,
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
                            processed_by BIGINT,
                            notes TEXT,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                        )
                    ");
                    echo "<p style='margin-left:40px; color:green;'>✅ refunds 생성 완룼</p>";
                    break;

                case 'fixed_expenses':
                    DB::statement("
                        CREATE TABLE fixed_expenses (
                            id BIGSERIAL PRIMARY KEY,
                            store_id BIGINT,
                            branch_id BIGINT,
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
                    echo "<p style='margin-left:40px; color:green;'>✅ fixed_expenses 생성 완룼</p>";
                    break;
            }
        }
    }
    echo '</ul>';

    // 3. 기본 데이터 시딩
    echo '<h2>3️⃣ 기본 데이터 시딩</h2>';

    $seeders = [
        'HeadquartersUserSeeder',
        'DealerProfileSeeder',
        'AdvancedSeeder',
    ];

    foreach ($seeders as $seeder) {
        echo "<h3>📊 {$seeder} 실행</h3>";
        $seed = runArtisan("db:seed --class={$seeder} --force");

        if ($seed['success']) {
            echo "<p>✅ {$seeder} 완료</p>";
        } else {
            echo "<p>⚠️ {$seeder} 실패 (계속 진행)</p>";
            echo "<pre style='color: #856404; font-size: 11px;'>";
            echo htmlspecialchars(substr($seed['output'], 0, 300));
            echo '</pre>';
        }
    }

    // 4. 최종 상태 확인
    echo '<h2>4️⃣ 최종 마이그레이션 상태</h2>';
    $finalStatus = runArtisan('migrate:status');
    echo "<pre style='background: #e8f5e8; padding: 10px; font-size: 12px;'>";
    echo htmlspecialchars($finalStatus['output']);
    echo '</pre>';

    echo '<hr>';
    echo "<div style='background: #d4edda; padding: 20px; border-radius: 8px;'>";
    echo '<h2>🎉 마이그레이션 완료!</h2>';
    echo '<p><strong>YKP ERP 데이터베이스가 준비되었습니다.</strong></p>';
    echo "<p><a href='/' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold;'>메인 사이트로 이동</a></p>";
    echo '</div>';

} catch (Exception $e) {
    echo '<p>❌ 에러: '.$e->getMessage().'</p>';
}
