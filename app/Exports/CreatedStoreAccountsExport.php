<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CreatedStoreAccountsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithTitle
{
    protected $results;

    public function __construct(array $results)
    {
        $this->results = $results;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return collect($this->results);
    }

    /**
     * 헤더 정의 (한글 깨짐 방지)
     */
    public function headings(): array
    {
        return [
            '번호',
            '지사명',
            '매장명',
            '매장코드',
            '관리자명',
            '전화번호',
            '이메일',
            '사용자명 (username)',
            '초기 비밀번호',
            '매장 ID',
            '사용자 ID',
            '생성 일시',
        ];
    }

    /**
     * 데이터 매핑
     */
    public function map($row): array
    {
        static $counter = 1;

        return [
            $counter++,
            $row['branch_name'],
            $row['store_name'],
            $row['store_code'],
            $row['owner_name'],
            $row['phone'],
            $row['email'],
            $row['username'],
            $row['password'],
            $row['store_id'],
            $row['user_id'],
            now()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * 스타일 적용
     */
    public function styles(Worksheet $sheet)
    {
        return [
            // 헤더 행 스타일
            1 => [
                'font' => [
                    'bold' => true,
                    'size' => 12,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                    ],
                ],
            ],
        ];
    }

    /**
     * 시트 제목
     */
    public function title(): string
    {
        return '생성된 매장 계정';
    }
}
