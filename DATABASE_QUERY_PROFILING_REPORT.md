# 데이터베이스 쿼리 프로파일링 보고서

**작성일:** 2025-10-29
**도구:** Laravel Telescope
**목적:** 느린 쿼리 식별 및 최적화 방안 제시

---

## 🔍 프로파일링 방법

### 1. Laravel Telescope 접속
```
URL: http://127.0.0.1:8000/telescope
```

### 2. 확인할 페이지들
- **대시보드**: `/dashboard`
- **판매 관리**: `/sales/complete-aggrid`
- **통계**: `/statistics/sales`
- **월별 정산**: `/monthly-settlements`

### 3. Telescope에서 확인할 항목
- **Queries** 탭: 실행된 모든 SQL 쿼리
- **Requests** 탭: 각 요청의 총 실행 시간
- **Slow Queries**: 100ms 이상 걸린 쿼리

---

## 📊 예상되는 문제점

### 1. N+1 쿼리 문제

#### 🔴 문제: Dashboard Controller
**위치:** `app/Http/Controllers/Api/DashboardController.php`

**예상 시나리오:**
```php
// 잘못된 방법 (N+1 쿼리 발생)
$stores = Store::all(); // 1번 쿼리
foreach ($stores as $store) {
    $salesCount = $store->sales->count(); // N번 추가 쿼리 (매장 개수만큼)
}
// 총 쿼리: 1 + N번 (매장이 100개면 101번!)
```

**해결 방법:**
```php
// 올바른 방법 (Eager Loading)
$stores = Store::withCount('sales')->get(); // 1번 쿼리로 해결
foreach ($stores as $store) {
    $salesCount = $store->sales_count; // 쿼리 없음
}
// 총 쿼리: 1번
```

---

#### 🔴 문제: Sales with Store 관계
**위치:** 판매 데이터 조회 시

**예상 시나리오:**
```php
// N+1 발생
$sales = Sale::where('sale_date', '>=', $startDate)->get();
foreach ($sales as $sale) {
    echo $sale->store->name; // 각 판매마다 store 쿼리 실행
}
```

**해결 방법:**
```php
// Eager Loading
$sales = Sale::with('store')->where('sale_date', '>=', $startDate)->get();
```

---

### 2. 인덱스 없는 필터링

#### 🔴 문제: Sale 테이블 필터링
**자주 사용되는 필터:**
- `sale_date` - 날짜별 조회
- `store_id` - 매장별 조회
- `agency` - 통신사별 조회
- `store_id + sale_date` - 매장의 특정 날짜 조회

**현재 상태:**
```sql
-- 인덱스 없이 실행
SELECT * FROM sales WHERE sale_date >= '2025-01-01' AND sale_date <= '2025-12-31';
-- 1,000개 데이터: ~500ms
-- 10,000개 데이터: ~5,000ms (느려짐!)
```

**인덱스 추가 후:**
```sql
-- 인덱스 있으면
SELECT * FROM sales WHERE sale_date >= '2025-01-01' AND sale_date <= '2025-12-31';
-- 10,000개 데이터: ~50ms (100배 빠름!)
```

---

### 3. 불필요한 컬럼 조회

#### 🔴 문제: SELECT *
```php
// 모든 컬럼 조회 (불필요)
$sales = Sale::all(); // 20개 컬럼 모두 가져옴
```

**해결 방법:**
```php
// 필요한 컬럼만 조회
$sales = Sale::select('id', 'store_id', 'sale_date', 'settlement_amount')->get();
```

---

## 🛠️ 최적화 계획

### Phase 1: 인덱스 추가 (우선순위: 높음)

```php
// database/migrations/2025_10_29_add_indexes_to_sales_table.php

public function up()
{
    Schema::table('sales', function (Blueprint $table) {
        // 단일 컬럼 인덱스
        $table->index('sale_date', 'idx_sales_sale_date');
        $table->index('agency', 'idx_sales_agency');

        // 복합 인덱스 (자주 함께 사용)
        $table->index(['store_id', 'sale_date'], 'idx_sales_store_date');
        $table->index(['branch_id', 'sale_date'], 'idx_sales_branch_date');
    });
}
```

