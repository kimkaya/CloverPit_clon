# 🍀 CloverPit - 슬롯머신 호러 로그라이크 게임

CloverPit은 발라트로와 벅샷 룰렛에서 영감을 받은 웹 기반 슬롯머신 로그라이크 게임입니다.
녹슨 감옥에 갇혀 슬롯머신으로 빚을 갚아야 하는 스릴 넘치는 게임플레이를 제공합니다.

## 🎮 게임 특징

- **슬롯머신 메커니즘**: 10원을 베팅하고 운명의 슬롯을 돌리세요
- **빚 시스템**: 각 라운드마다 빚이 50% 증가하며, 갚지 못하면 게임 오버
- **덱 빌딩**: 티켓으로 행운의 부적을 구매하여 당첨 확률과 배율 증가
- **로그라이크 진행**: 라운드가 진행될수록 난이도 증가
- **다크 호러 테마**: 몰입감 있는 다크 UI/UX

## 🛠️ 기술 스택

- **Frontend**: HTML5, CSS3, JavaScript (Vanilla)
- **Backend**: PHP 7.4+
- **Database**: MySQL 5.7+
- **보안**: 크리티컬 섹션을 통한 동시성 제어

## 📋 시스템 요구사항

- PHP 7.4 이상
- MySQL 5.7 이상
- Apache/Nginx 웹 서버
- PDO MySQL 확장 활성화

## 🚀 설치 방법

### 1. 파일 복사
프로젝트 파일을 웹 서버의 DocumentRoot에 복사합니다.

```bash
# Windows (XAMPP 예시)
C:\xampp\htdocs\cri_sec\

# Linux
/var/www/html/cri_sec/
```

### 2. 데이터베이스 설정

MySQL에 접속하여 데이터베이스를 생성합니다:

```bash
# MySQL 접속
mysql -u root -p0000

# 또는 phpMyAdmin을 사용하여 database.sql 파일 임포트
```

SQL 파일 실행:

```sql
source C:/Users/sanik/Documents/php_test/cri_sec/database.sql
```

또는 phpMyAdmin에서 `database.sql` 파일을 임포트하세요.

### 3. 데이터베이스 연결 정보 확인

`backend/Database.php` 파일에서 데이터베이스 연결 정보를 확인합니다:

```php
private $host = 'localhost';
private $dbname = 'cloverpit';
private $username = 'root';
private $password = '0000';
```

필요시 사용자 환경에 맞게 수정하세요.

### 4. 웹 서버 시작

**XAMPP 사용 시:**
1. XAMPP Control Panel 실행
2. Apache와 MySQL 시작
3. 브라우저에서 `http://localhost/cri_sec/frontend/` 접속

**PHP 내장 서버 사용 시:**
```bash
cd C:\Users\sanik\Documents\php_test\cri_sec
php -S localhost:8000
```

브라우저에서 `http://localhost:8000/frontend/` 접속

## 🎯 게임 플레이 방법

### 기본 규칙
1. **게임 시작**: 이름을 입력하고 게임을 시작합니다
2. **슬롯 돌리기**: 10원을 베팅하여 슬롯을 돌립니다
3. **당첨 확인**:
   - 3개 일치 시 대박 (50원~1000원)
   - 2개 일치 시 소액 당첨 (20원)
4. **티켓 획득**: 스핀할 때마다 랜덤으로 티켓 획득
5. **아이템 구매**: 티켓으로 행운의 부적 구매
6. **라운드 종료**: 빚을 갚고 다음 라운드로 진행

### 심볼 당첨표
| 심볼 | 3개 일치 시 당첨금 |
|------|-------------------|
| 7️⃣  | 1000원            |
| 💎  | 500원             |
| ⭐  | 200원             |
| 🔔  | 100원             |
| 기타 | 50원              |

### 아이템 종류
- **배율 증가**: 당첨금 배율 증가 (1.5배 ~ 5배)
- **추가 스핀**: 한 번 더 슬롯 돌리기
- **빚 탕감**: 현재 빚 20% 감소
- **보너스 머니**: 즉시 현금 획득
- **리롤**: 슬롯 결과 다시 돌리기

