<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * 생성된 매장 계정 정보 Export
 * 본사 전용 - 대량 생성 후 다운로드용
 */
class StoreAccountsExport implements FromArray, WithHeadings, ShouldAutoSize, WithStyles
{
    protected $accounts;

    public function __construct(array $accounts)
    {
        $this->accounts = $accounts;
    }

    /**
     * 계정 데이터 배열
     */
    public function array(): array
    {
        return array_map(function ($account) {
            return [
                $account['branch_code'],
                $account['branch_name'],
                $account['store_code'],
                $account['store_name'],
                $account['username'],
                $account['email'],
                $account['initial_password'],
                $account['created_at'],
            ];
        }, $this->accounts);
    }

    /**
     * 헤더 (컬럼명)
     */
    public function headings(): array
    {
        return [
            '지사 코드',
            '지사명',
            '매장 코드',
            '매장명',
            '로그인 ID',
            '이메일',
            '초기 비밀번호',
            '생성일시',
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
                    'startColor' => ['rgb' => '4472C4'],
                ],
                'font' => [
                    'color' => ['rgb' => 'FFFFFF'],
                    'bold' => true,
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                ],
            ],
            // 비밀번호 컬럼 강조
            'G' => [
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'FFF2CC'],
                ],
            ],
        ];
    }
}
