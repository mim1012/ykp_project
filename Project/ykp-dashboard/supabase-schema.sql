-- YKP Dashboard Supabase 데이터베이스 스키마
-- 생성일: 2025-09-01
-- 업데이트: 2025-09-15 (activity_logs, goals 테이블 추가)

-- 1. 지사 테이블 (IF NOT EXISTS로 안전하게)
CREATE TABLE IF NOT EXISTS branches (
    id BIGSERIAL PRIMARY KEY,
    code VARCHAR(255) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    manager_name VARCHAR(255),
    phone VARCHAR(255),
    address TEXT,
    status VARCHAR(50) DEFAULT 'active',
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- 2. 매장 테이블 (Supabase 최적화)
CREATE TABLE stores (
    id BIGSERIAL PRIMARY KEY,
    branch_id BIGINT NOT NULL REFERENCES branches(id) ON DELETE CASCADE,
    code VARCHAR(255) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    owner_name VARCHAR(255),
    phone VARCHAR(255),
    address TEXT,
    status VARCHAR(50) DEFAULT 'active',
    opened_at TIMESTAMP WITH TIME ZONE,
    created_by BIGINT,
    last_sync_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    sync_status JSONB DEFAULT '{"status": "active"}',
    supabase_id UUID DEFAULT gen_random_uuid(),
    metadata JSONB DEFAULT '{}',
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- 3. 사용자 테이블 (권한 체계 포함)
CREATE TABLE users (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    email_verified_at TIMESTAMP WITH TIME ZONE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(50) NOT NULL DEFAULT 'store', -- headquarters, branch, store, developer
    branch_id BIGINT REFERENCES branches(id),
    store_id BIGINT REFERENCES stores(id),
    is_active BOOLEAN DEFAULT true,
    created_by_user_id BIGINT REFERENCES users(id),
    last_login_at TIMESTAMP WITH TIME ZONE,
    remember_token VARCHAR(100),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- 4. 판매 데이터 테이블 (핵심 테이블)
CREATE TABLE sales (
    id BIGSERIAL PRIMARY KEY,
    dealer_code VARCHAR(255),
    store_id BIGINT NOT NULL REFERENCES stores(id) ON DELETE CASCADE,
    branch_id BIGINT NOT NULL REFERENCES branches(id) ON DELETE CASCADE,
    sale_date DATE NOT NULL,
    carrier VARCHAR(255),
    activation_type VARCHAR(255),
    model_name VARCHAR(255),
    
    -- 금액 필드들
    base_price DECIMAL(10,2) DEFAULT 0,
    verbal1 DECIMAL(10,2) DEFAULT 0,
    verbal2 DECIMAL(10,2) DEFAULT 0,
    grade_amount DECIMAL(10,2) DEFAULT 0,
    additional_amount DECIMAL(10,2) DEFAULT 0,
    rebate_total DECIMAL(10,2) DEFAULT 0,
    cash_activation DECIMAL(10,2) DEFAULT 0,
    usim_fee DECIMAL(10,2) DEFAULT 0,
    new_mnp_discount DECIMAL(10,2) DEFAULT 0,
    deduction DECIMAL(10,2) DEFAULT 0,
    settlement_amount DECIMAL(10,2) DEFAULT 0,
    tax DECIMAL(10,2) DEFAULT 0,
    margin_before_tax DECIMAL(10,2) DEFAULT 0,
    cash_received DECIMAL(10,2) DEFAULT 0,
    payback DECIMAL(10,2) DEFAULT 0,
    margin_after_tax DECIMAL(10,2) DEFAULT 0,
    monthly_fee DECIMAL(10,2) DEFAULT 0,
    
    -- 기타 정보
    phone_number VARCHAR(255),
    salesperson VARCHAR(255),
    memo TEXT,
    
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- 5. 대리점 프로필 테이블
CREATE TABLE dealer_profiles (
    id BIGSERIAL PRIMARY KEY,
    dealer_code VARCHAR(255) NOT NULL UNIQUE,
    dealer_name VARCHAR(255) NOT NULL,
    base_commission_rate DECIMAL(5,2) DEFAULT 0,
    volume_bonus_rate DECIMAL(5,2) DEFAULT 0,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- 6. 일일 지출 테이블
CREATE TABLE daily_expenses (
    id BIGSERIAL PRIMARY KEY,
    store_id BIGINT NOT NULL REFERENCES stores(id) ON DELETE CASCADE,
    branch_id BIGINT NOT NULL REFERENCES branches(id) ON DELETE CASCADE,
    expense_date DATE NOT NULL,
    category VARCHAR(255) NOT NULL,
    description TEXT,
    amount DECIMAL(10,2) NOT NULL,
    receipt_number VARCHAR(255),
    approved_by BIGINT REFERENCES users(id),
    created_by BIGINT NOT NULL REFERENCES users(id),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- 7. 고정 지출 테이블
CREATE TABLE fixed_expenses (
    id BIGSERIAL PRIMARY KEY,
    store_id BIGINT REFERENCES stores(id) ON DELETE CASCADE,
    branch_id BIGINT REFERENCES branches(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    category VARCHAR(255) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_date INTEGER NOT NULL, -- 매월 지불일 (1-31)
    status VARCHAR(50) DEFAULT 'active',
    payment_status VARCHAR(50) DEFAULT 'pending',
    last_paid_date DATE,
    created_by BIGINT NOT NULL REFERENCES users(id),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- 8. 급여 테이블
CREATE TABLE payrolls (
    id BIGSERIAL PRIMARY KEY,
    employee_name VARCHAR(255) NOT NULL,
    store_id BIGINT REFERENCES stores(id) ON DELETE CASCADE,
    branch_id BIGINT REFERENCES branches(id) ON DELETE CASCADE,
    base_salary DECIMAL(10,2) NOT NULL,
    commission DECIMAL(10,2) DEFAULT 0,
    bonus DECIMAL(10,2) DEFAULT 0,
    deductions DECIMAL(10,2) DEFAULT 0,
    total_salary DECIMAL(10,2) NOT NULL,
    payment_date DATE NOT NULL,
    payment_status VARCHAR(50) DEFAULT 'pending',
    notes TEXT,
    created_by BIGINT NOT NULL REFERENCES users(id),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- 9. 환수 테이블
CREATE TABLE refunds (
    id BIGSERIAL PRIMARY KEY,
    store_id BIGINT NOT NULL REFERENCES stores(id) ON DELETE CASCADE,
    branch_id BIGINT NOT NULL REFERENCES branches(id) ON DELETE CASCADE,
    refund_date DATE NOT NULL,
    customer_name VARCHAR(255),
    phone_number VARCHAR(255),
    original_sale_date DATE,
    refund_amount DECIMAL(10,2) NOT NULL,
    refund_reason TEXT,
    processing_fee DECIMAL(10,2) DEFAULT 0,
    net_refund_amount DECIMAL(10,2) NOT NULL,
    status VARCHAR(50) DEFAULT 'pending',
    processed_by BIGINT REFERENCES users(id),
    created_by BIGINT NOT NULL REFERENCES users(id),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- 10. 월정산 테이블  
CREATE TABLE monthly_settlements (
    id BIGSERIAL PRIMARY KEY,
    store_id BIGINT NOT NULL REFERENCES stores(id) ON DELETE CASCADE,
    branch_id BIGINT NOT NULL REFERENCES branches(id) ON DELETE CASCADE,
    settlement_month VARCHAR(7) NOT NULL, -- YYYY-MM 형식
    total_sales DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_expenses DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_payroll DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_refunds DECIMAL(12,2) NOT NULL DEFAULT 0,
    net_profit DECIMAL(12,2) NOT NULL DEFAULT 0,
    status VARCHAR(50) DEFAULT 'draft',
    confirmed_at TIMESTAMP WITH TIME ZONE,
    confirmed_by BIGINT REFERENCES users(id),
    created_by BIGINT NOT NULL REFERENCES users(id),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- 11. 매장 요청 테이블 (승인 워크플로우용)
CREATE TABLE store_requests (
    id BIGSERIAL PRIMARY KEY,
    requested_by BIGINT NOT NULL REFERENCES users(id),
    branch_id BIGINT NOT NULL REFERENCES branches(id),
    store_name VARCHAR(255) NOT NULL,
    store_code VARCHAR(255) NOT NULL,
    owner_name VARCHAR(255),
    phone VARCHAR(255),
    address TEXT,
    business_license VARCHAR(255),
    request_reason TEXT,
    status VARCHAR(50) DEFAULT 'pending', -- pending, approved, rejected
    reviewed_by BIGINT REFERENCES users(id),
    reviewed_at TIMESTAMP WITH TIME ZONE,
    review_comment TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- 12. Laravel 기본 테이블들
CREATE TABLE sessions (
    id VARCHAR(255) PRIMARY KEY,
    user_id BIGINT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    payload BYTEA NOT NULL,
    last_activity INTEGER NOT NULL
);

CREATE TABLE cache (
    key VARCHAR(255) PRIMARY KEY,
    value BYTEA NOT NULL,
    expiration INTEGER NOT NULL
);

CREATE TABLE jobs (
    id BIGSERIAL PRIMARY KEY,
    queue VARCHAR(255) NOT NULL,
    payload BYTEA NOT NULL,
    attempts SMALLINT NOT NULL,
    reserved_at INTEGER,
    available_at INTEGER NOT NULL,
    created_at INTEGER NOT NULL
);

-- 인덱스 생성 (성능 최적화)
CREATE INDEX sales_store_date_idx ON sales(store_id, sale_date);
CREATE INDEX sales_branch_date_idx ON sales(branch_id, sale_date);
CREATE INDEX sales_date_idx ON sales(sale_date);
CREATE INDEX users_role_idx ON users(role);
CREATE INDEX users_store_idx ON users(store_id);
CREATE INDEX users_branch_idx ON users(branch_id);
CREATE INDEX stores_branch_idx ON stores(branch_id);
CREATE INDEX daily_expenses_store_date_idx ON daily_expenses(store_id, expense_date);
CREATE INDEX monthly_settlements_store_month_idx ON monthly_settlements(store_id, settlement_month);

-- RLS (Row Level Security) 정책
ALTER TABLE sales ENABLE ROW LEVEL SECURITY;
ALTER TABLE stores ENABLE ROW LEVEL SECURITY;
ALTER TABLE daily_expenses ENABLE ROW LEVEL SECURITY;
ALTER TABLE fixed_expenses ENABLE ROW LEVEL SECURITY;
ALTER TABLE payrolls ENABLE ROW LEVEL SECURITY;
ALTER TABLE refunds ENABLE ROW LEVEL SECURITY;
ALTER TABLE monthly_settlements ENABLE ROW LEVEL SECURITY;

-- 권한별 RLS 정책 (사용자 인증 기반)
-- 본사: 모든 데이터 접근
CREATE POLICY "본사_전체_접근" ON sales FOR ALL 
TO authenticated 
USING (EXISTS (
    SELECT 1 FROM users 
    WHERE auth.uid()::text = id::text 
    AND role = 'headquarters'
));

-- 지사: 해당 지사 데이터만
CREATE POLICY "지사_소속_접근" ON sales FOR ALL 
TO authenticated 
USING (
    branch_id = (
        SELECT branch_id FROM users 
        WHERE auth.uid()::text = id::text 
        AND role = 'branch'
    )
);

-- 매장: 자기 매장 데이터만  
CREATE POLICY "매장_자기_데이터" ON sales FOR ALL 
TO authenticated 
USING (
    store_id = (
        SELECT store_id FROM users 
        WHERE auth.uid()::text = id::text 
        AND role = 'store'
    )
);

-- 기본 데이터 삽입
INSERT INTO branches (code, name, manager_name, phone, address) VALUES
('BR001', '서울지점', '김지점', '010-0000-0001', '서울시 강남구'),
('BR002', '경기지점', '이지점', '010-0000-0002', '경기도 수원시'),
('BR003', '인천지점', '박지점', '010-0000-0003', '인천시 남동구'),
('BR004', '부산지점', '최지점', '010-0000-0004', '부산시 해운대구'),
('BR005', '대구지점', '정지점', '010-0000-0005', '대구시 중구');

-- 기본 관리자 계정
INSERT INTO users (name, email, password, role) VALUES
('본사 관리자', 'hq@ykp.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'headquarters'),
('지사 관리자', 'branch@ykp.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'branch'),
('매장 직원', 'store@ykp.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'store');

-- 업데이트 트리거 (updated_at 자동 갱신)
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ language 'plpgsql';

-- 모든 테이블에 업데이트 트리거 적용
CREATE TRIGGER update_branches_updated_at BEFORE UPDATE ON branches FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_stores_updated_at BEFORE UPDATE ON stores FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_users_updated_at BEFORE UPDATE ON users FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_sales_updated_at BEFORE UPDATE ON sales FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_daily_expenses_updated_at BEFORE UPDATE ON daily_expenses FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_fixed_expenses_updated_at BEFORE UPDATE ON fixed_expenses FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_payrolls_updated_at BEFORE UPDATE ON payrolls FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_refunds_updated_at BEFORE UPDATE ON refunds FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_monthly_settlements_updated_at BEFORE UPDATE ON monthly_settlements FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- 실시간 구독 설정 (기본 supabase_realtime 사용)
-- Supabase에서 기본 제공하므로 별도 생성 불필요

-- 코멘트 추가 (문서화)
COMMENT ON TABLE branches IS '지사 정보 관리';
COMMENT ON TABLE stores IS '매장 정보 관리 (지사별 분류)';
COMMENT ON TABLE users IS '사용자 및 권한 관리';
COMMENT ON TABLE sales IS '개통표 판매 데이터 (일별 입력)';
COMMENT ON TABLE daily_expenses IS '일일 지출 관리';
COMMENT ON TABLE fixed_expenses IS '고정 지출 관리';
COMMENT ON TABLE payrolls IS '급여 관리';
COMMENT ON TABLE refunds IS '환수 관리';
COMMENT ON TABLE monthly_settlements IS '월정산 데이터';
COMMENT ON TABLE store_requests IS '매장 추가 요청 및 승인 관리';

-- 🆕 신규 테이블 추가 (2025-09-15)

-- 11. 목표 관리 테이블 (시스템/지사/매장별 목표 설정)
CREATE TABLE IF NOT EXISTS goals (
    id BIGSERIAL PRIMARY KEY,
    target_type VARCHAR(50) NOT NULL CHECK (target_type IN ('system', 'branch', 'store')),
    target_id BIGINT, -- 지사 ID 또는 매장 ID (system은 null)
    period_type VARCHAR(50) NOT NULL CHECK (period_type IN ('daily', 'weekly', 'monthly', 'quarterly', 'yearly')),
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,

    -- 목표 지표들
    sales_target DECIMAL(15,2) DEFAULT 0,
    activation_target INTEGER DEFAULT 0,
    margin_target DECIMAL(15,2) DEFAULT 0,

    -- 목표 설정 정보
    created_by BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    notes TEXT,
    is_active BOOLEAN DEFAULT true,

    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- 12. 활동 로그 테이블 (실시간 사용자 활동 추적)
CREATE TABLE IF NOT EXISTS activity_logs (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    activity_type VARCHAR(50) NOT NULL CHECK (activity_type IN (
        'login', 'logout',
        'sales_input', 'sales_update', 'sales_delete',
        'store_create', 'store_update', 'store_delete',
        'branch_create', 'branch_update', 'branch_delete',
        'account_create', 'account_update', 'account_delete',
        'goal_create', 'goal_update',
        'export_data', 'import_data'
    )),

    activity_title VARCHAR(255) NOT NULL,
    activity_description TEXT,
    activity_data JSONB,

    -- 관련 객체 정보
    target_type VARCHAR(50),
    target_id BIGINT,

    -- 컨텍스트 정보
    ip_address INET,
    user_agent TEXT,
    performed_at TIMESTAMP WITH TIME ZONE NOT NULL,

    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- 인덱스 추가 (성능 최적화)
CREATE INDEX IF NOT EXISTS idx_goals_target_type_id ON goals(target_type, target_id, period_type);
CREATE INDEX IF NOT EXISTS idx_goals_period ON goals(period_start, period_end);
CREATE INDEX IF NOT EXISTS idx_goals_active ON goals(is_active, target_type);

CREATE INDEX IF NOT EXISTS idx_activity_logs_performed_at ON activity_logs(performed_at, activity_type);
CREATE INDEX IF NOT EXISTS idx_activity_logs_user ON activity_logs(user_id, performed_at);
CREATE INDEX IF NOT EXISTS idx_activity_logs_target ON activity_logs(target_type, target_id);
CREATE INDEX IF NOT EXISTS idx_activity_logs_type ON activity_logs(activity_type, performed_at);

-- 신규 테이블 코멘트
COMMENT ON TABLE goals IS '시스템/지사/매장별 목표 관리';
COMMENT ON TABLE activity_logs IS '실시간 사용자 활동 로그';