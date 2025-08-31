<?php

namespace App\Helpers;

/**
 * 필드명 매핑 헬퍼 클래스
 * Laravel ↔ ykp-settlement ↔ AgGrid 간의 필드명 변환 처리
 */
class FieldMapper
{
    /**
     * Laravel (백엔드) → ykp-settlement (프론트엔드) 필드명 매핑
     */
    const LARAVEL_TO_YKP = [
        // 기본 정보
        'seller' => 'seller',
        'dealer' => 'dealer',
        'carrier' => 'carrier',
        'activation_type' => 'activationType',
        'model_name' => 'modelName',
        'activation_date' => 'activationDate',
        'customer_name' => 'customerName',
        'memo' => 'memo',
        
        // 입력 필드 (계산용)
        'price_setting' => 'priceSettling',
        'verbal1' => 'verbal1',
        'verbal2' => 'verbal2',
        'grade_amount' => 'gradeAmount',
        'addon_amount' => 'additionalAmount',
        'cash_in' => 'cashReceived',
        'payback' => 'payback',
        
        // 정책 필드 (프로파일 기반)
        'usim_fee' => 'simFee',
        'new_mnp_disc' => 'mnpDiscount',
        'paper_cash' => 'documentCash',
        'deduction' => 'deduction',
        'tax_rate' => 'taxRate',
        
        // 계산 결과 (읽기 전용)
        'total_rebate' => 'totalRebate',
        'settlement' => 'settlementAmount',
        'tax' => 'tax',
        'margin_before' => 'marginBeforeTax',
        'margin_after' => 'marginAfterTax',
    ];

    /**
     * ykp-settlement → Laravel 역매핑
     */
    const YKP_TO_LARAVEL = [
        // 기본 정보
        'seller' => 'seller',
        'dealer' => 'dealer',
        'carrier' => 'carrier',
        'activationType' => 'activation_type',
        'modelName' => 'model_name',
        'activationDate' => 'activation_date',
        'customerName' => 'customer_name',
        'memo' => 'memo',
        
        // 입력 필드
        'priceSettling' => 'price_setting',
        'verbal1' => 'verbal1',
        'verbal2' => 'verbal2',
        'gradeAmount' => 'grade_amount',
        'additionalAmount' => 'addon_amount',
        'cashReceived' => 'cash_in',
        'payback' => 'payback',
        
        // 정책 필드
        'simFee' => 'usim_fee',
        'mnpDiscount' => 'new_mnp_disc',
        'documentCash' => 'paper_cash',
        'deduction' => 'deduction',
        'taxRate' => 'tax_rate',
        
        // 계산 결과
        'totalRebate' => 'total_rebate',
        'settlementAmount' => 'settlement',
        'tax' => 'tax',
        'marginBeforeTax' => 'margin_before',
        'marginAfterTax' => 'margin_after',
    ];

