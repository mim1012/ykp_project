# Excel 가져오기/내보내기 기능 가이드

## 1. Laravel Excel 패키지 설치

```bash
composer require maatwebsite/excel
```

## 2. Excel Export 클래스 생성

```bash
php artisan make:export SalesExport --model=Sale
```

### app/Exports/SalesExport.php
```php
<?php

namespace App\Exports;

use App\Models\Sale;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class SalesExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    protected $storeId;
    protected $startDate;
    protected $endDate;

    public function __construct($storeId = null, $startDate = null, $endDate = null)
    {
        $this->storeId = $storeId;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function collection()
    {
        $query = Sale::with(['store', 'dealerProfile']);

        if ($this->storeId) {
            $query->where('store_id', $this->storeId);
        }

        if ($this->startDate && $this->endDate) {
            $query->whereBetween('sale_date', [$this->startDate, $this->endDate]);
        }

        return $query->get();
    }

    public function headings(): array
    {
        return [
            '날짜',
            '매장',
            '대리점',
            '통신사',
            '개통방식',
            '고객명',
            '연락처',
            '모델명',
            '일련번호',
            '액면가',
            '구두1',
            '구두2',
            '리베총계',
            '정산금',
            '세금',
            '세전마진',
            '세후마진'
        ];
    }

    public function map($sale): array
    {
        return [
            $sale->sale_date,
            $sale->store->name ?? '',
            $sale->dealerProfile->dealer_name ?? '',
            $sale->carrier,
            $sale->activation_type,
            $sale->customer_name,
            $sale->phone_number,
            $sale->model_name,
            $sale->serial_number,
            $sale->price_setting,
            $sale->verbal1,
            $sale->verbal2,
            $sale->total_rebate,
            $sale->settlement_amount,
            $sale->tax,
            $sale->margin_before,
            $sale->margin_after
        ];
    }
}
```

## 3. Excel Import 클래스 생성

```bash
php artisan make:import SalesImport --model=Sale
```

### app/Imports/SalesImport.php
```php
<?php

namespace App\Imports;

use App\Models\Sale;
use App\Helpers\SalesCalculator;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\WithBatchInserts;

class SalesImport implements ToModel, WithHeadingRow, WithValidation, WithBatchInserts
{
    protected $storeId;

    public function __construct($storeId)
    {
        $this->storeId = $storeId;
    }

    public function model(array $row)
    {
        // SalesCalculator로 자동 계산
        $calculated = SalesCalculator::calculateRow([
            'price_setting' => $row['액면가'] ?? 0,
            'verbal1' => $row['구두1'] ?? 0,
            'verbal2' => $row['구두2'] ?? 0,
            'grade_amount' => $row['그레이드'] ?? 0,
            'addon_amount' => $row['부가추가'] ?? 0,
            'paper_cash' => $row['서류상현금개통'] ?? 0,
            'usim_fee' => $row['유심비'] ?? 0,
            'new_mnp_discount' => $row['신규mnp할인'] ?? 0,
            'deduction' => $row['차감'] ?? 0,
            'cash_in' => $row['현금받음'] ?? 0,
            'payback' => $row['페이백'] ?? 0,
        ]);

        return new Sale([
            'store_id' => $this->storeId,
            'sale_date' => $row['날짜'] ?? now(),
            'dealer_code' => $row['대리점코드'] ?? null,
            'carrier' => $row['통신사'] ?? null,
            'activation_type' => $row['개통방식'] ?? null,
            'customer_name' => $row['고객명'] ?? null,
            'phone_number' => $row['연락처'] ?? null,
            'model_name' => $row['모델명'] ?? null,
            'serial_number' => $row['일련번호'] ?? null,
            'price_setting' => $row['액면가'] ?? 0,
            'verbal1' => $row['구두1'] ?? 0,
            'verbal2' => $row['구두2'] ?? 0,
            'grade_amount' => $row['그레이드'] ?? 0,
            'addon_amount' => $row['부가추가'] ?? 0,
            'paper_cash' => $row['서류상현금개통'] ?? 0,
            'usim_fee' => $row['유심비'] ?? 0,
            'new_mnp_discount' => $row['신규mnp할인'] ?? 0,
            'deduction' => $row['차감'] ?? 0,
            'cash_in' => $row['현금받음'] ?? 0,
            'payback' => $row['페이백'] ?? 0,
            'total_rebate' => $calculated['total_rebate'],
            'settlement_amount' => $calculated['settlement'],
            'tax' => $calculated['tax'],
            'margin_before' => $calculated['margin_before'],
            'margin_after' => $calculated['margin_after'],
        ]);
    }

    public function rules(): array
    {
        return [
            '날짜' => 'required|date',
            '통신사' => 'required|string',
            '고객명' => 'required|string',
            '연락처' => 'required|string',
        ];
    }

    public function batchSize(): int
    {
        return 100;
    }
}
```

