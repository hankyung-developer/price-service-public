<?php
/**
 * 스케줄러 관리 클래스
 * 일간, 주간, 월간, 분기, 년간 스케줄러를 관리합니다.
 */
class Scheduler {
    private $db;
    private $coId;

    public function __construct($db, $coId) {
        $this->db = $db;
        $this->coId = $coId;
    }

    /**
     * 스케줄러 목록 조회
     */
    public function getList($params = []) {
        $page = isset($params['page']) ? (int)$params['page'] : 1;
        $limit = isset($params['limit']) ? (int)$params['limit'] : 20;
        $offset = ($page - 1) * $limit;
        
        $where = "WHERE coId = ?";
        $bindParams = [$this->coId];
        
        // 검색 조건 추가
        if (!empty($params['searchText'])) {
            $searchItem = $params['searchItem'] ?? 'name';
            $searchText = $params['searchText'];
            
            switch ($searchItem) {
                case 'name':
                    $where .= " AND name LIKE ?";
                    $bindParams[] = "%{$searchText}%";
                    break;
                case 'type':
                    $where .= " AND type = ?";
                    $bindParams[] = $searchText;
                    break;
                case 'status':
                    $where .= " AND status = ?";
                    $bindParams[] = $searchText;
                    break;
            }
        }
        
        // 전체 개수 조회
        $countSql = "SELECT COUNT(*) as cnt FROM scheduler {$where}";
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($bindParams);
        $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['cnt'];
        
        // 목록 조회
        $sql = "SELECT * FROM scheduler {$where} ORDER BY id DESC LIMIT {$limit} OFFSET {$offset}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($bindParams);
        $list = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 페이징 정보 생성
        $totalPages = ceil($totalCount / $limit);
        $paging = [];
        for ($i = 1; $i <= $totalPages; $i++) {
            $paging[] = [
                'page' => $i,
                'current' => ($i == $page)
            ];
        }
        
        return [
            'list' => $list,
            'paging' => $paging,
            'totalCount' => $totalCount,
            'currentPage' => $page
        ];
    }

