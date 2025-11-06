#!/bin/bash

# Migration 실행 스크립트
# 사용법: ./run_migration.sh [API_ID] [START_DATE] [END_DATE]
# 또는: ./run_migration.sh (자동으로 migration 디렉토리의 .txt 파일 감지)

# 스크립트 디렉토리 설정
SCRIPT_DIR="/webSiteSource/wcms/cron"
MIGRATION_DIR="/webSiteSource/wcms/cron/migration"
LOG_DIR="/webSiteSource/wcms/cron/logs"

# MIGRATION_DIR에서 .txt 파일 이름 가져오기
get_txt_files() {
    if [ -d "$MIGRATION_DIR" ]; then
        # .txt 파일들을 찾아서 파일명만 출력 (확장자 제외)
        find "$MIGRATION_DIR" -name "*.txt" -type f -exec basename {} .txt \;
    else
        echo "Migration 디렉토리가 존재하지 않습니다: $MIGRATION_DIR"
        return 1
    fi
}

# 백그라운드로 PHP 스크립트 실행
run_migration_background() {
    local filename="$1"
    local start_date="$2"
    local end_date="$3"
    
    echo "백그라운드로 Migration 실행 중..."
    echo "파일명: $filename"
    echo "시작일: $start_date"
    echo "종료일: $end_date"
    
    # 실행할 명령어 출력
    local cmd="/usr/bin/nohup /usr/bin/php /webSiteSource/wcms/cron/allGetAipData.php $filename $start_date $end_date > /webSiteSource/wcms/cron/logs/allGetApi.log 2>&1 &"
    echo "실행 명령어: $cmd"
    echo ""
    
    # 백그라운드로 PHP 스크립트 실행
    /usr/bin/nohup /usr/bin/php /webSiteSource/wcms/cron/allGetAipData.php $filename $start_date $end_date > /webSiteSource/wcms/cron/logs/allGetApi.log 2>&1 &
    
    # 프로세스 ID 저장
    local pid=$!
    echo "Migration 프로세스가 백그라운드에서 시작되었습니다. PID: $pid"
    echo "로그 파일: /webSiteSource/wcms/cron/logs/allGetApi.log"
    
    # 프로세스 확인
    sleep 2
    if kill -0 "$pid" 2>/dev/null; then
        echo "프로세스가 정상적으로 실행 중입니다. PID: $pid"
    else
        echo "경고: 프로세스가 시작되지 않았거나 즉시 종료되었습니다."
        echo "로그 파일을 확인해보세요: /webSiteSource/wcms/cron/logs/allGetApi.log"
    fi
    
    return $pid
}

# 메인 실행 로직
main() {
    echo "=== Migration 실행 스크립트 ==="
    
    # 날짜 설정
    start_date="2010-01-01"
    end_date=$(date +%Y-%m-%d)
    
    echo "시작일: $start_date"
    echo "종료일: $end_date"
    echo ""
    
    # 명령행 인수 확인
    if [ $# -eq 1 ]; then
        # 인수가 1개인 경우: 파일명만 지정
        filename="$1"
        
        echo "사용자 지정 파일로 실행: $filename"
        run_migration_background "$filename" "$start_date" "$end_date"
        
    elif [ $# -eq 0 ]; then
        # 인수가 없는 경우: migration 디렉토리의 .txt 파일들을 자동 감지
        echo "Migration 디렉토리에서 .txt 파일을 자동 감지합니다..."
        
        txt_files=$(get_txt_files)
        if [ $? -eq 0 ] && [ -n "$txt_files" ]; then
            echo "발견된 .txt 파일들:"
            echo "$txt_files"
            echo ""
            
            # 각 .txt 파일에 대해 실행
            while IFS= read -r filename; do
                if [ -n "$filename" ]; then
                    echo "파일 '$filename' 처리 중..."
                    run_migration_background "$filename" "$start_date" "$end_date"
                    echo ""
                fi
            done <<< "$txt_files"
        else
            echo "Migration 디렉토리에 .txt 파일이 없습니다."
            exit 1
        fi
    else
        echo "사용법:"
        echo "  $0 [파일명]  - 특정 파일로 실행"
        echo "  $0           - migration 디렉토리의 모든 .txt 파일로 실행"
        echo ""
        echo "예시:"
        echo "  $0 migration_data"
        echo "  $0"
        echo ""
        echo "날짜 설정:"
        echo "  시작일: $start_date (고정)"
        echo "  종료일: $end_date (오늘 날짜)"
        exit 1
    fi
}

# 프로세스 모니터링 함수
monitor_processes() {
    echo "=== 실행 중인 Migration 프로세스 확인 ==="
    
    local pids=$(pgrep -f allGetAipData.php)
    if [ -n "$pids" ]; then
        echo "실행 중인 Migration 프로세스들:"
        ps -p "$pids" -o pid,ppid,cmd,etime,pcpu,pmem
        echo ""
        
        # 각 프로세스별 로그 확인
        for pid in $pids; do
            echo "PID $pid 프로세스 로그 (마지막 5줄):"
            if [ -f "/webSiteSource/wcms/cron/logs/allGetApi.log" ]; then
                tail -5 /webSiteSource/wcms/cron/logs/allGetApi.log
            else
                echo "로그 파일이 없습니다."
            fi
            echo "---"
        done
    else
        echo "실행 중인 Migration 프로세스가 없습니다."
    fi
}

# 프로세스 종료 함수
kill_processes() {
    echo "=== Migration 프로세스 종료 ==="
    
    local pids=$(pgrep -f allGetAipData.php)
    if [ -n "$pids" ]; then
        echo "종료할 프로세스들: $pids"
        kill $pids
        sleep 2
        
        # 강제 종료가 필요한지 확인
        local remaining=$(pgrep -f allGetAipData.php)
        if [ -n "$remaining" ]; then
            echo "일부 프로세스가 종료되지 않아 강제 종료합니다: $remaining"
            kill -9 $remaining
        fi
        
        echo "모든 Migration 프로세스가 종료되었습니다."
    else
        echo "실행 중인 Migration 프로세스가 없습니다."
    fi
}

# 스크립트 실행
case "${1:-}" in
    "monitor"|"status")
        monitor_processes
        ;;
    "kill"|"stop")
        kill_processes
        ;;
    *)
        main "$@"
        ;;
esac

