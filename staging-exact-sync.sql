-- 🎯 Staging을 Production과 정확히 동일하게 맞추는 SQL
-- 실행 위치: https://supabase.com/dashboard/project/qwafwqxdcfpqqwpmphkm (staging)
-- SQL Editor에서 전체 복사 → 실행

-- ===== 1. 기본 테이블들 (Production과 동일하게) =====

-- branches 테이블 (정확한 구조)
CREATE TABLE IF NOT EXISTS branches (
  id bigint NOT NULL DEFAULT nextval('branches_id_seq'::regclass),
  code character varying NOT NULL UNIQUE,
  name character varying NOT NULL,
  manager_name character varying,
  phone character varying,
  address text,
  status character varying NOT NULL DEFAULT 'active'::character varying CHECK (status::text = ANY (ARRAY['active'::character varying, 'inactive'::character varying]::text[])),
  created_at timestamp without time zone,
  updated_at timestamp without time zone,
  CONSTRAINT branches_pkey PRIMARY KEY (id)
);

-- stores 테이블 (Production과 정확히 동일)
CREATE TABLE IF NOT EXISTS stores (
  id bigint NOT NULL DEFAULT nextval('stores_id_seq'::regclass),
  branch_id bigint NOT NULL,
  code character varying NOT NULL UNIQUE,
  name character varying NOT NULL,
  owner_name character varying,
  phone character varying,
  address text,
  status character varying NOT NULL DEFAULT 'active'::character varying CHECK (status::text = ANY (ARRAY['active'::character varying, 'inactive'::character varying]::text[])),
  opened_at date,
  created_at timestamp without time zone,
  updated_at timestamp without time zone,
  created_by bigint,
  last_sync_at timestamp without time zone,
  sync_status json,
  supabase_id character varying UNIQUE,
  metadata json,
  CONSTRAINT stores_pkey PRIMARY KEY (id),
  CONSTRAINT stores_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id)
);

-- users 테이블 (Production과 정확히 동일)
CREATE TABLE IF NOT EXISTS users (
  id bigint NOT NULL DEFAULT nextval('users_id_seq'::regclass),
  name character varying NOT NULL,
  email character varying NOT NULL UNIQUE,
  email_verified_at timestamp without time zone,
  password character varying NOT NULL,
  remember_token character varying,
  created_at timestamp without time zone,
  updated_at timestamp without time zone,
  role character varying NOT NULL DEFAULT 'store'::character varying CHECK (role::text = ANY (ARRAY['headquarters'::character varying, 'branch'::character varying, 'store'::character varying]::text[])),
  branch_id bigint,
  store_id bigint,
  is_active boolean NOT NULL DEFAULT true,
  last_login_at timestamp without time zone,
  created_by_user_id character varying,
  CONSTRAINT users_pkey PRIMARY KEY (id),
  CONSTRAINT users_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id),
  CONSTRAINT users_store_id_foreign FOREIGN KEY (store_id) REFERENCES public.stores(id)
);

-- ===== 2. Sales 테이블 (Production과 정확히 동일) =====
CREATE TABLE IF NOT EXISTS sales (
  id bigint NOT NULL DEFAULT nextval('sales_id_seq'::regclass),
  store_id bigint NOT NULL,
  branch_id bigint NOT NULL,
  sale_date date NOT NULL,
  carrier character varying NOT NULL CHECK (carrier::text = ANY (ARRAY['SK'::character varying, 'KT'::character varying, 'LG'::character varying, 'MVNO'::character varying]::text[])),
  activation_type character varying NOT NULL CHECK (activation_type::text = ANY (ARRAY['신규'::character varying, '기변'::character varying, 'MNP'::character varying]::text[])),
  model_name character varying,
  base_price numeric NOT NULL DEFAULT '0'::numeric,
  verbal1 numeric NOT NULL DEFAULT '0'::numeric,
  verbal2 numeric NOT NULL DEFAULT '0'::numeric,
  grade_amount numeric NOT NULL DEFAULT '0'::numeric,
  additional_amount numeric NOT NULL DEFAULT '0'::numeric,
  rebate_total numeric NOT NULL DEFAULT '0'::numeric,
  cash_activation numeric NOT NULL DEFAULT '0'::numeric,
  usim_fee numeric NOT NULL DEFAULT '0'::numeric,
  new_mnp_discount numeric NOT NULL DEFAULT '0'::numeric,
  deduction numeric NOT NULL DEFAULT '0'::numeric,
  settlement_amount numeric NOT NULL DEFAULT '0'::numeric,
  tax numeric NOT NULL DEFAULT '0'::numeric,
  margin_before_tax numeric NOT NULL DEFAULT '0'::numeric,
  cash_received numeric NOT NULL DEFAULT '0'::numeric,
  payback numeric NOT NULL DEFAULT '0'::numeric,
  margin_after_tax numeric NOT NULL DEFAULT '0'::numeric,
  monthly_fee numeric NOT NULL DEFAULT '0'::numeric,
  phone_number character varying,
  salesperson character varying,
  memo text,
  created_at timestamp without time zone,
  updated_at timestamp without time zone,
  dealer_code character varying,
  dealer_name character varying,
  serial_number character varying,
  customer_name character varying,
  customer_birth_date date,
  CONSTRAINT sales_pkey PRIMARY KEY (id),
  CONSTRAINT sales_store_id_foreign FOREIGN KEY (store_id) REFERENCES public.stores(id),
  CONSTRAINT sales_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id)
);

