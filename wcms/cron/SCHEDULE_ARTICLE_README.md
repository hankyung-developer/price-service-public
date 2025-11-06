# AI 기사 자동 생성 스케줄러 (scheduleWriteArticle.php)

## 📋 개요

`scheduleWriteArticle.php`는 `scheduleEdit.html`에서 설정한 스케줄에 따라 AI 기사를 자동으로 생성하는 크론잡 프로그램입니다.

## ✨ 주요 기능

1. **스케줄 기반 자동 실행**
   - 일간 (daily): 매일 지정된 시간
   - 주간 (weekly): 지정된 요일의 지정된 시간
   - 월간 (monthly): 지정된 날짜의 지정된 시간
   - 분기별 (quarterly): 분기 첫 월의 지정된 날짜/시간
   - 연간 (yearly): 매년 지정된 월/날짜/시간

2. **AI 기사 생성**
   - 템플릿 기반 기사 작성
   - 프롬프트 적용
   - 품목 데이터 자동 조회
   - 이미지 자동 생성 (옵션)
   - 차트 자동 생성 (옵션)

3. **안전 장치**
   - 중복 실행 방지 (락 파일)
   - 시간 허용 범위 (±5분)
   - 최근 실행 체크 (50분 이내 재실행 방지)
   - 상세 로그 기록

## 🚀 설치 및 설정

### 1. Linux Crontab 설정

```bash
# crontab 편집
crontab -e

# 매분 실행 (가장 정확, 권장)
* * * * * /usr/bin/php /webSiteSource/wcms/cron/scheduleWriteArticle.php >> /webSiteSource/wcms/cron/logs/schedule_article.log 2>&1

# 또는 매 시간 정각 실행 (리소스 절약)
0 * * * * /usr/bin/php /webSiteSource/wcms/cron/scheduleWriteArticle.php >> /webSiteSource/wcms/cron/logs/schedule_article.log 2>&1

# 또는 10분마다 실행 (절충안)
*/10 * * * * /usr/bin/php /webSiteSource/wcms/cron/scheduleWriteArticle.php >> /webSiteSource/wcms/cron/logs/schedule_article.log 2>&1
```

### 2. 로그 디렉토리 권한 설정

```bash
# 로그 디렉토리 생성
mkdir -p /webSiteSource/wcms/cron/logs

# 권한 설정
chmod 755 /webSiteSource/wcms/cron/logs
chown www-data:www-data /webSiteSource/wcms/cron/logs
```

### 3. PHP 경로 확인

```bash
# PHP 경로 확인
which php
# 출력 예: /usr/bin/php

# crontab에 올바른 경로 사용
```

## 📊 스케줄 설정 예시

### 1. 매일 오전 9시 실행

```json
{
  "type": "daily",
  "daily": {
    "time": "09:00"
  }
}
```

### 2. 매주 월/수/금 오전 9시 실행

```json
{
  "type": "weekly",
  "weekly": {
    "days": [1, 3, 5],
    "time": "09:00"
  }
}
```

### 3. 매월 1일, 15일 오전 9시 실행

```json
{
  "type": "monthly",
  "monthly": {
    "dates": [1, 15],
    "time": "09:00"
  }
}
```

### 4. 분기별 (1월, 4월, 7월, 10월) 1일 오전 9시 실행

```json
{
  "type": "quarterly",
  "quarterly": {
    "months": [1, 4, 7, 10],
    "date": 1,
    "time": "09:00"
  }
}
```

### 5. 매년 1월 1일 오전 9시 실행

```json
{
  "type": "yearly",
  "yearly": {
    "month": 1,
    "date": 1,
    "time": "09:00"
  }
}
```

## 📝 로그 파일

### 로그 파일 위치

```
/webSiteSource/wcms/cron/logs/schedule_article_YYYY-MM-DD.log
```

### 로그 형식