    /**
     * 스케줄러 상세 조회
     */
    public function getInfo($id) {
        $sql = "SELECT * FROM scheduler WHERE id = ? AND coId = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id, $this->coId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * 스케줄러 저장 (등록/수정)
     */
    public function save($data) {
        $id = $data['id'] ?? null;
        
        // 스케줄 시간 정보 생성
        $scheduleTime = $this->generateScheduleTime($data);
        
        if ($id) {
            // 수정
            $sql = "UPDATE scheduler SET 
                    name = ?, description = ?, type = ?, status = ?, 
                    scheduleTime = ?, command = ?, workingDirectory = ?, 
                    environment = ?, timeout = ?, retryCount = ?, retryDelay = ?,
                    updateDate = NOW()
                    WHERE id = ? AND coId = ?";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                $data['name'],
                $data['description'],
                $data['type'],
                $data['status'],
                $scheduleTime,
                $data['command'],
                $data['workingDirectory'],
                $data['environment'],
                $data['timeout'] ?? 300,
                $data['retryCount'] ?? 0,
                $data['retryDelay'] ?? 60,
                $id,
                $this->coId
            ]);
        } else {
            // 등록
            $sql = "INSERT INTO scheduler (
                    coId, name, description, type, status, scheduleTime, 
                    command, workingDirectory, environment, timeout, retryCount, retryDelay,
                    insertDate, updateDate
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                $this->coId,
                $data['name'],
                $data['description'],
                $data['type'],
                $data['status'],
                $scheduleTime,
                $data['command'],
                $data['workingDirectory'],
                $data['environment'],
                $data['timeout'] ?? 300,
                $data['retryCount'] ?? 0,
                $data['retryDelay'] ?? 60
            ]);
            
            if ($result) {
                $id = $this->db->lastInsertId();
            }
        }
        
        return $result ? $id : false;
    }

    /**
     * 스케줄러 삭제
     */
    public function delete($ids) {
        if (!is_array($ids)) {
            $ids = [$ids];
        }
        
        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        $sql = "DELETE FROM scheduler WHERE id IN ({$placeholders}) AND coId = ?";
        
        $params = array_merge($ids, [$this->coId]);
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute($params);
    }

    /**
     * 스케줄러 테스트 실행
     */
    public function test($id) {
        $info = $this->getInfo($id);
        if (!$info) {
            return ['success' => false, 'message' => '스케줄러를 찾을 수 없습니다.'];
        }
        
        try {
            $result = $this->executeCommand($info);
            return ['success' => true, 'message' => $result];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * 스케줄 시간 정보 생성
     */
    private function generateScheduleTime($data) {
        $type = $data['type'];
        $scheduleTime = '';
        
        switch ($type) {
            case 'daily':
                $scheduleTime = $data['dailyTime'] ?? '09:00';
                break;
                
            case 'weekly':
                $time = $data['weeklyTime'] ?? '09:00';
                $days = $data['weeklyDays'] ?? [];
                $dayNames = ['일', '월', '화', '수', '목', '금', '토'];
                $selectedDays = [];
                foreach ($days as $day) {
                    $selectedDays[] = $dayNames[$day];
                }
                $scheduleTime = implode(',', $selectedDays) . ' ' . $time;
                break;
                
            case 'monthly':
                $time = $data['monthlyTime'] ?? '09:00';
                $day = $data['monthlyDay'] ?? 1;
                $scheduleTime = "매월 {$day}일 {$time}";
                break;
                
            case 'quarterly':
                $time = $data['quarterlyTime'] ?? '09:00';
                $month = $data['quarterlyMonth'] ?? 1;
                $day = $data['quarterlyDay'] ?? 1;
                $quarter = ceil($month / 3);
                $scheduleTime = "매 {$quarter}분기 {$month}월 {$day}일 {$time}";
                break;
                
            case 'yearly':
                $time = $data['yearlyTime'] ?? '09:00';
                $month = $data['yearlyMonth'] ?? 1;
                $day = $data['yearlyDay'] ?? 1;
                $scheduleTime = "매년 {$month}월 {$day}일 {$time}";
                break;
        }
        
        return $scheduleTime;
    }

    /**
     * 명령어 실행
     */
    private function executeCommand($scheduler) {
        $command = $scheduler['command'];
        $workingDir = $scheduler['workingDirectory'];
        $timeout = $scheduler['timeout'] ?? 300;
        
        // 작업 디렉토리 설정
        if ($workingDir && is_dir($workingDir)) {
            $command = "cd {$workingDir} && {$command}";
        }
        
        // 환경 변수 설정
        if (!empty($scheduler['environment'])) {
            $envVars = explode("\n", $scheduler['environment']);
            $envString = '';
            foreach ($envVars as $envVar) {
                $envVar = trim($envVar);
                if (!empty($envVar) && strpos($envVar, '=') !== false) {
                    $envString .= "export {$envVar} && ";
                }
            }
            if ($envString) {
                $command = $envString . $command;
            }
        }
        
        // 명령어 실행
        $descriptors = [
            0 => ['pipe', 'r'], // stdin
            1 => ['pipe', 'w'], // stdout
            2 => ['pipe', 'w']  // stderr
        ];
        
        $process = proc_open($command, $descriptors, $pipes);
        
        if (!is_resource($process)) {
            throw new Exception('명령어 실행을 시작할 수 없습니다.');
        }
        
        // 타임아웃 설정
        $startTime = time();
        $output = '';
        $error = '';
        
        // 비동기로 출력 읽기
        stream_set_blocking($pipes[1], 0);
        stream_set_blocking($pipes[2], 0);
        
        do {
            $status = proc_get_status($process);
            
            // stdout 읽기
            $stdout = stream_get_contents($pipes[1]);
            if ($stdout !== false) {
                $output .= $stdout;
            }
            
            // stderr 읽기
            $stderr = stream_get_contents($pipes[2]);
            if ($stderr !== false) {
                $error .= $stderr;
            }
            
            // 타임아웃 체크
            if (time() - $startTime > $timeout) {
                proc_terminate($process);
                throw new Exception("명령어 실행이 타임아웃({$timeout}초)되었습니다.");
            }
            
            usleep(100000); // 0.1초 대기
            
        } while ($status['running']);
        
        // 파이프 닫기
        fclose($pipes[0]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        
        // 프로세스 종료
        $returnCode = proc_close($process);
        
        // 실행 결과 로그 저장
        $this->saveExecutionLog($scheduler['id'], $command, $output, $error, $returnCode);
        
        if ($returnCode !== 0) {
            throw new Exception("명령어 실행이 실패했습니다. (종료 코드: {$returnCode})\n오류: {$error}");
        }
        
        return $output;
    }

    /**
     * 실행 로그 저장
     */
    private function saveExecutionLog($schedulerId, $command, $output, $error, $returnCode) {
        $sql = "INSERT INTO scheduler_log (
                    schedulerId, command, output, error, returnCode, executionDate
                ) VALUES (?, ?, ?, ?, ?, NOW())";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $schedulerId,
            $command,
            $output,
            $error,
            $returnCode
        ]);
    }

    /**
     * 실행할 스케줄러 목록 조회
     */
    public function getSchedulableList() {
        $sql = "SELECT * FROM scheduler WHERE status = 'active' AND coId = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->coId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 스케줄러 실행 시간 업데이트
     */
    public function updateExecutionTime($id, $lastRun = null, $nextRun = null) {
        $sql = "UPDATE scheduler SET ";
        $params = [];
        
        if ($lastRun) {
            $sql .= "lastRun = ?, ";
            $params[] = $lastRun;
        }
        
        if ($nextRun) {
            $sql .= "nextRun = ?, ";
            $params[] = $nextRun;
        }
        
        $sql .= "updateDate = NOW() WHERE id = ? AND coId = ?";
        $params[] = $id;
        $params[] = $this->coId;
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
}
?> 