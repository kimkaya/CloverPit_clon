<?php
/**
 * 보안 헬퍼 클래스
 * 입력 검증, CSRF 보호, Rate Limiting 등 보안 기능 제공
 */

class SecurityHelper {
    private $config;
    private static $rateLimitStore = [];

    public function __construct($config) {
        $this->config = $config;
    }

    /**
     * 문자열 입력 검증
     */
    public function validateString($input, $minLength = 1, $maxLength = 255) {
        if (!is_string($input)) {
            return false;
        }

        $input = trim($input);
        $length = mb_strlen($input, 'UTF-8');

        return $length >= $minLength && $length <= $maxLength;
    }

    /**
     * 정수 입력 검증
     */
    public function validateInteger($input, $min = null, $max = null) {
        if (!is_numeric($input)) {
            return false;
        }

        $value = intval($input);

        if ($min !== null && $value < $min) {
            return false;
        }

        if ($max !== null && $value > $max) {
            return false;
        }

        return true;
    }

    /**
     * 플레이어 이름 검증
     */
    public function validatePlayerName($name) {
        if (!$this->validateString($name, 1, 50)) {
            return false;
        }

        // XSS 방지: HTML 태그 제거
        $cleaned = strip_tags($name);
        return $cleaned === $name;
    }

    /**
     * 세션 ID 검증
     */
    public function validateSessionId($sessionId) {
        // 32자 16진수 문자열 검증
        return is_string($sessionId) && preg_match('/^[a-f0-9]{32}$/i', $sessionId);
    }

    /**
     * 입력 문자열 sanitize
     */
    public function sanitizeString($input) {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }

    /**
     * CSRF 토큰 생성
     */
    public function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * CSRF 토큰 검증
     */
    public function validateCSRFToken($token) {
        if (!$this->config['security']['csrf_enabled']) {
            return true;
        }

        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }

        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Rate Limiting 체크
     */
    public function checkRateLimit($identifier) {
        if (!$this->config['security']['rate_limit']['enabled']) {
            return true;
        }

        $maxRequests = $this->config['security']['rate_limit']['max_requests'];
        $timeWindow = $this->config['security']['rate_limit']['time_window'];
        $currentTime = time();

        // 만료된 항목 정리
        if (isset(self::$rateLimitStore[$identifier])) {
            self::$rateLimitStore[$identifier] = array_filter(
                self::$rateLimitStore[$identifier],
                function($timestamp) use ($currentTime, $timeWindow) {
                    return ($currentTime - $timestamp) < $timeWindow;
                }
            );
        } else {
            self::$rateLimitStore[$identifier] = [];
        }

        // 요청 수 확인
        if (count(self::$rateLimitStore[$identifier]) >= $maxRequests) {
            return false;
        }

        // 새 요청 기록
        self::$rateLimitStore[$identifier][] = $currentTime;
        return true;
    }

    /**
     * 보안 헤더 설정
     */
    public function setSecurityHeaders() {
        // XSS 방지
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');

        // HTTPS 강제 (프로덕션 환경)
        if ($this->config['environment'] === 'production') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }

        // Content Security Policy
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'");
    }

    /**
     * 세션 보안 강화
     */
    public function secureSession() {
        // 세션 설정
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_secure', $this->config['environment'] === 'production' ? 1 : 0);
        ini_set('session.cookie_samesite', 'Strict');

        // 세션 수명 설정
        ini_set('session.gc_maxlifetime', $this->config['security']['session_lifetime']);

        // 세션 재생성 (세션 고정 공격 방지)
        if (!isset($_SESSION['initiated'])) {
            session_regenerate_id(true);
            $_SESSION['initiated'] = true;
            $_SESSION['created_at'] = time();
        }

        // 세션 만료 확인
        if (isset($_SESSION['created_at'])) {
            $sessionLifetime = $this->config['security']['session_lifetime'];
            if (time() - $_SESSION['created_at'] > $sessionLifetime) {
                session_unset();
                session_destroy();
                session_start();
                $_SESSION['expired'] = true;
            }
        }
    }

    /**
     * IP 주소 가져오기
     */
    public function getClientIP() {
        $ip = '';

        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        }

        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : 'unknown';
    }
}
