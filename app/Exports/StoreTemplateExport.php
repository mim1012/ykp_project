<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * 매장 대량 생성용 엑셀 템플릿 Export
 * 본사 전용 기능
 */
class StoreTemplateExport implements FromArray, WithHeadings, ShouldAutoSize, WithStyles
{
    /**
     * 샘플 데이터 (예시)
     */
    public function array(): array
    {
        return [
            ['강남1호점', '강남지사'],
            ['강남2호점', '강남지사'],
            ['강남3호점', '강남지사'],
            ['판교1호점', '판교지사'],
            ['판교2호점', '판교지사'],
            ['분당1호점', '분당지사'],
        ];
    }

    /**
     * 헤더 (컬럼명)
     */
    public function headings(): array
    {
        return [
            '매장명 *',
            '소속 지사 *',
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
                    'size' => 11,
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E7E6E6'],
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                ],
            ],
        ];
    }
}
