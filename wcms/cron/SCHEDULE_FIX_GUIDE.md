# 스케줄 수정 가이드

## 🐛 발견된 문제

### Monthly (월간) 스케줄 키 이름 불일치

**원인**: 
- `scheduleEdit.html`에서 `days`로 저장
- `scheduleWriteArticle.php`에서 `dates`를 찾음

**증상**:
```json
// 잘못된 저장 (기존)
"monthly": {
  "days": [5],      // ❌ 찾을 수 없음
  "time": "11:05"
}

// 올바른 저장 (수정 후)
"monthly": {
  "dates": [5],     // ✅ 정상 작동
  "time": "11:05"
}
```

## ✅ 수정 완료

`scheduleEdit.html` 파일에서 `days` → `dates`로 변경 완료

## 📝 해야 할 일

### 1. 기존 스케줄 재저장 필요

**영향받는 스케줄**: 모든 monthly (월간) 스케줄

**조치 방법**:
1. https://datacms.hankyung.com/aiSetting/scheduleEdit?idx=3 접속
2. 설정을 확인하고 **저장 버튼 클릭** (변경 없이 저장만 하면 됨)
3. 저장하면 새로운 형식(`dates`)으로 저장됨

### 2. 테스트

```bash
# 설정 확인
php /webSiteSource/wcms/cron/test_schedule_config.php

# 실행 테스트
php /webSiteSource/wcms/cron/scheduleWriteArticle.php
```

**예상 로그**:
```
스케줄 체크: [매주 월요일 금속 가격정보 기사]
  타입: monthly, 현재시간: 11:06
  현재 상태 - 요일: 2, 날짜: 5, 월: 11
  월간 설정 - 날짜: [5], 시간: 11:05
  날짜 일치 ✓
  시간 일치: 11:06 ≈ 11:05 (차이: 60초)
✓ 기사 생성 성공
```

## ⚠️ 추가 확인 필요

### Quarterly (분기별) 스케줄
- 현재: `quarters` 배열로 저장
- 필요: `months` 배열 + `date` 값으로 변환
- 상태: **미수정** (사용 중이면 수정 필요)

### Yearly (연간) 스케줄  
- 현재: `months` (복수) 배열로 저장
- 필요: `month` (단수) + `date` 값
- 상태: **미수정** (사용 중이면 수정 필요)

## 📞 문제 발생 시

1. **로그 확인**:
   ```bash
   tail -f /webSiteSource/wcms/cron/logs/schedule_article_*.log
   ```

2. **설정 확인**:
   ```bash
   php /webSiteSource/wcms/cron/test_schedule_config.php
   ```

3. **MongoDB 직접 확인**:
   ```javascript
   db.aiSchedule.findOne({idx: 3})
   ```