```
[2025-11-04 09:00:01] ========================================
[2025-11-04 09:00:01] AI 기사 자동 생성 시작: 2025-11-04 09:00:01
[2025-11-04 09:00:01] 활성 스케줄 개수: 3
[2025-11-04 09:00:01] 스케줄 실행: [농산물 가격 동향] (idx: 1)
[2025-11-04 09:00:01]   타입: daily, 현재시간: 09:00
[2025-11-04 09:00:01]   시간 일치: 09:00 ≈ 09:00 (차이: 0초)
[2025-11-04 09:00:02]   카테고리: hkp001000000, 템플릿: 1, 프롬프트: 1
[2025-11-04 09:00:02]   이미지: Y, 차트: Y
[2025-11-04 09:00:02]   품목 개수: 5
[2025-11-04 09:00:03]   품목 로드 완료: 5개
[2025-11-04 09:00:03]   AI 기사 초안 생성 중...
[2025-11-04 09:00:15]   ✓ 초안 생성 완료
[2025-11-04 09:00:15]   차트 생성 중...
[2025-11-04 09:00:18]   ✓ 차트 생성 완료
[2025-11-04 09:00:18]   이미지 생성 중...
[2025-11-04 09:00:25]   ✓ 이미지 생성 완료
[2025-11-04 09:00:25]   기사 저장 중...
[2025-11-04 09:00:26]   ✓ 기사 저장 완료: hkp202511040001
[2025-11-04 09:00:26] ✓ 기사 생성 성공: hkp202511040001
[2025-11-04 09:00:26] 실행 완료 - 성공: 1, 실패: 0
[2025-11-04 09:00:26] ========================================
```

### 로그 모니터링

```bash
# 실시간 로그 확인
tail -f /webSiteSource/wcms/cron/logs/schedule_article_$(date +%Y-%m-%d).log

# 최근 100줄 확인
tail -100 /webSiteSource/wcms/cron/logs/schedule_article_$(date +%Y-%m-%d).log

# 특정 스케줄 검색
grep "농산물" /webSiteSource/wcms/cron/logs/schedule_article_*.log

# 에러 검색
grep "✗" /webSiteSource/wcms/cron/logs/schedule_article_*.log
```

## 🔧 문제 해결

### 1. 크론잡이 실행되지 않는 경우

```bash
# crontab 확인
crontab -l

# cron 서비스 상태 확인
sudo systemctl status cron

# cron 서비스 재시작
sudo systemctl restart cron

# 시스템 로그 확인
tail -f /var/log/syslog | grep CRON
```

### 2. PHP 파일 실행 권한

```bash
# 실행 권한 부여
chmod +x /webSiteSource/wcms/cron/scheduleWriteArticle.php

# 소유자 확인
ls -l /webSiteSource/wcms/cron/scheduleWriteArticle.php

# 필요시 소유자 변경
sudo chown www-data:www-data /webSiteSource/wcms/cron/scheduleWriteArticle.php
```

### 3. 수동 실행 테스트

```bash
# 직접 실행해보기
/usr/bin/php /webSiteSource/wcms/cron/scheduleWriteArticle.php

# 또는
cd /webSiteSource/wcms/cron
php scheduleWriteArticle.php
```

### 4. 중복 실행 방지 락 파일 제거

```bash
# 비정상 종료로 락 파일이 남은 경우
rm -f /webSiteSource/wcms/cron/logs/schedule_article.lock
```

## 📋 체크리스트

### 설정 전 확인사항

- [ ] MongoDB에 스케줄이 저장되어 있는가?
- [ ] 스케줄의 `isUse`가 `true`인가?
- [ ] 템플릿과 프롬프트가 등록되어 있는가?
- [ ] 카테고리와 품목이 선택되어 있는가?
- [ ] 로그 디렉토리 권한이 올바른가?

### 실행 후 확인사항

- [ ] 로그 파일이 생성되는가?
- [ ] 스케줄이 정상적으로 체크되는가?
- [ ] 기사가 생성되는가?
- [ ] 이미지/차트가 정상적으로 생성되는가?
- [ ] MongoDB에 기사가 저장되는가?

## 🔍 디버깅

### 디버그 모드 활성화

코드 내부에서 `$this->debug = true;`가 이미 설정되어 있어 상세 로그가 자동으로 기록됩니다.

### 상세 로그 확인

```bash
# 전체 로그
cat /webSiteSource/wcms/cron/logs/schedule_article_$(date +%Y-%m-%d).log

# 오류만 추출
grep "오류\|실패\|✗" /webSiteSource/wcms/cron/logs/schedule_article_$(date +%Y-%m-%d).log
```

## 📞 지원

문제가 계속되는 경우:
1. 로그 파일 확인
2. 스케줄 설정 확인
3. MongoDB 데이터 확인
4. PHP 에러 로그 확인 (`/var/log/php_errors.log`)

## 🔄 업데이트 이력

- **v1.0** (2025-11-04): 초기 버전 생성
  - 5가지 스케줄 타입 지원
  - AI 기사 자동 생성
  - 이미지/차트 자동 생성
  - 중복 실행 방지
  - 상세 로그 기록



