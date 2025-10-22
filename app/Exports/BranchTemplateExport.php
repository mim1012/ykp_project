<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * 지사 대량 생성용 엑셀 템플릿 Export
 * 본사 전용 기능
 */
class BranchTemplateExport implements FromArray, WithHeadings, ShouldAutoSize, WithStyles
{
    /**
     * 샘플 데이터 (예시)
     */
    public function array(): array
    {
        return [
            ['강남지사', '홍길동'],
            ['판교지사', '김철수'],
            ['분당지사', '이영희'],
            ['서초지사', '박민수'],
            ['역삼지사', '정수진'],
        ];
    }

    /**
     * 헤더 (컬럼명)
     */
    public function headings(): array
    {
        return [
            '지사명 *',
            '지역장 이름 *',
        ];
    }

    /**
     * 스타일 적용
     */
    public function styles(Worksheet $sheet)
    {
        return [
            // 헤더 스타일
            1 => [
                'font' => [
                    'bold' => true,
                    'size' => 12,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4'],
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                ],
            ],
        ];
    }
}
