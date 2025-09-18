-- 🔧 Supabase ykp-project에서 수동 실행할 SQL
-- 실행 순서: 1 → 2 → 3 → 4

-- ===== 1. activity_logs 테이블 생성 =====
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

-- ===== 2. goals 테이블 생성 =====
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

-- ===== 3. Sales 테이블 필드 추가 =====
ALTER TABLE sales ADD COLUMN IF NOT EXISTS dealer_code VARCHAR(255);
ALTER TABLE sales ADD COLUMN IF NOT EXISTS dealer_name VARCHAR(255);
ALTER TABLE sales ADD COLUMN IF NOT EXISTS serial_number VARCHAR(255);
ALTER TABLE sales ADD COLUMN IF NOT EXISTS customer_name VARCHAR(255);
ALTER TABLE sales ADD COLUMN IF NOT EXISTS customer_birth_date DATE;

-- ===== 4. 성능 인덱스 추가 =====
CREATE INDEX IF NOT EXISTS idx_activity_logs_performed_at ON activity_logs(performed_at, activity_type);
CREATE INDEX IF NOT EXISTS idx_activity_logs_user ON activity_logs(user_id, performed_at);
CREATE INDEX IF NOT EXISTS idx_activity_logs_target ON activity_logs(target_type, target_id);

CREATE INDEX IF NOT EXISTS idx_goals_target_type_id ON goals(target_type, target_id, period_type);
CREATE INDEX IF NOT EXISTS idx_goals_period ON goals(period_start, period_end);
CREATE INDEX IF NOT EXISTS idx_goals_active ON goals(is_active, target_type);

CREATE INDEX IF NOT EXISTS idx_sales_dealer_code ON sales(dealer_code);
CREATE INDEX IF NOT EXISTS idx_sales_dealer_name ON sales(dealer_name);
CREATE INDEX IF NOT EXISTS idx_sales_customer_name ON sales(customer_name);

-- ===== 5. 테이블 코멘트 =====
COMMENT ON TABLE activity_logs IS '실시간 사용자 활동 로그';
COMMENT ON TABLE goals IS '시스템/지사/매장별 목표 관리';

-- ===== 완료 확인 =====
SELECT 'activity_logs 테이블 생성됨' as status WHERE EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'activity_logs');
SELECT 'goals 테이블 생성됨' as status WHERE EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'goals');
SELECT 'sales.dealer_code 필드 추가됨' as status WHERE EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'sales' AND column_name = 'dealer_code');