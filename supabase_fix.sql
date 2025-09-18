-- 🚨 Supabase 직접 테이블 생성 스크립트
-- 누락된 핵심 테이블들을 수동으로 생성하여 500 오류 해결

-- 1. dealer_profiles 테이블 생성 (최우선)
CREATE TABLE IF NOT EXISTS dealer_profiles (
    id BIGSERIAL PRIMARY KEY,
    dealer_code VARCHAR(255) UNIQUE NOT NULL,
    dealer_name VARCHAR(255) NOT NULL,
    contact_person VARCHAR(255),
    phone VARCHAR(255),
    address TEXT,
    
    -- 기본 설정값들 (ykp-settlement 기반)
    default_sim_fee DECIMAL(10, 2) DEFAULT 0,
    default_mnp_discount DECIMAL(10, 2) DEFAULT 800,
    tax_rate DECIMAL(5, 3) DEFAULT 0.10,
    default_payback_rate DECIMAL(5, 2) DEFAULT 0,
    
    -- 계산 옵션들
    auto_calculate_tax BOOLEAN DEFAULT true,
    include_sim_fee_in_settlement BOOLEAN DEFAULT true,
    custom_calculation_rules JSON,
    
    -- 상태 관리
    status VARCHAR(20) DEFAULT 'active' CHECK (status IN ('active', 'inactive', 'suspended')),
    activated_at TIMESTAMP,
    deactivated_at TIMESTAMP,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 인덱스 생성
CREATE INDEX IF NOT EXISTS idx_dealer_profiles_code_status ON dealer_profiles (dealer_code, status);
CREATE INDEX IF NOT EXISTS idx_dealer_profiles_status ON dealer_profiles (status);

-- 2. daily_expenses 테이블 생성
CREATE TABLE IF NOT EXISTS daily_expenses (
    id BIGSERIAL PRIMARY KEY,
    store_id BIGINT REFERENCES stores(id) ON DELETE CASCADE,
    branch_id BIGINT REFERENCES branches(id) ON DELETE CASCADE,
    expense_date DATE NOT NULL,
    category VARCHAR(100) NOT NULL, -- '상담비', '메일접수비', '기타운영비' 등
    amount DECIMAL(10, 2) NOT NULL,
    description TEXT,
    receipt_number VARCHAR(100),
    payment_method VARCHAR(50) DEFAULT 'cash',
    status VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending', 'approved', 'rejected')),
    
    created_by BIGINT REFERENCES users(id),
    approved_by BIGINT REFERENCES users(id),
    approved_at TIMESTAMP,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. payrolls 테이블 생성 (급여 관리)
CREATE TABLE IF NOT EXISTS payrolls (
    id BIGSERIAL PRIMARY KEY,
    employee_name VARCHAR(255) NOT NULL,
    store_id BIGINT REFERENCES stores(id) ON DELETE CASCADE,
    branch_id BIGINT REFERENCES branches(id) ON DELETE CASCADE,
    year_month VARCHAR(7) NOT NULL, -- YYYY-MM
    
    -- 급여 구성
    base_salary DECIMAL(10, 2) DEFAULT 0,
    performance_bonus DECIMAL(10, 2) DEFAULT 0,
    overtime_pay DECIMAL(10, 2) DEFAULT 0,
    allowances DECIMAL(10, 2) DEFAULT 0,
    total_gross_pay DECIMAL(10, 2) NOT NULL,
    
    -- 공제
    income_tax DECIMAL(10, 2) DEFAULT 0,
    insurance DECIMAL(10, 2) DEFAULT 0,
    other_deductions DECIMAL(10, 2) DEFAULT 0,
    total_deductions DECIMAL(10, 2) DEFAULT 0,
    
    -- 실지급액
    net_pay DECIMAL(10, 2) NOT NULL,
    
    -- 상태
    payment_status VARCHAR(20) DEFAULT 'pending' CHECK (payment_status IN ('pending', 'paid', 'cancelled')),
    payment_date DATE,
    payment_method VARCHAR(50) DEFAULT 'bank_transfer',
    
    notes TEXT,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 4. refunds 테이블 생성 (환수금 관리)
CREATE TABLE IF NOT EXISTS refunds (
    id BIGSERIAL PRIMARY KEY,
    store_id BIGINT REFERENCES stores(id) ON DELETE CASCADE,
    branch_id BIGINT REFERENCES branches(id) ON DELETE CASCADE,
    
    -- 환수 정보
    refund_date DATE NOT NULL,
    customer_name VARCHAR(255),
    phone_number VARCHAR(20),
    model_name VARCHAR(255),
    carrier VARCHAR(20) NOT NULL, -- 'SK', 'KT', 'LGU+' 등
    
    -- 환수 금액
    original_settlement_amount DECIMAL(10, 2) NOT NULL,
    refund_amount DECIMAL(10, 2) NOT NULL,
    penalty_amount DECIMAL(10, 2) DEFAULT 0,
    total_refund_amount DECIMAL(10, 2) NOT NULL,
    
    -- 환수 사유
    refund_reason VARCHAR(255) NOT NULL,
    detailed_reason TEXT,
    
    -- 처리 상태
    status VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending', 'processing', 'completed', 'cancelled')),
    processed_date DATE,
    processed_by BIGINT REFERENCES users(id),
    
    notes TEXT,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 5. fixed_expenses 테이블 생성 (고정 지출)
CREATE TABLE IF NOT EXISTS fixed_expenses (
    id BIGSERIAL PRIMARY KEY,
    store_id BIGINT REFERENCES stores(id) ON DELETE CASCADE,
    branch_id BIGINT REFERENCES branches(id) ON DELETE CASCADE,
    
    -- 고정지출 정보
    year_month VARCHAR(7) NOT NULL, -- YYYY-MM
    category VARCHAR(100) NOT NULL, -- '임대료', '인건비', '통신비' 등
    amount DECIMAL(10, 2) NOT NULL,
    description TEXT,
    
    -- 지급 정보
    due_date DATE,
    payment_status VARCHAR(20) DEFAULT 'pending' CHECK (payment_status IN ('pending', 'paid', 'overdue')),
    payment_date DATE,
    payment_method VARCHAR(50),
    
    notes TEXT,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 6. monthly_settlements 테이블 생성 (월마감정산)
CREATE TABLE IF NOT EXISTS monthly_settlements (
    id BIGSERIAL PRIMARY KEY,
    year_month VARCHAR(7) NOT NULL, -- YYYY-MM
    dealer_code VARCHAR(20) NOT NULL,
    settlement_status VARCHAR(20) DEFAULT 'draft' CHECK (settlement_status IN ('draft', 'confirmed', 'closed')),
    
    -- 수익 집계
    total_sales_amount DECIMAL(15, 2) DEFAULT 0,
    total_sales_count INTEGER DEFAULT 0,
    average_margin_rate DECIMAL(5, 2) DEFAULT 0,
    total_vat_amount DECIMAL(15, 2) DEFAULT 0,
    
    -- 지출 집계
    total_daily_expenses DECIMAL(15, 2) DEFAULT 0,
    total_fixed_expenses DECIMAL(15, 2) DEFAULT 0,
    total_payroll_amount DECIMAL(15, 2) DEFAULT 0,
    total_refund_amount DECIMAL(15, 2) DEFAULT 0,
    total_expense_amount DECIMAL(15, 2) DEFAULT 0,
    
    -- 최종 계산
    gross_profit DECIMAL(15, 2) DEFAULT 0,
    net_profit DECIMAL(15, 2) DEFAULT 0,
    profit_rate DECIMAL(5, 2) DEFAULT 0,
    
    -- 전월 대비 분석
    prev_month_comparison DECIMAL(15, 2) DEFAULT 0,
    growth_rate DECIMAL(5, 2) DEFAULT 0,
    
    -- 관리 정보
    calculated_at TIMESTAMP,
    confirmed_at TIMESTAMP,
    confirmed_by BIGINT REFERENCES users(id),
    notes TEXT,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- 제약 조건
    UNIQUE(year_month, dealer_code)
);

-- 인덱스 생성
CREATE INDEX IF NOT EXISTS idx_monthly_settlements_year_month_status ON monthly_settlements (year_month, settlement_status);
CREATE INDEX IF NOT EXISTS idx_monthly_settlements_calculated_at ON monthly_settlements (calculated_at);

-- 🎯 기본 데이터 삽입
-- 기본 대리점 프로필 생성
INSERT INTO dealer_profiles (dealer_code, dealer_name, contact_person, status) VALUES 
('ENT', '이앤티', '김대리', 'active'),
('WIN', '앤투윈', '박과장', 'active'),
('초시대', '초시대', '관리자', 'active'),
('아엠티', '아엠티', '관리자', 'active')
ON CONFLICT (dealer_code) DO NOTHING;

-- 확인용 쿼리 (수동 실행용)
-- SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_name IN ('dealer_profiles', 'monthly_settlements', 'daily_expenses', 'payrolls', 'refunds', 'fixed_expenses');