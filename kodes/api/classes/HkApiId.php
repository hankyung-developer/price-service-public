<?php 
/**
 * $ HKP ID 생성 및 파싱 유틸리티 클래스
 * 
 * SID(13자, 비가역)와 RID(가역, 복호화 가능) ID를 생성하고 파싱하는 기능을 제공합니다.
 * 규칙 기반 정규화를 통해 일관된 ID 생성을 보장합니다.
 * 
 * ## 사용법
 * - SID 생성 (13자, 비가역)
 * $sid = HKPId::sid('쌀  백미  20㎏', '특', '20 kg', 'KR-NAT', 'https://www.kamis.or.kr');
 * - RID 생성 (가역, 복호화 가능)
 * $rid = HKPId::rid('쌀  백미  20㎏', '특', '20 kg', 'KR-NAT', 'https://www.kamis.or.kr');
 * - RID 파싱
 * $orgItem = HKPId::parseRid($rid);
 * 결과: ['org'=>'KAMIS','item'=>'쌀.백미.20', 'grade'=>'특', 'unit'=>'20KG', 'market'=>'KR-NAT']
 * 
 * @author  Kodes <kodesinfo@gmail.com>
 * @version 1.1
 * @since   1.0
 *
 * @license 해당 프로그램은 kodes에서 제작된 프로그램으로 저작원은 코드스(https://www.kode.co.kr)
 */
namespace Kodes\Api;

final class HkApiId
{
    // 상수 정의
    private const BASE32_ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
    private const SID_LENGTH = 13;  // SID 길이
    private const RID_PREFIX = 'RID1-';  // RID 접두사
    private const CHECKSUM_LENGTH = 5;  // 체크섬 길이
    private const HASH_LENGTH = 10;  // 해시 길이
    private const KR_DOMAINS = ['go.kr', 'or.kr', 'ac.kr', 'co.kr', 're.kr', 'pe.kr'];  // 한국 도메인
    
    // 허용된 문자 패턴
    private const ALLOWED_CHARS_PATTERN = '/[^0-9A-Za-z가-힣._\-\s]/u';  // 허용된 문자 패턴
    private const MARKET_PATTERN = '/[^0-9A-Za-z._-]/u';  // 시장 코드 패턴
    private const RID_PATTERN = '/^RID1\-([0-9A-HJKMNP-TVWXYZ]{5})\-([A-Za-z0-9\-\_]+)$/';  // RID 패턴

    /**
     * NFKC 정규화 및 공백 정리
     * 
     * @param string|null $s 입력 문자열
     * @return string 정규화된 문자열
     */
    private static function nfkc(?string $s): string 
    {
        if ($s === null) {
            return '';
        }
        
        // NFKC 정규화 (유니코드 정규화)
        if (class_exists('Normalizer')) {
            $s = \Normalizer::normalize($s, \Normalizer::FORM_KC) ?? $s;
        }
        
        // 연속된 공백을 단일 공백으로 변환하고 앞뒤 공백 제거
        $s = trim(preg_replace('/\s+/u', ' ', $s) ?? '');
        
        return $s ?: '';
    }
    /**
     * 허용된 문자만 유지
     * 
     * @param string $s 입력 문자열
     * @return string 정리된 문자열
     */
    private static function keep(string $s): string 
    {
        // 전각 문자를 반각으로 변환
        $s = str_replace(['／', '－', '＿'], ['/', '-', '_'], $s);
        
        // 허용된 문자만 유지 (숫자, 영문, 한글, 점, 언더스코어, 하이픈, 공백)
        return preg_replace(self::ALLOWED_CHARS_PATTERN, '', $s);
    }
    
    /**
     * 상품명 정규화
     * 
     * @param string $s 상품명
     * @return string 정규화된 상품명
     */
    private static function normItem(string $s): string 
    {
        $s = self::keep(self::nfkc($s));
        
        // 공백을 점으로 변환하고 연속된 점을 단일 점으로 정리
        $s = str_replace(' ', '.', preg_replace('/\s+/u', ' ', $s));
        
        return trim(preg_replace('/\.+/', '.', $s), '.');
    }
    
    /**
     * 등급 정규화
     * 
     * @param string|null $s 등급 문자열
     * @return string 정규화된 등급
     */
    private static function normGrade(?string $s): string 
    {
        $s = self::keep(self::nfkc($s));
        
        if ($s === '') {
            return '';
        }
        
        // 슬래시를 하이픈으로 변환
        $s = str_replace(['/', '\\'], '-', $s);
        
        // 하이픈 주변 공백 정리
        return preg_replace('/\s*-\s*/', '-', $s);
    }
    
