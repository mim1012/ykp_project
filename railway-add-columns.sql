-- 🚨 Railway PostgreSQL에서 수동 실행할 SQL
-- 누락된 컬럼들을 즉시 추가해서 HTTP 500 에러 해결

-- ===== 1. 누락된 컬럼들 추가 =====
ALTER TABLE public.sales ADD COLUMN IF NOT EXISTS salesperson CHARACTER VARYING(255);
ALTER TABLE public.sales ADD COLUMN IF NOT EXISTS model_name CHARACTER VARYING(255);
ALTER TABLE public.sales ADD COLUMN IF NOT EXISTS phone_number CHARACTER VARYING(255);
ALTER TABLE public.sales ADD COLUMN IF NOT EXISTS memo TEXT;

-- ===== 2. 인덱스 추가 (성능 최적화) =====
CREATE INDEX IF NOT EXISTS idx_sales_salesperson ON public.sales(salesperson);
CREATE INDEX IF NOT EXISTS idx_sales_model_name ON public.sales(model_name);
CREATE INDEX IF NOT EXISTS idx_sales_phone ON public.sales(phone_number);

-- ===== 3. 확인 쿼리 =====
SELECT
    column_name,
    data_type,
    is_nullable,
    '✅ 추가됨' as status
FROM information_schema.columns
WHERE table_name = 'sales'
  AND column_name IN ('salesperson', 'model_name', 'phone_number', 'memo')
ORDER BY column_name;

-- ===== 4. 전체 sales 테이블 컬럼 확인 =====
SELECT column_name, data_type
FROM information_schema.columns
WHERE table_name = 'sales'
ORDER BY ordinal_position;