## 🔒 크리티컬 섹션 (안정성 기능)

이 게임은 다중 사용자 환경에서 안정성을 보장하기 위해 크리티컬 섹션을 구현했습니다:

### 주요 보호 영역
1. **게임 시작**: 동시 세션 생성 방지
2. **슬롯 스핀**: 동시 스핀으로 인한 데이터 불일치 방지
3. **아이템 구매**: 중복 구매 및 티켓 차감 오류 방지
4. **라운드 종료**: 빚 계산 및 라운드 전환 보호

### 구현 방식
- **MySQL GET_LOCK/RELEASE_LOCK**: 세션 레벨 분산 락
- **트랜잭션**: ACID 보장
- **FOR UPDATE**: Row-level 락킹
- **락 타임아웃**: 데드락 방지 (10초)
- **자동 락 정리**: 만료된 락 자동 삭제

## 📁 파일 구조

```
cri_sec/
├── frontend/           # 클라이언트 코드 (UI/게임 로직)
│   ├── index.html     # 메인 게임 HTML
│   ├── game.js        # 게임 클라이언트 로직
│   └── style.css      # 다크 호러 테마 스타일
├── backend/           # 서버 측 코드 (API/DB)
│   ├── api.php        # 게임 API 엔드포인트
│   └── Database.php   # 데이터베이스 & 크리티컬 섹션 클래스
├── database/          # 데이터베이스 스키마
│   └── database.sql   # DB 초기화 스크립트
└── README.md          # 이 파일
```

## 🐛 문제 해결

### 데이터베이스 연결 오류
- MySQL 서비스가 실행 중인지 확인
- `Database.php`의 연결 정보가 정확한지 확인
- PDO MySQL 확장이 활성화되어 있는지 확인

### 세션 오류
- PHP의 `session.save_path`가 쓰기 가능한지 확인
- 브라우저 쿠키가 활성화되어 있는지 확인

### API 호출 실패
- 브라우저 개발자 도구의 Console 탭에서 오류 확인
- Network 탭에서 API 응답 확인
- PHP 오류 로그 확인 (`error_log`)

## 🎨 커스터마이징

### 게임 밸런스 조정
`backend/api.php`에서 다음 값들을 조정할 수 있습니다:

```php
// 초기 소지금 및 빚
VALUES (:session_id, :player_name, 100, 50, 1, 0)
                                    ^^^  ^^
                                   소지금 빚

// 빚 증가율
$newDebt = $game['debt'] * 1.5; // 50% 증가
```

### 슬롯 심볼 변경
`frontend/game.js`의 `symbols` 배열을 수정하세요:

```javascript
const symbols = ['🍒', '🍋', '🍊', '🔔', '💎', '⭐', '7️⃣'];
```

### 아이템 추가
`database/database.sql`에 새로운 아이템을 추가하세요:

```sql
INSERT INTO items (name, description, effect_type, effect_value, price, rarity)
VALUES ('새 아이템', '설명', 'multiplier', 2.0, 30, 'rare');
```

## 📊 데이터베이스 스키마

### 주요 테이블
- `game_sessions`: 게임 세션 정보
- `items`: 아이템 마스터 데이터
- `player_items`: 플레이어 보유 아이템
- `game_history`: 게임 플레이 히스토리
- `critical_locks`: 크리티컬 섹션 락 관리

## 🔐 보안 고려사항

1. **SQL Injection 방지**: Prepared Statements 사용
2. **동시성 제어**: 크리티컬 섹션 구현
3. **세션 관리**: PHP 세션 사용
4. **입력 검증**: 클라이언트 및 서버 양측 검증

## 📝 라이선스

이 프로젝트는 교육 목적으로 제작되었습니다.

## 🙏 크레딧

- **영감**: CloverPit (Panik Arcade)
- **개발**: Custom implementation with critical sections

## 📞 지원

문제가 발생하면 다음을 확인하세요:
1. PHP 버전 (7.4+)
2. MySQL 버전 (5.7+)
3. PHP PDO 확장
4. 웹 서버 설정
5. 파일 권한

---

**⚠️ 주의**: 이 게임은 중독성이 강할 수 있습니다. 적당히 즐기세요! 🎰
