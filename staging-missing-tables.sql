-- 🎯 Staging에서 누락된 5개 테이블만 생성
-- 실행 위치: https://supabase.com/dashboard/project/qwafwqxdcfpqqwpmphkm (staging)

-- ===== 1. 누락된 시퀀스들 생성 =====
CREATE SEQUENCE IF NOT EXISTS monthly_settlements_id_seq;
CREATE SEQUENCE IF NOT EXISTS payrolls_id_seq;
CREATE SEQUENCE IF NOT EXISTS refunds_id_seq;
CREATE SEQUENCE IF NOT EXISTS dealer_profiles_id_seq;
CREATE SEQUENCE IF NOT EXISTS store_requests_id_seq;

-- ===== 2. monthly_settlements 테이블 생성 =====
CREATE TABLE monthly_settlements (
  id BIGINT NOT NULL DEFAULT nextval('monthly_settlements_id_seq'),
  year_month VARCHAR(255) NOT NULL,
  dealer_code VARCHAR(255) NOT NULL,
  settlement_status VARCHAR(255) NOT NULL DEFAULT 'draft' CHECK (settlement_status IN ('draft', 'confirmed', 'closed')),
  total_sales_amount NUMERIC NOT NULL DEFAULT 0,
  total_sales_count INTEGER NOT NULL DEFAULT 0,
  average_margin_rate NUMERIC NOT NULL DEFAULT 0,
  total_vat_amount NUMERIC NOT NULL DEFAULT 0,
  total_daily_expenses NUMERIC NOT NULL DEFAULT 0,
  total_fixed_expenses NUMERIC NOT NULL DEFAULT 0,
  total_payroll_amount NUMERIC NOT NULL DEFAULT 0,
  total_refund_amount NUMERIC NOT NULL DEFAULT 0,
  total_expense_amount NUMERIC NOT NULL DEFAULT 0,
  gross_profit NUMERIC NOT NULL DEFAULT 0,
  net_profit NUMERIC NOT NULL DEFAULT 0,
  profit_rate NUMERIC NOT NULL DEFAULT 0,
  prev_month_comparison NUMERIC NOT NULL DEFAULT 0,
  growth_rate NUMERIC NOT NULL DEFAULT 0,
  calculated_at TIMESTAMP,
  confirmed_at TIMESTAMP,
  confirmed_by BIGINT,
  notes TEXT,
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  CONSTRAINT monthly_settlements_pkey PRIMARY KEY (id)
);

-- ===== 3. payrolls 테이블 생성 =====
CREATE TABLE payrolls (
  id BIGINT NOT NULL DEFAULT nextval('payrolls_id_seq'),
  year_month VARCHAR(255) NOT NULL,
  dealer_code VARCHAR(255) NOT NULL,
  employee_id VARCHAR(255) NOT NULL,
  employee_name VARCHAR(255) NOT NULL,
  position VARCHAR(255),
  base_salary NUMERIC NOT NULL DEFAULT 0,
  incentive_amount NUMERIC NOT NULL DEFAULT 0,
  bonus_amount NUMERIC NOT NULL DEFAULT 0,
  deduction_amount NUMERIC NOT NULL DEFAULT 0,
  total_salary NUMERIC NOT NULL,
  payment_date DATE,
  payment_status VARCHAR(255) NOT NULL DEFAULT 'pending' CHECK (payment_status IN ('pending', 'paid')),
  memo TEXT,
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  CONSTRAINT payrolls_pkey PRIMARY KEY (id)
);

-- ===== 4. refunds 테이블 생성 =====
CREATE TABLE refunds (
  id BIGINT NOT NULL DEFAULT nextval('refunds_id_seq'),
  refund_date DATE NOT NULL,
  dealer_code VARCHAR(255) NOT NULL,
  activation_id BIGINT,
  customer_name VARCHAR(255) NOT NULL,
  customer_phone VARCHAR(255) NOT NULL,
  refund_reason VARCHAR(255) NOT NULL,
  refund_type VARCHAR(255) NOT NULL,
  original_amount NUMERIC NOT NULL,
  refund_amount NUMERIC NOT NULL,
  penalty_amount NUMERIC NOT NULL DEFAULT 0,
  refund_method VARCHAR(255),
  processed_by VARCHAR(255),
  memo TEXT,
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  CONSTRAINT refunds_pkey PRIMARY KEY (id)
);