## 4. Controller 메소드 추가

### app/Http/Controllers/Api/SalesController.php에 추가
```php
use App\Exports\SalesExport;
use App\Imports\SalesImport;
use Maatwebsite\Excel\Facades\Excel;

// Excel 내보내기
public function export(Request $request)
{
    $storeId = $request->store_id;
    $startDate = $request->start_date;
    $endDate = $request->end_date;

    $fileName = 'sales_' . date('Y-m-d_His') . '.xlsx';

    return Excel::download(
        new SalesExport($storeId, $startDate, $endDate),
        $fileName
    );
}

// Excel 가져오기
public function import(Request $request)
{
    $request->validate([
        'file' => 'required|mimes:xlsx,xls,csv',
        'store_id' => 'required|exists:stores,id'
    ]);

    try {
        Excel::import(
            new SalesImport($request->store_id),
            $request->file('file')
        );

        return response()->json([
            'success' => true,
            'message' => 'Excel 파일이 성공적으로 가져와졌습니다.'
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => '파일 처리 중 오류가 발생했습니다: ' . $e->getMessage()
        ], 500);
    }
}
```

## 5. Route 추가

### routes/api.php
```php
Route::middleware('auth')->group(function () {
    // Excel 내보내기
    Route::get('/sales/export', [SalesController::class, 'export'])
        ->name('sales.export');

    // Excel 가져오기
    Route::post('/sales/import', [SalesController::class, 'import'])
        ->name('sales.import');
});
```

## 6. Frontend UI 추가

### 내보내기 버튼
```javascript
function exportToExcel() {
    const params = new URLSearchParams({
        store_id: currentStoreId,
        start_date: startDate,
        end_date: endDate
    });

    window.location.href = `/api/sales/export?${params}`;
}
```

### 가져오기 UI
```html
<!-- 파일 업로드 폼 -->
<form id="import-form" enctype="multipart/form-data">
    <input type="file"
           id="excel-file"
           accept=".xlsx,.xls,.csv"
           class="form-control">
    <button type="button"
            onclick="importExcel()"
            class="btn btn-success">
        Excel 가져오기
    </button>
</form>

<script>
function importExcel() {
    const fileInput = document.getElementById('excel-file');
    const file = fileInput.files[0];

    if (!file) {
        alert('파일을 선택해주세요.');
        return;
    }

    const formData = new FormData();
    formData.append('file', file);
    formData.append('store_id', currentStoreId);

    fetch('/api/sales/import', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Excel 파일이 성공적으로 가져와졌습니다.');
            location.reload();
        } else {
            alert('오류: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('파일 업로드 중 오류가 발생했습니다.');
    });
}
</script>
```

## 7. Excel 템플릿 제공

사용자가 올바른 형식으로 데이터를 입력할 수 있도록 템플릿 제공:

```php
public function downloadTemplate()
{
    $headers = [
        '날짜', '대리점코드', '통신사', '개통방식', '고객명', '연락처',
        '모델명', '일련번호', '액면가', '구두1', '구두2', '그레이드',
        '부가추가', '서류상현금개통', '유심비', '신규MNP할인', '차감',
        '현금받음', '페이백'
    ];

    return Excel::download(new class($headers) implements FromArray {
        protected $headers;

        public function __construct($headers) {
            $this->headers = $headers;
        }

        public function array(): array {
            return [$this->headers];
        }
    }, 'sales_template.xlsx');
}
```

## 사용 시나리오

### 1. 대량 데이터 가져오기
1. Excel 템플릿 다운로드
2. 데이터 입력
3. 파일 업로드
4. 자동 계산 및 저장

### 2. 월간 보고서 내보내기
1. 기간 선택
2. Excel 내보내기 클릭
3. 자동 다운로드

### 3. 데이터 백업
1. 전체 데이터 내보내기
2. 로컬 저장
3. 필요시 복원

## 장점
- ✅ 진짜 Excel 파일 (.xlsx) 지원
- ✅ 대량 데이터 처리 최적화
- ✅ 자동 계산 기능
- ✅ 유효성 검사
- ✅ 한글 완벽 지원