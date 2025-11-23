<?php
/**
 * API 데이터 수집 스케줄러 (실시간)
 * 
 * DB의 API 정보를 가져와서 각 API의 스케줄 설정에 따라 실행되는 CRON 스크립트
 * 오늘 날짜 중심으로 실시간 데이터를 수집하여 MongoDB에 저장합니다.
 * 
 * =============================================================================
 * 사용법 및 CRON 등록 방법
 * =============================================================================
 * 
 * 1. CRON 등록:
 *    crontab -e
 * 
 * 2. 실행 주기 설정 (권장: 5분마다): 첫번째 * /5의 ' '는 제거하고 사용해야 함.
 *    * /5 * * * * /usr/bin/php /webSiteSource/wcms/cron/getApiData.php >> /webSiteSource/wcms/cron/logs/cron_$(date +\%Y\%m\%d).log 2>&1
 * 
 * 3. CRON 시간 형식: 분 시 일 월 요일
 *    - 분: 0-59
 *    - 시: 0-23  
 *    - 일: 1-31
 *    - 월: 1-12
 *    - 요일: 0-7 (0과 7은 일요일)
 * 
 * 4. 실행 예시:
 *    - * /5 * * * * : 5분마다 실행 (권장)
 *    - * /10 * * * * : 10분마다 실행
 *    - 0 * * * * : 매시간마다 실행
 *    - 0 9 * * 1-5 : 평일 오전 9시에만 실행
 * 
 * 5. Force 모드 (수동 실행):
 *    php /webSiteSource/wcms/cron/getApiData.php force
 *    → 시간 조건을 무시하고 모든 API를 즉시 실행
 * 
 * @author  Kodes <kodesinfo@gmail.com>
 * @version 3.0
 * @since   2024-01-01
 * @updated 2025-11-22 - 공통 클래스 분리 및 리팩토링
 * 
 * @license 해당 프로그램은 kodes에서 제작된 프로그램으로 저작권은 코드스(https://www.kode.co.kr)
 * 
 * @see allGetApiData.php - 과거 데이터 일괄 수집 스크립트
 * @see ApiDataCollector.php - 공통 기능 기본 클래스
 * @see HkApiId.php - 시리즈 ID 생성 클래스
 */

// 오류 보고 설정
error_reporting(E_ALL);
ini_set('display_errors', 0);

// 시간대 설정 (한국 시간)
date_default_timezone_set('Asia/Seoul');

// 메모리 및 실행 시간 제한 설정
ini_set('memory_limit', '512M');
set_time_limit(300); // 5분 제한

// 상수 정의
define('MAX_DAYS_TO_SEARCH', 10); // 오늘 날짜에 데이터가 없을 때 최대 검색할 일수
define('PAST_DAYS_UPDATE', 6); // 과거 N일 전까지 데이터 업데이트/수집 (일일 데이터의 경우)
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
        'data' => __DIR__ . '/../../kodes/wcms/data'
    ]
];

// DB 설정 파일 경로 수정을 위한 전역 변수 설정
$GLOBALS['db_config_path'] = __DIR__ . '/../../kodes/wcms/config/db.json';

// 공통 기능 클래스 로드
require_once __DIR__ . '/../../kodes/wcms/classes/ApiDataCollector.php';

use Kodes\Wcms\ApiDataCollector;
use Exception;

/**
 * API 데이터 수집 스케줄러 클래스 (스케줄 기반 실시간 수집)
 * 
 * 메서드 구성:
 * - 초기화 및 실행: __construct(), run()
 * - 스케줄 관리: getScheduledApis(), shouldRunNow()
 * - 데이터 수집: collectApiData(), tryCollectForDate(), updatePastDaysData()
 * - 유틸리티: getCollectRange(), lastDataDate(), checkTodayDataExists()
 */
class ApiDataScheduler extends ApiDataCollector
{
    private $forceMode = false; // Force 모드 플래그

