-- YKP Dashboard Supabase 안전 스키마 (충돌 방지)

-- 기존 테이블이 있다면 드롭 (주의: 데이터 손실 가능)
-- DROP TABLE IF EXISTS sales CASCADE;
-- DROP TABLE IF EXISTS store_requests CASCADE; 
-- DROP TABLE IF EXISTS monthly_settlements CASCADE;
-- DROP TABLE IF EXISTS refunds CASCADE;
-- DROP TABLE IF EXISTS payrolls CASCADE;
-- DROP TABLE IF EXISTS fixed_expenses CASCADE;
-- DROP TABLE IF EXISTS daily_expenses CASCADE;
-- DROP TABLE IF EXISTS dealer_profiles CASCADE;
-- DROP TABLE IF EXISTS stores CASCADE;
-- DROP TABLE IF EXISTS users CASCADE;
-- DROP TABLE IF EXISTS branches CASCADE;

-- 1. 지사 테이블
CREATE TABLE branches (
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

-- 2. 매장 테이블  
CREATE TABLE stores (
    id BIGSERIAL PRIMARY KEY,
    branch_id BIGINT NOT NULL REFERENCES branches(id),
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

-- 3. 사용자 테이블
CREATE TABLE users (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(50) NOT NULL DEFAULT 'store',
    branch_id BIGINT REFERENCES branches(id),
    store_id BIGINT REFERENCES stores(id),
    is_active BOOLEAN DEFAULT true,
    remember_token VARCHAR(100),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- 4. 판매 데이터 테이블 (개통표)
CREATE TABLE sales (
    id BIGSERIAL PRIMARY KEY,
    store_id BIGINT NOT NULL REFERENCES stores(id),
    branch_id BIGINT NOT NULL REFERENCES branches(id),
    sale_date DATE NOT NULL,
    carrier VARCHAR(255),
    activation_type VARCHAR(255),
    model_name VARCHAR(255),
    base_price DECIMAL(10,2) DEFAULT 0,
    settlement_amount DECIMAL(10,2) DEFAULT 0,
    margin_after_tax DECIMAL(10,2) DEFAULT 0,
    phone_number VARCHAR(255),
    salesperson VARCHAR(255),
    memo TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- 필수 인덱스만
CREATE INDEX sales_store_date_idx ON sales(store_id, sale_date);
CREATE INDEX sales_branch_date_idx ON sales(branch_id, sale_date);
CREATE INDEX users_role_idx ON users(role);

-- RLS 활성화 (핵심 테이블만)
ALTER TABLE sales ENABLE ROW LEVEL SECURITY;
ALTER TABLE stores ENABLE ROW LEVEL SECURITY;

-- 기본 데이터 (순서 중요!)

-- 1. 지사 데이터 먼저
INSERT INTO branches (code, name, manager_name) VALUES
('BR001', '서울지사', '김지사장'),
('BR002', '경기지사', '이지사장'),
('BR003', '부산지사', '박지사장');

-- 2. 기본 매장 데이터 (테스트용)
INSERT INTO stores (branch_id, code, name, owner_name, phone) VALUES
(1, 'BR001-001', '서울 1호점', '김사장', '010-1111-1111'),
(1, 'BR001-002', '서울 2호점', '이사장', '010-2222-2222'),
(2, 'BR002-001', '경기 1호점', '박사장', '010-3333-3333');

-- 3. 테스트 계정 (외래키 참조 가능)
INSERT INTO users (name, email, password, role, branch_id, store_id) VALUES
('본사 관리자', 'hq@ykp.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'headquarters', NULL, NULL),
('지사 관리자', 'branch@ykp.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'branch', 1, NULL),
('매장 직원', 'store@ykp.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'store', 1, 1);