    /**
     * 단위 정규화
     * 
     * @param string|null $s 단위 문자열
     * @return string 정규화된 단위 (예: "20KG", "1개")
     */
    private static function normUnit(?string $s): string 
    {
        if ($s === null || $s === '') {
            return '';
        }
        
        $s = self::nfkc($s);
        
        if ($s === '') {
            return '';
        }
        
        // 공백, 점, 언더스코어, 하이픈, 슬래시 제거
        $raw = preg_replace('/[\s.\-_\/]+/u', '', $s);
        
        if ($raw === null || $raw === '') {
            return $s; // 정규식 실패 시 원본 반환
        }
        
        // 숫자와 문자 분리
        preg_match_all('/\p{N}+/u', $raw, $numbers);
        preg_match_all('/\p{L}+/u', $raw, $letters);
        
        $num = $numbers[0] ? implode('', $numbers[0]) : '';
        $let = $letters[0] ? implode('', $letters[0]) : '';
        
        // 영문자를 대문자로 변환
        if ($let !== '') {
            $let = preg_replace_callback('/[a-z]+/i', fn($m) => strtoupper($m[0]), $let);
        }
        
        return $num . $let;
    }
    
    /**
     * 시장 코드 정규화
     * 
     * @param string|null $s 시장 코드
     * @return string 정규화된 시장 코드
     */
    private static function normMarket(?string $s): string 
    {
        $s = self::nfkc($s);
        
        if ($s === '') {
            return '';
        }
        
        return preg_replace(self::MARKET_PATTERN, '', $s);
    }

    /**
     * URL에서 기관명 추출
     * 
     * @param string|null $url URL
     * @return string 기관명 (대문자)
     */
    private static function orgFromUrl(?string $url): string 
    {
        if (!$url) {
            return '';
        }
        
        $host = strtolower(parse_url($url, PHP_URL_HOST) ?? '');
        
        if ($host === '') {
            return '';
        }
        
        // IP 주소인 경우 IP를 그대로 반환 (대문자로 변환)
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return strtoupper($host);
        }
        
        $parts = explode('.', $host);
        $count = count($parts);
        
        if ($count < 2) {
            return strtoupper($host);
        }
        
        $tail = $parts[$count - 2] . '.' . $parts[$count - 1];
        
        // 한국 도메인 처리 (.go.kr, .or.kr 등)
        if (in_array($tail, self::KR_DOMAINS, true) && $count >= 3) {
            return strtoupper($parts[$count - 3]);
        }
        