    public function __construct($forceMode = false)
    {
        // 부모 클래스 생성자 호출
        parent::__construct('_scheduler');
        
        $this->forceMode = $forceMode;
    }

    /**
     * 메인 실행 함수
     */
    public function run()
    {
        $modeText = $this->forceMode ? " (FORCE 모드 - 시간 조건 무시)" : "";
        $this->log("API 스케줄러 시작: " . date('Y-m-d H:i:s') . $modeText);
        
        try {
            // 실행 가능한 API 목록 조회
            $apis = $this->getScheduledApis();
            
            if (empty($apis)) {
                $this->log("실행할 API가 없습니다.");
                return;
            }
            
            $this->log("총 " . count($apis) . "개의 API를 처리합니다.");
            
            $successCount = 0;
            $errorCount = 0;
            
            foreach ($apis as $api) {
                try {
                    $this->processApi($api);
                    $successCount++;
                } catch (Exception $e) {
                    $errorCount++;
                    $this->log("API 처리 실패 ({$api['title']}): " . $e->getMessage(), 'ERROR');
                }
            }
            
            $this->log("API 처리 완료 - 성공: {$successCount}개, 실패: {$errorCount}개");
            
        } catch (Exception $e) {
            $this->log("스케줄러 실행 중 오류: " . $e->getMessage(), 'ERROR');
        }
        
        $this->log("API 스케줄러 종료: " . date('Y-m-d H:i:s'));
    }

    // =========================================================================
    // 스케줄 관리
    // =========================================================================

