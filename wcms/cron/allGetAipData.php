<?php
/**
 * API 데이터 수집 스크립트 (전체 또는 특정 API)
 * 
 * 사용법:
 * php allGetAipData.php [API_ID] [START_DATE] [END_DATE]
 * 
 * 예시:
 * php allGetAipData.php api001             # 특정 API의 모든 데이터 처리 (최대 10년)
 * php allGetAipData.php api001 2024-01-01  # 특정 API의 2024-01-01 이후 데이터만 처리
 * php allGetAipData.php api001 2024-01-01 2024-01-31  # 특정 API의 2024-01-01부터 2024-01-31까지 처리
 * 
 * @author  Kodes <kodesinfo@gmail.com>
 * @version 2.0
 * 
 * @license 해당 프로그램은 kodes에서 제작된 프로그램으로 저작원은 코드스(https://www.kode.co.kr)
 */

// 오류 보고 설정 (프로덕션)
// error_reporting(E_ALL);
// ini_set('display_errors', 0);

// 메모리 및 실행 시간 제한 설정
ini_set('memory_limit', '512M');
set_time_limit(600); // 10분 제한

// 로그 레벨 설정: DEBUG < INFO < WARNING < ERROR
define('LOG_LEVEL', 'WARNING');

// CLI 환경에서 필요한 전역 변수 설정
if (!isset($_SESSION)) {
    $_SESSION = [];
}
$_SESSION['coId'] = 'hkp';
$GLOBALS['deviceType'] = 'pc';

// CLI 환경에서 필요한 설정 파일 경로 설정
$GLOBALS['common'] = [
    'path' => [
        'data' => __DIR__ . '/logs'
    ]
];

// DB 설정 파일 경로 수정을 위한 전역 변수 설정
$GLOBALS['db_config_path'] = __DIR__ . '/../../kodes/wcms/config/db.json';

// 클래스 직접 로드
require_once __DIR__ . '/../../kodes/wcms/classes/DB.php';
require_once __DIR__ . '/../../kodes/wcms/classes/Common.php';
require_once __DIR__ . '/../../kodes/wcms/classes/Json.php';
require_once __DIR__ . '/../../kodes/wcms/classes/Log.php';
require_once __DIR__ . '/../../kodes/wcms/classes/HkApiId.php';

use Kodes\Wcms\DB;
use Kodes\Wcms\Common;
use Kodes\Wcms\HkApiId;

/**
 * API 데이터 수집 스크립트 클래스
 */
class AllApiDataCollector
{
    private $db;
    private $apiDb;
    private $common;
    private $logFile;
    private $apiId = null;
    private $startDate = null;
    private $endDate = null;
    private $migrationFile = null;
    private $isMigration = false;
    private $runningFile = null;  // 실행 중임을 나타내는 파일 경로
    private $currentDate = null; // 실행중인 날짜.

    public function __construct($apiId = null, $startDate = null, $endDate = null)
    {
        $this->db = new DB("wcmsDB");
        $this->apiDb = new DB("apiDB");
        $this->common = new Common();
        
        $this->apiId = $apiId;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        
        // Migration 파일 확인
        if ($apiId) {
            $migrationDir = '/webSiteSource/wcms/cron/migration';
            $this->migrationFile = $migrationDir . '/' . $apiId . '.txt';
            $this->isMigration = file_exists($this->migrationFile);
            
            // Migration 실행 중인지 확인 (다른 확장자 파일 존재 시 실행 중)
            if ($this->isMigration) {
                $this->checkMigrationStatus();
            }
            
            // Running 파일 경로 설정 (Migration인 경우)
            // checkMigrationStatus()에서 .running으로 변경되므로 그 경로 사용
            $this->runningFile = $migrationDir . '/' . $apiId . '.running';
        }
        
        // 로그 파일 설정
        $logSuffix = $apiId ? "_api_{$apiId}" : "_all";
        
        // dataDir이 비어있거나 null인 경우 기본 경로 사용
        $baseLogDir = !empty($this->common->dataDir) ? $this->common->dataDir : '/webSiteSource/wcms/cron/logs';
        $this->logFile = $baseLogDir . '/all_api_collector' . $logSuffix . '_' . date('Y-m-d') . '.log';

        // 로그 디렉토리 생성
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }

