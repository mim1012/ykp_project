-- 🔧 단계별 안전한 테이블 생성 (에러 방지)
-- Staging Supabase: https://supabase.com/dashboard/project/qwafwqxdcfpqqwpmphkm

-- ===== STEP 1: 모든 시퀀스 생성 =====
DO $$
BEGIN
    -- 시퀀스들 생성 (에러 무시)
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
    CREATE SEQUENCE IF NOT EXISTS failed_jobs_id_seq;
    CREATE SEQUENCE IF NOT EXISTS jobs_id_seq;
    CREATE SEQUENCE IF NOT EXISTS migrations_id_seq;
EXCEPTION
    WHEN others THEN
        RAISE NOTICE 'Some sequences may already exist: %', SQLERRM;
END $$;

-- ===== STEP 2: 기본 테이블들 먼저 생성 =====

-- branches (다른 테이블들이 참조하므로 먼저)
CREATE TABLE IF NOT EXISTS branches (
  id BIGINT NOT NULL DEFAULT nextval('branches_id_seq'),
  code VARCHAR(255) NOT NULL,
  name VARCHAR(255) NOT NULL,
  manager_name VARCHAR(255),
  phone VARCHAR(255),
  address TEXT,
  status VARCHAR(50) NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT NOW(),
  updated_at TIMESTAMP DEFAULT NOW(),
  CONSTRAINT branches_pkey PRIMARY KEY (id),
  CONSTRAINT branches_code_unique UNIQUE (code),
  CONSTRAINT branches_status_check CHECK (status IN ('active', 'inactive'))
);

-- stores (branches 참조)
CREATE TABLE IF NOT EXISTS stores (
  id BIGINT NOT NULL DEFAULT nextval('stores_id_seq'),
  branch_id BIGINT NOT NULL,
  code VARCHAR(255) NOT NULL,
  name VARCHAR(255) NOT NULL,
  owner_name VARCHAR(255),
  phone VARCHAR(255),
  address TEXT,
  status VARCHAR(50) NOT NULL DEFAULT 'active',
  opened_at DATE,
  created_at TIMESTAMP DEFAULT NOW(),
  updated_at TIMESTAMP DEFAULT NOW(),
  created_by BIGINT,
  last_sync_at TIMESTAMP,
  sync_status JSON,
  supabase_id VARCHAR(255),
  metadata JSON,
  CONSTRAINT stores_pkey PRIMARY KEY (id),
  CONSTRAINT stores_code_unique UNIQUE (code),
  CONSTRAINT stores_supabase_id_unique UNIQUE (supabase_id),
  CONSTRAINT stores_status_check CHECK (status IN ('active', 'inactive'))
);

-- users (branches, stores 참조)
CREATE TABLE IF NOT EXISTS users (
  id BIGINT NOT NULL DEFAULT nextval('users_id_seq'),
  name VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL,
  email_verified_at TIMESTAMP,
  password VARCHAR(255) NOT NULL,
  remember_token VARCHAR(100),
  created_at TIMESTAMP DEFAULT NOW(),
  updated_at TIMESTAMP DEFAULT NOW(),
  role VARCHAR(50) NOT NULL DEFAULT 'store',
  branch_id BIGINT,
  store_id BIGINT,
  is_active BOOLEAN NOT NULL DEFAULT true,
  last_login_at TIMESTAMP,
  created_by_user_id VARCHAR(255),
  CONSTRAINT users_pkey PRIMARY KEY (id),
  CONSTRAINT users_email_unique UNIQUE (email),
  CONSTRAINT users_role_check CHECK (role IN ('headquarters', 'branch', 'store'))
);

-- ===== STEP 3: 외래 키 제약 조건 안전하게 추가 =====
DO $$
BEGIN
    -- stores → branches
    IF NOT EXISTS (SELECT 1 FROM information_schema.table_constraints WHERE constraint_name = 'stores_branch_id_foreign') THEN
        ALTER TABLE stores ADD CONSTRAINT stores_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES branches(id);
    END IF;

    -- users → branches
    IF NOT EXISTS (SELECT 1 FROM information_schema.table_constraints WHERE constraint_name = 'users_branch_id_foreign') THEN
        ALTER TABLE users ADD CONSTRAINT users_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES branches(id);
    END IF;

    -- users → stores
    IF NOT EXISTS (SELECT 1 FROM information_schema.table_constraints WHERE constraint_name = 'users_store_id_foreign') THEN
        ALTER TABLE users ADD CONSTRAINT users_store_id_foreign FOREIGN KEY (store_id) REFERENCES stores(id);
    END IF;
