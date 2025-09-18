-- 🛡️ 안전한 제약 조건 추가 (이미 존재하는 것은 스킵)
-- Staging Supabase에서 실행: https://supabase.com/dashboard/project/qwafwqxdcfpqqwpmphkm

-- ===== 1. 제약 조건 안전하게 추가 (에러 무시) =====

-- stores 외래 키 (이미 있을 수 있음)
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.table_constraints
        WHERE constraint_name = 'stores_branch_id_foreign'
    ) THEN
        ALTER TABLE stores ADD CONSTRAINT stores_branch_id_foreign
        FOREIGN KEY (branch_id) REFERENCES branches(id);
    END IF;
END $$;

-- users 외래 키들
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.table_constraints
        WHERE constraint_name = 'users_branch_id_foreign'
    ) THEN
        ALTER TABLE users ADD CONSTRAINT users_branch_id_foreign
        FOREIGN KEY (branch_id) REFERENCES branches(id);
    END IF;
END $$;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.table_constraints
        WHERE constraint_name = 'users_store_id_foreign'
    ) THEN
        ALTER TABLE users ADD CONSTRAINT users_store_id_foreign
        FOREIGN KEY (store_id) REFERENCES stores(id);
    END IF;
END $$;

-- sales 외래 키들
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.table_constraints
        WHERE constraint_name = 'sales_store_id_foreign'
    ) THEN
        ALTER TABLE sales ADD CONSTRAINT sales_store_id_foreign
        FOREIGN KEY (store_id) REFERENCES stores(id);
    END IF;
END $$;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.table_constraints
        WHERE constraint_name = 'sales_branch_id_foreign'
    ) THEN
        ALTER TABLE sales ADD CONSTRAINT sales_branch_id_foreign
        FOREIGN KEY (branch_id) REFERENCES branches(id);
    END IF;
END $$;

-- monthly_settlements 외래 키
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.table_constraints
        WHERE constraint_name = 'monthly_settlements_confirmed_by_foreign'
    ) THEN
        ALTER TABLE monthly_settlements ADD CONSTRAINT monthly_settlements_confirmed_by_foreign
        FOREIGN KEY (confirmed_by) REFERENCES users(id);
    END IF;
END $$;

-- refunds 외래 키
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.table_constraints
        WHERE constraint_name = 'refunds_activation_id_foreign'
    ) THEN
        ALTER TABLE refunds ADD CONSTRAINT refunds_activation_id_foreign
        FOREIGN KEY (activation_id) REFERENCES sales(id);
    END IF;
END $$;

-- store_requests 외래 키들
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.table_constraints
        WHERE constraint_name = 'store_requests_requested_by_foreign'
    ) THEN
        ALTER TABLE store_requests ADD CONSTRAINT store_requests_requested_by_foreign
        FOREIGN KEY (requested_by) REFERENCES users(id);
    END IF;
END $$;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.table_constraints
        WHERE constraint_name = 'store_requests_branch_id_foreign'
    ) THEN
        ALTER TABLE store_requests ADD CONSTRAINT store_requests_branch_id_foreign
        FOREIGN KEY (branch_id) REFERENCES branches(id);
    END IF;
END $$;

-- ===== 2. 인덱스 안전하게 추가 =====
CREATE INDEX IF NOT EXISTS idx_sales_store_date ON sales(store_id, sale_date);
CREATE INDEX IF NOT EXISTS idx_sales_branch_date ON sales(branch_id, sale_date);
CREATE INDEX IF NOT EXISTS idx_sales_dealer_code ON sales(dealer_code);
CREATE INDEX IF NOT EXISTS idx_sales_carrier ON sales(carrier);
CREATE INDEX IF NOT EXISTS idx_daily_expenses_date ON daily_expenses(expense_date);
CREATE INDEX IF NOT EXISTS idx_monthly_settlements_year_month ON monthly_settlements(year_month);
CREATE INDEX IF NOT EXISTS idx_activity_logs_performed_at ON activity_logs(performed_at);
CREATE INDEX IF NOT EXISTS idx_goals_target ON goals(target_type, target_id);

-- ===== 3. 완료 확인 =====
SELECT 'Safe constraints and indexes added successfully!' as message;

-- 제약 조건 확인
SELECT
    constraint_name,
    table_name,
    '✅ 적용됨' as status
FROM information_schema.table_constraints
WHERE constraint_type = 'FOREIGN KEY'
  AND table_name IN ('stores', 'users', 'sales', 'monthly_settlements', 'refunds', 'store_requests')
ORDER BY table_name, constraint_name;