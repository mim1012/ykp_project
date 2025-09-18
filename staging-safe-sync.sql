-- 🔧 Staging Supabase 안전한 동기화 (시퀀스 문제 해결)
-- 실행 위치: https://supabase.com/dashboard/project/qwafwqxdcfpqqwpmphkm

-- ===== 1. 시퀀스 먼저 생성 =====
CREATE SEQUENCE IF NOT EXISTS branches_id_seq;
CREATE SEQUENCE IF NOT EXISTS stores_id_seq;
CREATE SEQUENCE IF NOT EXISTS users_id_seq;
CREATE SEQUENCE IF NOT EXISTS sales_id_seq;
CREATE SEQUENCE IF NOT EXISTS daily_expenses_id_seq;
CREATE SEQUENCE IF NOT EXISTS fixed_expenses_id_seq;
CREATE SEQUENCE IF NOT EXISTS monthly_settlements_id_seq;
CREATE SEQUENCE IF NOT EXISTS payrolls_id_seq;
CREATE SEQUENCE IF NOT EXISTS refunds_id_seq;
CREATE SEQUENCE IF NOT EXISTS dealer_profiles_id_seq;
CREATE SEQUENCE IF NOT EXISTS store_requests_id_seq;
CREATE SEQUENCE IF NOT EXISTS activity_logs_id_seq;
CREATE SEQUENCE IF NOT EXISTS goals_id_seq;

-- ===== 2. 테이블 생성 (BIGSERIAL 대신 BIGINT + DEFAULT 사용) =====

-- branches 테이블
CREATE TABLE IF NOT EXISTS branches (
  id BIGINT NOT NULL DEFAULT nextval('branches_id_seq'::regclass),
  code VARCHAR(255) NOT NULL UNIQUE,
  name VARCHAR(255) NOT NULL,
  manager_name VARCHAR(255),
  phone VARCHAR(255),
  address TEXT,
  status VARCHAR(50) NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'inactive')),
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  CONSTRAINT branches_pkey PRIMARY KEY (id)
);

-- stores 테이블
CREATE TABLE IF NOT EXISTS stores (
  id BIGINT NOT NULL DEFAULT nextval('stores_id_seq'::regclass),
  branch_id BIGINT NOT NULL,
  code VARCHAR(255) NOT NULL UNIQUE,
  name VARCHAR(255) NOT NULL,
  owner_name VARCHAR(255),
  phone VARCHAR(255),
  address TEXT,
  status VARCHAR(50) NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'inactive')),
  opened_at DATE,
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  created_by BIGINT,
  last_sync_at TIMESTAMP,
  sync_status JSON,
  supabase_id VARCHAR(255) UNIQUE,
  metadata JSON,
  CONSTRAINT stores_pkey PRIMARY KEY (id)
);

-- users 테이블
CREATE TABLE IF NOT EXISTS users (
  id BIGINT NOT NULL DEFAULT nextval('users_id_seq'::regclass),
  name VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  email_verified_at TIMESTAMP,
  password VARCHAR(255) NOT NULL,
  remember_token VARCHAR(100),
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  role VARCHAR(50) NOT NULL DEFAULT 'store' CHECK (role IN ('headquarters', 'branch', 'store')),
  branch_id BIGINT,
  store_id BIGINT,
  is_active BOOLEAN NOT NULL DEFAULT true,
  last_login_at TIMESTAMP,
  created_by_user_id VARCHAR(255),
  CONSTRAINT users_pkey PRIMARY KEY (id)
);

