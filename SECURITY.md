# 보안 설정 가이드

## 설정 파일 구성

이 프로젝트를 로컬에서 실행하려면 다음 설정 파일들을 구성해야 합니다.

### 1. 데이터베이스 설정 파일

다음 위치에 `db.json` 파일을 생성해야 합니다:

- `kodes/wcms/config/db.json`
- `kodes/api/config/db.json`

각 디렉토리에 있는 `db.json.example` 파일을 복사하여 `db.json`으로 이름을 변경하고, 실제 데이터베이스 연결 정보로 수정하세요.

```bash
# 예시
cp kodes/wcms/config/db.json.example kodes/wcms/config/db.json
cp kodes/api/config/db.json.example kodes/api/config/db.json
```

### 2. db.json 구조

```json
{   
    "wcmsDB":{
        "server":"mongodb://username:password@host:port/database?options",
        "db":"wcms",
        "user":"username",
        "passwd":"password"
    },
    "apiDB":{
        "server":"mongodb://username:password@host:port/database?options",
        "db":"apiData",
        "user":"username",
        "passwd":"password"
    }
}
```

### 3. 보안 주의사항

⚠️ **중요**: 다음 파일들은 절대 Git에 커밋하지 마세요:

- `**/config/db.json` - 데이터베이스 연결 정보
- `**/config/common.json` - 공통 설정 (API 키 등 포함 가능)
- `.env` - 환경 변수 파일
- `logs/` - 로그 파일 (민감한 정보 포함 가능)

이러한 파일들은 `.gitignore`에 이미 등록되어 있습니다.

### 4. Composer 의존성 설치

```bash
cd kodes/wcms
composer install

cd ../api
composer install
```

### 5. 문의

설정 관련 문의사항은 프로젝트 관리자에게 문의하세요.