    /**
     * AgGrid 컬럼 정의용 필드명 매핑
     */
    const AGGRID_COLUMNS = [
        // 기본 정보 컬럼
        'seller' => ['field' => 'seller', 'headerName' => '판매자', 'editable' => true, 'width' => 100],
        'dealer' => ['field' => 'dealer', 'headerName' => '대리점', 'editable' => true, 'width' => 120],
        'carrier' => ['field' => 'carrier', 'headerName' => '통신사', 'editable' => true, 'width' => 80],
        'activationType' => ['field' => 'activationType', 'headerName' => '개통방식', 'editable' => true, 'width' => 90],
        'modelName' => ['field' => 'modelName', 'headerName' => '모델명', 'editable' => true, 'width' => 150],
        'activationDate' => ['field' => 'activationDate', 'headerName' => '개통일', 'editable' => true, 'width' => 100],
        'customerName' => ['field' => 'customerName', 'headerName' => '고객명', 'editable' => true, 'width' => 100],

        // 입력 필드 (계산용)
        'priceSettling' => ['field' => 'priceSettling', 'headerName' => '액면/셋팅가', 'editable' => true, 'width' => 110, 'type' => 'numericColumn'],
        'verbal1' => ['field' => 'verbal1', 'headerName' => '구두1', 'editable' => true, 'width' => 80, 'type' => 'numericColumn'],
        'verbal2' => ['field' => 'verbal2', 'headerName' => '구두2', 'editable' => true, 'width' => 80, 'type' => 'numericColumn'],
        'gradeAmount' => ['field' => 'gradeAmount', 'headerName' => '그레이드', 'editable' => true, 'width' => 90, 'type' => 'numericColumn'],
        'additionalAmount' => ['field' => 'additionalAmount', 'headerName' => '부가추가', 'editable' => true, 'width' => 90, 'type' => 'numericColumn'],
        'cashReceived' => ['field' => 'cashReceived', 'headerName' => '현금받음(+)', 'editable' => true, 'width' => 100, 'type' => 'numericColumn'],
        'payback' => ['field' => 'payback', 'headerName' => '페이백(-)', 'editable' => true, 'width' => 90, 'type' => 'numericColumn'],

        // 정책 필드 (프로파일 기반)
        'simFee' => ['field' => 'simFee', 'headerName' => '유심비(+)', 'editable' => true, 'width' => 90, 'type' => 'numericColumn'],
        'mnpDiscount' => ['field' => 'mnpDiscount', 'headerName' => '신규/번이할인(-)', 'editable' => true, 'width' => 120, 'type' => 'numericColumn'],
        'documentCash' => ['field' => 'documentCash', 'headerName' => '서류상현금개통', 'editable' => true, 'width' => 130, 'type' => 'numericColumn'],

        // 계산 결과 (읽기 전용)
        'totalRebate' => ['field' => 'totalRebate', 'headerName' => '리베총계', 'editable' => false, 'width' => 100, 'type' => 'numericColumn'],
        'settlementAmount' => ['field' => 'settlementAmount', 'headerName' => '정산금', 'editable' => false, 'width' => 100, 'type' => 'numericColumn'],
        'tax' => ['field' => 'tax', 'headerName' => '부가세', 'editable' => false, 'width' => 90, 'type' => 'numericColumn'],
        'marginBeforeTax' => ['field' => 'marginBeforeTax', 'headerName' => '세전마진', 'editable' => false, 'width' => 100, 'type' => 'numericColumn'],
        'marginAfterTax' => ['field' => 'marginAfterTax', 'headerName' => '세후마진', 'editable' => false, 'width' => 100, 'type' => 'numericColumn'],

        'memo' => ['field' => 'memo', 'headerName' => '메모', 'editable' => true, 'width' => 150],
    ];

    /**
     * Laravel → ykp-settlement 필드 변환
     *
     * @param  array  $data  Laravel 형식 데이터
     * @return array ykp-settlement 형식 데이터
     */
    public static function laravelToYkp(array $data): array
    {
        return self::mapFields($data, self::LARAVEL_TO_YKP);
    }

    /**
     * ykp-settlement → Laravel 필드 변환
     *
     * @param  array  $data  ykp-settlement 형식 데이터
     * @return array Laravel 형식 데이터
     */
    public static function ykpToLaravel(array $data): array
    {
        return self::mapFields($data, self::YKP_TO_LARAVEL);
    }

    /**
     * 배열 일괄 변환 (Laravel → ykp-settlement)
     *
     * @param  array  $dataArray  Laravel 형식 데이터 배열
     * @return array ykp-settlement 형식 데이터 배열
     */
    public static function laravelArrayToYkp(array $dataArray): array
    {
        return array_map([self::class, 'laravelToYkp'], $dataArray);
    }

    /**
     * 배열 일괄 변환 (ykp-settlement → Laravel)
     *
     * @param  array  $dataArray  ykp-settlement 형식 데이터 배열
     * @return array Laravel 형식 데이터 배열
     */
    public static function ykpArrayToLaravel(array $dataArray): array
    {
        return array_map([self::class, 'ykpToLaravel'], $dataArray);
    }

