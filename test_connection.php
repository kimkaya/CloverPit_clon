<?php
header('Content-Type: text/plain; charset=utf-8');

echo "=== PHP-MySQL 연결 테스트 ===\n\n";

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
    $dsn = "mysql:host=localhost;dbname=cloverpit;charset=utf8mb4";
    $username = "root";
    $password = "0000";
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
    echo "\n오류 메시지: " . $e->getMessage() . "\n";
    echo "오류 코드: " . $e->getCode() . "\n";
    exit(1);
}
?>
