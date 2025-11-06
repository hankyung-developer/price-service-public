<?php
/**
 * AI 기사 자동 생성 스케줄러
 * 
 * scheduleEdit.html에 저장된 스케줄 정보를 기반으로
 * 설정된 시간에 자동으로 AI 기사를 생성합니다.
 * 
 * @usage: Linux Crontab
 * # 매 시간 정각마다 실행 (1분 단위로 실행하면 더 정확)
 * 0 * * * * /usr/bin/php /webSiteSource/wcms/cron/scheduleWriteArticle.php >> /webSiteSource/wcms/cron/logs/schedule_article.log 2>&1
 * 
 * 또는 매분 실행 (더 정확한 시간 체크)
 * * * * * * /usr/bin/php /webSiteSource/wcms/cron/scheduleWriteArticle.php >> /webSiteSource/wcms/cron/logs/schedule_article.log 2>&1
 * 
 * @author  Kodes <kodesinfo@gmail.com>
 * @version 1.0
 */

// 에러 리포팅 설정
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// 타임존 설정
date_default_timezone_set('Asia/Seoul');

// 출력 버퍼링 시작
ob_start();

// 세션 시작 (Article 클래스에서 세션 사용)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 크론잡용 기본 세션 설정
if (empty($_SESSION['managerId'])) {
    $_SESSION['managerId'] = 'scheduler';
    $_SESSION['managerName'] = 'AI 스케줄러';
    $_SESSION['coId'] = 'hkp';  // 기본 회사 ID
}