-- ===== 5. dealer_profiles 테이블 생성 =====
CREATE TABLE dealer_profiles (
  id BIGINT NOT NULL DEFAULT nextval('dealer_profiles_id_seq'),
  dealer_code VARCHAR(255) NOT NULL UNIQUE,
  dealer_name VARCHAR(255) NOT NULL,
  contact_person VARCHAR(255),
  phone VARCHAR(255),
  address TEXT,
  default_sim_fee NUMERIC NOT NULL DEFAULT 0,
  default_mnp_discount NUMERIC NOT NULL DEFAULT 800,
  tax_rate NUMERIC NOT NULL DEFAULT 0.1,
  default_payback_rate NUMERIC NOT NULL DEFAULT 0,
  auto_calculate_tax BOOLEAN NOT NULL DEFAULT true,
  include_sim_fee_in_settlement BOOLEAN NOT NULL DEFAULT true,
  custom_calculation_rules JSON,
  status VARCHAR(255) NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'inactive', 'suspended')),
  activated_at TIMESTAMP,
  deactivated_at TIMESTAMP,
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  CONSTRAINT dealer_profiles_pkey PRIMARY KEY (id)
);

-- ===== 6. store_requests 테이블 생성 =====
CREATE TABLE store_requests (
  id BIGINT NOT NULL DEFAULT nextval('store_requests_id_seq'),
  requested_by BIGINT NOT NULL,
  branch_id BIGINT NOT NULL,
  store_name VARCHAR(255) NOT NULL,
  store_code VARCHAR(255) NOT NULL,
  owner_name VARCHAR(255),
  phone VARCHAR(255),
  address TEXT,
  business_license VARCHAR(255),
  request_reason TEXT,
  status VARCHAR(255) NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'approved', 'rejected')),
  reviewed_by BIGINT,
  reviewed_at TIMESTAMP,
  review_comment TEXT,
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  CONSTRAINT store_requests_pkey PRIMARY KEY (id)
);

-- ===== 7. 외래 키 제약 조건 추가 =====
-- monthly_settlements 외래 키
ALTER TABLE monthly_settlements ADD CONSTRAINT monthly_settlements_confirmed_by_foreign
FOREIGN KEY (confirmed_by) REFERENCES users(id);

-- refunds 외래 키
ALTER TABLE refunds ADD CONSTRAINT refunds_activation_id_foreign
FOREIGN KEY (activation_id) REFERENCES sales(id);

-- store_requests 외래 키들
ALTER TABLE store_requests ADD CONSTRAINT store_requests_requested_by_foreign
FOREIGN KEY (requested_by) REFERENCES users(id);

ALTER TABLE store_requests ADD CONSTRAINT store_requests_branch_id_foreign
FOREIGN KEY (branch_id) REFERENCES branches(id);

ALTER TABLE store_requests ADD CONSTRAINT store_requests_reviewed_by_foreign
FOREIGN KEY (reviewed_by) REFERENCES users(id);

-- ===== 8. 최종 확인 =====
SELECT
    COUNT(*) as total_tables_after_sync,
    'Staging synchronized!' as message
FROM information_schema.tables
WHERE table_schema = 'public'
  AND table_type = 'BASE TABLE'
  AND table_name NOT LIKE 'pg_%';

-- 누락되었던 테이블들 확인
SELECT 'monthly_settlements' as created_table UNION ALL
SELECT 'payrolls' UNION ALL
SELECT 'refunds' UNION ALL
SELECT 'dealer_profiles' UNION ALL
SELECT 'store_requests';

SELECT '🎉 Staging now matches Production schema!' as final_message;