    /**
     * 메인 실행 함수
     */
    public function run()
    {
        $this->log("API 데이터 수집 시작: " . date('Y-m-d H:i:s'));
        $this->log("파라미터 - API ID: " . ($this->apiId ?: 'ALL') . ", 시작일자: " . ($this->startDate ?: 'ALL') . ", 종료일자: " . ($this->endDate ?: 'ALL'));
        
        // Migration 시작 상태 업데이트
        if ($this->isMigration && $this->migrationFile) {
            $this->updateMigrationStatusToRunning();
        }
        
        try {
            // 실행 가능한 API 목록 조회
            $apis = $this->getTargetApis();
            
            if (empty($apis)) {
                $this->log("실행할 API가 없습니다.");
                if (!$this->apiId) {
                    // API ID가 지정되지 않은 경우 사용 가능한 API 목록 표시
                    $availableApis = $this->getAvailableApis();
                    $this->showAvailableApis($availableApis);
                }
                $this->showUsage();
                return;
            }
            
            $this->log("총 " . count($apis) . "개의 API를 처리합니다.");
            
            $successCount = 0;
            $errorCount = 0;
            
            foreach ($apis as $api) {
                try {
                    // Running 파일이 존재하는지 확인 (파일이 삭제되면 프로세스 종료)
                    $this->checkRunningFile();
                    
                    $this->processApi($api);
                    $successCount++;
                } catch (Exception $e) {
                    $errorCount++;
                    $this->log("API 처리 실패 ({$api['title']}): " . $e->getMessage(), 'ERROR');
                    
                    // Running 파일 삭제로 인한 종료인 경우 더 이상 진행하지 않음
                    if (strpos($e->getMessage(), 'Running 파일이 삭제되었습니다') !== false) {
                        $this->log("Running 파일 삭제로 인해 전체 프로세스를 종료합니다.", 'WARNING');
                        break;
                    }
                }
            }
            
            $this->log("API 처리 완료 - 성공: {$successCount}개, 실패: {$errorCount}개");
            
        } catch (Exception $e) {
            $this->log("스크립트 실행 중 오류: " . $e->getMessage(), 'ERROR');
        }
        
        $this->log("API 데이터 수집 종료: " . date('Y-m-d H:i:s'));
    }

    /**
     * 대상 API 목록 조회
     */
    private function getTargetApis()
    {
        $filter = [
            'isUse' => 'Y',
            'coId' => 'hkp' // 기본 회사 ID
        ];
        
        // 특정 API ID가 지정된 경우
        if ($this->apiId) {
            $filter['id'] = $this->apiId;
        }
        
        $options = [
            'sort' => ['title' => 1]
        ];
        
        $apis = $this->db->list('api', $filter, $options);
        
        if ($this->apiId && empty($apis)) {
            // 사용 가능한 API 목록 조회
            $availableApis = $this->getAvailableApis();
            $this->showAvailableApis($availableApis);
            throw new Exception("지정된 API ID '{$this->apiId}'를 찾을 수 없습니다.");
        }
        
        return $apis;
    }

    /**
     * 사용 가능한 API 목록 조회
     */
    public function getAvailableApis()
    {
        $filter = [
            'isUse' => 'Y',
            'coId' => 'hkp'
        ];
        
        $options = [
            'sort' => ['title' => 1],
            'projection' => ['id' => 1, 'title' => 1, 'description' => 1]
        ];
        
        return $this->db->list('api', $filter, $options);
    }

    /**
     * 사용 가능한 API 목록 표시
     */
    public function showAvailableApis($apis)
    {
        if (empty($apis)) {
            echo "\n사용 가능한 API가 없습니다.\n";
            return;
        }
        
        echo "\n=== 사용 가능한 API 목록 ===\n";
        echo "ID\t\t제목\n";
        echo str_repeat("-", 50) . "\n";
        
        foreach ($apis as $api) {
            $id = $api['id'] ?? 'N/A';
            $title = $api['title'] ?? 'N/A';
            $description = $api['description'] ?? '';
            
            // ID 길이에 따라 탭 조정
            $tabCount = strlen($id) > 8 ? 1 : 2;
            $tabs = str_repeat("\t", $tabCount);
            
            echo "{$id}{$tabs}{$title}";
            if (!empty($description)) {
                echo " - {$description}";
            }
            echo "\n";
        }
        
        echo "\n사용법: php allGetAipData.php [API_ID] [START_DATE] [END_DATE]\n";
        echo "예시: php allGetAipData.php {$apis[0]['id']} 2024-01-01\n\n";
    }

    /**
     * 개별 API 처리
     */
    private function processApi($api)
    {
        $this->log("API 처리 시작: {$api['title']} (ID: {$api['id']})");
        
        try {
            // API 데이터 수집
            $this->collectApiData($api);
            
            // 성공 기록
            $this->updateApiLastRun($api['id']);
            
            // Migration 완료 처리
            $this->completeMigration();
            
        } catch (Exception $e) {
            $this->log("API 처리 중 오류 ({$api['title']}): " . $e->getMessage(), 'ERROR');
            
            // 실패 기록
            $this->recordApiFailure($api['id'], $e->getMessage());
            
            // Migration 실패 상태 업데이트
            $this->updateMigrationStatusToFailed($e->getMessage());
            
            throw $e;
        }
    }