    /**
     * 실행 가능한 API 목록 조회 (스케줄 기반)
     */
    private function getScheduledApis()
    {
        $filter = [
            'isUse' => 'Y',
            'coId' => 'hkp' // 기본 회사 ID
        ];
        
        $options = [
            'sort' => ['title' => 1]
        ];
        
        $allApis = $this->db->list('api', $filter, $options);
        $scheduledApis = [];
        
        $this->log("전체 API 수: " . count($allApis));
        
        foreach ($allApis as $api) {
            $schedule = $api['schedule'] ?? [];
            $scheduleType = $schedule['type'] ?? 'daily';
            
            $this->log("API 스케줄 확인: {$api['title']} (ID: {$api['id']}, 타입: {$scheduleType})", 'DEBUG');
            
            if ($this->shouldRunNow($api)) {
                $scheduledApis[] = $api;
                $this->log("API 실행 대상 추가: {$api['title']} (ID: {$api['id']})");
            } else {
                $this->log("API 실행 조건 불만족: {$api['title']} (ID: {$api['id']})", 'DEBUG');
            }
        }
        
        $this->log("실행 대상 API 수: " . count($scheduledApis));
        
        return $scheduledApis;
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
            
        } catch (Exception $e) {
            $this->log("API 처리 중 오류 ({$api['title']}): " . $e->getMessage(), 'ERROR');
            
            // 실패 기록
            $this->recordApiFailure($api['id'], $e->getMessage());
        }
    }


    /**
     * 현재 시간이 실행 시간인지 확인
     */
    private function shouldRunNow($api)
    {
        $currentTime = date('H:i');
        $currentDay = date('N'); // 1(월요일) ~ 7(일요일)
        $currentDayOfMonth = date('j');
        $currentMonth = date('n');
        
        // schedule 필드에서 설정 가져오기
        $schedule = $api['schedule'] ?? [];
        $type = $schedule['type'] ?? 'daily';
        
        // Force 모드가 아닐 때만 시간 범위 확인
        if (!$this->forceMode) {
            // 시간 범위 확인
            $startTime = $schedule['startTime'] ?? '00:00';
            $endTime = $schedule['endTime'] ?? '23:59';
            
            if ($currentTime < $startTime || $currentTime > $endTime) {
                $this->log("시간 범위 외: 현재시간 {$currentTime}, 허용범위 {$startTime}~{$endTime}");
                return false;
            }
        } else {
            $this->log("Force 모드: 시간 조건 무시하고 실행 ({$api['title']})", 'INFO');
        }
        
        // 스케줄 타입별 확인
        switch ($type) {
            case 'daily':
                $this->log("일간 스케줄: 실행 조건 만족", 'DEBUG');
                return true; // 매일 실행
                
            case 'weekly':
                $days = $schedule['days'] ?? '';
                if (!empty($days)) {
                    $allowedDays = array_map('trim', explode(',', $days));
                    $isAllowed = in_array((string)$currentDay, $allowedDays);
                    $this->log("주간 스케줄: 현재요일 {$currentDay}, 허용요일 [" . implode(',', $allowedDays) . "], 결과: " . ($isAllowed ? '실행' : '대기'), 'DEBUG');
                    return $isAllowed;
                }
                $this->log("주간 스케줄: 허용 요일이 설정되지 않음", 'DEBUG');
                return false;
                
            case 'monthly':
                $days = $schedule['days'] ?? '';
                if (!empty($days)) {
                    $allowedDays = array_map('trim', explode(',', $days));
                    $isAllowed = in_array((string)$currentDayOfMonth, $allowedDays);
                    $this->log("월간 스케줄: 현재일자 {$currentDayOfMonth}, 허용일자 [" . implode(',', $allowedDays) . "], 결과: " . ($isAllowed ? '실행' : '대기'), 'DEBUG');
                    return $isAllowed;
                }
                $this->log("월간 스케줄: 허용 일자가 설정되지 않음", 'DEBUG');
                return false;
                
            case 'quarterly':
                // 분기별 스케줄 (1분기:1-3월, 2분기:4-6월, 3분기:7-9월, 4분기:10-12월)
                // 해당 월의 1일~7일 동안만 실행
                if ($currentDayOfMonth < 1 || $currentDayOfMonth > 7) {
                    $this->log("분기 스케줄: 실행 기간 외 (현재일자: {$currentDayOfMonth}, 허용범위: 1~7일)", 'DEBUG');
                    return false;
                }
                
                $quarters = $schedule['quarters'] ?? '';
                if (!empty($quarters)) {
                    $allowedQuarters = array_map('trim', explode(',', $quarters));
                    $currentQuarter = ceil($currentMonth / 3);
                    $isAllowed = in_array((string)$currentQuarter, $allowedQuarters);
                    $this->log("분기 스케줄: 현재분기 {$currentQuarter}분기, 허용분기 [" . implode(',', $allowedQuarters) . "], 결과: " . ($isAllowed ? '실행' : '대기'), 'DEBUG');
                    return $isAllowed;
                }
                $this->log("분기 스케줄: 허용 분기가 설정되지 않음", 'DEBUG');
                return false;
                
            case 'yearly':
                // 년간 스케줄: 해당 월의 1일~7일 동안만 실행
                if ($currentDayOfMonth < 1 || $currentDayOfMonth > 7) {
                    $this->log("년간 스케줄: 실행 기간 외 (현재일자: {$currentDayOfMonth}, 허용범위: 1~7일)", 'DEBUG');
                    return false;
                }
                
                $months = $schedule['months'] ?? '';
                if (!empty($months)) {
                    $allowedMonths = array_map('trim', explode(',', $months));
                    $isAllowed = in_array((string)$currentMonth, $allowedMonths);
                    $this->log("년간 스케줄: 현재월 {$currentMonth}월, 허용월 [" . implode(',', $allowedMonths) . "], 결과: " . ($isAllowed ? '실행' : '대기'), 'DEBUG');
                    return $isAllowed;
                }
                $this->log("년간 스케줄: 허용 월이 설정되지 않음", 'DEBUG');
                return false;
                
            default:
                $this->log("알 수 없는 스케줄 타입: {$type}", 'WARNING');
                return false;
        }
    }

    // =========================================================================
    // 데이터 수집
    // =========================================================================

    /**
     * API 데이터 수집 (메인 메서드)
     */
    private function collectApiData($api)
    {
        $currentDate = date('Y-m-d');
        $totalSuccessCount = 0;
        $totalErrorCount = 0;
        $consecutiveErrors = 0;
        $maxConsecutiveErrors = 10;
        $dataFound = false;
        
        // 스케줄 타입에 따른 수집 범위 설정
        $schedule = $api['schedule'] ?? [];
        $scheduleType = $schedule['type'] ?? 'daily';
        $collectRange = $this->getCollectRange($scheduleType);
        
        $this->log("API 데이터 수집 시작: {$api['title']} (스케줄 타입: {$scheduleType}, 수집 범위: {$collectRange}일)");
        
        // 오늘 날짜 데이터가 이미 존재하는지 확인 (일일 데이터의 경우)
        $todayDataExists = false;
        if ($scheduleType === 'daily') {
            $todayDataExists = $this->checkTodayDataExists($api, $currentDate);
            if ($todayDataExists) {
                $pastDays = PAST_DAYS_UPDATE;
                $this->log("오늘 날짜 데이터가 이미 존재함: {$api['title']} (날짜: {$currentDate})");
                $this->log("과거 {$pastDays}일 데이터 업데이트/수집을 시작합니다.");
                
                // 오늘 날짜 데이터가 이미 있으면 과거 날짜 데이터만 업데이트/수집
                $this->updatePastDaysData($api, $currentDate, PAST_DAYS_UPDATE);
                
                $this->log("API 데이터 처리 완료: {$api['title']} (오늘 데이터 존재, 과거 데이터 처리됨)");
                return; // 수집 완료
            }
        }
        
        // 메인 수집 범위 시도
        $latestSuccessDate = null; // 수집에 성공한 가장 최신 날짜
        
        for ($dayOffset = 0; $dayOffset < $collectRange; $dayOffset++) {
            $result = $this->tryCollectForDate($api, $currentDate, $dayOffset, $scheduleType);
            
            if ($result['success']) {
                $totalSuccessCount++;
                $consecutiveErrors = 0;
                $dataFound = true;
                
                // 가장 최신 성공 날짜 기록
                if ($latestSuccessDate === null) {
                    $latestSuccessDate = $result['date'];
                }
                
                // 오늘 날짜 데이터 발견 시 종료
                if ($dayOffset == 0) {
                    $this->log("오늘 날짜 데이터 발견, 수집 완료: {$api['title']}");
                    break;
                }
            } else {
                $consecutiveErrors++;
                $totalErrorCount++;
                
                if ($consecutiveErrors >= $maxConsecutiveErrors) {
                    $this->log("연속 오류 {$consecutiveErrors}회 발생. 수집 범위까지 계속 진행합니다.", 'WARNING');
                }
            }
            
            // API 호출 간격 대기
            if ($dayOffset < $collectRange - 1) {
                sleep(1);
            }
        }
        
        // 추가 검색 (오늘 날짜에 데이터가 없는 경우)
        if (!$dataFound && $totalSuccessCount == 0) {
            $this->log("오늘 날짜에 데이터가 없음. 추가로 " . MAX_DAYS_TO_SEARCH . "일 전까지 검색 시작: {$api['title']}");
            
            for ($dayOffset = $collectRange; $dayOffset < $collectRange + MAX_DAYS_TO_SEARCH; $dayOffset++) {
                $result = $this->tryCollectForDate($api, $currentDate, $dayOffset, $scheduleType, true);
                
                if ($result['success']) {
                    $totalSuccessCount++;
                    $dataFound = true;
                    
                    // 가장 최신 성공 날짜 기록
                    if ($latestSuccessDate === null) {
                        $latestSuccessDate = $result['date'];
                    }
                    
                    break; // 데이터 발견 시 즉시 중단
                }
                
                sleep(1); // API 호출 간격 대기
            }
        }
        
        // 최종 결과 로그
        if ($dataFound) {
            $this->log("API 데이터 수집 완료: {$api['title']} (성공: {$totalSuccessCount}일, 총오류: {$totalErrorCount}일)");
            
            // 일일 데이터의 경우, 수집한 가장 최신 날짜 기준으로 과거 데이터 업데이트/수집
            if ($scheduleType === 'daily' && $latestSuccessDate !== null) {
                $pastDays = PAST_DAYS_UPDATE;
                $this->log("가장 최신 데이터 날짜: {$latestSuccessDate} 기준으로 과거 {$pastDays}일 처리 시작");
                $this->updatePastDaysData($api, $latestSuccessDate, PAST_DAYS_UPDATE);
            }
        } else {
            $this->log("API 데이터 수집 실패: {$api['title']} (총 " . ($collectRange + MAX_DAYS_TO_SEARCH) . "일 검색했으나 데이터 없음)");
        }
    }
    
    /**
     * 특정 날짜에 대한 데이터 수집 시도
     * 
     * @param array $api API 설정
     * @param string $baseDate 기준 날짜
     * @param int $dayOffset 일자 오프셋
     * @param string $scheduleType 스케줄 타입
     * @param bool $isExtendedSearch 추가 검색 여부
     * @return array ['success' => bool, 'date' => string] 수집 성공 여부 및 날짜
     */
    private function tryCollectForDate($api, $baseDate, $dayOffset, $scheduleType, $isExtendedSearch = false)
    {
        $targetDate = date('Y-m-d', strtotime($baseDate . " -{$dayOffset} days"));
        $dateFormatted = $this->formatDate($targetDate, $api['dateChar']);
        $prefix = $isExtendedSearch ? '추가 검색 - ' : '';
        
        $this->log("{$prefix}API 호출 시도: {$api['title']} (날짜: {$targetDate}, 오프셋: {$dayOffset})");
        $this->currentDate = $targetDate;
        
        try {
            // API URL 생성 및 호출
            $url = $this->buildApiUrl($api, $dateFormatted);
            $responseData = $this->callApi($url, $api['returnType'], $api['header'] ?? '');
            
            if (empty($responseData)) {
                $this->log("{$prefix}API 응답이 비어있음: {$api['title']} (날짜: {$targetDate})", 'WARNING');
                return ['success' => false, 'date' => null];
            }
            
            // 데이터 파싱 및 저장
            $parsedData = $this->parseApiData($responseData, $api, $targetDate);
            
            if (empty($parsedData) || !$this->hasValidDataField($parsedData)) {
                $this->log("{$prefix}파싱된 데이터가 없거나 유효하지 않음: {$api['title']} (날짜: {$targetDate})", 'WARNING');
                return ['success' => false, 'date' => null];
            }
            
            // 데이터 저장
            $this->saveApiData($api, $parsedData, $targetDate);
            $this->log("{$prefix}API 데이터 저장 완료: {$api['title']} (날짜: {$targetDate}, 데이터 수: " . count($parsedData) . ")");
            
            return ['success' => true, 'date' => $targetDate];
            
        } catch (Exception $e) {
            $this->log("{$prefix}API 호출 실패: {$api['title']} (날짜: {$targetDate}): " . $e->getMessage(), 'WARNING');
            return ['success' => false, 'date' => null];
        }
    }


    /**
     * 과거 N일 전까지 데이터 업데이트/수집 (과거 가격 재계산 또는 신규 수집)
     * 
     * @param array $api API 설정
     * @param string $baseDate 기준 날짜 (오늘)
     * @param int $days 업데이트할 과거 일수 (상수 PAST_DAYS_UPDATE로 설정, 기본: 6일)
     */
    private function updatePastDaysData($api, $baseDate, $days)
    {
        $this->log("과거 {$days}일 데이터 업데이트/수집 시작: {$api['title']} (기준일: {$baseDate}) [설정: PAST_DAYS_UPDATE={$days}]");
        
        $updateCount = 0;
        $insertCount = 0;
        
        for ($i = 1; $i <= $days; $i++) {
            $pastDate = date('Y-m-d', strtotime($baseDate . " -{$i} days"));
            
            try {
                // 해당 날짜의 기존 데이터 조회
                $filter = [
                    'coId' => $api['coId'],
                    'id' => $api['id'],
                    'date' => $pastDate
                ];
                
                $options = [];
                
                $existingData = $this->apiDb->list('apiData', $filter, $options);
                
                if (empty($existingData)) {
                    // 데이터가 없으면 API 호출해서 수집
                    $this->log("과거 데이터 없음 (날짜: {$pastDate}), API 호출하여 수집");
                    
                    try {
                        // 날짜 포맷 변환
                        $dateFormatted = $this->formatDate($pastDate, $api['dateChar']);
                        
                        // API URL 생성
                        $url = $this->buildApiUrl($api, $dateFormatted);
                        $this->log("API URL: {$url}", 'DEBUG');
                        
                        // currentDate 설정
                        $this->currentDate = $pastDate;
                        
                        // API 호출
                        $responseData = $this->callApi($url, $api['returnType'], $api['header'] ?? '');
                        
                        if (!empty($responseData)) {
                            // 응답 데이터 크기 로그
                            $responseSize = is_string($responseData) ? strlen($responseData) : 
                                           (is_array($responseData) ? count($responseData) : 'unknown');
                            $this->log("API 응답 수신: 날짜={$pastDate}, 크기={$responseSize}");
                            
                            // 데이터 파싱
                            $parsedData = $this->parseApiData($responseData, $api, $pastDate);
                            
                            if (empty($parsedData)) {
                                $this->log("과거 데이터 파싱 결과 비어있음: 날짜={$pastDate} - API에서 해당 날짜 데이터 미제공 가능성", 'WARNING');
                            } elseif (!$this->hasValidDataField($parsedData)) {
                                $this->log("과거 데이터 유효성 검증 실패: 날짜={$pastDate}, 파싱된 항목 수=" . count($parsedData), 'WARNING');
                            } else {
                                // 데이터 저장
                                $this->saveApiData($api, $parsedData, $pastDate);
                                $insertCount += count($parsedData);
                                $this->log("과거 데이터 수집 완료: 날짜={$pastDate}, 건수=" . count($parsedData));
                            }
                        } else {
                            $this->log("과거 데이터 API 응답 없음: 날짜={$pastDate} - API 호출 실패 또는 빈 응답", 'WARNING');
                        }
                    } catch (Exception $e) {
                        $this->log("과거 데이터 수집 실패 (날짜: {$pastDate}): " . $e->getMessage(), 'WARNING');
                    }
                    
                    continue;
                }
                
                $this->log("과거 데이터 발견: {$pastDate} - " . count($existingData) . "건, 과거 가격 업데이트 수행");
                
                // 각 데이터의 과거 가격 재계산 및 업데이트
                foreach ($existingData as $item) {
                    // 과거 가격 재계산
                    $this->calculateHistoricalPrices($item, $api, $pastDate);
                    
                    // 업데이트 (sid를 기준으로)
                    $updateFilter = [
                        'coId' => $api['coId'],
                        'id' => $api['id'],
                        'sid' => $item['sid'],
                        'date' => $pastDate
                    ];
                    
                    // 과거 가격 필드만 업데이트
                    $updateFields = [];
                    $historicalFields = [
                        'prevDayPrice', 'prevDayChange',
                        'oneWeekAgoPrice', 'oneWeekAgoChange',
                        'oneMonthAgoPrice', 'oneMonthAgoChange',
                        'threeMonthsAgoPrice', 'threeMonthsAgoChange',
                        'sixMonthsAgoPrice', 'sixMonthsAgoChange',
                        'oneYearAgoPrice', 'oneYearAgoChange'
                    ];
                    
                    foreach ($historicalFields as $field) {
                        if (isset($item[$field])) {
                            $updateFields[$field] = $item[$field];
                        }
                    }
                    
                    if (!empty($updateFields)) {
                        $this->apiDb->update('apiData', $updateFilter, ['$set' => $updateFields]);
                        $updateCount++;
                        $this->log("과거 가격 업데이트 완료: 날짜={$pastDate}, SID={$item['sid']}", 'DEBUG');
                    }
                }
                
            } catch (Exception $e) {
                $this->log("과거 데이터 처리 실패 (날짜: {$pastDate}): " . $e->getMessage(), 'WARNING');
            }
            
            // API 호출 간격 대기 (서버 부하 방지)
            if ($i < $days) {
                sleep(1);
            }
        }
        
        $this->log("과거 {$days}일 데이터 처리 완료: 업데이트 {$updateCount}건, 신규 수집 {$insertCount}건");
    }

    // =========================================================================
    // 유틸리티 메서드
    // =========================================================================

    /**
     * 오늘 날짜 데이터가 이미 존재하는지 확인
     * 
     * @param array $api API 설정
     * @param string $date 확인할 날짜
     * @return bool 데이터 존재 여부
     */
    private function checkTodayDataExists($api, $date)
    {
        try {
            $filter = [
                'coId' => $api['coId'],
                'id' => $api['id'],
                'date' => $date
            ];
            
            $options = [
                'limit' => 1
            ];
            
            $existingData = $this->apiDb->list('apiData', $filter, $options);
            
            return !empty($existingData);
            
        } catch (Exception $e) {
            $this->log("오늘 날짜 데이터 확인 실패: " . $e->getMessage(), 'WARNING');
            return false;
        }
    }

    /**
     * 스케줄 타입에 따른 수집 범위 결정
     */
    private function getCollectRange($scheduleType)
    {
        switch ($scheduleType) {
            case 'daily':
                return 3; // 일일: 오늘만
            case 'weekly':
                return 3; // 주간: 최근 7일
            case 'monthly':
                return 5; // 월간: 최근 30일
            case 'quarterly':
                return 10; // 분기: 최근 90일 (약 3개월)
            case 'yearly':
                return 10; // 연간: 최근 365일 (1년)
            default:
                return 3; // 기본값: 1일 (오늘만)
        }
    }

    /**
     * API 데이터의 최신 날짜 조회
     */
    private function lastDataDate($apiId)
    {
        try {
            $filter = ['id' => $apiId];
            $options = [
                'projection' => ['_id' => 0, 'date' => 1], // _id 제외, date 필드만
                'sort' => ['date' => -1], // 날짜 내림차순 정렬
                'limit' => 1 // 최신 1개만
            ];
            
            $result = $this->apiDb->list('apiData', $filter, $options);
            
            if (!empty($result)) {
                $latestDate = $result[0]['date'] ?? null;
                $this->log("API 최신 데이터 날짜 조회: API ID {$apiId}, 최신 날짜: {$latestDate}");
                return $latestDate;
            } else {
                $this->log("API 데이터가 없음: API ID {$apiId}", 'WARNING');
                return null;
            }
            
        } catch (Exception $e) {
            $this->log("최신 데이터 날짜 조회 실패: " . $e->getMessage(), 'WARNING');
            return null;
        }
    }

}

// 커맨드 라인 인자 처리
$forceMode = false;
if (isset($argv) && in_array('force', $argv)) {
    $forceMode = true;
    echo "Force 모드로 실행됩니다. 시간 조건을 무시하고 모든 API를 처리합니다.\n";
}

// 스케줄러 실행
try {
    $scheduler = new ApiDataScheduler($forceMode);
    $scheduler->run();
} catch (Exception $e) {
    echo "스케줄러 실행 중 치명적 오류: " . $e->getMessage() . PHP_EOL;
    exit(1);
}