<?php
/**
 * 설정 파일 예시
 * 이 파일을 config.php로 복사하고 실제 값으로 수정하세요
 */

return [
    'database' => [
        'host' => 'localhost',
        'dbname' => 'cloverpit',
        'username' => 'root',
        'password' => 'your_password_here',
        'charset' => 'utf8mb4'
    ],

    'security' => [
        'session_lifetime' => 3600, // 1시간
        'rate_limit' => [
            'enabled' => true,
            'max_requests' => 100,
            'time_window' => 60 // 60초
        ],
        'csrf_enabled' => true
    ],

    'environment' => 'production', // 'development' 또는 'production'

    'logging' => [
        'enabled' => true,
        'level' => 'error', // 'debug', 'info', 'warning', 'error'
        'file' => __DIR__ . '/../logs/app.log'
    ]
];