-- sales 테이블 (Production과 정확히 동일)
CREATE TABLE IF NOT EXISTS sales (
  id BIGINT NOT NULL DEFAULT nextval('sales_id_seq'::regclass),
  store_id BIGINT NOT NULL,
  branch_id BIGINT NOT NULL,
  sale_date DATE NOT NULL,
  carrier VARCHAR(255) NOT NULL CHECK (carrier IN ('SK', 'KT', 'LG', 'MVNO')),
  activation_type VARCHAR(255) NOT NULL CHECK (activation_type IN ('신규', '기변', 'MNP')),
  model_name VARCHAR(255),
  base_price NUMERIC(12,2) NOT NULL DEFAULT 0,
  verbal1 NUMERIC(12,2) NOT NULL DEFAULT 0,
  verbal2 NUMERIC(12,2) NOT NULL DEFAULT 0,
  grade_amount NUMERIC(12,2) NOT NULL DEFAULT 0,
  additional_amount NUMERIC(12,2) NOT NULL DEFAULT 0,
  rebate_total NUMERIC(12,2) NOT NULL DEFAULT 0,
  cash_activation NUMERIC(12,2) NOT NULL DEFAULT 0,
  usim_fee NUMERIC(12,2) NOT NULL DEFAULT 0,
  new_mnp_discount NUMERIC(12,2) NOT NULL DEFAULT 0,
  deduction NUMERIC(12,2) NOT NULL DEFAULT 0,
  settlement_amount NUMERIC(12,2) NOT NULL DEFAULT 0,
  tax NUMERIC(12,2) NOT NULL DEFAULT 0,
  margin_before_tax NUMERIC(12,2) NOT NULL DEFAULT 0,
  cash_received NUMERIC(12,2) NOT NULL DEFAULT 0,
  payback NUMERIC(12,2) NOT NULL DEFAULT 0,
  margin_after_tax NUMERIC(12,2) NOT NULL DEFAULT 0,
  monthly_fee NUMERIC(12,2) NOT NULL DEFAULT 0,
  phone_number VARCHAR(255),
  salesperson VARCHAR(255),
  memo TEXT,
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  dealer_code VARCHAR(255),
  dealer_name VARCHAR(255),
  serial_number VARCHAR(255),
  customer_name VARCHAR(255),
  customer_birth_date DATE,
  CONSTRAINT sales_pkey PRIMARY KEY (id)
);

-- ===== 3. 외래 키 제약 조건 추가 =====
ALTER TABLE stores ADD CONSTRAINT stores_branch_id_foreign
FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE;

ALTER TABLE users ADD CONSTRAINT users_branch_id_foreign
FOREIGN KEY (branch_id) REFERENCES branches(id);

ALTER TABLE users ADD CONSTRAINT users_store_id_foreign
FOREIGN KEY (store_id) REFERENCES stores(id);

ALTER TABLE sales ADD CONSTRAINT sales_store_id_foreign
FOREIGN KEY (store_id) REFERENCES stores(id);

ALTER TABLE sales ADD CONSTRAINT sales_branch_id_foreign
FOREIGN KEY (branch_id) REFERENCES branches(id);

-- ===== 4. 인덱스 생성 =====
CREATE INDEX IF NOT EXISTS idx_sales_store_date ON sales(store_id, sale_date);
CREATE INDEX IF NOT EXISTS idx_sales_branch_date ON sales(branch_id, sale_date);
CREATE INDEX IF NOT EXISTS idx_sales_dealer_code ON sales(dealer_code);
CREATE INDEX IF NOT EXISTS idx_activity_logs_performed_at ON activity_logs(performed_at);
CREATE INDEX IF NOT EXISTS idx_goals_target ON goals(target_type, target_id);

-- ===== 5. 나머지 Production 테이블들 추가 =====

-- daily_expenses 테이블 (Production과 동일)
CREATE TABLE IF NOT EXISTS daily_expenses (
  id BIGINT NOT NULL DEFAULT nextval('daily_expenses_id_seq'::regclass),
  expense_date DATE NOT NULL,
  dealer_code VARCHAR(255) NOT NULL,
  category VARCHAR(255) NOT NULL,
  description VARCHAR(255),
  amount NUMERIC NOT NULL,
  payment_method VARCHAR(255),
  receipt_number VARCHAR(255),
  approved_by VARCHAR(255),
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  CONSTRAINT daily_expenses_pkey PRIMARY KEY (id)
);

-- fixed_expenses 테이블 (Production과 동일)
CREATE TABLE IF NOT EXISTS fixed_expenses (
  id BIGINT NOT NULL DEFAULT nextval('fixed_expenses_id_seq'::regclass),
  year_month VARCHAR(255) NOT NULL,
  dealer_code VARCHAR(255) NOT NULL,
  expense_type VARCHAR(255) NOT NULL,
  description VARCHAR(255),
  amount NUMERIC NOT NULL,
  due_date DATE,
  payment_date DATE,
  payment_status VARCHAR(255) NOT NULL DEFAULT 'pending' CHECK (payment_status IN ('pending', 'paid', 'overdue')),
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  CONSTRAINT fixed_expenses_pkey PRIMARY KEY (id)
);