    /**
     * API 데이터 수집
     */
    private function collectApiData($api)
    {
        $currentDate = date('Y-m-d');
        $totalSuccessCount = 0;
        $totalErrorCount = 0;
        $consecutiveErrors = 0;
        $maxConsecutiveErrors = 10; // 연속 오류 허용 횟수 (로깅용)
        
        // 수집 범위 설정
        $startDate = $this->startDate ?: $currentDate;
        $endDate = $this->endDate ?: $currentDate;
        
        // 시작일자와 종료일자 사이의 일수 계산
        $startTimestamp = strtotime($startDate);
        $endTimestamp = strtotime($endDate);
        $totalDays = ($endTimestamp - $startTimestamp) / (24 * 60 * 60) + 1;
        
        $this->log("API 데이터 수집 시작: {$api['title']} (총 {$totalDays}일, 시작일자: {$startDate}, 종료일자: {$endDate}, 처리순서: 과거순)");
        
        // 시작일자부터 종료일자까지 순회
        for ($dayOffset = 0; $dayOffset < $totalDays; $dayOffset++) {
            // Running 파일이 존재하는지 확인 (파일이 삭제되면 프로세스 종료)
            $this->checkRunningFile();
            
            $targetDate = date('Y-m-d', strtotime($startDate . " +{$dayOffset} days"));
            
            // 종료일자를 초과하면 중단
            if ($targetDate > $endDate) {
                $this->log("종료일자 초과로 중단: {$targetDate}");
                break;
            }
            
            // Migration 상태 업데이트
            $this->updateMigrationStatus($targetDate, $dayOffset + 1, $totalDays);
            
            $dateFormatted = $this->formatDate($targetDate, $api['dateChar']);
            
            $this->log("API 호출 시도: {$api['title']} (날짜: {$targetDate}, 오프셋: {$dayOffset})");
            $this->currentDate = $targetDate;

            try {
                // API URL 생성
                $url = $this->buildApiUrl($api, $dateFormatted);
                
                // API 호출 (API 설정에 header가 있으면 함께 전송)
                $responseData = $this->callApi($url, $api['returnType'], $api['header'] ?? '');
                
                if (empty($responseData)) {
                    $this->log("API 응답이 비어있음: {$api['title']} (날짜: {$targetDate})", 'WARNING');
                    $consecutiveErrors++;
                    $totalErrorCount++;
                    
                    // 연속 오류가 많으면 경고 로그만 출력하고 계속 진행
                    if ($consecutiveErrors >= $maxConsecutiveErrors) {
                        $this->log("연속 오류 {$consecutiveErrors}회 발생. 종료일자까지 계속 진행합니다.", 'WARNING');
                    }
                    continue;
                }
                
                // 데이터 파싱 및 저장
                $parsedData = $this->parseApiData($responseData, $api, $targetDate);
                
                if (!empty($parsedData)) {
                    // data 필드 유효성 검사
                    if ($this->hasValidDataField($parsedData)) {
                        $this->saveApiData($api, $parsedData, $targetDate);
                        $totalSuccessCount++;
                        $consecutiveErrors = 0; // 성공 시 연속 오류 카운트 리셋
                        $this->log("API 데이터 저장 완료: {$api['title']} (날짜: {$targetDate}, 데이터 수: " . count($parsedData) . ")");
                    } else {
                        $this->log("data 필드가 유효하지 않음: {$api['title']} (날짜: {$targetDate})", 'WARNING');
                        $consecutiveErrors++;
                        $totalErrorCount++;
                    }
                } else {
                    $this->log("파싱된 데이터가 없음: {$api['title']} (날짜: {$targetDate})", 'WARNING');
                    $consecutiveErrors++;
                    $totalErrorCount++;
                }
                
            } catch (Exception $e) {
                $this->log("API 호출 실패: {$api['title']} (날짜: {$targetDate}): " . $e->getMessage(), 'WARNING');
                $consecutiveErrors++;
                $totalErrorCount++;
                
                // 연속 오류가 많으면 경고 로그만 출력하고 계속 진행
                if ($consecutiveErrors >= $maxConsecutiveErrors) {
                    $this->log("연속 오류 {$consecutiveErrors}회 발생. 종료일자까지 계속 진행합니다.", 'WARNING');
                }
            }
            
            // API 호출 간격 대기 (서버 부하 방지)
            if ($dayOffset < $totalDays - 1) {
                sleep(1);
            }
        }

        $this->log("API 데이터 수집 완료: {$api['title']} (성공: {$totalSuccessCount}일, 총오류: {$totalErrorCount}일, 최대연속오류: {$consecutiveErrors}회)");
    }

    /**
     * 수집 범위 결정
     */
    // getCollectRange 메서드는 현재 사용하지 않으므로 제거하였습니다.

