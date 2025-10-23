<?php

namespace App\Imports;

use App\Models\Branch;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class StoresBulkImport implements WithMultipleSheets
{
    protected $results = [];
    protected $errors = [];

    public function sheets(): array
    {
        return [
            // 모든 시트를 동적으로 처리
            '*' => new StoreSheetImport($this->results, $this->errors),
        ];
    }

    public function getResults()
    {
        return $this->results;
    }

    public function getErrors()
    {
        return $this->errors;
    }
}

class StoreSheetImport implements ToCollection
{
    protected $results;
    protected $errors;

    public function __construct(&$results, &$errors)
    {
        $this->results = &$results;
        $this->errors = &$errors;
    }

    /**
     * 각 시트의 데이터를 처리
     *
     * 예상 컬럼 구조:
     * - 지사명 (A)
     * - 매장명 (B)
     * - 관리자명 (C)
     * - 전화번호 (D)
     */
    public function collection(Collection $rows)
    {
        // 첫 번째 행은 헤더로 간주하고 스킵
        $dataRows = $rows->skip(1);

        foreach ($dataRows as $index => $row) {
            $rowNumber = $index + 2; // 실제 엑셀 행 번호 (헤더 제외)

            try {
                // 빈 행 스킵
                if ($this->isEmptyRow($row)) {
                    continue;
                }

                // 데이터 추출
                $branchName = trim($row[0] ?? '');
                $storeName = trim($row[1] ?? '');
                $ownerName = trim($row[2] ?? '');
                $phone = trim($row[3] ?? '');

                // 필수 필드 검증
                if (empty($branchName) || empty($storeName)) {
                    $this->errors[] = [
                        'row' => $rowNumber,
                        'error' => '지사명과 매장명은 필수입니다.',
                        'data' => ['지사명' => $branchName, '매장명' => $storeName],
                    ];
                    continue;
                }

                // 지사 찾기
                $branch = Branch::where('name', $branchName)->first();
                if (! $branch) {
                    $this->errors[] = [
                        'row' => $rowNumber,
                        'error' => '존재하지 않는 지사명입니다.',
                        'data' => ['지사명' => $branchName],
                    ];
                    continue;
                }

                // 중복 체크 (지사 내에서 매장명 중복)
                $existingStore = Store::where('branch_id', $branch->id)
                    ->where('name', $storeName)
                    ->first();

                if ($existingStore) {
                    $this->errors[] = [
                        'row' => $rowNumber,
                        'error' => '이미 존재하는 매장입니다.',
                        'data' => ['지사명' => $branchName, '매장명' => $storeName],
                    ];
                    continue;
                }

                // 매장 코드 자동 생성
                $storeCount = Store::where('branch_id', $branch->id)->count();
                $storeCode = $branch->code.'-'.str_pad($storeCount + 1, 3, '0', STR_PAD_LEFT);

                // 매장 생성
                $store = Store::create([
                    'name' => $storeName,
                    'code' => $storeCode,
                    'branch_id' => $branch->id,
                    'owner_name' => $ownerName,
                    'phone' => $phone,
                    'address' => '', // 주소는 비워둠
                    'status' => 'active',
                    'opened_at' => now()->toDateTimeString(),
                ]);

                // 계정 정보 자동 생성
                $username = $this->generateUsername($storeName);
                $email = strtolower($storeCode).'@ykp.com';
                $password = 'store'.str_pad($store->id, 4, '0', STR_PAD_LEFT);

                // 이메일 중복 체크
                $existingUser = User::where('email', $email)->first();
                if ($existingUser) {
                    $this->errors[] = [
                        'row' => $rowNumber,
                        'error' => '이메일이 이미 존재합니다. 매장은 생성되었으나 계정 생성 실패.',
                        'data' => ['email' => $email, 'store_id' => $store->id],
                    ];
                    continue;
                }

                // 사용자 계정 생성
                $user = User::create([
                    'name' => $ownerName ?: $storeName.' 관리자',
                    'username' => $username,
                    'email' => $email,
                    'password' => Hash::make($password),
                    'role' => 'store',
                    'store_id' => $store->id,
                    'branch_id' => $branch->id,
                    'is_active' => true,
                    'must_change_password' => true,
                ]);

                // 성공 결과 저장
                $this->results[] = [
                    'row' => $rowNumber,
                    'branch_name' => $branchName,
                    'store_name' => $storeName,
                    'store_code' => $storeCode,
                    'owner_name' => $ownerName,
                    'phone' => $phone,
                    'email' => $email,
                    'username' => $username,
                    'password' => $password,
                    'store_id' => $store->id,
                    'user_id' => $user->id,
                ];

                Log::info('매장 및 계정 생성 성공', [
                    'row' => $rowNumber,
                    'store_id' => $store->id,
                    'user_id' => $user->id,
                ]);

            } catch (\Exception $e) {
                $this->errors[] = [
                    'row' => $rowNumber,
                    'error' => '처리 중 오류 발생: '.$e->getMessage(),
                    'data' => $row->toArray(),
                ];

                Log::error('매장 생성 실패', [
                    'row' => $rowNumber,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }
    }

    /**
     * 빈 행인지 확인
     */
    private function isEmptyRow(Collection $row): bool
    {
        return $row->filter(function ($cell) {
            return ! empty(trim($cell));
        })->isEmpty();
    }

    /**
     * 한글 이름을 영문 username으로 변환
     * 예: "강남점" → "gangnam", "서울1호점" → "seoul1"
     */
    private function generateUsername(string $storeName): string
    {
        // 숫자와 영문은 그대로 유지
        $username = preg_replace('/[^a-zA-Z0-9가-힣]/u', '', $storeName);

        // 한글을 영문으로 변환 (간단한 매핑)
        $koreanToEnglish = [
            '가' => 'ga', '나' => 'na', '다' => 'da', '라' => 'ra', '마' => 'ma',
            '바' => 'ba', '사' => 'sa', '아' => 'a', '자' => 'ja', '차' => 'cha',
            '카' => 'ka', '타' => 'ta', '파' => 'pa', '하' => 'ha',
            '강' => 'gang', '남' => 'nam', '서' => 'seo', '울' => 'ul', '점' => '',
            '호' => 'ho', '매' => 'mae', '장' => 'jang', '동' => 'dong', '중' => 'jung',
        ];

        $result = '';
        $chars = preg_split('//u', $username, -1, PREG_SPLIT_NO_EMPTY);

        foreach ($chars as $char) {
            if (preg_match('/[a-zA-Z0-9]/', $char)) {
                $result .= strtolower($char);
            } elseif (isset($koreanToEnglish[$char])) {
                $result .= $koreanToEnglish[$char];
            } else {
                // 매핑되지 않은 한글은 첫 자음으로 변환 (간단화)
                $result .= substr($char, 0, 1);
            }
        }

        // 너무 길면 앞부분만 사용
        $result = substr($result, 0, 20);

        // 중복 방지를 위해 타임스탬프 추가
        return $result.'_'.substr(md5($storeName.microtime()), 0, 6);
    }
}
