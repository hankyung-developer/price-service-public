# DataCMS - AI 기반 콘텐츠 관리 시스템

![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-blue)
![License](https://img.shields.io/badge/license-KODES-green)
![Status](https://img.shields.io/badge/status-active-success)

## 📋 프로젝트 개요

**DataCMS**는 한국언론진흥재단의 지원을 받아 개발된 한경닷컴(hankyung.com)의 가격 정보 전문 웹 콘텐츠 관리 시스템(WCMS)입니다.
공공데이터포털 등 여러 기관의 데이터를 수집하여 한경닷컴에서 과거 데이터를 효율적으로 활용할 수 있는 API 시스템과 
AI 기술을 활용한 데이터 기반 기사 자동 생성, 그리고 스케줄러를 통한 정기적인 콘텐츠 발행 기능을 제공하는 차세대 CMS 플랫폼입니다.

### 🎯 주요 특징

- **🤖 AI 기반 기사 자동 생성**: GPT, Claude, Gemini 등 다양한 AI 모델을 활용한 고품질 기사 자동 작성
- **⏰ 스케줄러 시스템**: 일간/주간/월간/분기/연간 단위의 유연한 자동 발행 시스템
- **📊 데이터 시각화**: 실시간 차트 및 그래프 자동 생성
- **🖼️ AI 이미지 생성**: 기사 내용에 맞는 이미지 자동 생성
- **📡 API 통합**: 외부 데이터 소스와의 원활한 연동
- **🔐 권한 관리**: 세분화된 관리자 권한 시스템
- **📱 반응형 UI**: 모던하고 직관적인 사용자 인터페이스

## 🏗️ 시스템 아키텍처

```
datacms/
├── api/                      # REST API 시스템
│   ├── classes/             # API 핵심 클래스
│   ├── config/              # API 설정 파일
│   └── web/                 # API 웹 인터페이스
│
├── kodes/                    # 핵심 라이브러리
│   ├── api/                 # API 비즈니스 로직
│   │   ├── classes/         # API 클래스 (Api, Category, HkApiId 등)
│   │   ├── config/          # 설정 파일 (common.json, db.json)
│   │   └── vendor/          # Composer 의존성
│   │
│   └── wcms/                # WCMS 비즈니스 로직
│       ├── classes/         # WCMS 핵심 클래스
│       │   ├── Article.php          # 기사 관리
│       │   ├── AIManager.php        # AI 통합 관리자
│       │   ├── GPT.php              # OpenAI GPT 인터페이스
│       │   ├── Claude.php           # Anthropic Claude 인터페이스
│       │   ├── Gemini.php           # Google Gemini 인터페이스
│       │   ├── AiSetting.php        # AI 설정 관리
│       │   ├── Category.php         # 카테고리 관리
│       │   ├── Auth.php             # 권한 관리
│       │   └── ...
│       ├── config/          # WCMS 설정
│       └── vendor/          # Composer 의존성
│
└── wcms/                     # WCMS 애플리케이션
    ├── classes/             # 애플리케이션 클래스
    ├── config/              # 메뉴 및 시스템 설정
    ├── cron/                # 크론잡 스크립트
    │   ├── scheduleWriteArticle.php  # AI 기사 자동 생성
    │   ├── getApiData.php            # API 데이터 수집
    │   └── logs/                      # 크론잡 로그
    │
    └── web/                 # 웹 인터페이스
        ├── _template/       # HTML 템플릿
        │   ├── article/     # 기사 관련 템플릿
        │   ├── aiSetting/   # AI 설정 템플릿
        │   ├── category/    # 카테고리 템플릿
        │   └── ...
        ├── css/             # 스타일시트
        ├── js/              # 자바스크립트
        ├── lib/             # 외부 라이브러리
        │   ├── tinymce/     # 텍스트 에디터
        │   ├── CodeMirror/  # 코드 에디터
        │   └── ...
        └── index.php        # 메인 진입점
```

## 🚀 시작하기

### 필수 요구사항

```
PHP >= 7.4 (권장: PHP 8.0+)
MongoDB >= 4.4
Composer >= 2.0
Nginx 또는 Apache 웹서버
Linux OS
```

### 설치 방법

1. **저장소 클론**
```bash
git clone https://github.com/your-org/datacms.git
cd datacms
```

2. **Composer 의존성 설치**
```bash
# API 의존성
cd kodes/api
composer install

# WCMS 의존성
cd ../wcms
composer install
```

3. **데이터베이스 설정**
```bash
# MongoDB 설정 파일 편집
vi kodes/wcms/config/db.json
```

```json
{
  "host": "localhost",
  "port": 27017,
  "database": "datacms",
  "username": "your_username",
  "password": "your_password"
}
```

4. **웹서버 설정 (Nginx 예시)**
```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/datacms/wcms/web;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

5. **권한 설정**
```bash
# 로그 디렉토리 생성 및 권한 설정
mkdir -p wcms/cron/logs
chmod 755 wcms/cron/logs
chown -R www-data:www-data wcms/cron/logs

# 세션 디렉토리 설정
mkdir -p /var/lib/php/sessions
chmod 770 /var/lib/php/sessions
chown -R nginx:nginx /var/lib/php/sessions
```

6. **크론잡 설정**
```bash
# crontab 편집
crontab -e

# Api 수집기 동작 (2010-01-01부터 데이터 수집)
* * * * * /webSiteSource/wcms/cron/runMigration.sh

# 일자별 API 데이터 수집 (20분마다 실행)
*/20 * * * * /usr/bin/php /webSiteSource/wcms/cron/getApiData.php >> /webSiteSource/wcms/cron/logs/cron_$(date +\%Y\%m\%d).log 2>&1

# 기사 전송 (매분 실행)
* * * * * /usr/bin/php /webSiteSource/wcms/cron/sendToHkWcms.php

# AI 기사 작성 (매분 실행)
* * * * * /usr/bin/php /webSiteSource/wcms/cron/scheduleWriteArticle.php
```

## 📚 주요 기능

### 1. AI 기사 작성

다양한 AI 모델을 활용하여 데이터 기반 기사를 자동으로 생성합니다.

**지원 AI 모델:**
- OpenAI GPT (GPT-3.5, GPT-4, GPT-4 Turbo)
- Anthropic Claude (Claude 3 Series)
- Google Gemini (Gemini Pro, Ultra)

**기능:**
- 템플릿 기반 기사 작성
- 커스텀 프롬프트 적용
- 실시간 데이터 통합
- 자동 이미지 생성
- 자동 차트 생성
- 기사 히스토리 관리

### 2. 스케줄러 시스템

정기적인 콘텐츠 발행을 위한 강력한 스케줄링 시스템입니다.

**스케줄 타입:**
- **일간 (Daily)**: 매일 지정된 시간에 실행
- **주간 (Weekly)**: 특정 요일의 지정된 시간에 실행
- **월간 (Monthly)**: 매월 지정된 날짜의 시간에 실행
- **분기별 (Quarterly)**: 분기 첫 월의 지정된 시간에 실행
- **연간 (Yearly)**: 매년 지정된 월/날짜/시간에 실행

**안전 기능:**
- 중복 실행 방지 (Lock File)
- 시간 허용 범위 (±5분)
- 최근 실행 체크
- 상세 로그 기록

### 3. 데이터 수집 시스템

외부 API를 통해 실시간 데이터를 수집하고 관리합니다.

**기능:**
- API 제공업체 관리
- API 엔드포인트 설정
- 자동 데이터 수집
- 데이터 변환 및 정제
- 수집 이력 관리

### 4. 카테고리 및 권한 관리

**카테고리 시스템:**
- 계층형 카테고리 구조
- 카테고리별 권한 설정
- 카테고리별 템플릿 설정

**권한 관리:**
- 관리자 등급별 권한 설정
- 메뉴별 접근 제어
- 카테고리별 접근 제어
- 세션 기반 인증

### 5. 대시보드

**실시간 통계:**
- 기사 작성 현황
- AI 사용량 통계
- API 호출 통계
- 시스템 상태 모니터링

## 🔧 설정 가이드

### AI 모델 설정

AI 모델을 사용하기 위해 API 키를 설정해야 합니다.

1. **관리 페이지 접속**: `AI설정 > AI모델`
2. **모델 등록**: 사용할 AI 모델 정보 입력
3. **API 키 설정**: 각 모델의 API 키 입력
4. **기본 모델 설정**: 기본으로 사용할 모델 선택

### 기사 템플릿 설정

1. **템플릿 생성**: `AI설정 > 기사 템플릿`
2. **구조 정의**: 기사의 기본 구조 설정
3. **변수 설정**: 데이터 바인딩 변수 정의
4. **미리보기**: 템플릿 결과 확인

### 프롬프트 설정

1. **프롬프트 생성**: `AI설정 > 프롬프트`
2. **지시사항 작성**: AI에게 전달할 상세 지시사항
3. **파라미터 설정**: 온도, 최대 토큰 등 설정
4. **테스트**: 프롬프트 결과 확인

### 스케줄 설정

1. **스케줄 생성**: `AI설정 > 스케줄러`
2. **실행 주기 설정**: 일간/주간/월간 등 선택
3. **시간 설정**: 실행 시각 지정
4. **템플릿 연결**: 사용할 템플릿 및 프롬프트 선택
5. **데이터 소스 설정**: 품목 및 카테고리 선택
6. **활성화**: 스케줄 활성화

## 📖 API 문서

### REST API 엔드포인트

```
GET    /api/articles           # 기사 목록 조회
GET    /api/articles/:id       # 기사 상세 조회
POST   /api/articles           # 기사 생성
PUT    /api/articles/:id       # 기사 수정
DELETE /api/articles/:id       # 기사 삭제

GET    /api/categories         # 카테고리 목록
POST   /api/ai/generate        # AI 기사 생성 요청
GET    /api/statistics         # 통계 조회
```

### API 사용 예시

```php
// AI 기사 생성
$article = new \Kodes\Wcms\Article();
$result = $article->createArticleByAI([
    'categoryId' => 'hkp001000000',
    'templateId' => 1,
    'promptId' => 1,
    'items' => ['item1', 'item2'],
    'generateImage' => true,
    'generateChart' => true
]);
```

```javascript
// REST API 호출
fetch('/api/ai/generate', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
    },
    body: JSON.stringify({
        categoryId: 'hkp001000000',
        templateId: 1,
        promptId: 1,
        items: ['item1', 'item2']
    })
});
```

### 로그 확인

```bash
# 스케줄러 로그
tail -f wcms/cron/logs/schedule_article_$(date +%Y-%m-%d).log

