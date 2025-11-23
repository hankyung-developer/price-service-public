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
 * @version 3.0
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

// 공통 기능 클래스 로드
require_once __DIR__ . '/../../kodes/wcms/classes/ApiDataCollector.php';

use Kodes\Wcms\ApiDataCollector;
use Exception;

/**
 * API 데이터 수집 스크립트 클래스 (Migration 전용)
 */
class AllApiDataCollector extends ApiDataCollector
{
    private $apiId = null;
    private $startDate = null;
    private $endDate = null;
    private $migrationFile = null;
    private $isMigration = false;
    private $runningFile = null;  // 실행 중임을 나타내는 파일 경로

    public function __construct($apiId = null, $startDate = null, $endDate = null)
    {
        // 부모 클래스 생성자 호출
        $logSuffix = $apiId ? "_api_{$apiId}" : "_all";
        parent::__construct($logSuffix);
        
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
        
        // 수집 시작 상태 업데이트
        $this->updateApiCompletionStatus($api['id'], 'processing', $this->startDate, $this->endDate);
        
        try {
            // API 데이터 수집
            $this->collectApiData($api);
            
            // 성공 기록
            $this->updateApiLastRun($api['id']);
            
            // 전체 수집 완료 상태 업데이트 (DB에 한 번만 저장)
            $this->updateApiCompletionStatus($api['id'], 'completed', $this->startDate, $this->endDate);
            
            // Migration 완료 처리
            $this->completeMigration();
            
        } catch (Exception $e) {
            $this->log("API 처리 중 오류 ({$api['title']}): " . $e->getMessage(), 'ERROR');
            
            // 실패 기록
            $this->recordApiFailure($api['id'], $e->getMessage());
            
            // 전체 수집 실패 상태 업데이트
            $this->updateApiCompletionStatus($api['id'], 'failed', $this->startDate, $this->endDate, $e->getMessage());
            
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
        
        // dateChar 확인 - 'Ym' 형식인 경우 월별 처리
        $dateChar = $api['dateChar'] ?? 'Y-m-d';
        $isMonthlyFormat = ($dateChar === 'Ym' || $dateChar === 'Y-m');
        
        if ($isMonthlyFormat) {
            // 월별 처리
            $this->collectApiDataByMonth($api, $startDate, $endDate);
        } else {
            // 일별 처리 (기존 로직)
            $this->collectApiDataByDay($api, $startDate, $endDate);
        }
    }

    /**
     * API 데이터 수집 (일별)
     */
    private function collectApiDataByDay($api, $startDate, $endDate)
    {
        $totalSuccessCount = 0;
        $totalErrorCount = 0;
        $consecutiveErrors = 0;
        $maxConsecutiveErrors = 10;
        
        // 시작일자와 종료일자 사이의 일수 계산
        $startTimestamp = strtotime($startDate);
        $endTimestamp = strtotime($endDate);
        $totalDays = ($endTimestamp - $startTimestamp) / (24 * 60 * 60) + 1;
        
        $this->log("API 데이터 수집 시작 (일별): {$api['title']} (총 {$totalDays}일, 시작일자: {$startDate}, 종료일자: {$endDate}, 처리순서: 과거순)");
        
        // 시작일자부터 종료일자까지 일별 순회
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

        $this->log("API 데이터 수집 완료 (일별): {$api['title']} (성공: {$totalSuccessCount}일, 총오류: {$totalErrorCount}일, 최대연속오류: {$consecutiveErrors}회)");
    }

    /**
     * API 데이터 수집 (월별)
     */
    private function collectApiDataByMonth($api, $startDate, $endDate)
    {
        $totalSuccessCount = 0;
        $totalErrorCount = 0;
        $consecutiveErrors = 0;
        $maxConsecutiveErrors = 10;
        
        // 시작월과 종료월 계산
        $startYear = (int)date('Y', strtotime($startDate));
        $startMonth = (int)date('m', strtotime($startDate));
        $endYear = (int)date('Y', strtotime($endDate));
        $endMonth = (int)date('m', strtotime($endDate));
        
        // 총 월수 계산
        $totalMonths = (($endYear - $startYear) * 12) + ($endMonth - $startMonth) + 1;
        
        $this->log("API 데이터 수집 시작 (월별): {$api['title']} (총 {$totalMonths}개월, 시작: {$startYear}-{$startMonth}, 종료: {$endYear}-{$endMonth}, 처리순서: 과거순)");
        
        // 시작월부터 종료월까지 월별 순회
        for ($monthOffset = 0; $monthOffset < $totalMonths; $monthOffset++) {
            // Running 파일이 존재하는지 확인 (파일이 삭제되면 프로세스 종료)
            $this->checkRunningFile();
            
            // 현재 처리할 년월 계산
            $currentYear = $startYear;
            $currentMonth = $startMonth + $monthOffset;
            
            // 월이 12를 넘으면 년도 증가
            while ($currentMonth > 12) {
                $currentMonth -= 12;
                $currentYear++;
            }
            
            // 종료 년월을 초과하면 중단
            if ($currentYear > $endYear || ($currentYear == $endYear && $currentMonth > $endMonth)) {
                $this->log("종료 년월 초과로 중단: {$currentYear}-{$currentMonth}");
                break;
            }
            
            // 해당 월의 첫날을 targetDate로 사용 (데이터 저장용)
            $targetDate = sprintf('%04d-%02d-01', $currentYear, $currentMonth);
            
            // Migration 상태 업데이트
            $this->updateMigrationStatus($targetDate, $monthOffset + 1, $totalMonths);
            
            // dateChar 형식으로 날짜 포맷 (Ym 또는 Y-m)
            $dateChar = $api['dateChar'] ?? 'Ym';
            if ($dateChar === 'Ym') {
                $dateFormatted = sprintf('%04d%02d', $currentYear, $currentMonth);
            } else { // Y-m
                $dateFormatted = sprintf('%04d-%02d', $currentYear, $currentMonth);
            }
            
            $this->log("API 호출 시도 (월별): {$api['title']} (년월: {$currentYear}-{$currentMonth}, 포맷: {$dateFormatted}, 오프셋: {$monthOffset})");
            $this->currentDate = $targetDate;

            try {
                // API URL 생성
                $url = $this->buildApiUrl($api, $dateFormatted);
                
                // API 호출 (API 설정에 header가 있으면 함께 전송)
                $responseData = $this->callApi($url, $api['returnType'], $api['header'] ?? '');
                
                if (empty($responseData)) {
                    $this->log("API 응답이 비어있음: {$api['title']} (년월: {$currentYear}-{$currentMonth})", 'WARNING');
                    $consecutiveErrors++;
                    $totalErrorCount++;
                    
                    // 연속 오류가 많으면 경고 로그만 출력하고 계속 진행
                    if ($consecutiveErrors >= $maxConsecutiveErrors) {
                        $this->log("연속 오류 {$consecutiveErrors}회 발생. 종료 년월까지 계속 진행합니다.", 'WARNING');
                    }
                    continue;
                }
                
                // 데이터 파싱 및 저장 (targetDate는 해당 월의 첫날)
                $parsedData = $this->parseApiData($responseData, $api, $targetDate);
                
                if (!empty($parsedData)) {
                    // data 필드 유효성 검사
                    if ($this->hasValidDataField($parsedData)) {
                        $this->saveApiData($api, $parsedData, $targetDate);
                        $totalSuccessCount++;
                        $consecutiveErrors = 0; // 성공 시 연속 오류 카운트 리셋
                        $this->log("API 데이터 저장 완료: {$api['title']} (년월: {$currentYear}-{$currentMonth}, 데이터 수: " . count($parsedData) . ")");
                    } else {
                        $this->log("data 필드가 유효하지 않음: {$api['title']} (년월: {$currentYear}-{$currentMonth})", 'WARNING');
                        $consecutiveErrors++;
                        $totalErrorCount++;
                    }
                } else {
                    $this->log("파싱된 데이터가 없음: {$api['title']} (년월: {$currentYear}-{$currentMonth})", 'WARNING');
                    $consecutiveErrors++;
                    $totalErrorCount++;
                }
                
            } catch (Exception $e) {
                $this->log("API 호출 실패: {$api['title']} (년월: {$currentYear}-{$currentMonth}): " . $e->getMessage(), 'WARNING');
                $consecutiveErrors++;
                $totalErrorCount++;
                
                // 연속 오류가 많으면 경고 로그만 출력하고 계속 진행
                if ($consecutiveErrors >= $maxConsecutiveErrors) {
                    $this->log("연속 오류 {$consecutiveErrors}회 발생. 종료 년월까지 계속 진행합니다.", 'WARNING');
                }
            }
            
            // API 호출 간격 대기 (서버 부하 방지)
            if ($monthOffset < $totalMonths - 1) {
                sleep(1);
            }
        }

        $this->log("API 데이터 수집 완료 (월별): {$api['title']} (성공: {$totalSuccessCount}개월, 총오류: {$totalErrorCount}개월, 최대연속오류: {$consecutiveErrors}회)");
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
     * API 전체 수집 상태 업데이트
     * 
     * @param string $apiId API ID
     * @param string $status 상태 (processing, completed, failed)
     * @param string $startDate 수집 시작일자
     * @param string $endDate 수집 종료일자
     * @param string $errorMessage 오류 메시지 (실패 시)
     */
    private function updateApiCompletionStatus($apiId, $status, $startDate = null, $endDate = null, $errorMessage = null)
    {
        try {
            $filter = ['id' => $apiId];
            
            // 업데이트할 데이터 구성
            $updateData = ['$set' => ['collectionStatus' => $status]];
            
            // 수집 범위 정보 추가
            if ($startDate || $endDate) {
                $updateData['$set']['collectionRange'] = [
                    'startDate' => $startDate ?: 'N/A',
                    'endDate' => $endDate ?: 'N/A'
                ];
            }
            
            // 상태별 처리
            if ($status === 'processing') {
                // 진행 중
                $updateData['$set']['collectionStartedAt'] = date('Y-m-d H:i:s');
                $updateData['$set']['collectionMessage'] = '데이터 수집 진행 중';
                $statusText = '진행중';
            } else if ($status === 'completed') {
                // 완료
                $updateData['$set']['collectionCompletedAt'] = date('Y-m-d H:i:s');
                $updateData['$set']['collectionMessage'] = '전체 데이터 수집 완료';
                $statusText = '완료';
            } else if ($status === 'failed') {
                // 실패
                $updateData['$set']['collectionCompletedAt'] = date('Y-m-d H:i:s');
                $updateData['$set']['collectionMessage'] = $errorMessage ?: '데이터 수집 실패';
                $statusText = '실패';
            } else {
                // 기타
                $updateData['$set']['collectionMessage'] = $status;
                $statusText = $status;
            }
            
            // DB 업데이트 (상태 변경 시에만 실행)
            $this->db->update('api', $filter, $updateData);
            
            $rangeText = ($startDate && $endDate) ? " (기간: {$startDate} ~ {$endDate})" : "";
            $this->log("API 수집 상태 업데이트: {$apiId} → {$statusText}{$rangeText}");
            
        } catch (Exception $e) {
            $this->log("API 수집 상태 업데이트 실패: " . $e->getMessage(), 'WARNING');
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