EXCEPTION
    WHEN others THEN
        RAISE NOTICE 'Some constraints may already exist: %', SQLERRM;
END $$;

-- ===== STEP 4: sales 테이블 (가장 중요) =====
CREATE TABLE IF NOT EXISTS sales (
  id BIGINT NOT NULL DEFAULT nextval('sales_id_seq'),
  store_id BIGINT NOT NULL,
  branch_id BIGINT NOT NULL,
  sale_date DATE NOT NULL,
  carrier VARCHAR(255) NOT NULL,
  activation_type VARCHAR(255) NOT NULL,
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
  created_at TIMESTAMP DEFAULT NOW(),
  updated_at TIMESTAMP DEFAULT NOW(),
  dealer_code VARCHAR(255),
  dealer_name VARCHAR(255),
  serial_number VARCHAR(255),
  customer_name VARCHAR(255),
  customer_birth_date DATE,
  CONSTRAINT sales_pkey PRIMARY KEY (id),
  CONSTRAINT sales_carrier_check CHECK (carrier IN ('SK', 'KT', 'LG', 'MVNO')),
  CONSTRAINT sales_activation_type_check CHECK (activation_type IN ('신규', '기변', 'MNP'))
);

-- sales 외래 키 추가
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.table_constraints WHERE constraint_name = 'sales_store_id_foreign') THEN
        ALTER TABLE sales ADD CONSTRAINT sales_store_id_foreign FOREIGN KEY (store_id) REFERENCES stores(id);
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.table_constraints WHERE constraint_name = 'sales_branch_id_foreign') THEN
        ALTER TABLE sales ADD CONSTRAINT sales_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES branches(id);
    END IF;
EXCEPTION
    WHEN others THEN
        RAISE NOTICE 'Sales constraints may already exist: %', SQLERRM;
END $$;

-- ===== STEP 5: 나머지 테이블들 (간단하게) =====
CREATE TABLE IF NOT EXISTS activity_logs (
  id BIGINT NOT NULL DEFAULT nextval('activity_logs_id_seq'),
  user_id BIGINT NOT NULL,
  activity_type VARCHAR(50) NOT NULL,
  activity_title VARCHAR(255) NOT NULL,
  activity_description TEXT,
  activity_data JSONB,
  target_type VARCHAR(50),
  target_id BIGINT,
  ip_address INET,
  user_agent TEXT,
  performed_at TIMESTAMP NOT NULL DEFAULT NOW(),
  created_at TIMESTAMP DEFAULT NOW(),
  updated_at TIMESTAMP DEFAULT NOW(),
  CONSTRAINT activity_logs_pkey PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS goals (
  id BIGINT NOT NULL DEFAULT nextval('goals_id_seq'),
  target_type VARCHAR(50) NOT NULL,
  target_id BIGINT,
  period_type VARCHAR(50) NOT NULL,
  period_start DATE NOT NULL,
  period_end DATE NOT NULL,
  sales_target NUMERIC(15,2) DEFAULT 0,
  activation_target INTEGER DEFAULT 0,
  margin_target NUMERIC(15,2) DEFAULT 0,
  created_by BIGINT NOT NULL,
  notes TEXT,
  is_active BOOLEAN DEFAULT true,
  created_at TIMESTAMP DEFAULT NOW(),
  updated_at TIMESTAMP DEFAULT NOW(),
  CONSTRAINT goals_pkey PRIMARY KEY (id)
);

-- ===== 6. 최종 확인 =====
SELECT 'Step-by-step table creation completed!' as message;

SELECT
    table_name,
    CASE
        WHEN table_name IN ('branches', 'stores', 'users', 'sales', 'activity_logs', 'goals')
        THEN '✅ 핵심 테이블'
        ELSE '📋 기타 테이블'
    END as status
FROM information_schema.tables
WHERE table_schema = 'public'
  AND table_type = 'BASE TABLE'
  AND table_name NOT LIKE 'pg_%'
ORDER BY table_name;