-- monthly_settlements 테이블 (Production과 정확히 동일)
CREATE TABLE IF NOT EXISTS monthly_settlements (
  id BIGINT NOT NULL DEFAULT nextval('monthly_settlements_id_seq'::regclass),
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

-- payrolls 테이블 (Production과 동일)
CREATE TABLE IF NOT EXISTS payrolls (
  id BIGINT NOT NULL DEFAULT nextval('payrolls_id_seq'::regclass),
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

-- refunds 테이블 (Production과 동일)
CREATE TABLE IF NOT EXISTS refunds (
  id BIGINT NOT NULL DEFAULT nextval('refunds_id_seq'::regclass),
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

-- dealer_profiles 테이블 (Production과 동일)
CREATE TABLE IF NOT EXISTS dealer_profiles (
  id BIGINT NOT NULL DEFAULT nextval('dealer_profiles_id_seq'::regclass),
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

-- store_requests 테이블 (Production과 동일)
CREATE TABLE IF NOT EXISTS store_requests (
  id BIGINT NOT NULL DEFAULT nextval('store_requests_id_seq'::regclass),
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

-- ===== 6. 시스템 테이블들 (Production에 있는 것들) =====
CREATE TABLE IF NOT EXISTS cache (
  key VARCHAR(255) NOT NULL,
  value TEXT NOT NULL,
  expiration INTEGER NOT NULL,
  CONSTRAINT cache_pkey PRIMARY KEY (key)
);

CREATE TABLE IF NOT EXISTS cache_locks (
  key VARCHAR(255) NOT NULL,
  owner VARCHAR(255) NOT NULL,
  expiration INTEGER NOT NULL,
  CONSTRAINT cache_locks_pkey PRIMARY KEY (key)
);

CREATE TABLE IF NOT EXISTS sessions (
  id VARCHAR(255) NOT NULL,
  user_id BIGINT,
  ip_address VARCHAR(255),
  user_agent TEXT,
  payload TEXT NOT NULL,
  last_activity INTEGER NOT NULL,
  CONSTRAINT sessions_pkey PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS password_reset_tokens (
  email VARCHAR(255) NOT NULL,
  token VARCHAR(255) NOT NULL,
  created_at TIMESTAMP,
  CONSTRAINT password_reset_tokens_pkey PRIMARY KEY (email)
);

CREATE TABLE IF NOT EXISTS failed_jobs (
  id BIGINT NOT NULL DEFAULT nextval('failed_jobs_id_seq'::regclass),
  uuid VARCHAR(255) NOT NULL UNIQUE,
  connection TEXT NOT NULL,
  queue TEXT NOT NULL,
  payload TEXT NOT NULL,
  exception TEXT NOT NULL,
  failed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT failed_jobs_pkey PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS jobs (
  id BIGINT NOT NULL DEFAULT nextval('jobs_id_seq'::regclass),
  queue VARCHAR(255) NOT NULL,
  payload TEXT NOT NULL,
  attempts SMALLINT NOT NULL,
  reserved_at INTEGER,
  available_at INTEGER NOT NULL,
  created_at INTEGER NOT NULL,
  CONSTRAINT jobs_pkey PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS job_batches (
  id VARCHAR(255) NOT NULL,
  name VARCHAR(255) NOT NULL,
  total_jobs INTEGER NOT NULL,
  pending_jobs INTEGER NOT NULL,
  failed_jobs INTEGER NOT NULL,
  failed_job_ids TEXT NOT NULL,
  options TEXT,
  cancelled_at INTEGER,
  created_at INTEGER NOT NULL,
  finished_at INTEGER,
  CONSTRAINT job_batches_pkey PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS migrations (
  id INTEGER NOT NULL DEFAULT nextval('migrations_id_seq'::regclass),
  migration VARCHAR(255) NOT NULL,
  batch INTEGER NOT NULL,
  CONSTRAINT migrations_pkey PRIMARY KEY (id)
);

-- ===== 7. 외래 키 제약 조건 (참조 관계) =====
ALTER TABLE monthly_settlements ADD CONSTRAINT monthly_settlements_confirmed_by_foreign
FOREIGN KEY (confirmed_by) REFERENCES users(id);

ALTER TABLE refunds ADD CONSTRAINT refunds_activation_id_foreign
FOREIGN KEY (activation_id) REFERENCES sales(id);

ALTER TABLE store_requests ADD CONSTRAINT store_requests_requested_by_foreign
FOREIGN KEY (requested_by) REFERENCES users(id);

ALTER TABLE store_requests ADD CONSTRAINT store_requests_branch_id_foreign
FOREIGN KEY (branch_id) REFERENCES branches(id);

-- ===== 8. 완료 확인 =====
SELECT 'All Production tables created successfully!' as message;

SELECT
    table_name,
    '✅ 생성됨' as status
FROM information_schema.tables
WHERE table_schema = 'public'
  AND table_type = 'BASE TABLE'
  AND table_name IN ('branches', 'stores', 'users', 'sales', 'activity_logs', 'goals', 'daily_expenses')
ORDER BY table_name;