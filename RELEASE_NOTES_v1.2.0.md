# YKP ERP Dashboard - Release Notes v1.2.0

**릴리즈 일시**: 2025-09-17
**브랜치**: staging
**태그**: v1.2.0
**유형**: 긴급 배포 (Emergency Release)

---

## 🚨 긴급 수정사항

### 대시보드 데이터 바인딩 문제 완전 해결
지사 및 매장 계정에서 대시보드 데이터가 0으로 표시되던 치명적 문제를 완전히 해결했습니다.

**수정된 문제:**
- ❌ 지사 계정: 관리 매장수 0개, 매출 0원 표시
- ❌ 매장 계정: 오늘 개통 0건, 매출 0원 표시
- ❌ 통계 페이지: 권한별 데이터 필터링 미적용

**해결 결과:**
- ✅ 지사 계정: 실제 매장 수, 실제 매출 데이터 표시
- ✅ 매장 계정: 실제 개통 건수, 실제 매출 데이터 표시
- ✅ 통계 페이지: 권한별 데이터 필터링 완전 적용

---

## 🔧 주요 기술적 개선사항

### 1. API 응답 구조 표준화
**파일**: `app/Http/Controllers/Api/DashboardController.php`

```json
// 표준화된 API 응답 구조
{
  "success": true,
  "data": {
    "stores": {"total": 3, "active": 2},
    "users": {"total": 5, "active": 4},
    "this_month_sales": 99200,
    "today_sales": 99200,
    "today_activations": 1,
    "monthly_target": 50000000,
    "achievement_rate": 0.2
  }
}
```

### 2. 프론트엔드 키 매핑 통일
**파일**: `resources/views/premium-dashboard.blade.php`

**지사 계정 수정:**
```javascript
// ❌ 기존 (잘못된 키)
const storeCount = data.branch?.store_count
const sales = data.month?.sales

// ✅ 수정 (올바른 키)
const storeCount = data.stores?.total
const sales = data.this_month_sales
```

**매장 계정 수정:**
```javascript
// ❌ 기존 (잘못된 키)
const activations = data.today?.activations
const sales = data.month?.sales

// ✅ 수정 (올바른 키)
const activations = data.today_activations
const sales = data.this_month_sales
```

### 3. PostgreSQL 호환성 완전 개선
**수정된 파일들:**
- `app/Http/Controllers/Api/DashboardController.php`
- `app/Http/Controllers/Api/BranchController.php`
- `app/Http/Controllers/Api/StoreController.php`
- `routes/web.php`

**Boolean 타입 처리 개선:**
```php
// ❌ PostgreSQL 오류 발생
->where('is_active', 1)
->update(['is_active' => false])

// ✅ PostgreSQL 호환
if (config('database.default') === 'pgsql') {
    ->whereRaw('is_active = true')
    ->update(['is_active' => \DB::raw('false')])
} else {
    ->where('is_active', true)
    ->update(['is_active' => false])
}
```

### 4. 통계 API 권한 필터링 강화
**파일**: `routes/web.php`

**권한별 데이터 접근 제어:**
```php
// 지사 계정: 소속 매장들만 조회
if ($user->role === 'branch') {
    $branchStoreIds = Store::where('branch_id', $user->branch_id)->pluck('id');
    $query->whereIn('store_id', $branchStoreIds);
}

// 매장 계정: 자신의 매장만 조회
elseif ($user->role === 'store') {
    $query->where('store_id', $user->store_id);
}
```

---

## 📊 권한별 기능 확인사항

### 본사 계정 (headquarters)
- ✅ 전체 매장 수 표시
- ✅ 전체 매출 데이터 표시
- ✅ 전체 통계 접근 가능
- ✅ 부가세 포함 매출 계산 (× 1.1)

### 지사 계정 (branch)
- ✅ 소속 매장 수만 표시
- ✅ 소속 매장들의 매출만 표시
- ✅ 지사별 통계만 접근 가능
- ✅ 목표 달성률 정확 계산

### 매장 계정 (store)
- ✅ 자신의 매장 데이터만 표시
- ✅ 오늘 개통 건수 정확 표시
- ✅ 매장 매출 데이터 정확 표시
- ✅ UI 간소화 (불필요한 버튼 제거)

---

## 🗂 주요 커밋 히스토리

```
7739c99a - fix: 프론트엔드-백엔드 API 응답 키 매핑 완전 통일
da65e1b4 - fix: 지사 통계 권한 필터링 및 매장 계정 UI 정리
794d817c - fix: PostgreSQL boolean 호환성 - 전체 codebase is_active 처리 개선
f7d554de - fix: PostgreSQL 호환성 - users 테이블 컬럼명 수정
1f069d47 - fix: 대시보드 API 라우트 통합 및 매장 계정 UI 개선
4175fdae - fix: 대시보드 API 응답 구조 파싱 오류 수정
```

---

## 🚀 배포 체크리스트

### 배포 전 확인사항
- [x] PostgreSQL 연결 테스트
- [x] 권한별 로그인 테스트
- [x] 대시보드 데이터 표시 확인
- [x] 통계 페이지 권한 확인
- [x] API 응답 구조 검증

### 배포 후 모니터링 항목
- [ ] 지사 계정 매장 수 표시 확인
- [ ] 매장 계정 개통 건수 표시 확인
- [ ] 통계 페이지 데이터 로딩 확인
- [ ] PostgreSQL 오류 로그 모니터링
- [ ] API 응답 시간 모니터링

---

## 📝 알려진 이슈 및 제한사항

### 해결된 이슈
- ✅ 대시보드 데이터 0 표시 문제
- ✅ PostgreSQL boolean 타입 오류
- ✅ 통계 페이지 권한 필터링 누락
- ✅ API 응답 구조 불일치

### 향후 개선 예정
- [ ] 통계 차트 실시간 업데이트
- [ ] 성능 최적화 (캐싱 개선)
- [ ] 모바일 반응형 개선
- [ ] 추가 권한 세분화

---

## 🔗 관련 문서

- [API 문서](./docs/api.md)
- [권한 시스템 가이드](./docs/permissions.md)
- [PostgreSQL 설정 가이드](./docs/postgresql.md)
- [배포 가이드](./docs/deployment.md)

---

**긴급 배포 승인**: 대시보드 데이터 바인딩 치명적 오류 완전 해결
**다음 릴리즈 예정**: v1.3.0 (성능 최적화 및 신규 기능 추가)