    /**
     * API URL 생성
     */
    private function buildApiUrl($api, $dateFormatted)
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
     */
    private function formatDate($date, $format)
    {
        return date($format, strtotime($date));
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
    private function callApi($url, $returnType = 'JSON', $headerParam = '', $retryCount = 0)
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
            curl_setopt($ch, CURLOPT_USERAGENT, 'HKPrice-All-API-Collector/2.0');
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
            
            // 요청 헤더 설정: API 설정에 헤더가 있으면 해당 헤더 사용, 없으면 기본 헤더 사용
            if (!empty($headerParam)) {
                $requestHeaders = [];
                $normalizedHeader = str_replace(["\r\n", "\r"], "\n", $headerParam);
                foreach (explode("\n", $normalizedHeader) as $headerLine) {
                    $headerLine = trim($headerLine);
                    if ($headerLine === '') {
                        continue;
                    }
                    if (strpos($headerLine, ':') !== false) {
                        $parts = explode(':', $headerLine, 2);
                        $headerName = trim($parts[0]);
                        $headerValue = trim($parts[1]);
                        $requestHeaders[] = $headerName . ': ' . $headerValue;
                    } else {
                        // 콜론이 없는 비정상적인 헤더 라인은 그대로 전달
                        $requestHeaders[] = $headerLine;
                    }
                }
                if (!empty($requestHeaders)) {
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $requestHeaders);
                }
            } else {
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Accept: application/json, application/xml, text/xml, */*',
                    'Accept-Language: ko-KR,ko;q=0.9,en;q=0.8',
                    'Cache-Control: no-cache'
                ]);
            }
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
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

    /**
     * API 데이터 파싱
     */
    private function parseApiData($responseData, $api, $targetDate)
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
            $i=0;

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
                    $keyFields[$i++] = $value; // item
                }
            }
            
            $apiUrl = $api['url'] ?? '';
            //$this->log("keyFields: ".json_encode($keyFields), 'DEBUG');
            $sid = HkApiId::sid($keyFields[0], $keyFields[1], $keyFields[2], $keyFields[3], $apiUrl);
            $rid = HkApiId::rid($keyFields[0], $keyFields[1], $keyFields[2], $keyFields[3], $apiUrl);

            // 아이템 파싱
            $parsedItem = $this->parseItem($item, $api, $targetDate);

            if (!empty($parsedItem)) {
                // sid, rid 추가
                $parsedItem['sid'] = $sid;
                $parsedItem['rid'] = $rid;
                
                // 과거 가격 및 등락률 계산 (parseApiData에서 직접 호출)
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

        $this->log("API 데이터 파싱 완료: " . json_encode($parsedData), 'WARNING');

        return $parsedData;
    }

    /**
     * listTag 파싱
     */
    private function parseListTag($listTag)
    {
        // ['data']['item'] 형태를 ['data', 'item'] 배열로 변환
        preg_match_all("/\['([^']+)'\]/", $listTag, $matches);
        return $matches[1] ?? [];
    }

    /**
     * 중첩된 배열에서 값 추출
     */
    private function getNestedValue($data, $path)
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
     */
    private function parseItem($item, $api, $targetDate)
    {
        $parsedItem = [
            'date' => $targetDate,
            'id' => $api['id'],
            'coId' => $api['coId'],
            'categoryId' => $api['categoryId'] ?? '', // API 설정의 categoryId 추가
            'insert' => [
                'date' => date('Y-m-d H:i:s'),
                'managerId' => 'system',
                'managerName' => 'All API Collector',
                'ip' => '127.0.0.1'
            ]
        ];
        
        // items 설정에 따라 필드 매핑
        foreach ($api['items'] as $itemConfig) {
            if (empty($itemConfig['field'])) {
                continue; // field가 없는 경우 제외
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
                            $curDate = isset($this->currentDate) && $this->currentDate !== '' ? strtotime($this->currentDate) : time();
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

            $parsedItem[$field] = $value;
        }
        
        return $parsedItem;
    }
    
    /**
     * 아이템 전체에 대한 조건 평가 (AND 조건)
     * 
     * @param array $item 아이템 데이터
     * @param array $api API 설정
     * @return bool 조건 만족 여부
     */
    private function evaluateItemConditions($item, $api)
    {
        $hasConditions = false;
        $allConditionsMet = true;
        
        // items 설정에서 조건이 있는 필드들을 찾아서 평가
        foreach ($api['items'] as $itemConfig) {
            if (empty($itemConfig['condition'])) {
                continue; // 조건이 없으면 건너뛰기
            }
            
            $hasConditions = true;
            $tag = $itemConfig['tag'];
            $value = $item[$tag] ?? '';
            $condition = $itemConfig['condition'];
            
            $conditionResult = $this->evaluateCondition($value, $condition);
            
            if (!$conditionResult) {
                $allConditionsMet = false;
                // 디버그 로그: 조건 실패 상세 정보
                $this->log("조건 실패: {$tag} = '{$value}' 조건: '{$condition}'", 'DEBUG');
            } else {
                // 디버그 로그: 조건 성공 상세 정보
                $this->log("조건 성공: {$tag} = '{$value}' 조건: '{$condition}'", 'DEBUG');
            }
        }
        
        // 조건이 없으면 포함, 조건이 있으면 모든 조건이 만족되어야 포함
        $result = !$hasConditions || $allConditionsMet;
        
        if ($hasConditions) {
            $this->log("조건 평가 결과: " . ($result ? '통과' : '실패') . " (모든 조건 만족: " . ($allConditionsMet ? 'YES' : 'NO') . ")", 'DEBUG');
        }
        
        return $result;
    }
    
    /**
     * 조건 평가 메서드
     * 
     * @param mixed $value 비교할 값
     * @param string $condition 조건 문자열 (예: "== '사과'", "> 20", ">= 20", "<= 20", "!= 0")
     * @return bool 조건 만족 여부
     */
    private function evaluateCondition($value, $condition)
    {
        try {
            // 조건 문자열 파싱
            $condition = trim($condition);
            
            // 등호 비교 (==)
            if (preg_match('/^==\s*(.+)$/', $condition, $matches)) {
                $expectedValue = trim($matches[1], " '\"");
                $valueStr = (string)$value;
                $expectedStr = (string)$expectedValue;
                $result = $valueStr === $expectedStr;
                return $result;
            }
            
            // 다름 비교 (!=)
            if (preg_match('/^!=\s*(.+)$/', $condition, $matches)) {
                $expectedValue = trim($matches[1], " '\"");
                return (string)$value !== $expectedValue;
            }
            
            // 크기 비교 (>)
            if (preg_match('/^>\s*(.+)$/', $condition, $matches)) {
                $expectedValue = trim($matches[1], " '\"");
                
                // 날짜 비교
                if (preg_match('/^\d{4}-\d{2}-\d{2}/', $expectedValue)) {
                    $valueDate = $this->parseDate($value);
                    $expectedDate = $this->parseDate($expectedValue);
                    return $valueDate && $expectedDate && $valueDate > $expectedDate;
                }
                
                // 숫자 비교
                if (is_numeric($value) && is_numeric($expectedValue)) {
                    return (float)$value > (float)$expectedValue;
                }
                
                // 문자열 비교
                return strcmp((string)$value, (string)$expectedValue) > 0;
            }
            
            // 크거나 같음 비교 (>=)
            if (preg_match('/^>=\s*(.+)$/', $condition, $matches)) {
                $expectedValue = trim($matches[1], " '\"");
                
                // 날짜 비교
                if (preg_match('/^\d{4}-\d{2}-\d{2}/', $expectedValue)) {
                    $valueDate = $this->parseDate($value);
                    $expectedDate = $this->parseDate($expectedValue);
                    return $valueDate && $expectedDate && $valueDate >= $expectedDate;
                }
                
                // 숫자 비교
                if (is_numeric($value) && is_numeric($expectedValue)) {
                    return (float)$value >= (float)$expectedValue;
                }
                
                // 문자열 비교
                return strcmp((string)$value, (string)$expectedValue) >= 0;
            }
            
            // 작거나 같음 비교 (<=)
            if (preg_match('/^<=\s*(.+)$/', $condition, $matches)) {
                $expectedValue = trim($matches[1], " '\"");
                
                // 날짜 비교
                if (preg_match('/^\d{4}-\d{2}-\d{2}/', $expectedValue)) {
                    $valueDate = $this->parseDate($value);
                    $expectedDate = $this->parseDate($expectedValue);
                    return $valueDate && $expectedDate && $valueDate <= $expectedDate;
                }
                
                // 숫자 비교
                if (is_numeric($value) && is_numeric($expectedValue)) {
                    return (float)$value <= (float)$expectedValue;
                }
                
                // 문자열 비교
                return strcmp((string)$value, (string)$expectedValue) <= 0;
            }
            
            // 작음 비교 (<)
            if (preg_match('/^<\s*(.+)$/', $condition, $matches)) {
                $expectedValue = trim($matches[1], " '\"");
                
                // 날짜 비교
                if (preg_match('/^\d{4}-\d{2}-\d{2}/', $expectedValue)) {
                    $valueDate = $this->parseDate($value);
                    $expectedDate = $this->parseDate($expectedValue);
                    return $valueDate && $expectedDate && $valueDate < $expectedDate;
                }
                
                // 숫자 비교
                if (is_numeric($value) && is_numeric($expectedValue)) {
                    return (float)$value < (float)$expectedValue;
                }
                
                // 문자열 비교
                return strcmp((string)$value, (string)$expectedValue) < 0;
            }
            
            // 지원하지 않는 조건
            $this->log("지원하지 않는 조건 형식: {$condition}", 'WARNING');
            return false;
            
        } catch (Exception $e) {
            $this->log("조건 평가 중 오류: {$condition} - " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    /**
     * 날짜 문자열을 DateTime 객체로 파싱
     * 
     * @param mixed $dateValue 날짜 값
     * @return DateTime|null 파싱된 DateTime 객체 또는 null
     */
    private function parseDate($dateValue)
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

    /**
     * 과거 가격 및 등락률 계산
     * 
     * @param array &$parsedItem 파싱된 아이템 데이터 (참조)
     * @param array $api API 설정
     * @param string $targetDate 대상 날짜
     */
    private function calculateHistoricalPrices(&$parsedItem, $api, $targetDate)
    {
        try {
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
            
        } catch (Exception $e) {
            $this->log("과거 가격 계산 중 오류: " . $e->getMessage(), 'WARNING');
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
    private function getHistoricalPrice($sid, $coId, $date)
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



    /**
     * data 필드 유효성 검사
     * 파싱된 데이터에 유효한 data 또는 price 필드가 있는지 확인
     */
    private function hasValidDataField($parsedData)
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

    /**
     * price 필드 유효성 검사
     */
    private function hasValidPriceField($parsedData)
    {
        $validCount = 0;
        $totalCount = count($parsedData);
        
        foreach ($parsedData as $index => $item) {
            if (isset($item['price'])) {
                $priceValue = $item['price'];
                
                // price 필드가 null, '-', 빈 문자열이 아닌 경우 유효
                if ($priceValue !== null && $priceValue !== '-' && $priceValue !== '') {
                    $validCount++;
                } else {
                    $this->log("유효하지 않은 price 값 발견 (항목 {$index}): '{$priceValue}'", 'WARNING');
                }
            } else {
                $this->log("price 필드가 없음 (항목 {$index})", 'WARNING');
            }
        }

        $this->log("price 필드 유효성 검사: {$validCount}/{$totalCount} 유효");
        
        // 최소 하나의 유효한 price 값이 있어야 함
        return $validCount > 0;
    }

    /**
     * API 실행 시간 업데이트
     */
    private function updateApiLastRun($apiId)
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
     */
    private function recordApiFailure($apiId, $errorMessage)
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

    /**
     * API 데이터 저장
     */
    private function saveApiData($api, $parsedData, $targetDate)
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
                    $this->log("Unique index 위반 (중복 데이터): {$collection} (날짜: {$targetDate}, coId: {$api['coId']}, id: {$api['id']}, sid: {$sid}) - 무시됨", 'WARNING');
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
     * Running 파일 존재 여부 확인
     * 파일이 삭제되었으면 프로세스를 종료한다.
     * 
     * @throws Exception 파일이 삭제된 경우 예외 발생
     */
    private function checkRunningFile()
    {
        // runningFile이 설정되지 않았으면 체크하지 않음 (일반 실행 모드)
        if (empty($this->runningFile)) {
            return;
        }
        
        // Running 파일이 존재하지 않으면 프로세스 종료
        if (!file_exists($this->runningFile)) {
            $message = "Running 파일이 삭제되었습니다. 프로세스를 종료합니다: {$this->runningFile}";
            $this->log($message, 'WARNING');
            throw new Exception($message);
        }
    }

    /**
     * 로그 기록
     */
    private function log($message, $level = 'INFO')
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

    /**
     * 사용법 안내
     */
    private function showUsage()
    {
        echo "\n=== API 데이터 수집 스크립트 사용법 ===\n";
        echo "php allGetAipData.php [API_ID] [START_DATE] [END_DATE]\n\n";
        echo "파라미터:\n";
        echo "  API_ID    : 처리할 API ID (필수, 위의 목록에서 선택)\n";
        echo "  START_DATE: 수집 시작일자 YYYY-MM-DD 형식 (선택사항, 미지정시 모든 데이터)\n";
        echo "  END_DATE  : 수집 종료일자 YYYY-MM-DD 형식 (선택사항, 미지정시 현재일)\n\n";
        echo "예시:\n";
        echo "  php allGetAipData.php api001             # 특정 API의 모든 데이터 처리 (최대 10년)\n";
        echo "  php allGetAipData.php api001 2024-01-01  # 특정 API의 2024-01-01 이후 데이터만 처리\n";
        echo "  php allGetAipData.php api001 2024-01-01 2024-01-31  # 특정 API의 2024-01-01부터 2024-01-31까지 처리\n\n";
        echo "주의: API_ID는 반드시 지정해야 합니다. 위의 목록에서 사용 가능한 API ID를 선택하세요.\n";
        echo "날짜를 지정하지 않으면 오류가 발생할 때까지 모든 과거 데이터를 수집합니다.\n\n";
    }

    /**
     * Migration 상태 확인 및 확장자 변경
     */
    private function checkMigrationStatus()
    {
        if (!$this->isMigration || !$this->migrationFile) {
            return;
        }

        try {
            $migrationDir = dirname($this->migrationFile);
            $apiId = basename($this->migrationFile, '.txt');
            
            // 다른 확장자 파일들 확인
            $runningFile = $migrationDir . '/' . $apiId . '.running';
            $completedFile = $migrationDir . '/' . $apiId . '.completed';
            $failedFile = $migrationDir . '/' . $apiId . '.failed';
            
            if (file_exists($runningFile)) {
                $this->log("Migration이 이미 실행 중입니다: {$runningFile}");
                $this->migrationFile = $runningFile;
                return;
            }
            
            if (file_exists($completedFile)) {
                $this->log("Migration이 이미 완료되었습니다: {$completedFile}");
                $this->migrationFile = $completedFile;
                return;
            }
            
            if (file_exists($failedFile)) {
                $this->log("Migration이 실패했습니다: {$failedFile}");
                $this->migrationFile = $failedFile;
                return;
            }
            
            // .txt 파일을 .running으로 변경하여 실행 중임을 표시
            $this->changeMigrationFileExtension('running');
            
        } catch (Exception $e) {
            $this->log("Migration 상태 확인 실패: " . $e->getMessage(), 'ERROR');
        }
    }

    /**
     * Migration 파일 확장자 변경
     */
    private function changeMigrationFileExtension($newExtension)
    {
        if (!$this->isMigration || !$this->migrationFile || empty($this->migrationFile)) {
            return;
        }

        try {
            $migrationDir = dirname($this->migrationFile);
            $apiId = basename($this->migrationFile, '.txt');
            $newFile = $migrationDir . '/' . $apiId . '.' . $newExtension;
            
            // migration 디렉토리가 존재하는지 확인
            if (!is_dir($migrationDir)) {
                mkdir($migrationDir, 0755, true);
            }
            
            // 파일 내용 읽기
            if (!file_exists($this->migrationFile)) {
                $this->log("Migration 파일이 존재하지 않습니다: {$this->migrationFile}", 'ERROR');
                return;
            }
            
            $migrationData = json_decode(file_get_contents($this->migrationFile), true);
            
            if ($migrationData) {
                // 상태 업데이트
                $migrationData['status'] = $newExtension;
                $migrationData['lastUpdate'] = date('Y-m-d H:i:s');
                
                // 새 파일에 저장
                file_put_contents($newFile, json_encode($migrationData, JSON_PRETTY_PRINT));
                
                // running 파일인 경우 소유자를 nginx:nginx로 변경
                if ($newExtension === 'running') {
                    $this->changeFileOwner($newFile, 'nginx', 'nginx');
                }
                
                // 기존 파일 삭제
                if (file_exists($this->migrationFile)) {
                    unlink($this->migrationFile);
                }
                
                // 파일 경로 업데이트
                $this->migrationFile = $newFile;
                
                $this->log("Migration 파일 확장자 변경: {$apiId}.txt → {$apiId}.{$newExtension}");
            }
            
        } catch (Exception $e) {
            $this->log("Migration 파일 확장자 변경 실패: " . $e->getMessage(), 'ERROR');
        }
    }

    /**
     * 파일 소유자 변경
     * 
     * @param string $filePath 파일 경로
     * @param string $user 사용자명
     * @param string $group 그룹명
     */
    private function changeFileOwner($filePath, $user, $group)
    {
        try {
            if (!file_exists($filePath)) {
                $this->log("파일이 존재하지 않아 소유자를 변경할 수 없습니다: {$filePath}", 'WARNING');
                return;
            }
            
            // 사용자 ID 가져오기
            $userInfo = posix_getpwnam($user);
            if ($userInfo === false) {
                $this->log("사용자를 찾을 수 없습니다: {$user}", 'WARNING');
                return;
            }
            $uid = $userInfo['uid'];
            
            // 그룹 ID 가져오기
            $groupInfo = posix_getgrnam($group);
            if ($groupInfo === false) {
                $this->log("그룹을 찾을 수 없습니다: {$group}", 'WARNING');
                return;
            }
            $gid = $groupInfo['gid'];
            
            // 소유자 변경
            if (@chown($filePath, $uid) && @chgrp($filePath, $gid)) {
                $this->log("파일 소유자 변경 완료: {$filePath} → {$user}:{$group}");
            } else {
                $this->log("파일 소유자 변경 실패: {$filePath} (권한 부족 가능성)", 'WARNING');
            }
            
        } catch (Exception $e) {
            $this->log("파일 소유자 변경 중 오류: " . $e->getMessage(), 'WARNING');
        }
    }

    /**
     * Migration 상태를 'running'으로 업데이트
     */
    private function updateMigrationStatusToRunning()
    {
        if (!$this->isMigration || !$this->migrationFile) {
            return;
        }

        try {
            $migrationData = json_decode(file_get_contents($this->migrationFile), true);
            
            if ($migrationData) {
                $migrationData['status'] = 'running';
                $migrationData['lastUpdate'] = date('Y-m-d H:i:s');
                
                file_put_contents($this->migrationFile, json_encode($migrationData, JSON_PRETTY_PRINT));
                
                $this->log("Migration 상태를 'running'으로 업데이트: {$this->migrationFile}");
            }
        } catch (Exception $e) {
            $this->log("Migration 상태 업데이트 실패: " . $e->getMessage(), 'ERROR');
        }
    }

    /**
     * Migration 상태를 'failed'로 업데이트
     */
    private function updateMigrationStatusToFailed($errorMessage)
    {
        if (!$this->isMigration || !$this->migrationFile) {
            return;
        }

        try {
            $migrationData = json_decode(file_get_contents($this->migrationFile), true);
            
            if ($migrationData) {
                $migrationData['status'] = 'failed';
                $migrationData['errorMessage'] = $errorMessage;
                $migrationData['lastUpdate'] = date('Y-m-d H:i:s');
                
                file_put_contents($this->migrationFile, json_encode($migrationData, JSON_PRETTY_PRINT));
                
                $this->log("Migration 상태를 'failed'로 업데이트: {$this->migrationFile}");
                
                // 확장자를 .failed로 변경
                $this->changeMigrationFileExtension('failed');
            }
        } catch (Exception $e) {
            $this->log("Migration 실패 상태 업데이트 실패: " . $e->getMessage(), 'ERROR');
        }
    }

    /**
     * Migration 상태 업데이트
     */
    private function updateMigrationStatus($currentDate, $processedDays, $totalDays)
    {
        if (!$this->isMigration || !$this->migrationFile) {
            return;
        }

        try {
            $migrationData = json_decode(file_get_contents($this->migrationFile), true);
            
            if ($migrationData) {
                $migrationData['currentDate'] = $currentDate;
                $migrationData['processedDays'] = $processedDays;
                $migrationData['totalDays'] = $totalDays;
                $migrationData['progress'] = $totalDays > 0 ? round(($processedDays / $totalDays) * 100, 2) : 0;
                $migrationData['lastUpdate'] = date('Y-m-d H:i:s');
                
                file_put_contents($this->migrationFile, json_encode($migrationData, JSON_PRETTY_PRINT));
                
                $this->log("Migration 진행상태 업데이트: {$processedDays}/{$totalDays} ({$migrationData['progress']}%) - 현재 날짜: {$currentDate}");
            }
        } catch (Exception $e) {
            $this->log("Migration 상태 업데이트 실패: " . $e->getMessage(), 'ERROR');
        }
    }

    /**
     * Migration 완료 처리
     */
    private function completeMigration()
    {
        if (!$this->isMigration || !$this->migrationFile) {
            return;
        }

        try {
            $migrationData = json_decode(file_get_contents($this->migrationFile), true);
            
            if ($migrationData) {
                $migrationData['status'] = 'completed';
                $migrationData['progress'] = 100;
                $migrationData['completedDate'] = date('Y-m-d H:i:s');
                $migrationData['lastUpdate'] = date('Y-m-d H:i:s');
                
                file_put_contents($this->migrationFile, json_encode($migrationData, JSON_PRETTY_PRINT));
                
                $this->log("Migration 완료: {$this->migrationFile}");
                
                // 확장자를 .completed로 변경
                $this->changeMigrationFileExtension('completed');
                
                // Migration 완료 후 파일 삭제 (5초 후)
                sleep(5);
                if (file_exists($this->migrationFile)) {
                    unlink($this->migrationFile);
                    $this->log("Migration 파일 삭제 완료: {$this->migrationFile}");
                }
            }
        } catch (Exception $e) {
            $this->log("Migration 완료 처리 실패: " . $e->getMessage(), 'ERROR');
        }
    }

}

// 명령행 인수 처리
$apiId = null;
$startDate = null;
$endDate = null;

if ($argc > 1) {
    $apiId = $argv[1];
}

if ($argc > 2) {
    $startDate = $argv[2];
    
    // 날짜 형식 검증
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
        echo "오류: 시작일자는 YYYY-MM-DD 형식이어야 합니다. (예: 2024-01-01)\n";
        exit(1);
    }
    
    // 날짜 유효성 검증
    $dateTimestamp = strtotime($startDate);
    if ($dateTimestamp === false) {
        echo "오류: 유효하지 않은 시작일자입니다: {$startDate}\n";
        exit(1);
    }
}

if ($argc > 3) {
    $endDate = $argv[3];
    
    // 날짜 형식 검증
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
        echo "오류: 종료일자는 YYYY-MM-DD 형식이어야 합니다. (예: 2024-01-31)\n";
        exit(1);
    }
    
    // 날짜 유효성 검증
    $dateTimestamp = strtotime($endDate);
    if ($dateTimestamp === false) {
        echo "오류: 유효하지 않은 종료일자입니다: {$endDate}\n";
        exit(1);
    }
    
    // 시작일자와 종료일자 비교
    if ($startDate && $endDate < $startDate) {
        echo "오류: 종료일자는 시작일자보다 크거나 같아야 합니다. (시작: {$startDate}, 종료: {$endDate})\n";
        exit(1);
    }
}

// API ID 검증
if (empty($apiId)) {
    echo "오류: API_ID가 지정되지 않았습니다.\n";
    
    // 사용 가능한 API 목록 조회 및 표시
    try {
        $tempCollector = new AllApiDataCollector();
        $availableApis = $tempCollector->getAvailableApis();
        $tempCollector->showAvailableApis($availableApis);
    } catch (Exception $e) {
        echo "API 목록을 가져올 수 없습니다: " . $e->getMessage() . "\n";
    }
    
    echo "사용법: php allGetAipData.php [API_ID] [START_DATE] [END_DATE]\n";
    exit(1);
}

// 스크립트 실행
try {
    $collector = new AllApiDataCollector($apiId, $startDate, $endDate);
    $collector->run();
} catch (Exception $e) {
    echo "스크립트 실행 중 치명적 오류: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
