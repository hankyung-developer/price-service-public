<?php
/**
 * AI 기사 스케줄러 테스트 스크립트
 * 
 * 스케줄 설정을 확인하고 실행 조건을 테스트합니다.
 * 실제 기사는 생성하지 않고 실행 여부만 체크합니다.
 * 
 * @usage: php test_schedule.php
 */

date_default_timezone_set('Asia/Seoul');
require_once dirname(__DIR__) . '/classes/autoload.php';

use Kodes\Wcms\DB;
use Kodes\Wcms\AiSetting;

echo "\n";
echo "==========================================\n";
echo "AI 기사 스케줄러 테스트\n";
echo "현재 시간: " . date('Y-m-d H:i:s') . "\n";
echo "==========================================\n\n";

try {
    $db = new DB();
    
    // 활성 스케줄 조회
    $filter = [
        'isUse' => true,
        'delete.is' => ['$ne' => true]
    ];
    
    $options = [
        'projection' => ['_id' => 0],
        'sort' => ['write.date' => -1]
    ];
    
    $schedules = $db->list(AiSetting::AI_SCHEDULE_COLLECTION, $filter, $options);
    
    echo "총 활성 스케줄: " . count($schedules) . "개\n\n";
    
    if (empty($schedules)) {
        echo "❌ 활성화된 스케줄이 없습니다.\n";
        echo "   /aiSetting/scheduleEdit에서 스케줄을 생성하고 활성화하세요.\n\n";
        exit(0);
    }
    
    // 현재 시간
    $now = new DateTime();
    $currentTime = $now->format('H:i');
    $currentDayOfWeek = (int)$now->format('N');
    $currentDayOfMonth = (int)$now->format('d');
    $currentMonth = (int)$now->format('m');
    
    echo "현재 상태:\n";
    echo "  - 시각: {$currentTime}\n";
    echo "  - 요일: " . getDayName($currentDayOfWeek) . " ({$currentDayOfWeek})\n";
    echo "  - 날짜: {$currentDayOfMonth}일\n";
    echo "  - 월: {$currentMonth}월\n\n";
    
    $willExecute = 0;
    
    // 각 스케줄 체크
    foreach ($schedules as $index => $schedule) {
        $num = $index + 1;
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "스케줄 #{$num}: {$schedule['title']}\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        
        // 기본 정보
        echo "📋 기본 정보:\n";
        echo "  - idx: {$schedule['idx']}\n";
        echo "  - 템플릿: {$schedule['templateId']}\n";
        echo "  - 프롬프트: {$schedule['promptId']}\n";
        echo "  - 카테고리: {$schedule['categoryName']} ({$schedule['categoryId']})\n";
        echo "  - 품목 수: " . (count($schedule['selectedItems'] ?? [])) . "개\n";
        echo "  - 이미지: " . (($schedule['makeImage'] ?? 'N') === 'Y' ? '✓' : '✗') . "\n";
        echo "  - 차트: " . (($schedule['makeChart'] ?? 'N') === 'Y' ? '✓' : '✗') . "\n";
        echo "  - 데이터 기간: " . ($schedule['dataPeriod'] ?? 7) . "일\n";
        
        // 마지막 실행
        if (!empty($schedule['lastExecution']['date'])) {
            $lastExec = $schedule['lastExecution']['date'];
            $lastTime = strtotime($lastExec);
            $diff = time() - $lastTime;
            $diffMinutes = floor($diff / 60);
            
            echo "  - 마지막 실행: {$lastExec} ({$diffMinutes}분 전)\n";
        } else {
            echo "  - 마지막 실행: 없음\n";
        }
        
        echo "\n";
        
        // 스케줄 설정
        $config = $schedule['scheduleConfig'] ?? [];
        $type = $config['type'] ?? '';
        
        if (empty($config) || empty($type)) {
            echo "⚠️  스케줄 설정 없음\n\n";
            continue;
        }
        
        echo "⏰ 스케줄 설정:\n";
        echo "  - 타입: {$type}\n";
        
        $shouldExecute = false;
        $reason = '';
        
        switch ($type) {
            case 'daily':
                $scheduledTime = $config['daily']['time'] ?? '09:00';
                echo "  - 실행 시간: 매일 {$scheduledTime}\n";
                
                $shouldExecute = isTimeMatch($currentTime, $scheduledTime);
                $reason = $shouldExecute ? "현재 시간이 실행 시간과 일치" : "현재 시간이 실행 시간과 불일치";
                break;
                
            case 'weekly':
                $scheduledDays = $config['weekly']['days'] ?? [];
                $scheduledTime = $config['weekly']['time'] ?? '09:00';
                $dayNames = array_map('getDayName', $scheduledDays);
                
                echo "  - 실행 요일: " . implode(', ', $dayNames) . "\n";
                echo "  - 실행 시간: {$scheduledTime}\n";
                
                if (!in_array($currentDayOfWeek, $scheduledDays)) {
                    $reason = "오늘은 실행 요일이 아님";
                } else if (!isTimeMatch($currentTime, $scheduledTime)) {
                    $reason = "요일은 맞지만 시간이 불일치";
                } else {
                    $shouldExecute = true;
                    $reason = "요일과 시간이 모두 일치";
                }
                break;
                
            case 'monthly':
                $scheduledDates = $config['monthly']['dates'] ?? [];
                $scheduledTime = $config['monthly']['time'] ?? '09:00';
                
                echo "  - 실행 날짜: 매월 " . implode('일, ', $scheduledDates) . "일\n";
                echo "  - 실행 시간: {$scheduledTime}\n";
                
                if (!in_array($currentDayOfMonth, $scheduledDates)) {
                    $reason = "오늘은 실행 날짜가 아님";
                } else if (!isTimeMatch($currentTime, $scheduledTime)) {
                    $reason = "날짜는 맞지만 시간이 불일치";
                } else {
                    $shouldExecute = true;
                    $reason = "날짜와 시간이 모두 일치";
                }
                break;
                
            case 'quarterly':
                $scheduledMonths = $config['quarterly']['months'] ?? [];
                $scheduledDate = $config['quarterly']['date'] ?? 1;
                $scheduledTime = $config['quarterly']['time'] ?? '09:00';
                
                echo "  - 실행 월: " . implode('월, ', $scheduledMonths) . "월\n";
                echo "  - 실행 날짜: {$scheduledDate}일\n";
                echo "  - 실행 시간: {$scheduledTime}\n";
                
                if (!in_array($currentMonth, $scheduledMonths)) {
                    $reason = "이번 달은 실행 월이 아님";
                } else if ($currentDayOfMonth !== $scheduledDate) {
                    $reason = "월은 맞지만 날짜가 불일치";
                } else if (!isTimeMatch($currentTime, $scheduledTime)) {
                    $reason = "월과 날짜는 맞지만 시간이 불일치";
                } else {
                    $shouldExecute = true;
                    $reason = "월, 날짜, 시간이 모두 일치";
                }
                break;
                
            case 'yearly':
                $scheduledMonth = $config['yearly']['month'] ?? 1;
                $scheduledDate = $config['yearly']['date'] ?? 1;
                $scheduledTime = $config['yearly']['time'] ?? '09:00';
                
                echo "  - 실행 월: {$scheduledMonth}월\n";
                echo "  - 실행 날짜: {$scheduledDate}일\n";
                echo "  - 실행 시간: {$scheduledTime}\n";
                
                if ($currentMonth !== $scheduledMonth) {
                    $reason = "이번 달은 실행 월이 아님";
                } else if ($currentDayOfMonth !== $scheduledDate) {
                    $reason = "월은 맞지만 날짜가 불일치";
                } else if (!isTimeMatch($currentTime, $scheduledTime)) {
                    $reason = "월과 날짜는 맞지만 시간이 불일치";
                } else {
                    $shouldExecute = true;
                    $reason = "월, 날짜, 시간이 모두 일치";
                }
                break;
                
            default:
                echo "  ⚠️  알 수 없는 타입\n";
                $reason = "알 수 없는 스케줄 타입";
        }
        
        echo "\n";
        echo "📊 실행 판정:\n";
        
        if ($shouldExecute) {
            echo "  ✅ 실행 예정\n";
            echo "  📝 사유: {$reason}\n";
            $willExecute++;
        } else {
            echo "  ❌ 실행 안 함\n";
            echo "  📝 사유: {$reason}\n";
        }
        
        echo "\n";
    }
    
    echo "==========================================\n";
    echo "📈 요약:\n";
    echo "  - 총 스케줄: " . count($schedules) . "개\n";
    echo "  - 실행 예정: {$willExecute}개\n";
    echo "  - 건너뛸 스케줄: " . (count($schedules) - $willExecute) . "개\n";
    echo "==========================================\n\n";
    
    if ($willExecute > 0) {
        echo "💡 실제 실행하려면:\n";
        echo "   php /webSiteSource/wcms/cron/scheduleWriteArticle.php\n\n";
    } else {
        echo "💡 현재 시간에 실행할 스케줄이 없습니다.\n";
        echo "   스케줄 설정을 확인하거나 실행 시간을 기다리세요.\n\n";
    }
    
} catch (\Exception $e) {
    echo "❌ 오류 발생: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

/**
 * 시간 일치 여부 (±5분)
 */
function isTimeMatch($current, $scheduled)
{
    $currentSeconds = strtotime(date('Y-m-d') . ' ' . $current);
    $scheduledSeconds = strtotime(date('Y-m-d') . ' ' . $scheduled);
    $diff = abs($currentSeconds - $scheduledSeconds);
    
    return $diff <= 300; // 5분 = 300초
}

/**
 * 요일 이름 반환
 */
function getDayName($dayNum)
{
    $days = [
        1 => '월요일',
        2 => '화요일',
        3 => '수요일',
        4 => '목요일',
        5 => '금요일',
        6 => '토요일',
        7 => '일요일'
    ];
    
    return $days[$dayNum] ?? '알 수 없음';
}

exit(0);



