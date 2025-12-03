<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use Carbon\Carbon;

class SalesExportController extends Controller
{
    /**
     * CSV 파일로 판매 데이터 내보내기
     */
    public function exportCsv(Request $request)
    {
        try {
            $user = Auth::user();

            // 날짜 범위 설정
            $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
            $endDate = $request->input('end_date', Carbon::now()->endOfMonth()->format('Y-m-d'));

            // 권한별 데이터 조회
            $query = Sale::with(['store', 'branch']);

            if ($user->role === 'store') {
                $query->where('store_id', $user->store_id);
            } elseif ($user->role === 'branch') {
                $query->where('branch_id', $user->branch_id);
            }

            // 날짜 필터 적용
            $query->whereBetween('sale_date', [$startDate, $endDate])
                  ->orderBy('sale_date', 'desc')
                  ->orderBy('created_at', 'desc');

            $sales = $query->get();

            // CSV 헤더 생성 (계산 필드 제외: 리베총계, 매출, 부/소세, 세전마진, 세후마진)
            $headers = [
                'ID',
                '개통일자',
                '판매자',
                '고객명',
                '연락처',
                '생년월일',
                '방문경로',
                '주소',
                '개통방식',
                '통신사',
                '모델명',
                '거래처',
                '액면가',
                '구두1',
                '구두1메모',
                '구두2',
                '구두2메모',
                '그레이드',
                '부가추가',
                '서류상현금개통',
                '유심비',
                '신규/MNP할인',
                '차감',
                '현금받음',
                '페이백',
                '메모',
                '지사명',
                '매장명',
                '등록일시'
            ];

            // CSV 데이터 생성
            $csvData = [];
            $csvData[] = $headers; // 헤더 추가

            foreach ($sales as $sale) {
                $csvData[] = [
                    $sale->id,
                    $sale->sale_date,
                    $sale->salesperson ?? '',
                    $sale->customer_name ?? '',
                    $sale->phone_number ?? '',
                    $sale->customer_birth_date ?? '',
                    $sale->visit_path ?? '',
                    $sale->customer_address ?? '',
                    $sale->activation_type ?? '',
                    $sale->carrier ?? '',
                    $sale->model_name ?? '',
                    $sale->dealer_name ?? '',
                    $sale->base_price ?? 0,
                    $sale->verbal1 ?? 0,
                    $sale->verbal1_memo ?? '',
                    $sale->verbal2 ?? 0,
                    $sale->verbal2_memo ?? '',
                    $sale->grade_amount ?? 0,
                    $sale->additional_amount ?? 0,
                    $sale->cash_activation ?? 0,
                    $sale->usim_fee ?? 0,
                    $sale->new_mnp_discount ?? 0,
                    $sale->deduction ?? 0,
                    $sale->cash_received ?? 0,
                    $sale->payback ?? 0,
                    $sale->memo ?? '',
                    $sale->branch->name ?? '',
                    $sale->store->name ?? '',
                    $sale->created_at->format('Y-m-d H:i:s')
                ];
            }

            // CSV 파일 생성
            $filename = 'sales_export_' . date('Ymd_His') . '.csv';
            $callback = function() use ($csvData) {
                $file = fopen('php://output', 'w');

                // UTF-8 BOM 추가 (엑셀에서 한글 인식)
                fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

                foreach ($csvData as $row) {
                    fputcsv($file, $row);
                }

                fclose($file);
            };

            return Response::stream($callback, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'CSV 내보내기 실패: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * CSV 업로드용 템플릿 다운로드
     */
    public function downloadTemplate()
    {
        try {
            // 템플릿 헤더 (계산 필드 제외)
            $headers = [
                '개통일자',
                '판매자',
                '고객명',
                '연락처',
                '생년월일',
                '방문경로',
                '주소',
                '개통방식',
                '통신사',
                '모델명',
                '거래처',
                '액면가',
                '구두1',
                '구두1메모',
                '구두2',
                '구두2메모',
                '그레이드',
                '부가추가',
                '서류상현금개통',
                '유심비',
                '신규/MNP할인',
                '차감',
                '현금받음',
                '페이백',
                '메모'
            ];

            // 샘플 데이터
            $sampleData = [
                '2025-09-22',
                '김판매',
                '홍길동',
                '010-1234-5678',
                '971220',
                '매장방문',
                '서울 강남구',
                '신규',
                'SK',
                'iPhone15',
                'SM',
                '50000',
                '10000',
                '구두1 메모',
                '5000',
                '구두2 메모',
                '3000',
                '2000',
                '0',
                '6000',
                '0',
                '0',
                '5000',
                '10000',
                '테스트 메모'
            ];

            $csvData = [];
            $csvData[] = $headers;
            $csvData[] = $sampleData;

            $filename = 'sales_upload_template.csv';
            $callback = function() use ($csvData) {
                $file = fopen('php://output', 'w');

                // UTF-8 BOM 추가
                fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

                foreach ($csvData as $row) {
                    fputcsv($file, $row);
                }

                fclose($file);
            };

            return Response::stream($callback, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '템플릿 다운로드 실패: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * CSV 파일 업로드 및 데이터 저장
     */
    public function importCsv(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|file|mimes:csv,txt|max:10240' // 최대 10MB
            ]);

            $file = $request->file('file');
            $user = Auth::user();

            // CSV 파일 읽기
            $csvData = array_map('str_getcsv', file($file->getRealPath()));
            $headers = array_shift($csvData); // 첫 번째 줄은 헤더

            $successCount = 0;
            $errorCount = 0;
            $errors = [];

            foreach ($csvData as $index => $row) {
                try {
                    // 빈 행 건너뛰기
                    if (empty(array_filter($row))) {
                        continue;
                    }

                    // 데이터 매핑 (새 템플릿 순서에 맞춤)
                    $saleData = [
                        'sale_date' => $row[0] ?? Carbon::now()->format('Y-m-d'),
                        'salesperson' => $row[1] ?? '',
                        'customer_name' => $row[2] ?? '',
                        'phone_number' => $row[3] ?? '',
                        'customer_birth_date' => $this->parseBirthDate($row[4] ?? ''),
                        'visit_path' => $row[5] ?? '',
                        'customer_address' => $row[6] ?? '',
                        'activation_type' => $row[7] ?? '신규',
                        'carrier' => $row[8] ?? 'SK',
                        'model_name' => $row[9] ?? '',
                        'dealer_name' => $row[10] ?? '',
                        'base_price' => floatval($row[11] ?? 0),
                        'verbal1' => floatval($row[12] ?? 0),
                        'verbal1_memo' => $row[13] ?? '',
                        'verbal2' => floatval($row[14] ?? 0),
                        'verbal2_memo' => $row[15] ?? '',
                        'grade_amount' => floatval($row[16] ?? 0),
                        'additional_amount' => floatval($row[17] ?? 0),
                        'cash_activation' => floatval($row[18] ?? 0),
                        'usim_fee' => floatval($row[19] ?? 0),
                        'new_mnp_discount' => floatval($row[20] ?? 0),
                        'deduction' => floatval($row[21] ?? 0),
                        'cash_received' => floatval($row[22] ?? 0),
                        'payback' => floatval($row[23] ?? 0),
                        'memo' => $row[24] ?? '',
                        'store_id' => $user->store_id,
                        'branch_id' => $user->branch_id ?? $user->store->branch_id
                    ];

                    // 계산 필드 자동 계산
                    $saleData['rebate_total'] = $saleData['base_price'] + $saleData['verbal1']
                                               + $saleData['verbal2'] + $saleData['grade_amount']
                                               + $saleData['additional_amount'];

                    $saleData['settlement_amount'] = $saleData['rebate_total'] - $saleData['cash_activation']
                                                    + $saleData['usim_fee'] + $saleData['new_mnp_discount']
                                                    - $saleData['deduction'] + $saleData['cash_received']
                                                    - $saleData['payback'];

                    // 세금 제거: 마진 = 정산금
                    $saleData['tax'] = 0;
                    $saleData['margin_before_tax'] = $saleData['settlement_amount'];
                    $saleData['margin_after_tax'] = $saleData['settlement_amount'];

                    // 데이터 저장
                    Sale::create($saleData);
                    $successCount++;

                } catch (\Exception $e) {
                    $errorCount++;
                    $errors[] = '행 ' . ($index + 2) . ': ' . $e->getMessage();
                }
            }

            return response()->json([
                'success' => true,
                'message' => "업로드 완료: 성공 {$successCount}건, 실패 {$errorCount}건",
                'details' => [
                    'success_count' => $successCount,
                    'error_count' => $errorCount,
                    'errors' => $errors
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'CSV 업로드 실패: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 생년월일 6자리(YYMMDD) → YYYY-MM-DD 변환
     */
    private function parseBirthDate(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        $str = preg_replace('/[^0-9]/', '', $value);

        // 6자리인 경우 (YYMMDD)
        if (strlen($str) === 6) {
            $yy = (int) substr($str, 0, 2);
            $mm = substr($str, 2, 2);
            $dd = substr($str, 4, 2);
            // 00~30은 2000년대, 31~99는 1900년대로 추정
            $yyyy = $yy <= 30 ? 2000 + $yy : 1900 + $yy;
            return sprintf('%04d-%s-%s', $yyyy, $mm, $dd);
        }

        // 8자리인 경우 (YYYYMMDD)
        if (strlen($str) === 8) {
            return sprintf('%s-%s-%s', substr($str, 0, 4), substr($str, 4, 2), substr($str, 6, 2));
        }

        // YYYY-MM-DD 형식인 경우 그대로 반환
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        return null;
    }
}