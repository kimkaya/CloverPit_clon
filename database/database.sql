-- CloverPit 게임 데이터베이스 스키마
-- MySQL 데이터베이스 생성 및 테이블 설정

CREATE DATABASE IF NOT EXISTS cloverpit CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE cloverpit;

-- 게임 세션 테이블
CREATE TABLE IF NOT EXISTS game_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(64) UNIQUE NOT NULL,
    player_name VARCHAR(50) NOT NULL,
    money DECIMAL(10, 2) DEFAULT 100.00,
    debt DECIMAL(10, 2) DEFAULT 50.00,
    round INT DEFAULT 1,
    tickets INT DEFAULT 0,
    game_over BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_session_id (session_id),
    INDEX idx_game_over (game_over)
) ENGINE=InnoDB;

-- 행운의 부적 (아이템) 테이블
CREATE TABLE IF NOT EXISTS items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    effect_type ENUM('multiplier', 'extra_spin', 'debt_reduce', 'bonus_money', 'reroll') NOT NULL,
    effect_value DECIMAL(10, 2) NOT NULL,
    price INT NOT NULL,
    rarity ENUM('common', 'rare', 'epic', 'legendary') DEFAULT 'common',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 플레이어 보유 아이템 테이블
CREATE TABLE IF NOT EXISTS player_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(64) NOT NULL,
    item_id INT NOT NULL,
    quantity INT DEFAULT 1,
    acquired_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    INDEX idx_session_items (session_id),
    UNIQUE KEY unique_session_item (session_id, item_id)
) ENGINE=InnoDB;

-- 게임 히스토리 테이블 (통계용)
CREATE TABLE IF NOT EXISTS game_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(64) NOT NULL,
    round INT NOT NULL,
    spin_result TEXT,
    money_change DECIMAL(10, 2),
    money_after DECIMAL(10, 2),
    debt_after DECIMAL(10, 2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_session_history (session_id)
) ENGINE=InnoDB;

-- 크리티컬 섹션 락 테이블
CREATE TABLE IF NOT EXISTS critical_locks (
    lock_name VARCHAR(64) PRIMARY KEY,
    locked_by VARCHAR(64),
    locked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP,
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB;

-- 초기 아이템 데이터 삽입
INSERT INTO items (name, description, effect_type, effect_value, price, rarity) VALUES
('황금 클로버', '당첨 배율이 1.5배 증가합니다', 'multiplier', 1.5, 10, 'common'),
('행운의 동전', '당첨 배율이 2배 증가합니다', 'multiplier', 2.0, 20, 'rare'),
('다이아몬드 부적', '당첨 배율이 3배 증가합니다', 'multiplier', 3.0, 40, 'epic'),
('추가 레버', '한 번 더 슬롯을 돌릴 수 있습니다', 'extra_spin', 1, 15, 'common'),
('빚 탕감 쿠폰', '빚이 20% 감소합니다', 'debt_reduce', 0.2, 25, 'rare'),
('보너스 머니', '즉시 50원을 획득합니다', 'bonus_money', 50, 15, 'common'),
('리롤 칩', '슬롯 결과를 다시 돌릴 수 있습니다', 'reroll', 1, 30, 'epic'),
('전설의 토끼발', '당첨 배율이 5배 증가합니다', 'multiplier', 5.0, 100, 'legendary');
