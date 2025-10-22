<?php

namespace App\Helpers;

/**
 * 매장/지사 대량 생성용 랜덤 데이터 생성 헬퍼
 */
class RandomDataGenerator
{
    /**
     * 서울 지역 목록
     */
    protected static $seoulDistricts = [
        '강남구', '강동구', '강북구', '강서구', '관악구',
        '광진구', '구로구', '금천구', '노원구', '도봉구',
        '동대문구', '동작구', '마포구', '서대문구', '서초구',
        '성동구', '성북구', '송파구', '양천구', '영등포구',
        '용산구', '은평구', '종로구', '중구', '중랑구',
    ];

    /**
     * 경기 도시 목록
     */
    protected static $gyeonggiCities = [
        '수원시', '성남시', '고양시', '용인시', '부천시',
        '안산시', '안양시', '남양주시', '화성시', '평택시',
        '의정부시', '시흥시', '파주시', '광명시', '김포시',
        '군포시', '하남시', '오산시', '양주시', '이천시',
    ];

    /**
     * 도로명 목록
     */
    protected static $streetNames = [
        '테헤란로', '강남대로', '역삼로', '언주로', '선릉로',
        '봉은사로', '도산대로', '압구정로', '청담로', '삼성로',
        '판교로', '분당로', '성남대로', '서현로', '수내로',
    ];

    /**
     * 랜덤 주소 생성
     */
    public static function generateAddress(): string
    {
        $type = rand(0, 1);

        if ($type === 0) {
            // 서울
            $district = self::$seoulDistricts[array_rand(self::$seoulDistricts)];
            $street = self::$streetNames[array_rand(self::$streetNames)];
            $number = rand(1, 999);

            return "서울특별시 {$district} {$street} {$number}";
        } else {
            // 경기
            $city = self::$gyeonggiCities[array_rand(self::$gyeonggiCities)];
            $street = self::$streetNames[array_rand(self::$streetNames)];
            $number = rand(1, 999);

            return "경기도 {$city} {$street} {$number}";
        }
    }

    /**
     * 랜덤 전화번호 생성
     */
    public static function generatePhoneNumber(?string $address = null): string
    {
        // 주소 기반으로 지역번호 결정
        if ($address && str_contains($address, '서울')) {
            $areaCode = '02';
        } elseif ($address && str_contains($address, '경기')) {
            $areaCodes = ['031', '032', '033'];
            $areaCode = $areaCodes[array_rand($areaCodes)];
        } else {
            $areaCodes = ['02', '031', '032'];
            $areaCode = $areaCodes[array_rand($areaCodes)];
        }

        // 중간 4자리
        $middle = str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);

        // 마지막 4자리
        $last = str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);

        return "{$areaCode}-{$middle}-{$last}";
    }

    /**
     * 지사명에서 코드명 추출 (한글 → 영문)
     */
    public static function generateCodeName(string $name): string
    {
        // "지사" 제거
        $cleaned = str_replace('지사', '', $name);

        // 한글 → 영문 매핑 (간단한 버전)
        $korToEng = [
            '강남' => 'gangnam',
            '강동' => 'gangdong',
            '강북' => 'gangbuk',
            '강서' => 'gangseo',
            '판교' => 'pangyo',
            '분당' => 'bundang',
            '서초' => 'seocho',
            '역삼' => 'yeoksam',
            '삼성' => 'samsung',
            '압구정' => 'apgujeong',
            '청담' => 'cheongdam',
            '수원' => 'suwon',
            '성남' => 'seongnam',
            '용인' => 'yongin',
            '부천' => 'bucheon',
            '안양' => 'anyang',
        ];

        // 매핑된 영문명 찾기
        foreach ($korToEng as $kor => $eng) {
            if (str_contains($cleaned, $kor)) {
                return $eng;
            }
        }

        // 매핑 없으면 로마자 표기 (간단 버전)
        return self::koreanToRomanSimple($cleaned);
    }

    /**
     * 매장명에서 코드명 추출
     */
    public static function generateStoreCodeName(string $storeName): string
    {
        // "호점" 제거
        $cleaned = str_replace(['호점', '점'], '', $storeName);

        // 지역명과 숫자 분리
        preg_match('/^(.+?)(\d+)$/', $cleaned, $matches);

        if (count($matches) === 3) {
            $area = self::generateCodeName($matches[1]);
            $number = $matches[2];

            return "{$area}{$number}";
        }

        // 분리 실패 시 전체를 코드명으로
        return self::koreanToRomanSimple($cleaned);
    }

    /**
     * 한글 → 로마자 간단 변환
     */
    protected static function koreanToRomanSimple(string $korean): string
    {
        $consonants = [
            'ㄱ' => 'g', 'ㄴ' => 'n', 'ㄷ' => 'd', 'ㄹ' => 'r', 'ㅁ' => 'm',
            'ㅂ' => 'b', 'ㅅ' => 's', 'ㅇ' => '', 'ㅈ' => 'j', 'ㅊ' => 'ch',
            'ㅋ' => 'k', 'ㅌ' => 't', 'ㅍ' => 'p', 'ㅎ' => 'h',
        ];

        $vowels = [
            'ㅏ' => 'a', 'ㅑ' => 'ya', 'ㅓ' => 'eo', 'ㅕ' => 'yeo', 'ㅗ' => 'o',
            'ㅛ' => 'yo', 'ㅜ' => 'u', 'ㅠ' => 'yu', 'ㅡ' => 'eu', 'ㅣ' => 'i',
        ];

        // 숫자는 그대로 유지
        $result = '';
        for ($i = 0; $i < mb_strlen($korean); $i++) {
            $char = mb_substr($korean, $i, 1);

            if (is_numeric($char)) {
                $result .= $char;
            } else {
                // 간단한 변환 (완벽하지 않음, 실제로는 더 복잡한 로직 필요)
                $result .= strtolower($char);
            }
        }

        // 특수문자 제거
        $result = preg_replace('/[^a-z0-9]/', '', $result);

        return $result ?: 'store';
    }

    /**
     * 지사 계정 ID 생성
     */
    public static function generateBranchUsername(string $branchName): string
    {
        $codeName = self::generateCodeName($branchName);

        return "{$codeName}_manager";
    }

    /**
     * 매장 계정 ID 생성
     */
    public static function generateStoreUsername(string $storeName): string
    {
        $codeName = self::generateStoreCodeName($storeName);

        return "{$codeName}_store";
    }

    /**
     * 이메일 생성
     */
    public static function generateEmail(string $username): string
    {
        return strtolower($username).'@ykp.local';
    }

    /**
     * 지사 초기 비밀번호 생성
     */
    public static function generateBranchPassword(): string
    {
        return 'Branch@'.date('ymd');
    }

    /**
     * 매장 초기 비밀번호 생성
     */
    public static function generateStorePassword(): string
    {
        return 'Store@'.date('ymd');
    }

    /**
     * 지사 코드 생성 (순차적)
     */
    public static function generateBranchCode(int $sequence): string
    {
        return 'YKP-BR-'.str_pad($sequence, 3, '0', STR_PAD_LEFT);
    }

    /**
     * 매장 코드 생성 (지사 코드 + 순번)
     */
    public static function generateStoreCode(string $branchCode, int $sequence): string
    {
        return $branchCode.'-ST-'.str_pad($sequence, 3, '0', STR_PAD_LEFT);
    }
}
