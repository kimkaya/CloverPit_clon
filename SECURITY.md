# PHP 보안 가이드

이 문서는 CloverPit 게임 프로젝트에 적용된 보안 강화 사항을 설명합니다.

## 적용된 보안 강화 사항

### 1. 데이터베이스 자격 증명 보호

**문제점:**
- 하드코딩된 데이터베이스 자격 증명이 소스 코드에 노출됨

**해결책:**
- `backend/config.php`: 민감한 정보를 별도 설정 파일로 분리
- `.gitignore`에 `config.php` 추가하여 버전 관리에서 제외
- `config.example.php`를 템플릿으로 제공

**사용법:**
```bash
# config.example.php를 복사하여 config.php 생성
cp backend/config.example.php backend/config.php

# config.php에서 실제 자격 증명으로 수정
```

### 2. 입력 검증 및 Sanitization

**적용된 검증:**
- 플레이어 이름: 길이 및 XSS 방지 검증
- 세션 ID: 16진수 형식 검증
- 아이템 ID: 정수 및 범위 검증

**구현 파일:**
- `backend/SecurityHelper.php`: 입력 검증 헬퍼 메서드 제공

**주요 메서드:**
- `validateString()`: 문자열 길이 검증
- `validateInteger()`: 정수 및 범위 검증
- `validatePlayerName()`: XSS 방지를 위한 HTML 태그 검증
- `sanitizeString()`: HTML 특수문자 이스케이핑

### 3. 에러 메시지 일반화

**문제점:**
- 상세한 에러 메시지가 내부 정보를 노출할 수 있음

**해결책:**
- 사용자에게는 일반화된 메시지 표시
- 상세 정보는 로그 파일에만 기록
- 개발 환경에서만 스택 트레이스 포함

**로그 위치:**
- `logs/app.log`

### 4. 보안 헤더

다음 보안 헤더가 자동으로 설정됩니다:

```
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
X-XSS-Protection: 1; mode=block
Strict-Transport-Security: max-age=31536000 (프로덕션만)
Content-Security-Policy: default-src 'self'
```

### 5. CSRF 보호

**활성화:**
- `config.php`의 `security.csrf_enabled`를 `true`로 설정

**사용법:**

```javascript
// 1. CSRF 토큰 가져오기
fetch('backend/api.php?action=get_csrf_token')
  .then(response => response.json())
  .then(data => {
    const csrfToken = data.csrf_token;

    // 2. POST 요청 시 토큰 포함
    fetch('backend/api.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-CSRF-TOKEN': csrfToken
      },
      body: new URLSearchParams({
        action: 'start',
        player_name: 'Player1',
        csrf_token: csrfToken
      })
    });
  });
```

### 6. 세션 보안

**적용된 설정:**
- `session.cookie_httponly`: JavaScript로부터 쿠키 보호
- `session.use_only_cookies`: URL 파라미터 세션 ID 사용 금지
- `session.cookie_secure`: HTTPS에서만 쿠키 전송 (프로덕션)
- `session.cookie_samesite`: CSRF 공격 방지
- 세션 재생성: 세션 고정 공격 방지
- 세션 만료: 설정된 시간 후 자동 만료 (기본 1시간)

### 7. Rate Limiting

**설정:**
- `config.php`의 `security.rate_limit` 섹션에서 설정
- 기본값: 60초 동안 100회 요청

**동작:**
- IP 주소 기반으로 요청 제한
- 제한 초과 시 HTTP 429 응답

**설정 예시:**
```php
'rate_limit' => [
    'enabled' => true,
    'max_requests' => 100,  // 최대 요청 수
    'time_window' => 60     // 시간 창 (초)
]
```

## 환경별 설정

### 개발 환경
```php
'environment' => 'development'
```
- 상세한 로그 기록
- test_connection.php 접근 허용
- HTTPS 강제 비활성화

### 프로덕션 환경
```php
'environment' => 'production'
```
- 일반화된 에러 메시지만 표시
- test_connection.php 접근 차단
- HTTPS 강제 활성화
- 보안 헤더 강화

## 추가 권장 사항

### 1. HTTPS 사용
프로덕션 환경에서는 반드시 HTTPS를 사용하세요:
```apache
# .htaccess
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

### 2. 파일 권한 설정
```bash
# 설정 파일은 읽기 전용으로
chmod 400 backend/config.php

# 로그 디렉토리는 쓰기 가능하게
chmod 755 logs/
```

### 3. 정기적인 보안 업데이트
- PHP 버전 최신 상태 유지
- 의존성 패키지 업데이트
- 보안 취약점 모니터링

### 4. 데이터베이스 보안
- 최소 권한 원칙: API용 DB 사용자는 필요한 권한만 부여
- 별도의 DB 사용자 생성 권장:
```sql
CREATE USER 'cloverpit_api'@'localhost' IDENTIFIED BY 'strong_password';
GRANT SELECT, INSERT, UPDATE, DELETE ON cloverpit.* TO 'cloverpit_api'@'localhost';
FLUSH PRIVILEGES;
```

### 5. 프로덕션 배포 체크리스트
- [ ] `config.php`에 강력한 비밀번호 설정
- [ ] `environment`를 `production`으로 변경
- [ ] `test_connection.php` 삭제 또는 접근 차단
- [ ] HTTPS 설정
- [ ] 파일 권한 확인
- [ ] 에러 로그 모니터링 설정
- [ ] Rate Limiting 설정 조정
- [ ] DB 사용자 권한 최소화

## 보안 취약점 신고

보안 취약점을 발견하시면 즉시 개발팀에 연락해주세요.

## 참고 자료

- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [PHP Security Guide](https://www.php.net/manual/en/security.php)
- [PDO Prepared Statements](https://www.php.net/manual/en/pdo.prepared-statements.php)