    /**
     * AgGrid 컬럼 정의 생성
     *
     * @param  array  $fields  표시할 필드 목록 (비어있으면 전체)
     * @param  array  $options  옵션 설정
     * @return array AgGrid 컬럼 정의
     */
    public static function getAgGridColumns(array $fields = [], array $options = []): array
    {
        $columns = self::AGGRID_COLUMNS;
        
        // 특정 필드만 선택
        if (!empty($fields)) {
            $columns = array_intersect_key($columns, array_flip($fields));
        }

        // 옵션 적용
        foreach ($columns as $field => &$column) {
            // 그룹핑 옵션
            if (isset($options['grouping'])) {
                $column = self::applyGrouping($field, $column, $options['grouping']);
            }

            // 정렬 옵션
            if (isset($options['sorting'])) {
                $column['sortable'] = $options['sorting'];
            }

            // 필터 옵션
            if (isset($options['filtering'])) {
                $column['filter'] = $options['filtering'] ? 'agTextColumnFilter' : false;
                if ($column['type'] === 'numericColumn') {
                    $column['filter'] = 'agNumberColumnFilter';
                }
            }

            // 리사이징 옵션
            if (isset($options['resizable'])) {
                $column['resizable'] = $options['resizable'];
            }
        }

        return array_values($columns);
    }

    /**
     * 그룹핑된 컬럼 구조 생성
     *
     * @param  array  $groupConfig  그룹 설정
     * @return array 그룹핑된 컬럼 정의
     */
    public static function getGroupedColumns(array $groupConfig): array
    {
        $groupedColumns = [];

        foreach ($groupConfig as $groupName => $fieldsList) {
            $children = [];
            
            foreach ($fieldsList as $field) {
                if (isset(self::AGGRID_COLUMNS[$field])) {
                    $children[] = self::AGGRID_COLUMNS[$field];
                }
            }

            if (!empty($children)) {
                $groupedColumns[] = [
                    'headerName' => $groupName,
                    'children' => $children,
                ];
            }
        }

        return $groupedColumns;
    }

    /**
     * 필드 매핑 수행
     *
     * @param  array  $data  원본 데이터
     * @param  array  $mapping  매핑 규칙
     * @return array 변환된 데이터
     */
    protected static function mapFields(array $data, array $mapping): array
    {
        $mapped = [];

        foreach ($data as $key => $value) {
            $mappedKey = $mapping[$key] ?? $key;
            $mapped[$mappedKey] = $value;
        }

        return $mapped;
    }

    /**
     * 그룹핑 적용
     *
     * @param  string  $field  필드명
     * @param  array  $column  컬럼 정의
     * @param  array  $grouping  그룹핑 설정
     * @return array 그룹핑 적용된 컬럼 정의
     */
    protected static function applyGrouping(string $field, array $column, array $grouping): array
    {
        // 그룹핑 로직은 필요에 따라 구현
        return $column;
    }

    /**
     * 디버깅용: 사용 가능한 필드 목록 반환
     *
     * @return array 필드 정보
     */
    public static function getAvailableFields(): array
    {
        return [
            'laravel_fields' => array_keys(self::LARAVEL_TO_YKP),
            'ykp_fields' => array_keys(self::YKP_TO_LARAVEL),
            'aggrid_fields' => array_keys(self::AGGRID_COLUMNS),
        ];
    }

    /**
     * 필드 검증
     *
     * @param  array  $data  검증할 데이터
     * @param  string  $format  형식 ('laravel', 'ykp')
     * @return array 검증 결과
     */
    public static function validateFields(array $data, string $format = 'laravel'): array
    {
        $validFields = $format === 'laravel' 
            ? array_keys(self::LARAVEL_TO_YKP)
            : array_keys(self::YKP_TO_LARAVEL);

        $invalidFields = array_diff(array_keys($data), $validFields);
        $missingFields = array_diff($validFields, array_keys($data));

        return [
            'valid' => empty($invalidFields),
            'invalid_fields' => $invalidFields,
            'missing_fields' => $missingFields,
            'total_fields' => count($data),
            'valid_fields_count' => count($data) - count($invalidFields),
        ];
    }
}