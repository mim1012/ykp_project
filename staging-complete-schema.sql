-- 🎯 Staging Supabase에서 실행할 완전한 스키마 (Production 기준)
-- 실행 위치: https://supabase.com/dashboard/project/qwafwqxdcfpqqwpmphkm
-- SQL Editor에서 전체 복사 → 실행

-- ===== 1. 기본 테이블들 (이미 있을 수 있음) =====

-- 지사 테이블
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

-- 매장 테이블
CREATE TABLE IF NOT EXISTS stores (
    id BIGSERIAL PRIMARY KEY,
    branch_id BIGINT NOT NULL REFERENCES branches(id) ON DELETE CASCADE,
    code VARCHAR(255) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    owner_name VARCHAR(255),
    phone VARCHAR(255),
    address TEXT,
    status VARCHAR(50) DEFAULT 'active',
    opened_at TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- 사용자 테이블
CREATE TABLE IF NOT EXISTS users (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    email_verified_at TIMESTAMP WITH TIME ZONE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(50) NOT NULL DEFAULT 'store',
    branch_id BIGINT REFERENCES branches(id),
    store_id BIGINT REFERENCES stores(id),
    is_active BOOLEAN DEFAULT true,
    remember_token VARCHAR(100),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- ===== 2. Sales 테이블 (완전한 필드) =====
CREATE TABLE IF NOT EXISTS sales (
    id BIGSERIAL PRIMARY KEY,
    store_id BIGINT NOT NULL REFERENCES stores(id) ON DELETE CASCADE,
    branch_id BIGINT NOT NULL REFERENCES branches(id) ON DELETE CASCADE,
    sale_date DATE NOT NULL,
    carrier VARCHAR(255) CHECK (carrier IN ('SK', 'KT', 'LG', 'MVNO')),
    activation_type VARCHAR(255) CHECK (activation_type IN ('신규', '기변', 'MNP')),
    model_name VARCHAR(255),

    -- 금액 필드들 (DECIMAL 12,2)
    base_price DECIMAL(12,2) DEFAULT 0,
    verbal1 DECIMAL(12,2) DEFAULT 0,
    verbal2 DECIMAL(12,2) DEFAULT 0,
    grade_amount DECIMAL(12,2) DEFAULT 0,
    additional_amount DECIMAL(12,2) DEFAULT 0,
    rebate_total DECIMAL(12,2) DEFAULT 0,
    cash_activation DECIMAL(12,2) DEFAULT 0,
    usim_fee DECIMAL(12,2) DEFAULT 0,
    new_mnp_discount DECIMAL(12,2) DEFAULT 0,
    deduction DECIMAL(12,2) DEFAULT 0,
    settlement_amount DECIMAL(12,2) DEFAULT 0,
    tax DECIMAL(12,2) DEFAULT 0,
    margin_before_tax DECIMAL(12,2) DEFAULT 0,
    cash_received DECIMAL(12,2) DEFAULT 0,
    payback DECIMAL(12,2) DEFAULT 0,
    margin_after_tax DECIMAL(12,2) DEFAULT 0,
    monthly_fee DECIMAL(12,2) DEFAULT 0,

    -- 추가 정보 필드들
    phone_number VARCHAR(255),
    salesperson VARCHAR(255),
    memo TEXT,
    dealer_code VARCHAR(255),
    dealer_name VARCHAR(255),
    serial_number VARCHAR(255),
    customer_name VARCHAR(255),
    customer_birth_date DATE,

    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- ===== 3. 활동 로그 테이블 =====
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

    target_type VARCHAR(50),
    target_id BIGINT,

    ip_address INET,
    user_agent TEXT,
    performed_at TIMESTAMP WITH TIME ZONE NOT NULL,

    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- ===== 4. 목표 관리 테이블 =====
CREATE TABLE IF NOT EXISTS goals (
    id BIGSERIAL PRIMARY KEY,
    target_type VARCHAR(50) NOT NULL CHECK (target_type IN ('system', 'branch', 'store')),
    target_id BIGINT,
    period_type VARCHAR(50) NOT NULL CHECK (period_type IN ('daily', 'weekly', 'monthly', 'quarterly', 'yearly')),
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,

    sales_target DECIMAL(15,2) DEFAULT 0,
    activation_target INTEGER DEFAULT 0,
    margin_target DECIMAL(15,2) DEFAULT 0,

    created_by BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    notes TEXT,
    is_active BOOLEAN DEFAULT true,

    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- ===== 5. 추가 테이블들 (Production에 있다면) =====
CREATE TABLE IF NOT EXISTS daily_expenses (
    id BIGSERIAL PRIMARY KEY,
    store_id BIGINT NOT NULL REFERENCES stores(id) ON DELETE CASCADE,
    expense_date DATE NOT NULL,
    category VARCHAR(255) NOT NULL,
    description TEXT,
    amount DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS fixed_expenses (
    id BIGSERIAL PRIMARY KEY,
    store_id BIGINT REFERENCES stores(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    due_date DATE NOT NULL,
    is_paid BOOLEAN DEFAULT false,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- ===== 6. 성능 인덱스 =====
CREATE INDEX IF NOT EXISTS idx_sales_store_date ON sales(store_id, sale_date);
CREATE INDEX IF NOT EXISTS idx_sales_branch_date ON sales(branch_id, sale_date);
CREATE INDEX IF NOT EXISTS idx_sales_dealer_code ON sales(dealer_code);
CREATE INDEX IF NOT EXISTS idx_activity_logs_performed_at ON activity_logs(performed_at, activity_type);
CREATE INDEX IF NOT EXISTS idx_activity_logs_user ON activity_logs(user_id, performed_at);
CREATE INDEX IF NOT EXISTS idx_goals_target_type_id ON goals(target_type, target_id, period_type);

-- ===== 7. 완료 확인 =====
SELECT
    'activity_logs' as table_name,
    CASE WHEN EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'activity_logs')
         THEN '✅ 생성됨'
         ELSE '❌ 없음'
    END as status
UNION ALL
SELECT
    'goals' as table_name,
    CASE WHEN EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'goals')
         THEN '✅ 생성됨'
         ELSE '❌ 없음'
    END as status
UNION ALL
SELECT
    'sales.dealer_code' as field_name,
    CASE WHEN EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'sales' AND column_name = 'dealer_code')
         THEN '✅ 생성됨'
         ELSE '❌ 없음'
    END as status;