# API 수집 로그
tail -f wcms/cron/logs/api_data_$(date +%Y-%m-%d).log

# 에러 검색
grep "ERROR\|실패\|✗" wcms/cron/logs/*.log
```

## 🐛 문제 해결

### 일반적인 문제

**1. 세션 관련 오류**
```bash
# 세션 디렉토리 권한 확인
ls -la /var/lib/php/sessions

# 권한 재설정
chmod 770 /var/lib/php/sessions
chown -R nginx:nginx /var/lib/php/sessions
```

**2. MongoDB 연결 오류**
```bash
# MongoDB 상태 확인
sudo systemctl status mongod

# MongoDB 재시작
sudo systemctl restart mongod

# 설정 파일 확인
cat kodes/wcms/config/db.json
```

**3. 크론잡 미실행**
```bash
# cron 서비스 확인
sudo systemctl status cron

# crontab 확인
crontab -l

# 시스템 로그 확인
tail -f /var/log/syslog | grep CRON
```

**4. PHP 에러**
```bash
# PHP 에러 로그 확인
tail -f /var/log/php_errors.log

# PHP-FPM 로그 확인
tail -f /var/log/php7.4-fpm.log
```

## 📁 주요 클래스 설명

### Article.php
기사 관리를 담당하는 핵심 클래스입니다.

**주요 메서드:**
- `createArticleByAI()`: AI를 활용한 기사 생성
- `generateChart()`: 데이터 기반 차트 생성
- `generateImage()`: AI 이미지 생성
- `save()`: 기사 저장
- `publish()`: 기사 발행

### AIManager.php
다양한 AI 모델을 통합 관리하는 클래스입니다.

**주요 메서드:**
- `selectModel()`: AI 모델 선택
- `generateContent()`: 콘텐츠 생성
- `checkUsage()`: API 사용량 확인

### AiSetting.php
AI 설정(템플릿, 프롬프트, 스케줄)을 관리합니다.

**주요 메서드:**
- `getTemplate()`: 템플릿 조회
- `getPrompt()`: 프롬프트 조회
- `getSchedules()`: 스케줄 목록 조회
- `checkScheduleTime()`: 스케줄 실행 시간 확인

### Category.php
카테고리 계층 구조 및 권한을 관리합니다.

**주요 메서드:**
- `getTree()`: 카테고리 트리 조회
- `checkPermission()`: 권한 확인
- `save()`: 카테고리 저장

## 🔐 보안 고려사항

- **인증**: 세션 기반 인증 시스템
- **권한**: 역할 기반 접근 제어 (RBAC)
- **데이터 검증**: 모든 입력 데이터 검증 및 새니타이제이션
- **SQL 인젝션 방지**: MongoDB 파라미터화된 쿼리 사용
- **XSS 방지**: 출력 데이터 이스케이핑
- **CSRF 방지**: 토큰 기반 폼 보호
- **API 키 관리**: 환경 변수 또는 보안 설정 파일 사용

## 📊 성능 최적화

- **캐싱**: 자주 사용되는 데이터 캐싱
- **지연 로딩**: 필요한 시점에 리소스 로드
- **데이터베이스 인덱싱**: MongoDB 인덱스 최적화
- **이미지 최적화**: 자동 이미지 리사이징 및 압축
- **코드 미니피케이션**: CSS/JS 파일 압축

## 💻 코딩 스타일 가이드

- **PSR-4 오토로딩** 표준 준수
- **PSR-12 코딩 스타일 가이드** 준수
- 명확하고 상세한 주석 작성
- 의미 있고 직관적인 변수명 사용
- 함수 및 클래스에 대한 독스트링(Docstring) 작성

## 📝 변경 이력

### v1.0.0 (2025-11-06)
- 🎉 초기 릴리스
- ✨ AI 기반 기사 자동 생성 기능
- ✨ 스케줄러 시스템 구현
- ✨ 다중 AI 모델 지원 (GPT, Claude, Gemini)
- ✨ 데이터 수집 API 시스템
- ✨ 관리자 대시보드
- ✨ 권한 관리 시스템

## 👥 개발팀

**KODES Inc.**
- Website: https://www.kode.co.kr
- Email: kodesinfo@kode.co.kr
- Tel: 02-6252-5500

## 📞 지원 및 문의

문제가 발생하거나 질문이 있으신 경우 다음 순서로 확인해주세요:

1. **문서 확인**: 이 README 문서와 관련 위키 문서를 먼저 확인
2. **로그 확인**: `wcms/cron/logs/` 디렉토리의 로그 파일에서 에러 메시지 확인
3. **이슈 등록**: GitHub Issues에 문제 상황과 환경을 상세히 작성하여 등록
4. **이메일 문의**: kodesinfo@kode.co.kr로 문의

## 🔗 관련 링크
- [한국언론진흥재단](https://www.kpf.or.kr)
- [한경닷컴](https://www.hankyung.com)
- [KODES 공식 사이트](https://www.kode.co.kr)

<p align="center">Made with ❤️ by KODES Inc.</p>

