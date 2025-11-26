<?php
/**
 * API 데이터 수집기 공통 기본 클래스
 * 
 * allGetAipData.php와 getApiData.php에서 공통으로 사용하는 기능을 제공
 * - API 호출 및 데이터 파싱
 * - 과거 가격 계산
 * - 데이터 저장 및 유효성 검사
 * - 조건 평가
 * - 로깅
 * 
 * @author  Kodes <kodesinfo@gmail.com>
 * @version 3.0
 * @since   2025-11-22
 * 
 * @license 해당 프로그램은 kodes에서 제작된 프로그램으로 저작권은 코드스(https://www.kode.co.kr)
 * 
 * 
 * todo list
 *  - api data field의 value date("Y-m",$curDate) 인경우 curDate 가 2025-10 이런식으로 나와야하는데 오늘 일자로 2025-11-21 이런식으로 저장되고 있음
 *  - 
 *  
 */

namespace Kodes\Wcms;

use Exception;
use DateTime;

require_once __DIR__ . '/DB.php';
require_once __DIR__ . '/Common.php';
require_once __DIR__ . '/Json.php';
require_once __DIR__ . '/Log.php';
require_once __DIR__ . '/HkApiId.php';

/**
 * API 데이터 수집기 기본 클래스
 */
abstract class ApiDataCollector
{
    protected $db;           // wcmsDB 연결
    protected $apiDb;        // apiDB 연결
    protected $common;       // Common 유틸리티
    protected $logFile;      // 로그 파일 경로
    protected $currentDate;  // 현재 처리 중인 날짜
    
    /**
     * 생성자
     * 
     * @param string $logSuffix 로그 파일 접미사
     */
    public function __construct($logSuffix = '')
    {
        $this->db = new DB("wcmsDB");
        $this->apiDb = new DB("apiDB");
        $this->common = new Common();
        
        // 로그 파일 설정
        $baseLogDir = !empty($this->common->dataDir) 
            ? $this->common->dataDir 
            : '/webSiteSource/wcms/cron/logs';
            
        $logFileName = 'api_collector' . $logSuffix . '_' . date('Y-m-d') . '.log';
        $this->logFile = $baseLogDir . '/' . $logFileName;

        // 로그 디렉토리 생성
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }

    // =========================================================================
    // API 통신
    // =========================================================================

    /**
     * API URL 생성
     * 
     * @param array $api API 설정
     * @param string $dateFormatted 포맷된 날짜
     * @return string 생성된 URL
     */
    protected function buildApiUrl($api, $dateFormatted)
    {
        $url = $api['url'];
        
        // {key} 치환
        if (!empty($api['key'])) {
            $url = str_replace('{key}', $api['key'], $url);
        }

        // {date}, {date-7 day}, {date+1 month} 등 다양한 데이트 토큰 처리
        if (preg_match_all('/{date[^}]*}/', $url, $matches)) {
            // 기본 {date} 먼저 치환 (기준일은 수집 대상일자)
            $url = str_replace('{date}', $dateFormatted, $url);

            // 나머지 {date...} 토큰들 치환
            $dateChar = $api['dateChar'] ?? 'Y-m-d';
            foreach ($matches[0] as $match) {
                if ($match === '{date}') {
                    continue;
                }
                // 예: {date-3 day} -> "{$dateFormatted}-3 day" 로 만들어 strtotime 처리
                $expr = str_replace(['date', '{', '}'], [$dateFormatted, '', ''], $match);
                $timestamp = strtotime($expr);
                if ($timestamp !== false) {
                    $replacement = date($dateChar, $timestamp);
                    $url = str_replace($match, $replacement, $url);
                }
            }
        }

        return $url;
    }

    /**
     * 날짜 포맷 변환
     * 
     * @param string $date 날짜
     * @param string $format 포맷
     * @return string 포맷된 날짜
     */
    protected function formatDate($date, $format)
    {
        return date($format ?: 'Y-m-d', strtotime($date));
    }

    /**
     * HTTP 헤더 파싱
     * 
     * @param string $headerParam 헤더 문자열 (줄바꿈으로 구분)
     * @return array 파싱된 헤더 배열
     */
    protected function parseHttpHeaders($headerParam)
    {
        // 기본 헤더
        $defaultHeaders = [
            'Accept: application/json, application/xml, text/xml, */*',
            'Accept-Language: ko-KR,ko;q=0.9,en;q=0.8',
            'Cache-Control: no-cache'
        ];
        
        // 커스텀 헤더가 없으면 기본 헤더 반환
        if (empty($headerParam)) {
            return $defaultHeaders;
        }
        
        // 커스텀 헤더 파싱
        $headers = [];
        $normalizedHeader = str_replace(["\r\n", "\r"], "\n", $headerParam);
        
        foreach (explode("\n", $normalizedHeader) as $headerLine) {
            $headerLine = trim($headerLine);
            
            if ($headerLine === '') {
                continue;
            }
            
            // "Name: Value" 형식이면 파싱, 아니면 그대로 추가
            if (strpos($headerLine, ':') !== false) {
                $parts = explode(':', $headerLine, 2);
                $headers[] = trim($parts[0]) . ': ' . trim($parts[1]);
            } else {
                $headers[] = $headerLine;
            }
        }
        
        return !empty($headers) ? $headers : $defaultHeaders;
    }

