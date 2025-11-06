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
 * =============================================================================
 * API 스케줄 설정 구조
 * =============================================================================
 * 
 * 각 API는 다음과 같은 스케줄 구조를 가집니다:
 * 
 * 1. 일간 스케줄 (daily):
 *    {
 *      "type": "daily",
 *      "startTime": "09:00",
 *      "endTime": "18:00",
 *      "interval": "30"
 *    }
 *    → 매일 09:00~18:00 시간대에 실행
 * 
 * 2. 주간 스케줄 (weekly):
 *    {
 *      "type": "weekly", 
 *      "startTime": "09:01",
 *      "endTime": "18:00",
 *      "interval": "5",
 *      "days": "1,2,3,4,5,6"
 *    }
 *    → 월~토요일 09:01~18:00 시간대에 실행
 *    → days: 1=월요일, 2=화요일, ..., 7=일요일
 * 
 * 3. 월간 스케줄 (monthly):
 *    {
 *      "type": "monthly",
 *      "startTime": "09:00", 
 *      "endTime": "18:00",
 *      "interval": "20",
 *      "days": "1,2,3,4"
 *    }
 *    → 매월 1,2,3,4일에 09:00~18:00 시간대에 실행
 * 
 * 4. 분기 스케줄 (quarterly):
 *    {
 *      "type": "quarterly",
 *      "startTime": "09:00",
 *      "endTime": "18:00", 
 *      "interval": "10",
 *      "quarters": "1,2,3,4"
 *    }
 *    → 1,2,3,4분기의 해당 월 1일~7일 동안만 실행
 *    → quarters: 1=1분기(1-3월), 2=2분기(4-6월), 3=3분기(7-9월), 4=4분기(10-12월)
 * 
 * 5. 년간 스케줄 (yearly):
 *    {
 *      "type": "yearly",
 *      "startTime": "09:00",
 *      "endTime": "18:00",
 *      "interval": "60", 
 *      "months": "1,2,3,4,5,6,7,8,9,10,11,12"
 *    }
 *    → 지정된 월의 1일~7일 동안만 실행
 * 
 * =============================================================================
 * 데이터 수집 범위
 * =============================================================================
 * 
 * 스케줄 타입별 데이터 수집 범위:
 * - 일간: 오늘 (1일)
 * - 주간: 최근 7일
 * - 월간: 최근 30일  
 * - 분기: 최근 90일
 * - 년간: 최근 365일
 * 
 * 추가 검색 기능:
 * - 오늘 날짜에 데이터가 없으면 최대 10일 전까지 추가 검색
 * - 상수 MAX_DAYS_TO_SEARCH로 검색 일수 조정 가능
 * - 데이터를 찾으면 즉시 검색 중단
 * 
 * 과거 데이터 자동 업데이트/수집 (일일 데이터만 해당):
 * - 오늘 데이터 저장 후 과거 N일 전까지 데이터를 자동으로 처리
 * - 상수 PAST_DAYS_UPDATE로 일수 조정 가능 (기본: 6일)
 * - 기존 데이터가 있으면: 과거 가격 재계산 및 업데이트
 * - 데이터가 없으면: API 호출하여 신규 데이터 수집 및 저장
 * - 예시: 오늘(11/05) 저장 → 11/04, 11/03, 11/02, 11/01, 10/31, 10/30 처리
 * 
 * =============================================================================
 * 로그 확인 방법
 * =============================================================================
 * 
 * 1. CRON 로그 확인:
 *    tail -f /path/to/logs/cron.log
 * 
 * 2. API 스케줄러 상세 로그 확인:
 *    tail -f /path/to/kodes/wcms/data/logs/api_scheduler_YYYY-MM-DD.log
 * 
 * 3. 실시간 로그 모니터링:
 *    tail -f /path/to/kodes/wcms/data/logs/api_scheduler_$(date +%Y-%m-%d).log
 * 
 * 4. 로그 레벨:
 *    - INFO: 일반 정보
 *    - DEBUG: 상세 디버그 정보 (스케줄 조건, 데이터 처리 등)
 *    - WARNING: 경고 (API 응답 없음, 데이터 유효성 문제 등)
 *    - ERROR: 오류 (API 호출 실패, DB 연결 오류 등)
 * 
 * =============================================================================
 * 주요 기능
 * =============================================================================
 * 
 * 1. 스케줄 기반 API 실행:
 *    - 각 API의 schedule 설정에 따라 자동 실행
 *    - 시간 범위, 요일, 일자, 분기, 월별 조건 확인
 * 
 * 2. 실시간 데이터 수집:
 *    - 오늘 날짜 중심으로 최신 데이터 수집
 *    - 과거 가격 및 등락률 자동 계산
 * 
 * 3. 데이터 검증 및 저장:
 *    - API 응답 데이터 유효성 검사
 *    - MongoDB upsert를 통한 중복 방지
 *    - 상세한 저장 결과 로그
 * 
 * 4. 오류 처리:
 *    - 연속 오류 카운트 및 최대 허용 횟수 설정
 *    - API 호출 실패 시 재시도 로직
 *    - 상세한 오류 분류 및 로깅
 * 
 * 5. 성능 최적화:
 *    - API 호출 간격 제어 (서버 부하 방지)
 *    - 메모리 사용량 제한 (512MB)
 *    - 실행 시간 제한 (5분)
 * 
 * =============================================================================
 * 데이터베이스 구조
 * =============================================================================
 * 
 * 1. API 설정 테이블 (api):
 *    - id: API 고유 ID
 *    - title: API 제목
 *    - url: API URL (날짜 파라미터 {date} 포함)
 *    - schedule: 스케줄 설정 (JSON)
 *    - items: 필드 매핑 설정 (JSON)
 *    - isUse: 사용 여부 (Y/N)
 *    - coId: 회사 ID
 * 
 * 2. API 데이터 테이블 (apiData):
 *    - coId: 회사 ID
 *    - id: API ID
 *    - sid: 시리즈 ID (HkApiId로 생성)
 *    - rid: 레코드 ID (HkApiId로 생성)
 *    - date: 데이터 날짜
 *    - data: 실제 데이터 값
 *    - 과거 가격 필드들 (prevDayPrice, oneWeekAgoPrice 등)
 *    - 등락률 필드들 (prevDayChange, oneWeekAgoChange 등)
 * 
 * =============================================================================
 * 문제 해결 가이드
 * =============================================================================
 * 
 * 1. API가 실행되지 않는 경우:
 *    - 로그에서 "실행 조건 불만족" 메시지 확인
 *    - API의 schedule 설정 및 현재 시간 확인
 *    - isUse 필드가 'Y'인지 확인
 * 
 * 2. 데이터가 저장되지 않는 경우:
 *    - API 응답 데이터 확인 (URL 출력 로그 참조)
 *    - data 필드 유효성 검사 실패 로그 확인
 *    - MongoDB 연결 상태 확인
 * 
 * 3. 성능 문제:
 *    - API 호출 간격 조정 (sleep 시간 증가)
 *    - 수집 범위 축소 (getCollectRange 메서드 수정)
 *    - 메모리 제한 증가 (memory_limit 설정)
 * 
 * @author  Kodes <kodesinfo@gmail.com>
 * @version 2.1
 * @since   2024-01-01
 * @updated 2025-11-02 - 코드 리팩토링 및 최적화
 * 
 * =============================================================================
 * 주요 개선 사항 (v2.1)
 * =============================================================================
 * 
 * 1. 코드 중복 제거:
 *    - collectApiData()의 중복 로직을 tryCollectForDate()로 분리
 *    - 150줄 이상의 코드를 50% 감소
 * 
 * 2. 조건 평가 로직 개선:
 *    - evaluateCondition() 메서드 간소화 (120줄 → 20줄)
 *    - 비교 로직을 별도 메서드로 분리 (compareValues, compareDates 등)
 * 
 * 3. 헤더 파싱 분리:
 *    - HTTP 헤더 파싱을 parseHttpHeaders()로 분리
 *    - callApi() 메서드 가독성 향상
 * 
 * 4. 날짜 포맷 변환 간소화:
 *    - formatDate() 메서드를 한 줄로 간소화
 * 
 * 5. 불필요한 코드 제거:
 *    - 디버그 print_r() 제거
 *    - 주석처리된 코드 제거
 *    - 사용하지 않는 변수 제거
 * 
 * @license 해당 프로그램은 kodes에서 제작된 프로그램으로 저작권은 코드스(https://www.kode.co.kr)
 * 
 * @see allGetApiData.php - 과거 데이터 일괄 수집 스크립트
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
define('LOG_LEVEL', 'WARNING'); // 과거 데이터 수집 디버깅을 위해 임시로 INFO로 변경

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
 * API 데이터 수집 스케줄러 클래스
 * 
 * 메서드 구성:
 * - 초기화 및 실행: __construct(), run()
 * - 스케줄 관리: getScheduledApis(), shouldRunNow()
 * - 데이터 수집: collectApiData(), tryCollectForDate(), updatePastDaysData()
 * - API 통신: buildApiUrl(), callApi(), parseHttpHeaders()
 * - 데이터 파싱: parseApiData(), parseItem(), parseListTag(), getNestedValue()
 * - 조건 평가: evaluateItemConditions(), evaluateCondition(), compareValues()
 * - 과거 가격: calculateHistoricalPrices(), getHistoricalPrice()
 * - 데이터 저장: saveApiData(), hasValidDataField()
 * - 유틸리티: formatDate(), getCollectRange(), lastDataDate()
 * - 로깅: log(), updateApiLastRun(), recordApiFailure()
 */
class ApiDataScheduler
{
    private $db;
    private $apiDb;
    private $common;
    private $logFile;
    private $currentDate = null; // 실행중인 날짜.
    private $forceMode = false; // Force 모드 플래그

    // =========================================================================
    // 초기화 및 실행
    // =========================================================================

    public function __construct($forceMode = false)
    {
        $this->db = new DB("wcmsDB");
        $this->apiDb = new DB("apiDB");
        $this->common = new Common();
        $this->forceMode = $forceMode;
        
        // 로그 파일 설정
        // dataDir이 비어있거나 null인 경우 기본 경로 사용
        $baseLogDir = !empty($this->common->dataDir) ? $this->common->dataDir : '/webSiteSource/wcms/cron/logs';
        $this->logFile = $baseLogDir . '/api_scheduler_' . date('Y-m-d') . '.log';

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

    // =========================================================================
    // API 통신
    // =========================================================================

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
        return date($format ?: 'Y-m-d', strtotime($date));
    }
    
    /**
     * HTTP 헤더 파싱
     * 
     * @param string $headerParam 헤더 문자열 (줄바꿈으로 구분)
     * @return array 파싱된 헤더 배열
     */
    private function parseHttpHeaders($headerParam)
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
     * API 호출
     */
    private function callApi($url, $returnType = 'JSON', $headerParam = '')
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'HKPrice-API-Scheduler/1.0');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        
        // 요청 헤더 설정
        $headers = $this->parseHttpHeaders($headerParam);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($response === false) {
            throw new Exception("cURL 오류: " . $curlError);
        }
        
        if ($httpCode < 200 || $httpCode >= 300) {
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
        
        return $data;
    }

    // =========================================================================
    // 데이터 파싱
    // =========================================================================

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
                'managerName' => 'API Scheduler',
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
    private function compareValues($value1, $value2, $operator)
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
     */
    private function compareDates($date1, $date2, $operator)
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
     */
    private function compareNumbers($num1, $num2, $operator)
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
     */
    private function compareStrings($str1, $str2, $operator)
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
    private function calculateHistoricalPrices(&$parsedItem, $api, $targetDate)
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
     * 과거 가격 조회 (해당 일자부터 15일 범위 내 최신 데이터)
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
    // 데이터 저장 및 유효성 검사
    // =========================================================================

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

    // =========================================================================
    // 로깅 및 상태 관리
    // =========================================================================

    /**
     * API 실행 시간 업데이트
     */
    private function updateApiLastRun($apiId)
    {
        try {
            $latestDate = $this->lastDataDate($apiId);
            $filter = ['id' => $apiId];
            $updateData = ['$set' => [
                'lastRun' => date('Y-m-d H:i:s'),
                'lastRunStatus' => 'success',
                'latestDataDate' => $latestDate
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
     * 로그 기록
     */
    private function log($message, $level = 'INFO')
    {
        // 로그 레벨 필터링 (기본: WARNING 이상)
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
        
        // 파일 로그
        file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
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