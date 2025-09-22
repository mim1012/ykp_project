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

            // CSV 헤더 생성
            $headers = [
                'ID',
                '개통일자',
                '고객명',
                '연락처',
                '개통방식',
                '통신사',
                '요금제',
                '거래처',
                '액면가',
                '구두1',
                '구두2',
                '그레이드',
                '부가추가',
                '서류상현금개통',
                '유심비',
                '신규/MNP할인',
                '차감',
                '리베총계',
                '정산금',
                '세금',
                '현금받음',
                '페이백',
                '세전마진',
                '세후마진',
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
                    $sale->customer_name,
                    $sale->phone_number,
                    $sale->activation_type,
                    $sale->agency,
                    $sale->rate_plan,
                    $sale->dealer_name,
                    $sale->price_setting ?? 0,
                    $sale->verbal1 ?? 0,
                    $sale->verbal2 ?? 0,
                    $sale->grade_amount ?? 0,
                    $sale->addon_amount ?? 0,
                    $sale->paper_cash ?? 0,
                    $sale->usim_fee ?? 0,
                    $sale->new_mnp_discount ?? 0,
                    $sale->deduction ?? 0,
                    $sale->total_rebate ?? 0,
                    $sale->settlement_amount ?? 0,
                    $sale->tax ?? 0,
                    $sale->cash_in ?? 0,
                    $sale->payback ?? 0,
                    $sale->margin_before ?? 0,
                    $sale->margin_after ?? 0,
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
            // 템플릿 헤더
            $headers = [
                '개통일자',
                '고객명',
                '연락처',
                '개통방식',
                '통신사',
                '요금제',
                '거래처',
                '액면가',
                '구두1',
                '구두2',
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
                '홍길동',
                '010-1234-5678',
                '신규',
                'SK',
                '5G 요금제',
                'SM',
                '50000',
                '10000',
                '5000',
                '3000',
                '2000',
                '0',
                '6000',
                '-800',
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

                    // 데이터 매핑
                    $saleData = [
                        'sale_date' => $row[0] ?? Carbon::now()->format('Y-m-d'),
                        'customer_name' => $row[1] ?? '',
                        'phone_number' => $row[2] ?? '',
                        'activation_type' => $row[3] ?? '신규',
                        'agency' => $row[4] ?? 'SK',
                        'rate_plan' => $row[5] ?? '',
                        'dealer_name' => $row[6] ?? '',
                        'price_setting' => floatval($row[7] ?? 0),
                        'verbal1' => floatval($row[8] ?? 0),
                        'verbal2' => floatval($row[9] ?? 0),
                        'grade_amount' => floatval($row[10] ?? 0),
                        'addon_amount' => floatval($row[11] ?? 0),
                        'paper_cash' => floatval($row[12] ?? 0),
                        'usim_fee' => floatval($row[13] ?? 0),
                        'new_mnp_discount' => floatval($row[14] ?? 0),
                        'deduction' => floatval($row[15] ?? 0),
                        'cash_in' => floatval($row[16] ?? 0),
                        'payback' => floatval($row[17] ?? 0),
                        'memo' => $row[18] ?? '',
                        'store_id' => $user->store_id,
                        'branch_id' => $user->branch_id ?? $user->store->branch_id
                    ];

                    // 계산 필드 자동 계산
                    $saleData['total_rebate'] = $saleData['price_setting'] + $saleData['verbal1']
                                               + $saleData['verbal2'] + $saleData['grade_amount']
                                               + $saleData['addon_amount'];

                    $saleData['settlement_amount'] = $saleData['total_rebate'] - $saleData['paper_cash']
                                                    + $saleData['usim_fee'] + $saleData['new_mnp_discount']
                                                    + $saleData['deduction'];

                    $saleData['tax'] = $saleData['settlement_amount'] * 0.1;
                    $saleData['margin_before'] = $saleData['settlement_amount'] - $saleData['tax']
                                                + $saleData['cash_in'] + $saleData['payback'];
                    $saleData['margin_after'] = $saleData['tax'] + $saleData['margin_before'];

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
}