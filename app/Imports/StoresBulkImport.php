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
            // 파일 확장자 확인
            $extension = pathinfo($this->filePath, PATHINFO_EXTENSION);

            if (strtolower($extension) === 'csv') {
                // CSV 파일은 인코딩 변환 후 처리
                $this->processCsvFile();
                return;
            }

            // Excel 파일 처리
            $reader = IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(false);
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
     * CSV 파일 처리 (인코딩 자동 감지 및 변환)
     */
    protected function processCsvFile()
    {
        try {
            // 파일 내용 읽기
            $content = file_get_contents($this->filePath);

            // 인코딩 자동 감지
            $encoding = mb_detect_encoding($content, ['UTF-8', 'CP949', 'EUC-KR', 'ISO-8859-1'], true);

            if ($encoding && $encoding !== 'UTF-8') {
                // UTF-8로 변환
                $content = mb_convert_encoding($content, 'UTF-8', $encoding);
                Log::info('CSV 인코딩 변환', ['from' => $encoding, 'to' => 'UTF-8']);
            }

            // 임시 파일에 UTF-8로 저장
            $tempFile = tempnam(sys_get_temp_dir(), 'csv_utf8_');
            file_put_contents($tempFile, $content);

            // CSV 파싱
            $rows = [];
            if (($handle = fopen($tempFile, 'r')) !== false) {
                while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                    $rows[] = $data;
                }
                fclose($handle);
            }

            // 임시 파일 삭제
            unlink($tempFile);

            // 첫 번째 행은 헤더
            $header = array_shift($rows);

            // 데이터 처리
            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2; // 헤더 제외

                // 배열 키를 A, B, C, D로 변환
                $processedRow = [];
                foreach ($row as $colIndex => $value) {
                    $colLetter = chr(65 + $colIndex); // 65 = 'A'
                    $processedRow[$colLetter] = $value;
                }

                $this->processRow($processedRow, $rowNumber, 'Sheet1');
            }

        } catch (\Exception $e) {
            Log::error('CSV 파일 처리 실패', [
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

            // 데이터 추출 (A, B, C, D 컬럼) - UTF-8 정리 (mb_scrub은 PHP 8.4+에서 잘못된 UTF-8 시퀀스 정리)
            $branchName = mb_scrub(trim($row['A'] ?? ''), 'UTF-8');
            $storeName = mb_scrub(trim($row['B'] ?? ''), 'UTF-8');
            $ownerName = mb_scrub(trim($row['C'] ?? ''), 'UTF-8');
            $phone = mb_scrub(trim($row['D'] ?? ''), 'UTF-8');

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
                // 매장은 이미 있으니, user 계정만 확인하고 생성
                $existingUser = User::where('store_id', $existingStore->id)->first();

                if ($existingUser) {
                    // 매장도 있고 user도 있으면 스킵
                    $this->errors[] = [
                        'sheet' => $sheetName,
                        'row' => $rowNumber,
                        'error' => '매장과 계정이 이미 존재합니다.',
                        'data' => ['지사명' => $branchName, '매장명' => $storeName, '기존 이메일' => $existingUser->email],
                    ];
                    return;
                }

                // 매장은 있지만 user가 없으면 → user만 생성
                $username = $this->generateUsername($storeName);
                $email = strtolower($existingStore->code) . '@ykp.com';
                $password = 'store' . str_pad($existingStore->id, 4, '0', STR_PAD_LEFT);

                // 이메일 중복 체크
                $emailExists = User::where('email', $email)->first();
                if ($emailExists) {
                    $this->errors[] = [
                        'sheet' => $sheetName,
                        'row' => $rowNumber,
                        'error' => '이메일이 이미 존재합니다.',
                        'data' => ['email' => $email, 'store_id' => $existingStore->id],
                    ];
                    return;
                }

                // user 계정 생성
                $user = User::create([
                    'name' => $ownerName ?: $storeName . ' 관리자',
                    'username' => $username,
                    'email' => $email,
                    'password' => Hash::make($password),
                    'role' => 'store',
                    'store_id' => $existingStore->id,
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
                    'store_code' => $existingStore->code,
                    'owner_name' => $ownerName,
                    'phone' => $phone,
                    'email' => $email,
                    'username' => $username,
                    'password' => $password,
                    'store_id' => $existingStore->id,
                    'user_id' => $user->id,
                ];

                Log::info('기존 매장에 user 계정 생성 성공', [
                    'sheet' => $sheetName,
                    'row' => $rowNumber,
                    'store_id' => $existingStore->id,
                    'user_id' => $user->id,
                ]);

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
            '산' => 'san', '구' => 'gu', '주' => 'ju', '천' => 'cheon', '원' => 'won',
            '평' => 'pyeong', '곡' => 'gok', '양' => 'yang', '성' => 'seong', '영' => 'young',
            '포' => 'po', '항' => 'hang', '인' => 'in', '천' => 'cheon', '안' => 'an',
            '용' => 'yong', '오' => 'o', '신' => 'sin', '정' => 'jeong', '로' => 'ro',
            '왕' => 'wang', '생' => 'saeng', '경' => 'gyeong', '기' => 'gi', '리' => 'ri',
            '수' => 'su', '역' => 'yeok', '시' => 'si', '문' => 'mun', '대' => 'dae',
            '두' => 'du', '상' => 'sang', '봉' => 'bong', '모' => 'mo', '도' => 'do',
            '농' => 'nong', '계' => 'gye', '명' => 'myeong', '황' => 'hwang', '진' => 'jin',
            '량' => 'ryang', '하' => 'ha', '미' => 'mi', '덕' => 'deok', '혁' => 'hyeok',
            '이' => 'i', '화' => 'hwa', '교' => 'gyo', '북' => 'buk', '서' => 'seo',
            '문' => 'mun', '신' => 'sin', '반' => 'ban', '송' => 'song', '전' => 'jeon',
        ];

        $result = '';
        $chars = preg_split('//u', $username, -1, PREG_SPLIT_NO_EMPTY);

        foreach ($chars as $char) {
            if (preg_match('/[a-zA-Z0-9]/', $char)) {
                $result .= strtolower($char);
            } elseif (isset($koreanToEnglish[$char])) {
                $result .= $koreanToEnglish[$char];
            }
            // 매핑되지 않은 한글은 스킵 (invalid UTF-8 방지)
        }

        // 결과가 너무 짧으면 기본값 사용
        if (strlen($result) < 3) {
            $result = 'store';
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
