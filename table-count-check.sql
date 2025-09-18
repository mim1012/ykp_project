-- 🔍 Production vs Staging 테이블 비교 스크립트
-- 각각의 Supabase SQL Editor에서 실행

-- ===== 테이블 개수 확인 =====
SELECT
    COUNT(*) as total_tables,
    'Production: hekvjnunknzzykuagltr' as environment
FROM information_schema.tables
WHERE table_schema = 'public'
  AND table_type = 'BASE TABLE'
  AND table_name NOT LIKE 'pg_%'
  AND table_name NOT LIKE 'sql_%';

-- ===== 모든 테이블 목록 (알파벳 순) =====
SELECT
    table_name,
    'Production: hekvjnunknzzykuagltr' as environment
FROM information_schema.tables
WHERE table_schema = 'public'
  AND table_type = 'BASE TABLE'
  AND table_name NOT LIKE 'pg_%'
  AND table_name NOT LIKE 'sql_%'
ORDER BY table_name;

-- ===== 각 테이블의 컬럼 수도 확인 =====
SELECT
    table_name,
    COUNT(column_name) as column_count,
    string_agg(column_name, ', ' ORDER BY ordinal_position) as columns
FROM information_schema.columns
WHERE table_schema = 'public'
  AND table_name IN (
    SELECT table_name
    FROM information_schema.tables
    WHERE table_schema = 'public'
      AND table_type = 'BASE TABLE'
      AND table_name NOT LIKE 'pg_%'
  )
GROUP BY table_name
ORDER BY table_name;

-- ===== 핵심 비즈니스 테이블 존재 확인 =====
SELECT
    CASE
        WHEN EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'branches') THEN '✅'
        ELSE '❌'
    END || ' branches' as table_status
UNION ALL
SELECT
    CASE
        WHEN EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'stores') THEN '✅'
        ELSE '❌'
    END || ' stores'
UNION ALL
SELECT
    CASE
        WHEN EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'users') THEN '✅'
        ELSE '❌'
    END || ' users'
UNION ALL
SELECT
    CASE
        WHEN EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'sales') THEN '✅'
        ELSE '❌'
    END || ' sales'
UNION ALL
SELECT
    CASE
        WHEN EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'activity_logs') THEN '✅'
        ELSE '❌'
    END || ' activity_logs'
UNION ALL
SELECT
    CASE
        WHEN EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'goals') THEN '✅'
        ELSE '❌'
    END || ' goals'
UNION ALL
SELECT
    CASE
        WHEN EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'daily_expenses') THEN '✅'
        ELSE '❌'
    END || ' daily_expenses'
UNION ALL
SELECT
    CASE
        WHEN EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'fixed_expenses') THEN '✅'
        ELSE '❌'
    END || ' fixed_expenses'
UNION ALL
SELECT
    CASE
        WHEN EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'monthly_settlements') THEN '✅'
        ELSE '❌'
    END || ' monthly_settlements'
UNION ALL
SELECT
    CASE
        WHEN EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'payrolls') THEN '✅'
        ELSE '❌'
    END || ' payrolls'
UNION ALL
SELECT
    CASE
        WHEN EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'refunds') THEN '✅'
        ELSE '❌'
    END || ' refunds'
UNION ALL
SELECT
    CASE
        WHEN EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'dealer_profiles') THEN '✅'
        ELSE '❌'
    END || ' dealer_profiles'
UNION ALL
SELECT
    CASE
        WHEN EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'store_requests') THEN '✅'
        ELSE '❌'
    END || ' store_requests';