    /**
     * API 호출 (재시도 로직 포함)
     * 
     * @param string $url API URL
     * @param string $returnType 반환 타입 (JSON|XML)
     * @param string $headerParam 커스텀 헤더
     * @param int $retryCount 현재 재시도 횟수 (내부 사용)
     * @return array 파싱된 API 응답 데이터
     * @throws Exception API 호출 실패 시
     */
    protected function callApi($url, $returnType = 'JSON', $headerParam = '', $retryCount = 0)
    {
        // 재시도 설정
        $maxRetries = 5;           // 최대 재시도 횟수
        $retryDelay = 2;           // 재시도 간 대기 시간 (초)
        
        // 재시도 로그
        if ($retryCount > 0) {
            $this->log("API 재시도 ({$retryCount}/{$maxRetries}): {$url}", 'WARNING');
        } else {
            $this->log("API 호출: {$url}", 'WARNING');
        }
        
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'HKPrice-API-Collector/3.0');
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
            
            // 요청 헤더 설정
            $headers = $this->parseHttpHeaders($headerParam);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            $curlErrno = curl_errno($ch);
            curl_close($ch);
            
            // cURL 오류 발생 시
            if ($response === false) {
                // 타임아웃 관련 오류 코드 확인
                $isTimeoutError = in_array($curlErrno, [
                    CURLE_OPERATION_TIMEDOUT,  // 28: 타임아웃
                    CURLE_COULDNT_CONNECT,     // 7: 연결 실패
                    CURLE_COULDNT_RESOLVE_HOST // 6: 호스트 해석 실패
                ]);
                
                // 타임아웃 또는 연결 오류이고 재시도 횟수가 남은 경우
                if ($isTimeoutError && $retryCount < $maxRetries) {
                    $this->log("연결 오류 발생 (errno: {$curlErrno}): {$curlError} - {$retryDelay}초 후 재시도", 'WARNING');
                    sleep($retryDelay);
                    return $this->callApi($url, $returnType, $headerParam, $retryCount + 1);
                }
                
                // 재시도 횟수 초과 또는 재시도 불가능한 오류
                throw new Exception("cURL 오류 (errno: {$curlErrno}): " . $curlError);
            }
            
            // HTTP 오류 코드 확인
            if ($httpCode < 200 || $httpCode >= 300) {
                // 5xx 서버 오류인 경우 재시도
                if ($httpCode >= 500 && $httpCode < 600 && $retryCount < $maxRetries) {
                    $this->log("서버 오류 발생 (HTTP {$httpCode}) - {$retryDelay}초 후 재시도", 'WARNING');
                    sleep($retryDelay);
                    return $this->callApi($url, $returnType, $headerParam, $retryCount + 1);
                }
                
                throw new Exception("HTTP 오류 코드: " . $httpCode);
            }
            
