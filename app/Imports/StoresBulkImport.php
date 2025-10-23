<?php

namespace App\Imports;

use App\Models\Branch;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

class StoresBulkImport
{
    protected $results = [];
    protected $errors = [];
    protected $filePath;

    public function __construct($filePath = null)
    {
        $this->filePath = $filePath;
    }

    /**
     * 엑셀 파일의 모든 시트를 처리 (단일 시트 + 멀티 시트 모두 지원)
     */
    public function processAllSheets()
    {
        try {
            $reader = IOFactory::createReader('Xlsx');
            $spreadsheet = $reader->load($this->filePath);

            // 모든 시트 반복 처리
            foreach ($spreadsheet->getAllSheets() as $worksheet) {
                $sheetName = $worksheet->getTitle();
                $rows = $worksheet->toArray(null, true, true, true);

                // 빈 시트 스킵
                if (count($rows) <= 1) {
                    continue;
                }

                // 첫 번째 행은 헤더로 간주하고 스킵
                $dataRows = array_slice($rows, 1);

                foreach ($dataRows as $index => $row) {
                    $rowNumber = $index + 2; // 실제 엑셀 행 번호 (헤더 제외)

                    $this->processRow($row, $rowNumber, $sheetName);
                }
            }
        } catch (\Exception $e) {
            Log::error('엑셀 시트 처리 실패', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * 각 행 처리
     */
    protected function processRow(array $row, int $rowNumber, string $sheetName)
    {
        try {
            // 빈 행 스킵
            if ($this->isEmptyRow($row)) {
                return;
            }

            // 데이터 추출 (A, B, C, D 컬럼)
            $branchName = trim($row['A'] ?? '');
            $storeName = trim($row['B'] ?? '');
            $ownerName = trim($row['C'] ?? '');
            $phone = trim($row['D'] ?? '');

            // 필수 필드 검증
            if (empty($branchName) || empty($storeName)) {
                $this->errors[] = [
                    'sheet' => $sheetName,
                    'row' => $rowNumber,
                    'error' => '지사명과 매장명은 필수입니다.',
                    'data' => ['지사명' => $branchName, '매장명' => $storeName],
                ];
                return;
            }

            // 지사 찾기
            $branch = Branch::where('name', $branchName)->first();
            if (!$branch) {
                $this->errors[] = [
                    'sheet' => $sheetName,
                    'row' => $rowNumber,
                    'error' => '존재하지 않는 지사명입니다.',
                    'data' => ['지사명' => $branchName],
                ];
                return;
            }

            // 중복 체크 (지사 내에서 매장명 중복)
            $existingStore = Store::where('branch_id', $branch->id)
                ->where('name', $storeName)
                ->first();

            if ($existingStore) {
                $this->errors[] = [
                    'sheet' => $sheetName,
                    'row' => $rowNumber,
                    'error' => '이미 존재하는 매장입니다.',
                    'data' => ['지사명' => $branchName, '매장명' => $storeName],
                ];
                return;
            }

            // 매장 코드 자동 생성
            $storeCount = Store::where('branch_id', $branch->id)->count();
            $storeCode = $branch->code . '-' . str_pad($storeCount + 1, 3, '0', STR_PAD_LEFT);

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
            $email = strtolower($storeCode) . '@ykp.com';
            $password = 'store' . str_pad($store->id, 4, '0', STR_PAD_LEFT);

            // 이메일 중복 체크
            $existingUser = User::where('email', $email)->first();
            if ($existingUser) {
                $this->errors[] = [
                    'sheet' => $sheetName,
                    'row' => $rowNumber,
                    'error' => '이메일이 이미 존재합니다. 매장은 생성되었으나 계정 생성 실패.',
                    'data' => ['email' => $email, 'store_id' => $store->id],
                ];
                return;
            }

            // 사용자 계정 생성
            $user = User::create([
                'name' => $ownerName ?: $storeName . ' 관리자',
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
                'sheet' => $sheetName,
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
                'sheet' => $sheetName,
                'row' => $rowNumber,
                'store_id' => $store->id,
                'user_id' => $user->id,
            ]);

        } catch (\Exception $e) {
            $this->errors[] = [
                'sheet' => $sheetName,
                'row' => $rowNumber,
                'error' => '처리 중 오류 발생: ' . $e->getMessage(),
                'data' => $row,
            ];

            Log::error('매장 생성 실패', [
                'sheet' => $sheetName,
                'row' => $rowNumber,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * 빈 행인지 확인
     */
    protected function isEmptyRow(array $row): bool
    {
        foreach ($row as $cell) {
            if (!empty(trim($cell))) {
                return false;
            }
        }
        return true;
    }

    /**
     * 한글 이름을 영문 username으로 변환
     */
    protected function generateUsername(string $storeName): string
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
        return $result . '_' . substr(md5($storeName . microtime()), 0, 6);
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
