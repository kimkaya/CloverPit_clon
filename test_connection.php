<?php
/**
 * 보안 경고: 이 파일은 개발/테스트 환경 전용입니다.
 * 프로덕션 환경에서는 반드시 삭제하거나 접근을 차단하세요!
 */

// 프로덕션 환경에서 접근 차단
$config = require_once __DIR__ . '/backend/config.php';
if ($config['environment'] === 'production') {
    http_response_code(403);
    die('Access Denied');
}

header('Content-Type: text/plain; charset=utf-8');

echo "=== PHP-MySQL 연결 테스트 ===\n";
echo "경고: 이 페이지는 개발 환경 전용입니다.\n\n";

// PHP 버전 확인
echo "1. PHP 버전: " . phpversion() . "\n";

// PDO MySQL 확장 확인
if (extension_loaded('pdo_mysql')) {
    echo "2. PDO MySQL 확장: ✓ 설치됨\n";
} else {
    echo "2. PDO MySQL 확장: ✗ 설치되지 않음\n";
    exit(1);
}

// 데이터베이스 연결 시도
echo "\n3. 데이터베이스 연결 시도...\n";
try {
    $dbConfig = $config['database'];
    $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}";
    $username = $dbConfig['username'];
    $password = $dbConfig['password'];
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $conn = new PDO($dsn, $username, $password, $options);
    echo "   연결 성공! ✓\n";

    // 테이블 확인
    echo "\n4. 테이블 확인:\n";
    $stmt = $conn->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        echo "   - $table\n";
    }

    // 테스트 쿼리 실행
    echo "\n5. 테스트 쿼리 실행:\n";
    $stmt = $conn->query("SELECT COUNT(*) as count FROM items");
    $result = $stmt->fetch();
    echo "   items 테이블 레코드 수: " . $result['count'] . "\n";

    echo "\n✓ 모든 테스트 통과!\n";
    echo "데이터베이스 연결이 정상적으로 작동합니다.\n";

} catch (PDOException $e) {
    echo "   연결 실패! ✗\n";
    echo "\n오류: 데이터베이스 연결에 실패했습니다.\n";
    // 개발 환경에서만 상세 정보 표시
    if ($config['logging']['enabled']) {
        error_log("DB Connection Error: " . $e->getMessage());
    }
    exit(1);
}
?>