// CLI 환경에서 REQUEST_METHOD 설정
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'datacms.hankyung.com';
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/cron/scheduleWriteArticle';
$_SERVER['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

// 클래스 자동 로드
require_once dirname(__DIR__) . '/classes/autoload.php';

use Kodes\Wcms\DB;
use Kodes\Wcms\Common;
use Kodes\Wcms\Article;
use Kodes\Wcms\AiSetting;
use Kodes\Wcms\Api;
use Kodes\Wcms\Log;
use Kodes\Wcms\Category;

/**
 * AI 기사 자동 생성 스케줄러 클래스
 */
class ScheduleArticleWriter
{
    /** @var DB */
    private $db;
    
    /** @var Common */
    private $common;
    
    /** @var Article */
    private $article;
    
    /** @var AiSetting */
    private $aiSetting;
    
    /** @var Log */
    private $log;
    
    /** @var string 로그 파일 경로 */
    private $logFile;
    
    /** @var string 락 파일 경로 (중복 실행 방지) */
    private $lockFile;
    
    /** @var resource 락 파일 핸들 */
    private $lockHandle;
    
    /** @var bool 디버그 모드 */
    private $debug = true;
    
    /**
     * 생성자
     */
    public function __construct()
    {
        $this->db = new DB();
        $this->common = new Common();
        $this->article = new Article();
        $this->aiSetting = new AiSetting();
        $this->log = new Log();
        
        // 로그 디렉토리 생성
        $logDir = dirname(__DIR__) . '/cron/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $this->logFile = $logDir . '/schedule_article_' . date('Y-m-d') . '.log';
        $this->lockFile = $logDir . '/schedule_article.lock';
        $this->lockHandle = null;  // 락 핸들 초기화
    }
    
    /**
     * 메인 실행 함수
     */
    public function run()
    {
        try {
            $this->writeLog("========================================");
            $this->writeLog("AI 기사 자동 생성 시작: " . date('Y-m-d H:i:s'));
            
            // 중복 실행 방지 락 획득
            if (!$this->acquireLock()) {
                $this->writeLog("이미 실행 중입니다. 종료합니다.");
                return;
            }
            
            // 1. 활성화된 스케줄 조회
            $schedules = $this->getActiveSchedules();
            $this->writeLog("활성 스케줄 개수: " . count($schedules));
            
            if (empty($schedules)) {
                $this->writeLog("실행할 스케줄이 없습니다.");
                $this->releaseLock();
                return;
            }
            
            // 2. 각 스케줄 처리
            $executedCount = 0;
            $errorCount = 0;
            
            foreach ($schedules as $schedule) {
                try {
                    // 실행 시간 체크
                    if ($this->shouldExecute($schedule)) {
                        $this->writeLog("스케줄 실행: [{$schedule['title']}] (idx: {$schedule['idx']})");
                        
                        // AI 기사 생성
                        $result = $this->generateArticle($schedule);
                        
                        if ($result['success']) {
                            $executedCount++;
                            $this->writeLog("✓ 기사 생성 성공: {$result['aid']}");
                            
                            // 마지막 실행 시간 업데이트
                            $this->updateLastExecutionTime($schedule['idx']);
                        } else {
                            $errorCount++;
                            $this->writeLog("✗ 기사 생성 실패: " . ($result['msg'] ?? '알 수 없는 오류'));
                        }
                    }
                } catch (\Exception $e) {
                    $errorCount++;
                    $this->writeLog("✗ 스케줄 처리 오류 [{$schedule['title']}]: " . $e->getMessage());
                }
            }
            
            $this->writeLog("실행 완료 - 성공: {$executedCount}, 실패: {$errorCount}");
            $this->writeLog("========================================\n");
            
        } catch (\Exception $e) {
            $this->writeLog("치명적 오류: " . $e->getMessage());
            $this->writeLog("Stack trace: " . $e->getTraceAsString());
        } finally {
            $this->releaseLock();
        }
    }
    
    /**
     * 활성화된 스케줄 조회
     * 
     * @return array 스케줄 목록
     */
    private function getActiveSchedules()
    {
        $filter = [
            'isUse' => true,  // 사용 중
            'delete.is' => ['$ne' => true]  // 삭제되지 않음
        ];
        
        $options = [
            'projection' => ['_id' => 0],
            'sort' => ['write.date' => -1]
        ];
        
        return $this->db->list(AiSetting::AI_SCHEDULE_COLLECTION, $filter, $options);
    }
    
    /**
     * 스케줄 실행 여부 판단
     * 
     * @param array $schedule 스케줄 정보
     * @return bool 실행 여부
     */
    private function shouldExecute($schedule)
    {
        // scheduleConfig 확인
        if (empty($schedule['scheduleConfig']) || !is_array($schedule['scheduleConfig'])) {
            $this->writeLog("  스케줄 설정 없음");
            return false;
        }
        
        $config = $schedule['scheduleConfig'];
        $type = $config['type'] ?? '';
        
        // 현재 시간
        $now = new DateTime();
        $currentTime = $now->format('H:i');
        $currentDayOfWeek = (int)$now->format('N'); // 1(월) ~ 7(일)
        $currentDayOfMonth = (int)$now->format('d');
        $currentMonth = (int)$now->format('m');
        
        $this->writeLog("  타입: {$type}, 현재시간: {$currentTime}");
        $this->writeLog("  현재 상태 - 요일: {$currentDayOfWeek}, 날짜: {$currentDayOfMonth}, 월: {$currentMonth}");
        
        // 마지막 실행 시간 체크 (동일 시간대 중복 실행 방지)
        // 5분 이내에만 중복 실행 방지 (같은 시간대 내 크론 중복 방지용)
        if ($this->wasExecutedRecently($schedule, 5)) {
            $this->writeLog("  최근 실행됨 (5분 이내 중복 실행 방지)");
            return false;
        }
        
        // 타입별 실행 조건 체크
        switch ($type) {
            case 'daily':
                // 매일 지정된 시간
                $scheduledTime = $config['daily']['time'] ?? '09:00';
                return $this->isTimeMatch($currentTime, $scheduledTime);
                
            case 'weekly':
                // 지정된 요일의 지정된 시간
                $scheduledDays = $config['weekly']['days'] ?? [];
                $scheduledTime = $config['weekly']['time'] ?? '09:00';
                
                $this->writeLog("  주간 설정 - 요일: [" . implode(',', $scheduledDays) . "], 시간: {$scheduledTime}");
                
                if (empty($scheduledDays)) {
                    $this->writeLog("  ✗ 설정된 요일 없음");
                    return false;
                }
                
                if (!in_array($currentDayOfWeek, $scheduledDays)) {
                    $this->writeLog("  요일 불일치 (설정: " . implode(',', $scheduledDays) . ", 현재: {$currentDayOfWeek})");
                    return false;
                }
                
                $this->writeLog("  요일 일치 ✓");
                return $this->isTimeMatch($currentTime, $scheduledTime);
                
            case 'monthly':
                // 지정된 날짜의 지정된 시간
                $scheduledDates = $config['monthly']['dates'] ?? [];
                $scheduledTime = $config['monthly']['time'] ?? '09:00';
                
                $this->writeLog("  월간 설정 - 날짜: [" . implode(',', $scheduledDates) . "], 시간: {$scheduledTime}");
                
                if (empty($scheduledDates)) {
                    $this->writeLog("  ✗ 설정된 날짜 없음");
                    return false;
                }
                
                if (!in_array($currentDayOfMonth, $scheduledDates)) {
                    $this->writeLog("  날짜 불일치 (설정: " . implode(',', $scheduledDates) . ", 현재: {$currentDayOfMonth})");
                    return false;
                }
                
                $this->writeLog("  날짜 일치 ✓");
                return $this->isTimeMatch($currentTime, $scheduledTime);
                
            case 'quarterly':
                // 분기별 지정 월의 지정 날짜, 시간
                $scheduledMonths = $config['quarterly']['months'] ?? [];
                $scheduledDate = $config['quarterly']['date'] ?? 1;
                $scheduledTime = $config['quarterly']['time'] ?? '09:00';
                
                if (!in_array($currentMonth, $scheduledMonths)) {
                    $this->writeLog("  월 불일치 (설정: " . implode(',', $scheduledMonths) . ", 현재: {$currentMonth})");
                    return false;
                }
                
                if ($currentDayOfMonth !== $scheduledDate) {
                    $this->writeLog("  날짜 불일치 (설정: {$scheduledDate}, 현재: {$currentDayOfMonth})");
                    return false;
                }
                
                return $this->isTimeMatch($currentTime, $scheduledTime);
                
            case 'yearly':
                // 매년 지정 월의 지정 날짜, 시간
                $scheduledMonth = $config['yearly']['month'] ?? 1;
                $scheduledDate = $config['yearly']['date'] ?? 1;
                $scheduledTime = $config['yearly']['time'] ?? '09:00';
                
                if ($currentMonth !== $scheduledMonth) {
                    $this->writeLog("  월 불일치 (설정: {$scheduledMonth}, 현재: {$currentMonth})");
                    return false;
                }
                
                if ($currentDayOfMonth !== $scheduledDate) {
                    $this->writeLog("  날짜 불일치 (설정: {$scheduledDate}, 현재: {$currentDayOfMonth})");
                    return false;
                }
                
                return $this->isTimeMatch($currentTime, $scheduledTime);
                
            default:
                $this->writeLog("  알 수 없는 스케줄 타입: {$type}");
                return false;
        }
    }
    
    /**
     * 시간 일치 여부 확인 (±5분 허용)
     * 
     * @param string $currentTime 현재 시간 (HH:MM)
     * @param string $scheduledTime 예약 시간 (HH:MM)
     * @return bool 일치 여부
     */
    private function isTimeMatch($currentTime, $scheduledTime)
    {
        $current = strtotime(date('Y-m-d') . ' ' . $currentTime);
        $scheduled = strtotime(date('Y-m-d') . ' ' . $scheduledTime);
        
        // ±5분 이내면 실행
        $diff = abs($current - $scheduled);
        $isMatch = $diff <= 300; // 5분 = 300초
        
        if ($isMatch) {
            $this->writeLog("  시간 일치: {$currentTime} ≈ {$scheduledTime} (차이: {$diff}초)");
        } else {
            $this->writeLog("  시간 불일치: {$currentTime} vs {$scheduledTime} (차이: {$diff}초)");
        }
        
        return $isMatch;
    }
    
    /**
     * 최근 실행 여부 체크
     * 
     * @param array $schedule 스케줄 정보
     * @param int $minutes 분 단위 (기본: 5분)
     * @return bool 최근 실행 여부
     */
    private function wasExecutedRecently($schedule, $minutes = 5)
    {
        if (empty($schedule['lastExecution']['date'])) {
            return false;
        }
        
        $lastExecution = strtotime($schedule['lastExecution']['date']);
        $now = time();
        $diff = $now - $lastExecution;
        
        // 디버그 로그
        if ($diff < ($minutes * 60)) {
            $lastTimeStr = date('Y-m-d H:i:s', $lastExecution);
            $diffMinutes = round($diff / 60, 1);
            $this->writeLog("  마지막 실행: {$lastTimeStr} ({$diffMinutes}분 전)");
        }
        
        return $diff < ($minutes * 60);
    }
    
    /**
     * AI 기사 생성
     * 
     * @param array $schedule 스케줄 정보
     * @return array 결과
     */
    private function generateArticle($schedule)
    {
        try {
            // 스케줄 작성자 정보를 세션에 설정 (기사 작성자로 기록됨)
            if (!empty($schedule['insert']['managerId'])) {
                $_SESSION['managerId'] = $schedule['insert']['managerId'];
                $_SESSION['managerName'] = $schedule['insert']['managerName'] ?? 'AI 스케줄러';
                $this->writeLog("  작성자: {$_SESSION['managerName']} ({$_SESSION['managerId']})");
            }
            
            // 1. 스케줄 정보 파싱
            $categoryId = $schedule['categoryId'] ?? '';
            $templateIdx = (int)($schedule['templateId'] ?? 0);
            $promptIdx = (int)($schedule['promptId'] ?? 0);
            
            // selectedItems 파싱 (문자열일 경우 JSON 디코딩)
            $selectedItems = $schedule['selectedItems'] ?? [];
            if (is_string($selectedItems)) {
                $selectedItems = json_decode($selectedItems, true) ?: [];
            }
            if (!is_array($selectedItems)) {
                $selectedItems = [];
            }
            
            $makeImage = ($schedule['makeImage'] ?? 'no-generate') === 'generate';
            $makeChart = ($schedule['makeChart'] ?? 'no-generate') === 'generate';
            $dataPeriod = (int)($schedule['dataPeriod'] ?? 7);
            
            $this->writeLog("  카테고리: {$categoryId}, 템플릿: {$templateIdx}, 프롬프트: {$promptIdx}");
            $this->writeLog("  이미지: " . ($makeImage ? 'Y' : 'N') . ", 차트: " . ($makeChart ? 'Y' : 'N'));
            $this->writeLog("  데이터 기간: {$dataPeriod}일");
            $this->writeLog("  품목 개수: " . count($selectedItems));
            
            // 필수 정보 검증
            if (empty($categoryId) || empty($templateIdx) || empty($promptIdx)) {
                throw new \Exception('필수 정보가 누락되었습니다.');
            }
            
            // 2. 품목 데이터 가져오기 (API 또는 selectedItems 사용)
            $items = $this->getItems($categoryId, $selectedItems, $dataPeriod);
            
            if (empty($items)) {
                throw new \Exception('품목 데이터를 가져올 수 없습니다.');
            }
            
            // 3. AI 기사 초안 생성
            $this->writeLog("  AI 기사 초안 생성 중...");
            $_POST = [
                'items' => json_encode($items),
                'categoryId' => $categoryId,
                'templateIdx' => $templateIdx,
                'promptIdx' => $promptIdx,
                'modelIdx' => 4, // gpt-4o
                'articlePrompt' => '',
                'makeImage' => $makeImage ? 'generate' : 'no-generate',
                'makeChart' => $makeChart ? 'generate' : 'no-generate'
            ];
            
            $this->writeLog("    POST 데이터 준비 완료");
            
            // aiDraft() 직접 호출 (CLI 환경에서는 배열 반환)
            $draft = $this->article->aiDraft();
            
            // 결과 검증
            if (!is_array($draft)) {
                $this->writeLog("    ✗ 잘못된 응답 타입: " . gettype($draft));
                throw new \Exception('AI Draft 응답이 배열이 아님');
            }
            
            if (empty($draft['success']) || empty($draft['data'])) {
                $errorMsg = $draft['msg'] ?? '알 수 없는 오류';
                $this->writeLog("    ✗ AI Draft 실패: {$errorMsg}");
                throw new \Exception('AI 기사 초안 생성 실패: ' . $errorMsg);
            }
            
            $this->writeLog("  ✓ 초안 생성 완료");
            
            $articleData = $draft['data'];
            $chartData = $articleData['chart_data'] ?? null;
            
            $this->writeLog("    제목: " . ($articleData['title'] ?? '없음'));
            $this->writeLog("    부제: " . ($articleData['subtitle'] ?? '없음'));
            $this->writeLog("    본문 길이: " . strlen($articleData['content'] ?? '') . "자");
            
            // 4. 이미지 생성 (옵션)
            $imageInfo = null;
            if ($makeImage && !empty($articleData['image_prompt'])) {
                $this->writeLog("  이미지 생성 중...");
                $imageInfo = $this->generateImage($articleData['image_prompt']);
                
                if ($imageInfo['success']) {
                    $this->writeLog("  ✓ 이미지 생성 완료");
                } else {
                    $this->writeLog("  ✗ 이미지 생성 실패 (계속 진행)");
                }
            }
            
            // 6. 본문 HTML 생성
            $reviewContent = $this->buildReviewContent($articleData, $imageInfo);
            
            // 7. 기사 저장
            $this->writeLog("  기사 저장 중...");
            
            // 이미지 데이터 키 이름 변환 (image_url → url, image_path → path)
            $imageData = null;
            if (!empty($imageInfo['data'])) {
                $imageData = [
                    'url' => $imageInfo['data']['image_url'] ?? '',
                    'path' => $imageInfo['data']['image_path'] ?? '',
                    'filename' => $imageInfo['data']['image_filename'] ?? ''
                ];
            }
            
            // MongoDB 저장용: items를 간단한 {id, title} 형식으로 변환
            $simpleItems = [];
            $itemCount = 0;
            $maxItems = 10;
            
            foreach ($items as $item) {
                if ($itemCount >= $maxItems) {
                    break;
                }
                
                $simpleItems[] = [
                    'id' => $item['sid'] ?? '',
                    'title' => $item['name'] ?? ''
                ];
                $itemCount++;
            }
            
            $saveData = [
                'categoryId' => $categoryId,
                'title' => $articleData['title'] ?? '',
                'subtitle' => $articleData['subtitle'] ?? '',
                'body' => $articleData['content'] ?? '',
                'reviewContent' => $reviewContent,
                'tags' => $articleData['tags'] ?? [],
                'items' => $simpleItems,  // 간단한 형식으로 변환된 데이터
                'image_prompt' => $articleData['image_prompt'] ?? '',
                'image' => $imageData,
                'chart' => (!empty($chartInfo['data']) ? $chartInfo['data'] : null)
            ];
            
            $this->writeLog("    저장 데이터 준비 완료");
            $this->writeLog("    - 품목: " . count($simpleItems) . "개 (간단한 형식)");
            $this->writeLog("    - 이미지: " . (!empty($imageData) ? 'O' : 'X'));
            if (!empty($imageData)) {
                $this->writeLog("      URL: " . ($imageData['url'] ?? '없음'));
                $this->writeLog("      Path: " . ($imageData['path'] ?? '없음'));
            }
            $this->writeLog("    - 차트: " . (!empty($chartInfo['data']) ? 'O' : 'X'));
            
            $_POST = ['data' => json_encode($saveData)];
            
            // aiSave() 직접 호출 (CLI 환경에서는 배열 반환)
            $saveResult = $this->article->aiSave();
            
            // 결과 검증
            if (!is_array($saveResult)) {
                $this->writeLog("    ✗ 잘못된 응답 타입: " . gettype($saveResult));
                throw new \Exception('aiSave 응답이 배열이 아님');
            }
            
            if (empty($saveResult['success'])) {
                $errorMsg = $saveResult['msg'] ?? '알 수 없는 오류';
                $this->writeLog("    ✗ 저장 실패: {$errorMsg}");
                throw new \Exception('기사 저장 실패: ' . $errorMsg);
            }
            
            $aid = $saveResult['aid'] ?? '';
            $this->writeLog("  ✓ 기사 저장 완료: {$aid}");
            $this->writeLog("    MongoDB 저장: " . ($aid ? 'O' : 'X'));
            
            // 저장된 이미지 정보 확인
            if (!empty($saveResult['data']['image'])) {
                $this->writeLog("    이미지 저장: O");
                $this->writeLog("      저장된 경로: " . ($saveResult['data']['image']['path'] ?? '없음'));
            } else {
                $this->writeLog("    이미지 저장: X");
            }
            
            $this->writeLog("    전송 디렉토리: /wcms/sendArticle/{$aid}.json");
            
            return [
                'success' => true,
                'aid' => $aid,
                'msg' => '기사가 성공적으로 생성되었습니다.',
                'data' => $saveResult['data'] ?? []
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'msg' => $e->getMessage()
            ];
        }
    }
    
    /**
     * 품목 데이터 가져오기
     * 
     * @param string $categoryId 카테고리 ID
     * @param array $selectedItems 선택된 품목 (옵션)
     * @param int $dataPeriod 데이터 기간 (일)
     * @return array 품목 목록
     */
    private function getItems($categoryId, $selectedItems = [], $dataPeriod = 7)
    {
        try {
            // selectedItems가 있으면 해당 품목만 사용
            if (!empty($selectedItems) && is_array($selectedItems)) {
                $sids = array_column($selectedItems, 'id');
                $_GET['sid'] = implode(',', $sids);
                $this->writeLog("  품목 ID로 조회: " . $_GET['sid']);
            } else {
                // 없으면 카테고리 전체
                $_GET['categoryId'] = $categoryId;
                $this->writeLog("  카테고리 ID로 조회: {$categoryId}");
            }
            
            $_GET['startDate'] = date('Y-m-d', strtotime("-{$dataPeriod} days"));
            $_GET['endDate'] = date('Y-m-d');
            
            // dataPeriod에 따른 정렬 기준 설정
            switch ($dataPeriod) {
                case 1:
                    $_GET['sortField'] = 'prevDayChange';
                    break;
                case 7:
                    $_GET['sortField'] = 'oneWeekAgoChange';
                    break;
                case 30:
                    $_GET['sortField'] = 'oneMonthAgoChange';
                    break;
                case 90:
                    $_GET['sortField'] = 'threeMonthsAgoChange';
                    break;
                case 180:
                    $_GET['sortField'] = 'sixMonthsAgoChange';
                    break;
                case 365:
                    $_GET['sortField'] = 'oneYearAgoChange';
                    break;
                default:
                    $_GET['sortField'] = 'oneWeekAgoChange';
            }
            
            $_GET['sortOrder'] = 'desc';
            
            $this->writeLog("  조회 기간: {$_GET['startDate']} ~ {$_GET['endDate']}");
            $this->writeLog("  정렬 기준: {$_GET['sortField']} (desc)");
            
            $api = new Api();
            $response = $api->data();

            if (empty($response['data'])) {
                $this->writeLog("  ⚠ API 응답 데이터 없음");
                return [];
            }
            
            // 전체 데이터 반환 (AI 기사 작성에 필요)
            // MongoDB 저장 시에는 generateArticle()에서 간단한 형식으로 변환
            $this->writeLog("  품목 로드 완료: " . count($response['data']) . "개");
            
            return $response['data'];
            
        } catch (\Exception $e) {
            $this->writeLog("  ✗ 품목 데이터 조회 오류: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * 차트 생성
     * 
     * @param array $chartData 차트 데이터
     * @param string $chartTitle 차트 제목
     * @return array 차트 정보
     */
    private function generateChart($chartData, $chartTitle)
    {
        try {
            $_POST = [
                'chartData' => json_encode($chartData),
                'chartType' => 'line',
                'chartTitle' => $chartTitle,
                'maxItems' => 5
            ];
            
            $this->writeLog("    차트 타입: line, 제목: {$chartTitle}");
            
            // aiGenerateChartCode() 직접 호출 (CLI 환경에서는 배열 반환)
            $result = $this->article->aiGenerateChartCode();
            
            if (empty($result['success'])) {
                $this->writeLog("    차트 생성 실패: " . ($result['msg'] ?? '알 수 없는 오류'));
            } else {
                $this->writeLog("    차트 URL: " . ($result['data']['chart_url'] ?? ''));
            }
            
            return $result;
            
        } catch (\Exception $e) {
            $this->writeLog("    ✗ 차트 생성 예외: " . $e->getMessage());
            return ['success' => false, 'msg' => $e->getMessage()];
        }
    }
    
    /**
     * 이미지 생성
     * 
     * @param string $imagePrompt 이미지 프롬프트
     * @return array 이미지 정보
     */
    private function generateImage($imagePrompt)
    {
        try {
            $_POST = [
                'imagePrompt' => $imagePrompt,
                'imageModel' => 'dall-e-3',
                'imageSize' => '1792x1024',
                'imageQuality' => 'hd',      // 고품질 이미지
                'imageStyle' => 'natural'     // 사진 스타일 (자연스럽고 사실적)
            ];
            
            $this->writeLog("    이미지 프롬프트: " . substr($imagePrompt, 0, 100) . "...");
            
            // aiGenerateArticleImage() 직접 호출 (CLI 환경에서는 배열 반환)
            $result = $this->article->aiGenerateArticleImage();
            
            if (empty($result['success'])) {
                $this->writeLog("    ✗ 이미지 생성 실패: " . ($result['msg'] ?? '알 수 없는 오류'));
            } else {
                $this->writeLog("    ✓ 이미지 생성 성공");
                $this->writeLog("      URL: " . ($result['data']['image_url'] ?? '없음'));
                $this->writeLog("      Path: " . ($result['data']['image_path'] ?? '없음'));
                $this->writeLog("      Filename: " . ($result['data']['image_filename'] ?? '없음'));
            }
            
            return $result;
            
        } catch (\Exception $e) {
            $this->writeLog("    ✗ 이미지 생성 예외: " . $e->getMessage());
            return ['success' => false, 'msg' => $e->getMessage()];
        }
    }
    
    /**
     * 본문 HTML 생성
     * 
     * @param array $articleData 기사 데이터
     * @param array $imageInfo 이미지 정보
     * @param array $chartInfo 차트 정보
     * @return string HTML
     */
    private function buildReviewContent($articleData, $imageInfo=null, $chartInfo=null)
    {
        $html = '';
        
        // 본문
        $content = $articleData['content'] ?? '';
        $paragraphs = explode("\n\n", $content);
        
        // 이미지 HTML 생성 (1개, 사진 스타일)
        $imageHtml = '';
        if (!empty($imageInfo['data']['image_url'])) {
            $imageUrl = $imageInfo['data']['image_url'];
            $imageCaption = $articleData['title'] ?? '';
            $imageHtml = '<div class="article-image" style="margin: 20px 0;">';
            $imageHtml .= '<img src="' . htmlspecialchars($imageUrl) . '" alt="' . htmlspecialchars($imageCaption) . '" style="max-width: 100%; height: auto; display: block;">';
            $imageHtml .= '<p class="caption" style="font-size: 0.9em; color: #666; margin-top: 8px; text-align: center;">' . htmlspecialchars($imageCaption) . '</p>';
            $imageHtml .= '</div>';
        }
        
        // 본문 구성: 첫 번째 단락 → 이미지 → 나머지 단락
        $paraCount = count($paragraphs);
        foreach ($paragraphs as $index => $para) {
            $para = trim($para);
            if (!empty($para)) {
                // HTML 태그가 이미 있는 경우 (표 등) 그대로 사용, 없으면 p 태그로 감싸기
                if (strpos($para, '<table') !== false || strpos($para, '<div') !== false) {
                    $html .= $para;
                } else {
                    $html .= '<p>' . nl2br(htmlspecialchars($para)) . '</p>';
                }
                
                // 첫 번째 단락 뒤에 이미지 삽입 (이미지가 있는 경우)
                if ($index === 0 && !empty($imageHtml)) {
                    $html .= $imageHtml;
                }
            }
        }
        
        // 차트
        if (!empty($chartInfo['data']['chart_url'])) {
            $chartUrl = $chartInfo['data']['chart_url'];
            $html .= '<div class="article-chart" style="margin: 20px 0;">';
            $html .= '<iframe id="reviewChartFrame" src="' . htmlspecialchars($chartUrl) . '" style="width: 100%; height: 400px; border: none;"></iframe>';
            $html .= '</div>';
        }
        
        return $html;
    }
    
    /**
     * 마지막 실행 시간 업데이트
     * 
     * @param int $idx 스케줄 idx
     */
    private function updateLastExecutionTime($idx)
    {
        try {
            $filter = ['idx' => $idx];
            
            // MongoDB $set 연산자 사용 (특정 필드만 업데이트, 전체 문서 덮어쓰기 방지)
            $data = [
                '$set' => [
                    'lastExecution' => [
                        'date' => date('Y-m-d H:i:s'),
                        'status' => 'success'
                    ]
                ]
            ];
            
            $this->db->update(AiSetting::AI_SCHEDULE_COLLECTION, $filter, $data);
            $this->writeLog("  마지막 실행 시간 업데이트 완료");
            
        } catch (\Exception $e) {
            $this->writeLog("  ✗ 마지막 실행 시간 업데이트 실패: " . $e->getMessage());
        }
    }
    
    /**
     * 락 파일 획득 (중복 실행 방지)
     * 
     * @return bool 락 획득 성공 여부
     */
    private function acquireLock()
    {
        $this->lockHandle = @fopen($this->lockFile, 'c');
        
        if ($this->lockHandle === false) {
            $this->lockHandle = null;
            return false;
        }
        
        $locked = @flock($this->lockHandle, LOCK_EX | LOCK_NB);
        
        if (!$locked) {
            // 락 획득 실패 시 파일 닫기
            if (is_resource($this->lockHandle)) {
                fclose($this->lockHandle);
            }
            $this->lockHandle = null;
            return false;
        }
        
        return true;
    }
    
    /**
     * 락 파일 해제
     */
    private function releaseLock()
    {
        if ($this->lockHandle && is_resource($this->lockHandle)) {
            flock($this->lockHandle, LOCK_UN);
            fclose($this->lockHandle);
            $this->lockHandle = null;
        }
    }
    
    /**
     * 로그 기록
     * 
     * @param string $message 로그 메시지
     */
    private function writeLog($message)
    {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] {$message}\n";
        
        // 파일에 기록
        file_put_contents($this->logFile, $logMessage, FILE_APPEND);
        
        // 디버그 모드면 콘솔 출력
        if ($this->debug) {
            echo $logMessage;
        }
    }
}

// 실행
try {
    $scheduler = new ScheduleArticleWriter();
    $scheduler->run();
    
    // 출력 버퍼 정리
    if (ob_get_level()) {
        ob_end_flush();
    }
    
    exit(0);
    
} catch (\Exception $e) {
    // 출력 버퍼 정리
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    echo "치명적 오류: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