        return strtoupper($parts[$count - 2]);
    }

    // ===== Base32 인코딩/디코딩 =====
    
    /**
     * 바이너리를 Base32로 인코딩
     * 
     * @param string $bin 바이너리 데이터
     * @return string Base32 인코딩된 문자열
     */
    private static function b32bin(string $bin): string 
    {
        $bits = '';
        
        // 각 바이트를 8비트 이진수로 변환
        foreach (str_split($bin) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }
        
        $output = '';
        
        // 5비트씩 묶어서 Base32 문자로 변환
        for ($i = 0; $i < strlen($bits); $i += 5) {
            $chunk = substr($bits, $i, 5);
            
            // 마지막 청크가 5비트 미만인 경우 패딩
            if (strlen($chunk) < 5) {
                $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            }
            
            $output .= self::BASE32_ALPHABET[bindec($chunk)];
        }
        
        return $output;
    }
    
    /**
     * 정수를 Base32로 인코딩
     * 
     * @param int $value 정수값
     * @param int $length 고정 길이 (패딩용)
     * @return string Base32 인코딩된 문자열
     */
    private static function b32int(int $value, int $length = 1): string 
    {
        $result = '';
        
        do {
            $result = self::BASE32_ALPHABET[$value % 32] . $result;
            $value = intdiv($value, 32);
        } while ($value > 0);
        
        return str_pad($result, $length, '0', STR_PAD_LEFT);
    }
    
    /**
     * Base64URL 인코딩
     * 
     * @param string $data 인코딩할 데이터
     * @return string Base64URL 인코딩된 문자열
     */
    private static function b64uEnc(string $data): string 
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    /**
     * Base64URL 디코딩
     * 
     * @param string $encoded 인코딩된 문자열
     * @return string 디코딩된 데이터
     */
    private static function b64uDec(string $encoded): string 
    {
        return base64_decode(strtr($encoded, '-_', '+/') . '==');
    }

    // ===== 공개 메서드: SID/RID 생성 및 파싱 =====
    
    /**
     * SID (Short ID) 생성 - 13자리 비가역 ID
     * 
     * @param string $item 상품명
     * @param string|null $grade 등급
     * @param string|null $unit 단위
     * @param string|null $market 시장코드
     * @param string|null $url 기관 URL
     * @return string 13자리 SID
     */
    public static function sid(string $item, ?string $grade = null, ?string $unit = null, ?string $market = null, ?string $url = null): string 
    {
        // 각 필드 정규화
        $org = self::orgFromUrl($url);
        $normalizedItem = self::normItem($item);
        $normalizedGrade = self::normGrade($grade);
        $normalizedUnit = self::normUnit($unit);
        $normalizedMarket = self::normMarket($market);
        
        // 헤더 비트 생성 (각 필드 존재 여부를 비트로 표현)
        $header = (($normalizedGrade !== '') << 2) | (($normalizedUnit !== '') << 1) | (($normalizedMarket !== '') << 0);
        
        // 정규화된 문자열 생성
        $canonical = 'org=' . ($org ?: '-') . 
                    '|item=' . ($normalizedItem ?: '-') . 
                    '|grade=' . ($normalizedGrade ?: '-') . 
                    '|unit=' . ($normalizedUnit ?: '-') . 
                    '|market=' . ($normalizedMarket ?: '-');
        
        // SHA256 해시 생성
        $hash = hex2bin(hash('sha256', $canonical, false));
        
        // 해시의 앞 10바이트를 Base32로 인코딩하여 12자리 생성
        $tail = substr(self::b32bin(substr($hash, 0, self::HASH_LENGTH)), 0, 12);
        
        // 헤더(1자리) + 테일(12자리) = 13자리 SID
        return self::b32int($header, 1) . $tail;
    }
    
    /**
     * RID (Reversible ID) 생성 - 가역 ID
     * 
     * @param string $item 상품명
     * @param string|null $grade 등급
     * @param string|null $unit 단위
     * @param string|null $market 시장코드
     * @param string|null $url 기관 URL
     * @return string RID 형식의 가역 ID
     */
    public static function rid(string $item, ?string $grade = null, ?string $unit = null, ?string $market = null, ?string $url = null): string 
    {
        // 각 필드 정규화
        $org = self::orgFromUrl($url);
        $normalizedItem = self::normItem($item);
        $normalizedGrade = self::normGrade($grade);
        $normalizedUnit = self::normUnit($unit);
        $normalizedMarket = self::normMarket($market);
        
        // 정규화된 문자열 생성
        $canonical = 'org=' . ($org ?: '-') . 
                    '|item=' . ($normalizedItem ?: '-') . 
                    '|grade=' . ($normalizedGrade ?: '-') . 
                    '|unit=' . ($normalizedUnit ?: '-') . 
                    '|market=' . ($normalizedMarket ?: '-');
        
        // 압축 및 Base64URL 인코딩
        $payload = self::b64uEnc(zlib_encode($canonical, ZLIB_ENCODING_DEFLATE));
        
        // 체크섬 생성
        $crc = pack('N', crc32($canonical));
        $checksum = substr(self::b32bin($crc), 0, self::CHECKSUM_LENGTH);
        
        return self::RID_PREFIX . $checksum . '-' . $payload;
    }
    
    /**
     * RID 파싱 - 가역 ID에서 원본 데이터 추출
     * 
     * @param string $rid RID 형식의 문자열
     * @return array 파싱된 데이터 배열
     * @throws \InvalidArgumentException RID 형식이 잘못된 경우
     * @throws \RuntimeException 디코딩 실패 또는 체크섬 불일치
     */
    public static function parseRid(string $rid): array 
    {
        // RID 형식 검증
        if (!preg_match(self::RID_PATTERN, $rid, $matches)) {
            throw new \InvalidArgumentException('RID 형식이 올바르지 않습니다: ' . $rid);
        }
        
        [, $checksum, $payload] = $matches;
        
        // 페이로드 디코딩
        $canonical = zlib_decode(self::b64uDec($payload));
        
        if ($canonical === false || $canonical === '') {
            throw new \RuntimeException('RID 디코딩에 실패했습니다');
        }
        
        // 체크섬 검증
        $expectedChecksum = substr(self::b32bin(pack('N', crc32($canonical))), 0, self::CHECKSUM_LENGTH);
        
        if ($checksum !== $expectedChecksum) {
            throw new \RuntimeException('RID 체크섬이 일치하지 않습니다');
        }
        
        echo "1";

        // 파싱된 데이터 초기화
        $result = [
            'org' => '',
            'item' => '',
            'grade' => '',
            'unit' => '',
            'market' => ''
        ];
        
        // 정규화된 문자열 파싱
        foreach (explode('|', $canonical) as $pair) {
            [$key, $value] = array_pad(explode('=', $pair, 2), 2, null);
            
            if (isset($result[$key])) {
                $result[$key] = ($value === '-') ? '' : $value;
            }
        }
        
        return $result + ['canonical' => $canonical];
    }
}
