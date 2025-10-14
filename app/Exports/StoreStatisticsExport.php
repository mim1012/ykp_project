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

class StoreStatisticsExport implements FromCollection, WithHeadings, WithStyles, WithColumnWidths, WithTitle
{
    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * 데이터 반환
     */
    public function collection()
    {
        $rows = [];

        foreach ($this->data['stores'] as $store) {
            $row = [
                $store['branch_name'],    // 지역장
                $store['store_name'],     // 매장명
                $store['total_revenue'],  // 전체 총 매출액
                $store['total_count'],    // 전체 건수
            ];

            // 월별 매출/건수 추가
            foreach ($this->data['months'] as $month) {
                $row[] = $store["revenue_{$month}"] ?: '';
                $row[] = $store["count_{$month}"] ?: '';
            }

            $rows[] = $row;
        }

        return collect($rows);
    }

    /**
     * 헤더 설정
     */
    public function headings(): array
    {
        $headers = [
            '지역장',
            '매장명',
            '전체 총 매출액',
            '전체 건수',
        ];

        // 월별 헤더 추가
        foreach ($this->data['months'] as $month) {
            $headers[] = "{$month} 매출";
            $headers[] = "{$month} 건수";
        }

        return $headers;
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
                    'size' => 12,
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
            'A:ZZ' => [
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
        $widths = [
            'A' => 15,  // 지역장
            'B' => 20,  // 매장명
            'C' => 18,  // 전체 총 매출액
            'D' => 12,  // 전체 건수
        ];

        // 월별 컬럼 너비 (매출/건수)
        $columnIndex = 5; // E열부터 시작
        foreach ($this->data['months'] as $month) {
            $column1 = $this->getColumnLetter($columnIndex++);
            $column2 = $this->getColumnLetter($columnIndex++);

            $widths[$column1] = 15;  // 매출 컬럼
            $widths[$column2] = 10;  // 건수 컬럼
        }

        return $widths;
    }

    /**
     * 숫자를 엑셀 컬럼 문자로 변환 (1=A, 2=B, ..., 27=AA)
     */
    private function getColumnLetter(int $columnIndex): string
    {
        $letter = '';
        while ($columnIndex > 0) {
            $columnIndex--;
            $letter = chr(65 + ($columnIndex % 26)) . $letter;
            $columnIndex = (int)($columnIndex / 26);
        }
        return $letter;
    }

    /**
     * 시트 제목 설정 (한글 인코딩 명시)
     */
    public function title(): string
    {
        return '매장별통계';
    }
}