-- ===== 3. 지출 관리 테이블들 =====
CREATE TABLE IF NOT EXISTS daily_expenses (
  id bigint NOT NULL DEFAULT nextval('daily_expenses_id_seq'::regclass),
  expense_date date NOT NULL,
  dealer_code character varying NOT NULL,
  category character varying NOT NULL,
  description character varying,
  amount numeric NOT NULL,
  payment_method character varying,
  receipt_number character varying,
  approved_by character varying,
  created_at timestamp without time zone,
  updated_at timestamp without time zone,
  CONSTRAINT daily_expenses_pkey PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS fixed_expenses (
  id bigint NOT NULL DEFAULT nextval('fixed_expenses_id_seq'::regclass),
  year_month character varying NOT NULL,
  dealer_code character varying NOT NULL,
  expense_type character varying NOT NULL,
  description character varying,
  amount numeric NOT NULL,
  due_date date,
  payment_date date,
  payment_status character varying NOT NULL DEFAULT 'pending'::character varying CHECK (payment_status::text = ANY (ARRAY['pending'::character varying, 'paid'::character varying, 'overdue'::character varying]::text[])),
  created_at timestamp without time zone,
  updated_at timestamp without time zone,
  CONSTRAINT fixed_expenses_pkey PRIMARY KEY (id)
);

-- ===== 4. 정산 및 급여 테이블들 =====
CREATE TABLE IF NOT EXISTS monthly_settlements (
  id bigint NOT NULL DEFAULT nextval('monthly_settlements_id_seq'::regclass),
  year_month character varying NOT NULL,
  dealer_code character varying NOT NULL,
  settlement_status character varying NOT NULL DEFAULT 'draft'::character varying CHECK (settlement_status::text = ANY (ARRAY['draft'::character varying, 'confirmed'::character varying, 'closed'::character varying]::text[])),
  total_sales_amount numeric NOT NULL DEFAULT '0'::numeric,
  total_sales_count integer NOT NULL DEFAULT 0,
  average_margin_rate numeric NOT NULL DEFAULT '0'::numeric,
  total_vat_amount numeric NOT NULL DEFAULT '0'::numeric,
  total_daily_expenses numeric NOT NULL DEFAULT '0'::numeric,
  total_fixed_expenses numeric NOT NULL DEFAULT '0'::numeric,
  total_payroll_amount numeric NOT NULL DEFAULT '0'::numeric,
  total_refund_amount numeric NOT NULL DEFAULT '0'::numeric,
  total_expense_amount numeric NOT NULL DEFAULT '0'::numeric,
  gross_profit numeric NOT NULL DEFAULT '0'::numeric,
  net_profit numeric NOT NULL DEFAULT '0'::numeric,
  profit_rate numeric NOT NULL DEFAULT '0'::numeric,
  prev_month_comparison numeric NOT NULL DEFAULT '0'::numeric,
  growth_rate numeric NOT NULL DEFAULT '0'::numeric,
  calculated_at timestamp without time zone,
  confirmed_at timestamp without time zone,
  confirmed_by bigint,
  notes text,
  created_at timestamp without time zone,
  updated_at timestamp without time zone,
  CONSTRAINT monthly_settlements_pkey PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS payrolls (
  id bigint NOT NULL DEFAULT nextval('payrolls_id_seq'::regclass),
  year_month character varying NOT NULL,
  dealer_code character varying NOT NULL,
  employee_id character varying NOT NULL,
  employee_name character varying NOT NULL,
  position character varying,
  base_salary numeric NOT NULL DEFAULT '0'::numeric,
  incentive_amount numeric NOT NULL DEFAULT '0'::numeric,
  bonus_amount numeric NOT NULL DEFAULT '0'::numeric,
  deduction_amount numeric NOT NULL DEFAULT '0'::numeric,
  total_salary numeric NOT NULL,
  payment_date date,
  payment_status character varying NOT NULL DEFAULT 'pending'::character varying CHECK (payment_status::text = ANY (ARRAY['pending'::character varying, 'paid'::character varying]::text[])),
  memo text,
  created_at timestamp without time zone,
  updated_at timestamp without time zone,
  CONSTRAINT payrolls_pkey PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS refunds (
  id bigint NOT NULL DEFAULT nextval('refunds_id_seq'::regclass),
  refund_date date NOT NULL,
  dealer_code character varying NOT NULL,
  activation_id bigint,
  customer_name character varying NOT NULL,
  customer_phone character varying NOT NULL,
  refund_reason character varying NOT NULL,
  refund_type character varying NOT NULL,
  original_amount numeric NOT NULL,
  refund_amount numeric NOT NULL,
  penalty_amount numeric NOT NULL DEFAULT '0'::numeric,
  refund_method character varying,
  processed_by character varying,
  memo text,
  created_at timestamp without time zone,
  updated_at timestamp without time zone,
  CONSTRAINT refunds_pkey PRIMARY KEY (id),
  CONSTRAINT refunds_activation_id_foreign FOREIGN KEY (activation_id) REFERENCES public.sales(id)
);

-- ===== 5. 대리점 프로필 테이블 =====
CREATE TABLE IF NOT EXISTS dealer_profiles (
  id bigint NOT NULL DEFAULT nextval('dealer_profiles_id_seq'::regclass),
  dealer_code character varying NOT NULL UNIQUE,
  dealer_name character varying NOT NULL,
  contact_person character varying,
  phone character varying,
  address text,
  default_sim_fee numeric NOT NULL DEFAULT '0'::numeric,
  default_mnp_discount numeric NOT NULL DEFAULT '800'::numeric,
  tax_rate numeric NOT NULL DEFAULT 0.1,
  default_payback_rate numeric NOT NULL DEFAULT '0'::numeric,
  auto_calculate_tax boolean NOT NULL DEFAULT true,
  include_sim_fee_in_settlement boolean NOT NULL DEFAULT true,
  custom_calculation_rules json,
  status character varying NOT NULL DEFAULT 'active'::character varying CHECK (status::text = ANY (ARRAY['active'::character varying, 'inactive'::character varying, 'suspended'::character varying]::text[])),
  activated_at timestamp without time zone,
  deactivated_at timestamp without time zone,
  created_at timestamp without time zone,
  updated_at timestamp without time zone,
  CONSTRAINT dealer_profiles_pkey PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS store_requests (
  id bigint NOT NULL DEFAULT nextval('store_requests_id_seq'::regclass),
  requested_by bigint NOT NULL,
  branch_id bigint NOT NULL,
  store_name character varying NOT NULL,
  store_code character varying NOT NULL,
  owner_name character varying,
  phone character varying,
  address text,
  business_license character varying,
  request_reason text,
  status character varying NOT NULL DEFAULT 'pending'::character varying CHECK (status::text = ANY (ARRAY['pending'::character varying, 'approved'::character varying, 'rejected'::character varying]::text[])),
  reviewed_by bigint,
  reviewed_at timestamp without time zone,
  review_comment text,
  created_at timestamp without time zone,
  updated_at timestamp without time zone,
  CONSTRAINT store_requests_pkey PRIMARY KEY (id),
  CONSTRAINT store_requests_requested_by_foreign FOREIGN KEY (requested_by) REFERENCES public.users(id),
  CONSTRAINT store_requests_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id),
  CONSTRAINT store_requests_reviewed_by_foreign FOREIGN KEY (reviewed_by) REFERENCES public.users(id)
);

-- ===== 6. 시스템 테이블들 =====
CREATE TABLE IF NOT EXISTS cache (
  key character varying NOT NULL,
  value text NOT NULL,
  expiration integer NOT NULL,
  CONSTRAINT cache_pkey PRIMARY KEY (key)
);

CREATE TABLE IF NOT EXISTS cache_locks (
  key character varying NOT NULL,
  owner character varying NOT NULL,
  expiration integer NOT NULL,
  CONSTRAINT cache_locks_pkey PRIMARY KEY (key)
);

CREATE TABLE IF NOT EXISTS sessions (
  id character varying NOT NULL,
  user_id bigint,
  ip_address character varying,
  user_agent text,
  payload text NOT NULL,
  last_activity integer NOT NULL,
  CONSTRAINT sessions_pkey PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS password_reset_tokens (
  email character varying NOT NULL,
  token character varying NOT NULL,
  created_at timestamp without time zone,
  CONSTRAINT password_reset_tokens_pkey PRIMARY KEY (email)
);

CREATE TABLE IF NOT EXISTS failed_jobs (
  id bigint NOT NULL DEFAULT nextval('failed_jobs_id_seq'::regclass),
  uuid character varying NOT NULL UNIQUE,
  connection text NOT NULL,
  queue text NOT NULL,
  payload text NOT NULL,
  exception text NOT NULL,
  failed_at timestamp without time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT failed_jobs_pkey PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS jobs (
  id bigint NOT NULL DEFAULT nextval('jobs_id_seq'::regclass),
  queue character varying NOT NULL,
  payload text NOT NULL,
  attempts smallint NOT NULL,
  reserved_at integer,
  available_at integer NOT NULL,
  created_at integer NOT NULL,
  CONSTRAINT jobs_pkey PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS job_batches (
  id character varying NOT NULL,
  name character varying NOT NULL,
  total_jobs integer NOT NULL,
  pending_jobs integer NOT NULL,
  failed_jobs integer NOT NULL,
  failed_job_ids text NOT NULL,
  options text,
  cancelled_at integer,
  created_at integer NOT NULL,
  finished_at integer,
  CONSTRAINT job_batches_pkey PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS migrations (
  id integer NOT NULL DEFAULT nextval('migrations_id_seq'::regclass),
  migration character varying NOT NULL,
  batch integer NOT NULL,
  CONSTRAINT migrations_pkey PRIMARY KEY (id)
);

-- ===== 7. 새로운 테이블들 (아직 Production에 없다면 추가) =====
CREATE TABLE IF NOT EXISTS activity_logs (
  id bigint NOT NULL DEFAULT nextval('activity_logs_id_seq'::regclass),
  user_id bigint NOT NULL,
  activity_type character varying(50) NOT NULL,
  activity_title character varying(255) NOT NULL,
  activity_description text,
  activity_data jsonb,
  target_type character varying(50),
  target_id bigint,
  ip_address inet,
  user_agent text,
  performed_at timestamp without time zone NOT NULL,
  created_at timestamp without time zone DEFAULT NOW(),
  updated_at timestamp without time zone DEFAULT NOW(),
  CONSTRAINT activity_logs_pkey PRIMARY KEY (id),
  CONSTRAINT activity_logs_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS goals (
  id bigint NOT NULL DEFAULT nextval('goals_id_seq'::regclass),
  target_type character varying(50) NOT NULL CHECK (target_type IN ('system', 'branch', 'store')),
  target_id bigint,
  period_type character varying(50) NOT NULL,
  period_start date NOT NULL,
  period_end date NOT NULL,
  sales_target numeric(15,2) DEFAULT 0,
  activation_target integer DEFAULT 0,
  margin_target numeric(15,2) DEFAULT 0,
  created_by bigint NOT NULL,
  notes text,
  is_active boolean DEFAULT true,
  created_at timestamp without time zone DEFAULT NOW(),
  updated_at timestamp without time zone DEFAULT NOW(),
  CONSTRAINT goals_pkey PRIMARY KEY (id),
  CONSTRAINT goals_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE CASCADE
);

-- ===== 8. 완료 확인 쿼리 =====
SELECT
    COUNT(*) as total_tables,
    string_agg(table_name, ', ' ORDER BY table_name) as table_list
FROM information_schema.tables
WHERE table_schema = 'public'
  AND table_type = 'BASE TABLE'
  AND table_name NOT LIKE 'pg_%'
  AND table_name NOT LIKE 'sql_%';

-- 개별 테이블 확인
SELECT 'branches' as table_name, CASE WHEN EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'branches') THEN '✅ 존재' ELSE '❌ 없음' END as status
UNION ALL SELECT 'stores', CASE WHEN EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'stores') THEN '✅ 존재' ELSE '❌ 없음' END
UNION ALL SELECT 'users', CASE WHEN EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'users') THEN '✅ 존재' ELSE '❌ 없음' END
UNION ALL SELECT 'sales', CASE WHEN EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'sales') THEN '✅ 존재' ELSE '❌ 없음' END
UNION ALL SELECT 'activity_logs', CASE WHEN EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'activity_logs') THEN '✅ 존재' ELSE '❌ 없음' END
UNION ALL SELECT 'goals', CASE WHEN EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'goals') THEN '✅ 존재' ELSE '❌ 없음' END
UNION ALL SELECT 'daily_expenses', CASE WHEN EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'daily_expenses') THEN '✅ 존재' ELSE '❌ 없음' END
UNION ALL SELECT 'monthly_settlements', CASE WHEN EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'monthly_settlements') THEN '✅ 존재' ELSE '❌ 없음' END;