            // 응답 데이터 파싱
            // 응답 데이터의 첫 글자가 '<'이면 XML, 아니면 JSON으로 처리
            if (isset($response[0]) && $response[0] === '<') {
                $data = json_decode(json_encode(simplexml_load_string($response, "SimpleXMLElement", LIBXML_NOCDATA)), true);
            } else {
                $data = json_decode($response, true);
            }
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("JSON 파싱 오류: " . json_last_error_msg());
            }
            
            // 재시도 성공 시 로그
            if ($retryCount > 0) {
                $this->log("API 재시도 성공 ({$retryCount}번째 시도)", 'INFO');
            }
            
            return $data;
            
        } catch (Exception $e) {
            // 예외 발생 시 재시도 가능한지 확인
            $errorMsg = $e->getMessage();
            $isRetryableError = (
                strpos($errorMsg, 'timeout') !== false ||
                strpos($errorMsg, 'timed out') !== false ||
                strpos($errorMsg, 'connection') !== false ||
                strpos($errorMsg, 'Connection refused') !== false
            );
            
            // 재시도 가능한 오류이고 재시도 횟수가 남은 경우
            if ($isRetryableError && $retryCount < $maxRetries) {
                $this->log("재시도 가능한 오류 발생: {$errorMsg} - {$retryDelay}초 후 재시도", 'WARNING');
                sleep($retryDelay);
                return $this->callApi($url, $returnType, $headerParam, $retryCount + 1);
            }
            
            // 재시도 불가능하거나 재시도 횟수 초과
            if ($retryCount > 0) {
                $this->log("API 재시도 실패 ({$retryCount}/{$maxRetries}): {$errorMsg}", 'ERROR');
            }
            
            throw $e;
        }
    }

    // =========================================================================
    // 데이터 파싱
    // =========================================================================

    /**
     * API 데이터 파싱
     * 
     * @param array $responseData API 응답 데이터
     * @param array $api API 설정
     * @param string $targetDate 대상 날짜
     * @return array 파싱된 데이터 배열
     * @throws Exception 리스트 데이터를 찾을 수 없을 때
     */
    protected function parseApiData($responseData, $api, $targetDate)
    {
        $parsedData = [];
        
        // listTag를 사용하여 데이터 추출
        $listTag = $api['listTag'] ?? "['data']['item']";
        $listPath = $this->parseListTag($listTag);
        $items = $this->getNestedValue($responseData, $listPath);

        if (empty($items) || !is_array($items)) {
            throw new Exception("리스트 데이터를 찾을 수 없습니다.");
        }
        
        foreach ($items as $index => $item) {
            // 조건 평가 먼저 수행
            if (!$this->evaluateItemConditions($item, $api)) {
                continue;
            }
            
            // keyField 값들 수집 (HkApiId 파라미터 순서에 맞게)
            $keyFields = ['', '', '', '']; // item, grade, unit, market 순서
            $i = 0;

            foreach ($api['items'] as $itemConfig) {
                if (!empty($itemConfig['field']) && $itemConfig['keyField'] == 1 && $itemConfig['field'] != 'date') {
                    $tag = $itemConfig['tag'] ?? '';
                    $defaultValue = $itemConfig['value'] ?? '';

                    // 우선 원본 데이터에서 추출
                    $value = '';
                    if ($tag !== '' && isset($item[$tag])) {
                        $value = $item[$tag];
                    }

                    // 태그가 없거나 값이 비어있으면 기본값 사용
                    if (($value === '' || $value === null) && $defaultValue !== '') {
                        $value = $defaultValue;
                    }

                    // HkApiId 파라미터 순서에 맞게 매핑
                    $keyFields[$i++] = $value;
                }
            }
            
            $apiUrl = $api['url'] ?? '';
            $sid = HkApiId::sid($keyFields[0], $keyFields[1], $keyFields[2], $keyFields[3], $apiUrl);
            $rid = HkApiId::rid($keyFields[0], $keyFields[1], $keyFields[2], $keyFields[3], $apiUrl);

            // 아이템 파싱
            $parsedItem = $this->parseItem($item, $api, $targetDate);

            if (!empty($parsedItem)) {
                // sid, rid 추가
                $parsedItem['sid'] = $sid;
                $parsedItem['rid'] = $rid;
                
                // 과거 가격 및 등락률 계산
                $this->calculateHistoricalPrices($parsedItem, $api, $targetDate);
                
                // 디버그 로그: 과거 가격 데이터 확인
                $historicalFields = array_filter($parsedItem, function($key) {
                    return strpos($key, 'Price') !== false || strpos($key, 'Change') !== false;
                }, ARRAY_FILTER_USE_KEY);
                
                if (!empty($historicalFields)) {
                    $this->log("과거 가격 데이터 추가됨: " . json_encode($historicalFields), 'DEBUG');
                }
                
                $parsedData[] = $parsedItem;
            }
        }

        $this->log("API 데이터 파싱 완료: 건수=" . count($parsedData), 'DEBUG');

        return $parsedData;
    }

    /**
     * listTag 파싱
     * 
     * @param string $listTag listTag 문자열
     * @return array 파싱된 경로 배열
     */
    protected function parseListTag($listTag)
    {
        // ['data']['item'] 형태를 ['data', 'item'] 배열로 변환
        preg_match_all("/\['([^']+)'\]/", $listTag, $matches);
        return $matches[1] ?? [];
    }

    /**
     * 중첩된 배열에서 값 추출
     * 
     * @param array $data 데이터 배열
     * @param array $path 경로 배열
     * @return mixed 추출된 값
     */
    protected function getNestedValue($data, $path)
    {
        foreach ($path as $key) {
            if (isset($data[$key])) {
                $data = $data[$key];
            } else {
                return null;
            }
        }
        return $data;
    }

    /**
     * 개별 아이템 파싱
     * 
     * @param array $item 아이템 데이터
     * @param array $api API 설정
     * @param string $targetDate 대상 날짜
     * @return array 파싱된 아이템
     */
    protected function parseItem($item, $api, $targetDate)
    {
        $parsedItem = [
            'date' => $targetDate,
            'id' => $api['id'],
            'coId' => $api['coId'],
            'categoryId' => $api['categoryId'] ?? '',
            'insert' => [
                'date' => date('Y-m-d H:i:s'),
                'managerId' => 'system',
                'managerName' => get_class($this), // 클래스 이름 사용
                'ip' => '127.0.0.1'
            ]
        ];
        
        // items 설정에 따라 필드 매핑
        foreach ($api['items'] as $itemConfig) {
            if (empty($itemConfig['field'])) {
                continue;
            }

            $tag = $itemConfig['tag'] ?? '';
            $field = $itemConfig['field'];
            $defaultValue = $itemConfig['value'] ?? '';

            // 원본 데이터 우선
            $value = '';
            if ($tag !== '' && isset($item[$tag])) {
                $value = $item[$tag];
            }

            // 값이 없고 기본값이 있으면 기본값 사용
            if (($value === '' || $value === null) && $defaultValue !== '') {
                if ($field === 'date') {
                    // date 필드는 기존의 eval/date 처리 로직 적용
                    if (strpos($defaultValue, 'date(') !== false || strpos($defaultValue, 'strtotime(') !== false) {
                        try {
                            // 날짜 형식이 Ym, Y-m, Y/m 인 경우 01일을 붙여서 완전한 날짜로 보정하여 오류 방지
                            $tempDate = isset($this->currentDate) ? $this->currentDate : '';
                            
                            if ($tempDate !== '') {
                                if (preg_match('/^\d{6}$/', $tempDate)) {
                                    $tempDate .= '01'; // 202510 -> 20251001
                                } elseif (preg_match('/^\d{4}-\d{2}$/', $tempDate)) {
                                    $tempDate .= '-01'; // 2025-10 -> 2025-10-01
                                } elseif (preg_match('/^\d{4}\/\d{2}$/', $tempDate)) {
                                    $tempDate .= '/01'; // 2025/10 -> 2025/10/01
                                }
                                $curDate = strtotime($tempDate);
                            } else {
                                $curDate = time();
                            }
                            $evalCode = $defaultValue;
                            $evalCode = str_replace('$curDate', "'{$curDate}'", $evalCode);
                            $value = eval("return {$evalCode};");
                        } catch (Exception $e) {
                            $this->log("PHP 코드 실행 오류: {$defaultValue} - " . $e->getMessage(), 'WARNING');
                            $value = date("Y-m-d");
                        }
                    } else {
                        $value = $defaultValue;
                    }
                } else {
                    $value = $defaultValue;
                }
            }

            // 기본값 적용 이후에도 여전히 값이 없으면 스킵 (단, '0' 또는 '-'는 허용)
            if (($value === '' || $value === null) && $value !== '0' && $value !== '-') {
                continue;
            }

            if ($field === 'date' && $value !== '' && $value !== null) {
                // date 필드 최종 정규화
                $value = date("Y-m-d", strtotime($value));
            }

            // 숫자 데이터 검증 및 정리
            if (is_string($value) && preg_match('/^[\d,\.]+$/', $value)) {
                $value = str_replace(',', '', $value);
                // 정수/실수 구분
                $value = strpos((string)$value, '.') !== false ? (float)$value : (int)$value;
            }
            if (is_string($value)) {
                $value = trim($value);
            }

            print_r($value);

            $parsedItem[$field] = $value;
        }
        
        return $parsedItem;
    }

    // =========================================================================
    // 조건 평가
    // =========================================================================

    /**
     * 아이템 전체에 대한 조건 평가 (AND 조건)
     * 
     * @param array $item 아이템 데이터
     * @param array $api API 설정
     * @return bool 조건 만족 여부
     */
    protected function evaluateItemConditions($item, $api)
    {
        $hasConditions = false;
        $allConditionsMet = true;
        
        // items 설정에서 조건이 있는 필드들을 찾아서 평가
        foreach ($api['items'] as $itemConfig) {
            if (empty($itemConfig['condition'])) {
                continue;
            }
            
            $hasConditions = true;
            $tag = $itemConfig['tag'];
            $value = $item[$tag] ?? '';
            $condition = $itemConfig['condition'];
            
            $conditionResult = $this->evaluateCondition($value, $condition);
            
            if (!$conditionResult) {
                $allConditionsMet = false;
                $this->log("조건 실패: {$tag} = '{$value}' 조건: '{$condition}'", 'DEBUG');
            } else {
                $this->log("조건 성공: {$tag} = '{$value}' 조건: '{$condition}'", 'DEBUG');
            }
        }
        
        // 조건이 없으면 포함, 조건이 있으면 모든 조건이 만족되어야 포함
        $result = !$hasConditions || $allConditionsMet;
        
        if ($hasConditions) {
            $this->log("조건 평가 결과: " . ($result ? '통과' : '실패'), 'DEBUG');
        }
        
        return $result;
    }

    /**
     * 조건 평가
     * 
     * @param mixed $value 비교할 값
     * @param string $condition 조건 문자열
     * @return bool 조건 만족 여부
     */
    protected function evaluateCondition($value, $condition)
    {
        try {
            $condition = trim($condition);
            $this->log("조건 평가: value='{$value}', condition='{$condition}'", 'DEBUG');
            
            // 연산자와 값 추출
            $operators = ['==', '!=', '>=', '<=', '>', '<'];
            foreach ($operators as $operator) {
                if (preg_match('/^' . preg_quote($operator, '/') . '\s*(.+)$/', $condition, $matches)) {
                    $expectedValue = trim($matches[1], " '\"");
                    return $this->compareValues($value, $expectedValue, $operator);
                }
            }
            
            $this->log("지원하지 않는 조건 형식: {$condition}", 'WARNING');
            return false;
            
        } catch (Exception $e) {
            $this->log("조건 평가 중 오류: {$condition} - " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * 두 값을 비교
     * 
     * @param mixed $value1 첫 번째 값
     * @param mixed $value2 두 번째 값
     * @param string $operator 비교 연산자
     * @return bool 비교 결과
     */
    protected function compareValues($value1, $value2, $operator)
    {
        // 등호/부등호 비교는 문자열로
        if ($operator === '==' || $operator === '!=') {
            $result = (string)$value1 === (string)$value2;
            return $operator === '==' ? $result : !$result;
        }
        
        // 날짜 비교
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value2)) {
            return $this->compareDates($value1, $value2, $operator);
        }
        
        // 숫자 비교
        if (is_numeric($value1) && is_numeric($value2)) {
            return $this->compareNumbers((float)$value1, (float)$value2, $operator);
        }
        
        // 문자열 비교
        return $this->compareStrings((string)$value1, (string)$value2, $operator);
    }

    /**
     * 날짜 비교
     * 
     * @param mixed $date1 첫 번째 날짜
     * @param mixed $date2 두 번째 날짜
     * @param string $operator 비교 연산자
     * @return bool 비교 결과
     */
    protected function compareDates($date1, $date2, $operator)
    {
        $dateObj1 = $this->parseDate($date1);
        $dateObj2 = $this->parseDate($date2);
        
        if (!$dateObj1 || !$dateObj2) {
            return false;
        }
        
        switch ($operator) {
            case '>':  return $dateObj1 > $dateObj2;
            case '>=': return $dateObj1 >= $dateObj2;
            case '<':  return $dateObj1 < $dateObj2;
            case '<=': return $dateObj1 <= $dateObj2;
            default:   return false;
        }
    }

    /**
     * 숫자 비교
     * 
     * @param float $num1 첫 번째 숫자
     * @param float $num2 두 번째 숫자
     * @param string $operator 비교 연산자
     * @return bool 비교 결과
     */
    protected function compareNumbers($num1, $num2, $operator)
    {
        switch ($operator) {
            case '>':  return $num1 > $num2;
            case '>=': return $num1 >= $num2;
            case '<':  return $num1 < $num2;
            case '<=': return $num1 <= $num2;
            default:   return false;
        }
    }

    /**
     * 문자열 비교
     * 
     * @param string $str1 첫 번째 문자열
     * @param string $str2 두 번째 문자열
     * @param string $operator 비교 연산자
     * @return bool 비교 결과
     */
    protected function compareStrings($str1, $str2, $operator)
    {
        $cmp = strcmp($str1, $str2);
        
        switch ($operator) {
            case '>':  return $cmp > 0;
            case '>=': return $cmp >= 0;
            case '<':  return $cmp < 0;
            case '<=': return $cmp <= 0;
            default:   return false;
        }
    }

    /**
     * 날짜 문자열을 DateTime 객체로 파싱
     * 
     * @param mixed $dateValue 날짜 값
     * @return DateTime|null 파싱된 DateTime 객체 또는 null
     */
    protected function parseDate($dateValue)
    {
        try {
            $dateString = (string)$dateValue;
            
            // 다양한 날짜 형식 지원
            $formats = [
                'Y-m-d',
                'Y-m-d H:i:s',
                'Y/m/d',
                'Y.m.d',
                'm/d/Y',
                'd/m/Y'
            ];
            
            foreach ($formats as $format) {
                $date = DateTime::createFromFormat($format, $dateString);
                if ($date !== false) {
                    return $date;
                }
            }
            
            // strtotime으로 시도
            $timestamp = strtotime($dateString);
            if ($timestamp !== false) {
                return new DateTime('@' . $timestamp);
            }
            
            return null;
            
        } catch (Exception $e) {
            return null;
        }
    }

    // =========================================================================
    // 과거 가격 계산
    // =========================================================================

    /**
     * 과거 가격 및 등락률 계산
     * 
     * @param array &$parsedItem 파싱된 아이템 데이터 (참조)
     * @param array $api API 설정
     * @param string $targetDate 대상 날짜
     */
    protected function calculateHistoricalPrices(&$parsedItem, $api, $targetDate)
    {
        // 현재 가격 데이터가 없으면 계산하지 않음
        if (!isset($parsedItem['price']) || empty($parsedItem['price']) || !is_numeric($parsedItem['price'])) {
            return;
        }
        
        $currentPrice = (float)$parsedItem['price'];
        $sid = $parsedItem['sid'] ?? '';
        
        if (empty($sid)) {
            return;
        }
        
        // 과거 날짜별 가격 조회 및 등락률 계산
        $periods = [
            'prevDayPrice' => ['type' => 'days', 'value' => 1, 'changeField' => 'prevDayChange'],
            'oneWeekAgoPrice' => ['type' => 'days', 'value' => 7, 'changeField' => 'oneWeekAgoChange'],
            'oneMonthAgoPrice' => ['type' => 'months', 'value' => 1, 'changeField' => 'oneMonthAgoChange'],
            'threeMonthsAgoPrice' => ['type' => 'months', 'value' => 3, 'changeField' => 'threeMonthsAgoChange'],
            'sixMonthsAgoPrice' => ['type' => 'months', 'value' => 6, 'changeField' => 'sixMonthsAgoChange'],
            'oneYearAgoPrice' => ['type' => 'years', 'value' => 1, 'changeField' => 'oneYearAgoChange']
        ];
        
        foreach ($periods as $priceField => $config) {
            $type = $config['type'];
            $value = $config['value'];
            $changeField = $config['changeField'];
            
            // 날짜 계산 방식에 따라 과거 날짜 계산
            if ($type === 'days') {
                $pastDate = date('Y-m-d', strtotime($targetDate . " -{$value} days"));
            } elseif ($type === 'months') {
                $pastDate = date('Y-m-d', strtotime($targetDate . " -{$value} months"));
            } elseif ($type === 'years') {
                $pastDate = date('Y-m-d', strtotime($targetDate . " -{$value} years"));
            }
            
            $this->log("과거 날짜 계산: {$priceField} = {$targetDate} - {$value} {$type} = {$pastDate}", 'DEBUG');
            
            $pastPrice = $this->getHistoricalPrice($sid, $api['coId'], $pastDate);
            
            if ($pastPrice !== null && $pastPrice > 0) {
                // 과거 가격 저장
                $parsedItem[$priceField] = $pastPrice;
                
                // 등락률 계산: ((현재가격 - 과거가격) / 과거가격) * 100
                $changeRate = (($currentPrice - $pastPrice) / $pastPrice) * 100;
                $parsedItem[$changeField] = round($changeRate, 2);
                
                $this->log("과거 가격 계산 완료: {$priceField} = {$pastPrice}, {$changeField} = {$parsedItem[$changeField]}%", 'DEBUG');
            } else {
                // 과거 데이터가 없는 경우 - null 대신 빈 문자열로 설정
                $parsedItem[$priceField] = '';
                $parsedItem[$changeField] = '';
                
                $this->log("과거 가격 데이터 없음: {$priceField} (기준일: {$pastDate})", 'DEBUG');
            }
        }
    }

    /**
     * 과거 가격 조회 (기준일 포함 과거 15일 범위 내 최신 데이터)
     * 
     * @param string $sid 시리즈 ID
     * @param string $coId 회사 ID
     * @param string $date 기준 날짜
     * @return float|null 과거 가격 또는 null
     */
    protected function getHistoricalPrice($sid, $coId, $date)
    {
        try {
            // 기준 날짜 포함하여 과거 15일 범위 설정
            $endDate = date('Y-m-d', strtotime($date)); // 기준 날짜 포함
            $startDate = date('Y-m-d', strtotime($date . " -15 days")); // 기준 날짜 15일 전
            
            $filter = [
                'sid' => $sid,
                'coId' => $coId,
                'date' => [
                    '$gte' => $startDate,
                    '$lte' => $endDate
                ]
            ];
            
            $options = [
                'projection' => ['_id' => 0, 'price' => 1, 'date' => 1],
                'sort' => ['date' => -1], // 날짜 내림차순 (최신 데이터 우선)
                'limit' => 1
            ];
            
            $this->log("과거 가격 조회: SID={$sid}, 범위={$startDate}~{$endDate}, 요청일={$date}", 'DEBUG');
            
            $result = $this->apiDb->list('apiData', $filter, $options);
            
            if (!empty($result) && isset($result[0]['price']) && is_numeric($result[0]['price'])) {
                $foundDate = $result[0]['date'];
                $price = (float)$result[0]['price'];
                
                // 로그에 실제 찾은 날짜 기록
                if ($foundDate !== $date) {
                    $this->log("과거 가격 조회: 요청일자 {$date} → 실제 데이터 {$foundDate} 사용 (가격: {$price})", 'DEBUG');
                } else {
                    $this->log("과거 가격 조회: 정확한 날짜 {$date} 데이터 발견 (가격: {$price})", 'DEBUG');
                }
                
                return $price;
            }
            
            $this->log("과거 가격 조회: 데이터 없음 (SID={$sid}, 범위={$startDate}~{$endDate})", 'DEBUG');
            return null;
            
        } catch (Exception $e) {
            $this->log("과거 가격 조회 중 오류: " . $e->getMessage(), 'WARNING');
            return null;
        }
    }

    // =========================================================================
    // 데이터 저장 및 유효성 검사
    // =========================================================================

    /**
     * API 데이터 저장
     * 
     * @param array $api API 설정
     * @param array $parsedData 파싱된 데이터
     * @param string $targetDate 대상 날짜
     */
    protected function saveApiData($api, $parsedData, $targetDate)
    {
        $collection = 'apiData';
        $upsertCount = 0;
        $errorCount = 0;
        $duplicateCount = 0;
        
        foreach ($parsedData as $item) {
            try {
                // upsert 필터 조건 생성 (coId, id, sid, date)
                $sid = isset($item['sid']) ? $item['sid'] : '';
                $filter = [
                    'coId' => $api['coId'],
                    'id' => $api['id'],
                    'sid' => $sid,
                    'date' => $targetDate
                ];
                
                // 디버그 로그: 저장될 데이터 확인
                $historicalFields = array_filter($item, function($key) {
                    return strpos($key, 'Price') !== false || strpos($key, 'Change') !== false;
                }, ARRAY_FILTER_USE_KEY);
                
                if (!empty($historicalFields)) {
                    $this->log("저장될 과거 가격 데이터: " . json_encode($historicalFields), 'DEBUG');
                }
                
                // upsert 실행 (기존 데이터가 있으면 업데이트, 없으면 삽입)
                $result = $this->apiDb->upsert($collection, $filter, $item);
                
                // MongoDB upsert 결과 확인
                $upsertedCount = is_object($result) ? $result->getUpsertedCount() : 0;
                $modifiedCount = is_object($result) ? $result->getModifiedCount() : 0;
                $matchedCount = is_object($result) ? $result->getMatchedCount() : 0;
                
                if ($upsertedCount > 0) {
                    // 새로 삽입된 경우
                    $upsertCount++;
                    $sidDisplay = isset($item['sid']) ? $item['sid'] : 'N/A';
                    $itemName = isset($item['itemName']) ? $item['itemName'] : 'N/A';
                    $price = isset($item['price']) ? $item['price'] : 'N/A';
                    $this->log("삽입: {$itemName} | {$price} | SID: {$sidDisplay} | 날짜: {$targetDate}");
                    
                    // 과거 가격 데이터 삽입 로그
                    if (!empty($historicalFields)) {
                        $this->log("과거 가격 데이터 삽입됨: " . json_encode($historicalFields), 'INFO');
                    }
                } elseif ($modifiedCount > 0) {
                    // 업데이트된 경우
                    $upsertCount++;
                    $sidDisplay = isset($item['sid']) ? $item['sid'] : 'N/A';
                    $itemName = isset($item['itemName']) ? $item['itemName'] : 'N/A';
                    $price = isset($item['price']) ? $item['price'] : 'N/A';
                    $this->log("수정: {$itemName} | {$price} | SID: {$sidDisplay} | 날짜: {$targetDate}");
                    
                    // 과거 가격 데이터 업데이트 로그
                    if (!empty($historicalFields)) {
                        $this->log("과거 가격 데이터 업데이트됨: " . json_encode($historicalFields), 'INFO');
                    }
                } elseif ($matchedCount > 0) {
                    // 매칭되었지만 수정되지 않은 경우 (데이터가 동일)
                    $duplicateCount++;
                    $this->log("데이터 동일 (수정 불필요): SID: {$sid} | 날짜: {$targetDate}", 'DEBUG');
                } else {
                    // 예상치 못한 경우
                    $duplicateCount++;
                    $this->log("예상치 못한 upsert 결과: SID: {$sid} | 날짜: {$targetDate}", 'WARNING');
                }
                
            } catch (Exception $e) {
                $errorCount++;
                $errorMessage = $e->getMessage();
                
                // unique index 위반 오류 처리
                if (strpos($errorMessage, 'duplicate key') !== false || 
                    strpos($errorMessage, 'E11000') !== false ||
                    strpos($errorMessage, 'unique') !== false ||
                    strpos($errorMessage, 'duplicate') !== false) {
                    $duplicateCount++;
                    $this->log("Unique index 위반 (중복 데이터): {$collection} (날짜: {$targetDate}, sid: {$sid}) - 무시됨", 'WARNING');
                } elseif (strpos($errorMessage, 'timeout') !== false ||
                         strpos($errorMessage, 'connection') !== false) {
                    $this->log("데이터베이스 연결 오류: {$errorMessage} - 재시도 필요", 'ERROR');
                } elseif (strpos($errorMessage, 'validation') !== false ||
                         strpos($errorMessage, 'schema') !== false) {
                    $this->log("데이터 검증 오류: {$errorMessage}", 'ERROR');
                } else {
                    $this->log("데이터 저장 중 알 수 없는 오류: {$errorMessage}", 'ERROR');
                }
            }
        }
        
        $this->log("데이터 저장 완료: upsert {$upsertCount}건, 중복 {$duplicateCount}건, 오류 {$errorCount}건");
    }

    /**
     * data 필드 유효성 검사
     * 파싱된 데이터에 유효한 data 또는 price 필드가 있는지 확인
     * 
     * @param array $parsedData 파싱된 데이터
     * @return bool 유효성 여부
     */
    protected function hasValidDataField($parsedData)
    {
        $validCount = 0;
        $totalCount = count($parsedData);
        
        // 빈 배열은 유효하지 않음
        if (empty($parsedData)) {
            return false;
        }
        
        foreach ($parsedData as $index => $item) {
            $hasValidValue = false;
            
            // data 필드 체크
            if (isset($item['data'])) {
                $dataValue = $item['data'];
                if ($dataValue !== null && $dataValue !== '-' && $dataValue !== '') {
                    $hasValidValue = true;
                }
            }
            
            // data 필드가 없으면 price 필드 체크 (fallback)
            if (!$hasValidValue && isset($item['price'])) {
                $priceValue = $item['price'];
                if ($priceValue !== null && $priceValue !== '-' && $priceValue !== '') {
                    $hasValidValue = true;
                }
            }
            
            if ($hasValidValue) {
                $validCount++;
            } else {
                $this->log("유효하지 않은 data/price 값 발견 (항목 {$index})", 'DEBUG');
            }
        }
        
        $this->log("data 필드 유효성 검사: {$validCount}/{$totalCount} 유효", 'DEBUG');
        
        // 최소 하나의 유효한 값이 있어야 함
        return $validCount > 0;
    }

    // =========================================================================
    // API 상태 관리
    // =========================================================================

    /**
     * API 실행 시간 업데이트
     * 
     * @param string $apiId API ID
     */
    protected function updateApiLastRun($apiId)
    {
        try {
            $filter = ['id' => $apiId];
            $updateData = ['$set' => [
                'lastRun' => date('Y-m-d H:i:s'),
                'lastRunStatus' => 'success'
            ]];
            $this->db->update('api', $filter, $updateData);
        } catch (Exception $e) {
            $this->log("API 실행 시간 업데이트 실패: " . $e->getMessage(), 'WARNING');
        }
    }

    /**
     * API 실행 실패 기록
     * 
     * @param string $apiId API ID
     * @param string $errorMessage 오류 메시지
     */
    protected function recordApiFailure($apiId, $errorMessage)
    {
        try {
            $filter = ['id' => $apiId];
            $updateData = ['$set' => [
                'lastRun' => date('Y-m-d H:i:s'),
                'lastRunStatus' => 'failed',
                'lastError' => $errorMessage
            ]];
            $this->db->update('api', $filter, $updateData);
        } catch (Exception $e) {
            $this->log("API 실패 기록 실패: " . $e->getMessage(), 'WARNING');
        }
    }

    // =========================================================================
    // 로깅
    // =========================================================================

    /**
     * 로그 기록
     * 
     * @param string $message 로그 메시지
     * @param string $level 로그 레벨 (DEBUG|INFO|WARNING|ERROR)
     */
    protected function log($message, $level = 'INFO')
    {
        // 로그 레벨 필터링
        $levelMap = [
            'DEBUG' => 10,
            'INFO' => 20,
            'WARNING' => 30,
            'ERROR' => 40
        ];
        $currentLevelName = defined('LOG_LEVEL') ? LOG_LEVEL : 'WARNING';
        $currentLevel = $levelMap[$currentLevelName] ?? 30;
        $incomingLevel = $levelMap[$level] ?? 20;
        
        if ($incomingLevel < $currentLevel) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
        
        // 콘솔 출력
        echo $logMessage;
        
        // 파일 로그 (logFile이 유효한 경우에만)
        if (!empty($this->logFile) && is_string($this->logFile)) {
            try {
                // 로그 디렉토리가 존재하는지 확인
                $logDir = dirname($this->logFile);
                if (!is_dir($logDir)) {
                    mkdir($logDir, 0755, true);
                }
                
                file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
            } catch (Exception $e) {
                // 로그 파일 쓰기 실패 시 콘솔에만 출력
                echo "[ERROR] 로그 파일 쓰기 실패: " . $e->getMessage() . PHP_EOL;
            }
        }
    }
}

