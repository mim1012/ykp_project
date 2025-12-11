<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class StoreSalesExport implements FromCollection, WithHeadings, WithStyles, WithColumnWidths, WithTitle
{
    protected $sales;
    protected $storeName;
    protected $period;

    public function __construct(array $sales, string $storeName, string $period = '')
    {
        $this->sales = $sales;
        $this->storeName = $storeName;
        $this->period = $period;
    }

    /**
     * 데이터 반환
     */
    public function collection()
    {
        $rows = [];

        foreach ($this->sales as $sale) {
            $rows[] = [
                $sale['sale_date'] ?? '',                    // 개통일자
                $sale['created_at'] ?? '',                   // 최초저장일
                $sale['carrier'] ?? '',                      // 통신사
                $sale['activation_type'] ?? '',              // 개통유형
                $sale['model_name'] ?? '',                   // 모델명
                $sale['customer_name'] ?? '',                // 고객명
                $sale['customer_birth_date'] ?? '',          // 생년월일
                $sale['phone_number'] ?? '',                 // 전화번호
                $sale['salesperson'] ?? '',                  // 판매자
                $sale['dealer_name'] ?? '',                  // 딜러명
                $sale['dealer_code'] ?? '',                  // 딜러코드
                $sale['serial_number'] ?? '',                // 시리얼번호
                $sale['agency'] ?? '',                       // 대리점
                $sale['visit_path'] ?? '',                   // 방문경로
                number_format($sale['base_price'] ?? 0),     // 출고가
                number_format($sale['settlement_amount'] ?? 0), // 정산금액
                number_format($sale['margin_after_tax'] ?? 0),  // 마진
                $sale['memo'] ?? '',                         // 메모
            ];
        }

        return collect($rows);
    }

    /**
     * 헤더 설정
     */
    public function headings(): array
    {
        return [
            '개통일자',
            '최초저장일',
            '통신사',
            '개통유형',
            '모델명',
            '고객명',
            '생년월일',
            '전화번호',
            '판매자',
            '딜러명',
            '딜러코드',
            '시리얼번호',
            '대리점',
            '방문경로',
            '출고가',
            '정산금액',
            '마진',
            '메모',
        ];
    }

    /**
     * 스타일 설정
     */
    public function styles(Worksheet $sheet)
    {
        return [
            // 헤더 스타일
            1 => [
                'font' => [
                    'bold' => true,
                    'size' => 11,
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E8F4F8'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
            // 전체 데이터 중앙 정렬
            'A:R' => [
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }

    /**
     * 컬럼 너비 설정
     */
    public function columnWidths(): array
    {
        return [
            'A' => 12,  // 개통일자
            'B' => 18,  // 최초저장일
            'C' => 10,  // 통신사
            'D' => 10,  // 개통유형
            'E' => 20,  // 모델명
            'F' => 12,  // 고객명
            'G' => 12,  // 생년월일
            'H' => 14,  // 전화번호
            'I' => 10,  // 판매자
            'J' => 12,  // 딜러명
            'K' => 12,  // 딜러코드
            'L' => 18,  // 시리얼번호
            'M' => 12,  // 대리점
            'N' => 10,  // 방문경로
            'O' => 12,  // 출고가
            'P' => 12,  // 정산금액
            'Q' => 12,  // 마진
            'R' => 20,  // 메모
        ];
    }

    /**
     * 시트 제목 설정
     */
    public function title(): string
    {
        return '개통표내역';
    }
}