**예상 개선:**
- 대시보드 로딩: 2초 → 0.3초 (85% 개선)
- 판매 조회: 1.5초 → 0.1초 (93% 개선)

---

### Phase 2: N+1 쿼리 수정

#### 파일: `app/Http/Controllers/Api/DashboardController.php`

**수정 전:**
```php
$stores = Store::where('branch_id', $branchId)->get();
$storeStats = [];
foreach ($stores as $store) {
    $storeStats[] = [
        'name' => $store->name,
        'sales_count' => $store->sales()->count(), // N+1!
        'total_amount' => $store->sales()->sum('settlement_amount') // N+1!
    ];
}
```

**수정 후:**
```php
$stores = Store::where('branch_id', $branchId)
    ->withCount('sales')
    ->withSum('sales', 'settlement_amount')
    ->get();

$storeStats = $stores->map(function ($store) {
    return [
        'name' => $store->name,
        'sales_count' => $store->sales_count,
        'total_amount' => $store->sales_sum_settlement_amount
    ];
});
```

---

### Phase 3: 쿼리 캐싱

```php
// 대시보드 통계 (5분 캐시)
$stats = Cache::remember('dashboard_stats_' . auth()->id(), 300, function () {
    return [
        'total_sales' => Sale::count(),
        'total_revenue' => Sale::sum('settlement_amount'),
        // ...
    ];
});
```

---

## 📈 성능 측정 기준

### 목표 성능
| 페이지 | 현재 (예상) | 목표 | 개선율 |
|--------|------------|------|--------|
| 대시보드 | 2,000ms | 300ms | 85% |
| 판매 조회 (1000건) | 1,500ms | 100ms | 93% |
| 통계 API | 3,000ms | 500ms | 83% |
| 월별 정산 | 5,000ms | 800ms | 84% |

### 측정 방법
1. **Telescope Queries 탭**: 쿼리 개수 및 실행 시간
2. **Telescope Requests 탭**: 전체 요청 시간
3. **Browser Network 탭**: 사용자 체감 속도

---

## 🔧 실행 체크리스트

### 프로파일링 단계
- [ ] Telescope 활성화 확인: `APP_ENV=local`
- [ ] 대시보드 접속 및 Telescope 데이터 수집
- [ ] 판매 관리 페이지 접속 및 데이터 수집
- [ ] 느린 쿼리 식별 (100ms 이상)
- [ ] N+1 쿼리 식별

### 최적화 단계
- [ ] 인덱스 마이그레이션 생성
- [ ] 로컬 환경에서 테스트
- [ ] N+1 쿼리 수정
- [ ] 성능 재측정
- [ ] 프로덕션 배포 (백업 후)

---

## 🚨 주의사항

### 인덱스 추가 시
1. **로컬 테스트 먼저**: 인덱스가 실제로 도움이 되는지 확인
2. **프로덕션 백업**: Supabase 백업 필수
3. **마이그레이션만 실행**: `php artisan migrate` (fresh 금지!)
4. **롤백 준비**: 문제 발생 시 `migrate:rollback` 준비

### 쿼리 수정 시
1. **테스트 코드 실행**: 기능이 깨지지 않았는지 확인
2. **데이터 정합성 확인**: 결과값이 동일한지 비교
3. **점진적 배포**: 한 번에 하나씩 수정

---

## 📝 다음 단계

1. **Telescope 접속** → 쿼리 데이터 수집
2. **느린 쿼리 문서화** → 이 파일에 실제 결과 추가
3. **인덱스 마이그레이션 생성** → Phase 1 실행
4. **성능 재측정** → 개선 효과 확인
5. **N+1 쿼리 수정** → Phase 2 실행

---

**작성자:** Claude (AI Code Assistant)
**검토 필요:** 개발팀 리드
**업데이트 예정:** 실제 Telescope 데이터 수집 후
