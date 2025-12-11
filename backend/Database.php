<?php
/**
 * Database 클래스 - MySQL 연결 및 크리티컬 섹션 관리
 * 멀티 유저 환경에서 안정성을 보장하는 트랜잭션 및 락 메커니즘 제공
 */

class Database {
    private $host = 'localhost';
    private $dbname = 'cloverpit';
    private $username = 'root';
    private $password = '0000';
    private $conn = null;
    private $inTransaction = false;

    /**
     * 데이터베이스 연결 생성 (싱글톤 패턴)
     */
    public function connect() {
        if ($this->conn !== null) {
            return $this->conn;
        }

        try {
            $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => false // 크리티컬 섹션을 위해 persistent 연결 사용 안 함
            ];

            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
            return $this->conn;
        } catch (PDOException $e) {
            throw new Exception("데이터베이스 연결 실패: " . $e->getMessage());
        }
    }

    /**
     * 크리티컬 섹션 시작 - 분산 락 획득
     * @param string $lockName 락 이름
     * @param int $timeout 락 타임아웃 (초)
     * @return bool 락 획득 성공 여부
     */
    public function acquireLock($lockName, $timeout = 10) {
        $conn = $this->connect();

        try {
            // 트랜잭션 시작
            if (!$this->inTransaction) {
                $conn->beginTransaction();
                $this->inTransaction = true;
            }

            // MySQL GET_LOCK 함수 사용 (세션 레벨 락)
            $stmt = $conn->prepare("SELECT GET_LOCK(:lock_name, :timeout) as lock_result");
            $stmt->execute([
                ':lock_name' => $lockName,
                ':timeout' => $timeout
            ]);

            $result = $stmt->fetch();

            if ($result['lock_result'] == 1) {
                // 락 테이블에 기록
                $sessionId = session_id() ?: uniqid('lock_', true);
                $expiresAt = date('Y-m-d H:i:s', time() + $timeout);

                $stmt = $conn->prepare("
                    INSERT INTO critical_locks (lock_name, locked_by, locked_at, expires_at)
                    VALUES (:lock_name, :locked_by, NOW(), :expires_at)
                    ON DUPLICATE KEY UPDATE
                        locked_by = VALUES(locked_by),
                        locked_at = NOW(),
                        expires_at = VALUES(expires_at)
                ");

                $stmt->execute([
                    ':lock_name' => $lockName,
                    ':locked_by' => $sessionId,
                    ':expires_at' => $expiresAt
                ]);

                return true;
            }

            return false;
        } catch (PDOException $e) {
            if ($this->inTransaction) {
                $conn->rollBack();
                $this->inTransaction = false;
            }
            throw new Exception("락 획득 실패: " . $e->getMessage());
        }
    }

    /**
     * 크리티컬 섹션 종료 - 락 해제
     * @param string $lockName 락 이름
     */
    public function releaseLock($lockName) {
        $conn = $this->connect();

        try {
            // MySQL RELEASE_LOCK 함수 사용
            $stmt = $conn->prepare("SELECT RELEASE_LOCK(:lock_name) as release_result");
            $stmt->execute([':lock_name' => $lockName]);

            // 락 테이블에서 삭제
            $stmt = $conn->prepare("DELETE FROM critical_locks WHERE lock_name = :lock_name");
            $stmt->execute([':lock_name' => $lockName]);

            // 트랜잭션 커밋
            if ($this->inTransaction) {
                $conn->commit();
                $this->inTransaction = false;
            }
        } catch (PDOException $e) {
            if ($this->inTransaction) {
                $conn->rollBack();
                $this->inTransaction = false;
            }
            throw new Exception("락 해제 실패: " . $e->getMessage());
        }
    }

    /**
     * 만료된 락 정리
     */
    public function cleanupExpiredLocks() {
        $conn = $this->connect();

        try {
            $stmt = $conn->prepare("DELETE FROM critical_locks WHERE expires_at < NOW()");
            $stmt->execute();
        } catch (PDOException $e) {
            error_log("락 정리 실패: " . $e->getMessage());
        }
    }

    /**
     * 트랜잭션 시작
     */
    public function beginTransaction() {
        $conn = $this->connect();
        if (!$this->inTransaction) {
            $conn->beginTransaction();
            $this->inTransaction = true;
        }
    }

    /**
     * 트랜잭션 커밋
     */
    public function commit() {
        if ($this->inTransaction && $this->conn) {
            $this->conn->commit();
            $this->inTransaction = false;
        }
    }

    /**
     * 트랜잭션 롤백
     */
    public function rollback() {
        if ($this->inTransaction && $this->conn) {
            $this->conn->rollback();
            $this->inTransaction = false;
        }
    }

    /**
     * 연결 종료
     */
    public function close() {
        if ($this->inTransaction) {
            $this->rollback();
        }
        $this->conn = null;
    }
}
