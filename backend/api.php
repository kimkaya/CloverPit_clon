<?php
/**
 * CloverPit 게임 API
 * 크리티컬 섹션을 통한 안정적인 게임 상태 관리
 */

require_once 'Database.php';
require_once 'SecurityHelper.php';

// 세션 시작 전 설정
$config = require_once 'config.php';
$security = new SecurityHelper($config);

// 보안 헤더 설정
$security->setSecurityHeaders();

// 세션 시작
session_start();

// 세션 보안 강화
$security->secureSession();

// Content-Type 설정
header('Content-Type: application/json');

class GameAPI {
    private $db;
    private $security;
    private $config;

    public function __construct($security) {
        $this->db = new Database();
        $this->security = $security;
        $this->config = $this->db->getConfig();
        $this->db->cleanupExpiredLocks();
    }

    /**
     * 새 게임 시작
     */
    public function startGame($playerName) {
        // 입력 검증
        if (!$this->security->validatePlayerName($playerName)) {
            return ['success' => false, 'error' => '유효하지 않은 플레이어 이름입니다.'];
        }

        // 입력 sanitize
        $playerName = $this->security->sanitizeString($playerName);

        $sessionId = bin2hex(random_bytes(16));
        $_SESSION['game_session_id'] = $sessionId;

        $lockName = "game_start_{$sessionId}";

        try {
            // 크리티컬 섹션 진입
            if (!$this->db->acquireLock($lockName, 5)) {
                throw new Exception("게임 시작 락 획득 실패");
            }

            $conn = $this->db->connect();
            $stmt = $conn->prepare("
                INSERT INTO game_sessions (session_id, player_name, money, debt, round, tickets)
                VALUES (:session_id, :player_name, 100, 50, 1, 0)
            ");

            $stmt->execute([
                ':session_id' => $sessionId,
                ':player_name' => $playerName
            ]);

            $this->db->commit();

            return [
                'success' => true,
                'session_id' => $sessionId,
                'player_name' => $playerName,
                'money' => 100,
                'debt' => 50,
                'round' => 1,
                'tickets' => 0
            ];
        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'error' => $e->getMessage()];
        } finally {
            $this->db->releaseLock($lockName);
        }
    }

    /**
     * 게임 상태 가져오기
     */
    public function getGameState() {
        $sessionId = $_SESSION['game_session_id'] ?? null;

        if (!$sessionId) {
            return ['success' => false, 'error' => '세션 없음'];
        }

        try {
            $conn = $this->db->connect();
            $stmt = $conn->prepare("
                SELECT * FROM game_sessions WHERE session_id = :session_id
            ");
            $stmt->execute([':session_id' => $sessionId]);

            $game = $stmt->fetch();

            if (!$game) {
                return ['success' => false, 'error' => '게임을 찾을 수 없음'];
            }

            // 보유 아이템 가져오기
            $stmt = $conn->prepare("
                SELECT i.*, pi.quantity
                FROM player_items pi
                JOIN items i ON pi.item_id = i.id
                WHERE pi.session_id = :session_id
            ");
            $stmt->execute([':session_id' => $sessionId]);
            $items = $stmt->fetchAll();

            return [
                'success' => true,
                'game' => $game,
                'items' => $items
            ];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * 슬롯 스핀 (크리티컬 섹션 사용)
     */
    public function spinSlot() {
        $sessionId = $_SESSION['game_session_id'] ?? null;

        if (!$sessionId) {
            return ['success' => false, 'error' => '세션 없음'];
        }

        $lockName = "spin_{$sessionId}";

        try {
            // 크리티컬 섹션 진입 - 동시 스핀 방지
            if (!$this->db->acquireLock($lockName, 10)) {
                throw new Exception("다른 스핀이 진행 중입니다");
            }

            $conn = $this->db->connect();

            // 현재 게임 상태 조회
            $stmt = $conn->prepare("SELECT * FROM game_sessions WHERE session_id = :session_id FOR UPDATE");
            $stmt->execute([':session_id' => $sessionId]);
            $game = $stmt->fetch();

            if (!$game || $game['game_over']) {
                throw new Exception("게임 오버 또는 존재하지 않음");
            }

            if ($game['money'] < 10) {
                throw new Exception("돈이 부족합니다 (최소 10원 필요)");
            }

            // 5x3 그리드 슬롯 결과 생성
            $symbols = ['🍒', '🍋', '🍊', '🔔', '💎', '⭐', '7️⃣'];
            $result = [
                // Row 0
                [
                    $symbols[array_rand($symbols)],
                    $symbols[array_rand($symbols)],
                    $symbols[array_rand($symbols)],
                    $symbols[array_rand($symbols)],
                    $symbols[array_rand($symbols)]
                ],
                // Row 1
                [
                    $symbols[array_rand($symbols)],
                    $symbols[array_rand($symbols)],
                    $symbols[array_rand($symbols)],
                    $symbols[array_rand($symbols)],
                    $symbols[array_rand($symbols)]
                ],
                // Row 2
                [
                    $symbols[array_rand($symbols)],
                    $symbols[array_rand($symbols)],
                    $symbols[array_rand($symbols)],
                    $symbols[array_rand($symbols)],
                    $symbols[array_rand($symbols)]
                ]
            ];

            // 당첨 계산 (승리 라인 체크)
            $winResult = $this->calculateWin($result, $game, $sessionId);
            $winAmount = $winResult['total_win'];
            $winLines = $winResult['win_lines'];

            // 티켓 획득 (랜덤)
            $ticketsEarned = rand(0, 3);

            // 돈 업데이트 (베팅 10원 차감 후 당첨금 추가)
            $newMoney = $game['money'] - 10 + $winAmount;
            $newTickets = $game['tickets'] + $ticketsEarned;

            $stmt = $conn->prepare("
                UPDATE game_sessions
                SET money = :money, tickets = :tickets, updated_at = NOW()
                WHERE session_id = :session_id
            ");

            $stmt->execute([
                ':money' => $newMoney,
                ':tickets' => $newTickets,
                ':session_id' => $sessionId
            ]);

            // 히스토리 기록
            $stmt = $conn->prepare("
                INSERT INTO game_history (session_id, round, spin_result, money_change, money_after, debt_after)
                VALUES (:session_id, :round, :spin_result, :money_change, :money_after, :debt_after)
            ");

            $stmt->execute([
                ':session_id' => $sessionId,
                ':round' => $game['round'],
                ':spin_result' => json_encode($result),
                ':money_change' => $winAmount - 10,
                ':money_after' => $newMoney,
                ':debt_after' => $game['debt']
            ]);

            $this->db->commit();

            return [
                'success' => true,
                'result' => $result,
                'win_amount' => $winAmount,
                'win_lines' => $winLines,
                'bet_amount' => 10,
                'net_change' => $winAmount - 10,
                'new_money' => $newMoney,
                'tickets_earned' => $ticketsEarned,
                'new_tickets' => $newTickets
            ];
        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'error' => $e->getMessage()];
        } finally {
            $this->db->releaseLock($lockName);
        }
    }

    /**
     * 승리 라인 패턴 정의
     * 가로 3줄, 세로 5줄, 대각선 2줄, V자형, 역V자형, 마름모
     */
    private function getWinLinePatterns() {
        return [
            // 가로 라인 3줄
            ['name' => '상단 가로', 'positions' => [[0,0], [0,1], [0,2], [0,3], [0,4]]],
            ['name' => '중간 가로', 'positions' => [[1,0], [1,1], [1,2], [1,3], [1,4]]],
            ['name' => '하단 가로', 'positions' => [[2,0], [2,1], [2,2], [2,3], [2,4]]],

            // 세로 라인 5줄
            ['name' => '좌측1 세로', 'positions' => [[0,0], [1,0], [2,0]]],
            ['name' => '좌측2 세로', 'positions' => [[0,1], [1,1], [2,1]]],
            ['name' => '중앙 세로', 'positions' => [[0,2], [1,2], [2,2]]],
            ['name' => '우측1 세로', 'positions' => [[0,3], [1,3], [2,3]]],
            ['name' => '우측2 세로', 'positions' => [[0,4], [1,4], [2,4]]],

            // 대각선 2줄
            ['name' => '좌상→우하', 'positions' => [[0,0], [1,1], [2,2]]],
            ['name' => '우상→좌하', 'positions' => [[0,4], [1,3], [2,2]]],

            // V자형 (상→중→상)
            ['name' => 'V자형', 'positions' => [[0,0], [1,1], [2,2], [1,3], [0,4]]],
            // 역V자형 (하→중→하)
            ['name' => '역V자형', 'positions' => [[2,0], [1,1], [0,2], [1,3], [2,4]]],

            // 마름모 (중앙 기준)
            ['name' => '마름모', 'positions' => [[0,2], [1,1], [1,3], [2,2]]],

            // 지그재그 패턴
            ['name' => '지그재그1', 'positions' => [[0,0], [1,1], [0,2], [1,3], [0,4]]],
            ['name' => '지그재그2', 'positions' => [[2,0], [1,1], [2,2], [1,3], [2,4]]],
        ];
    }

    /**
     * 당첨금 계산 (아이템 효과 적용)
     */
    private function calculateWin($result, $game, $sessionId) {
        $winLines = [];
        $totalWin = 0;

        // 승리 라인 패턴 가져오기
        $patterns = $this->getWinLinePatterns();

        foreach ($patterns as $pattern) {
            $symbols = [];
            $positions = [];

            // 해당 라인의 심볼들 수집
            foreach ($pattern['positions'] as $pos) {
                $row = $pos[0];
                $col = $pos[1];
                $symbols[] = $result[$row][$col];
                $positions[] = ['row' => $row, 'col' => $col];
            }

            // 왼쪽부터 연속된 같은 심볼 체크
            $winCheck = $this->checkWinningLine($symbols);

            if ($winCheck['matched']) {
                $winSymbol = $winCheck['symbol'];
                $count = $winCheck['count'];
                $lineWin = $this->getSymbolWinAmount($winSymbol, $count);

                // 실제로 매칭된 위치만 포함 (연속된 심볼 개수만큼)
                $matchedPositions = array_slice($positions, 0, $count);

                $winLines[] = [
                    'name' => $pattern['name'],
                    'symbol' => $winSymbol,
                    'count' => $count,
                    'amount' => $lineWin,
                    'positions' => $matchedPositions
                ];

                $totalWin += $lineWin;
            }
        }

        // 아이템 배율 적용
        $multiplier = 1.0;
        $conn = $this->db->connect();
        $stmt = $conn->prepare("
            SELECT i.effect_type, i.effect_value, pi.quantity
            FROM player_items pi
            JOIN items i ON pi.item_id = i.id
            WHERE pi.session_id = :session_id AND i.effect_type = 'multiplier'
        ");
        $stmt->execute([':session_id' => $sessionId]);
        $items = $stmt->fetchAll();

        foreach ($items as $item) {
            $multiplier += ($item['effect_value'] - 1) * $item['quantity'];
        }

        $totalWin = floor($totalWin * $multiplier);

        return [
            'total_win' => $totalWin,
            'win_lines' => $winLines
        ];
    }

    /**
     * 승리 라인 체크 (왼쪽부터 연속된 같은 심볼)
     * @return array ['matched' => bool, 'symbol' => string, 'count' => int]
     */
    private function checkWinningLine($symbols) {
        if (empty($symbols)) {
            return ['matched' => false, 'symbol' => null, 'count' => 0];
        }

        // 왼쪽부터 연속된 같은 심볼 개수 세기
        $firstSymbol = $symbols[0];
        $consecutiveCount = 1;

        for ($i = 1; $i < count($symbols); $i++) {
            if ($symbols[$i] === $firstSymbol) {
                $consecutiveCount++;
            } else {
                break; // 다른 심볼이 나오면 중단
            }
        }

        // 연속 3개 이상이면 당첨
        if ($consecutiveCount >= 3) {
            return [
                'matched' => true,
                'symbol' => $firstSymbol,
                'count' => $consecutiveCount
            ];
        }

        return ['matched' => false, 'symbol' => null, 'count' => 0];
    }

    /**
     * 심볼별 당첨금
     */
    private function getSymbolWinAmount($symbol, $count) {
        $baseAmounts = [
            '7️⃣' => 1000,
            '💎' => 500,
            '⭐' => 200,
            '🔔' => 100,
            '🍊' => 50,
            '🍋' => 50,
            '🍒' => 50
        ];

        $baseWin = $baseAmounts[$symbol] ?? 50;

        // 3개: 기본, 4개: 1.5배, 5개: 2배
        $multiplier = 1.0;
        if ($count == 4) {
            $multiplier = 1.5;
        } elseif ($count >= 5) {
            $multiplier = 2.0;
        }

        return floor($baseWin * $multiplier);
    }

    /**
     * 라운드 종료 (빚 갚기)
     */
    public function endRound() {
        $sessionId = $_SESSION['game_session_id'] ?? null;

        if (!$sessionId) {
            return ['success' => false, 'error' => '세션 없음'];
        }

        $lockName = "end_round_{$sessionId}";

        try {
            // 크리티컬 섹션 진입
            if (!$this->db->acquireLock($lockName, 10)) {
                throw new Exception("라운드 종료 처리 중 락 획득 실패");
            }

            $conn = $this->db->connect();
            $stmt = $conn->prepare("SELECT * FROM game_sessions WHERE session_id = :session_id FOR UPDATE");
            $stmt->execute([':session_id' => $sessionId]);
            $game = $stmt->fetch();

            if (!$game) {
                throw new Exception("게임을 찾을 수 없음");
            }

            $debtPayment = $game['debt'];
            $remainingMoney = $game['money'] - $debtPayment;

            if ($remainingMoney < 0) {
                // 게임 오버
                $stmt = $conn->prepare("
                    UPDATE game_sessions
                    SET game_over = TRUE, updated_at = NOW()
                    WHERE session_id = :session_id
                ");
                $stmt->execute([':session_id' => $sessionId]);
                $this->db->commit();

                return [
                    'success' => true,
                    'game_over' => true,
                    'message' => "빚을 갚지 못했습니다! 게임 오버!",
                    'final_round' => $game['round']
                ];
            }

            // 다음 라운드로
            $newDebt = $game['debt'] * 1.5; // 빚 50% 증가
            $newRound = $game['round'] + 1;

            // 빚 상환 보너스 티켓 지급 (라운드가 높을수록 더 많이)
            $bonusTickets = 3 + ($game['round'] * 2); // 기본 3개 + 라운드당 2개 추가
            $newTickets = $game['tickets'] + $bonusTickets;

            $stmt = $conn->prepare("
                UPDATE game_sessions
                SET money = :money, debt = :debt, round = :round, tickets = :tickets, updated_at = NOW()
                WHERE session_id = :session_id
            ");

            $stmt->execute([
                ':money' => $remainingMoney,
                ':debt' => $newDebt,
                ':round' => $newRound,
                ':tickets' => $newTickets,
                ':session_id' => $sessionId
            ]);

            $this->db->commit();

            return [
                'success' => true,
                'game_over' => false,
                'message' => "라운드 {$game['round']} 클리어!",
                'new_round' => $newRound,
                'new_money' => $remainingMoney,
                'new_debt' => $newDebt,
                'bonus_tickets' => $bonusTickets,
                'new_tickets' => $newTickets
            ];
        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'error' => $e->getMessage()];
        } finally {
            $this->db->releaseLock($lockName);
        }
    }

    /**
     * 아이템 구매 (크리티컬 섹션)
     */
    public function buyItem($itemId) {
        // 입력 검증
        if (!$this->security->validateInteger($itemId, 1)) {
            return ['success' => false, 'error' => '유효하지 않은 아이템 ID입니다.'];
        }

        $sessionId = $_SESSION['game_session_id'] ?? null;

        if (!$sessionId) {
            return ['success' => false, 'error' => '세션 없음'];
        }

        // 세션 ID 검증
        if (!$this->security->validateSessionId($sessionId)) {
            return ['success' => false, 'error' => '유효하지 않은 세션입니다.'];
        }

        $lockName = "buy_item_{$sessionId}";

        try {
            // 크리티컬 섹션 진입
            if (!$this->db->acquireLock($lockName, 10)) {
                throw new Exception("아이템 구매 처리 중");
            }

            $conn = $this->db->connect();

            // 아이템 정보 조회
            $stmt = $conn->prepare("SELECT * FROM items WHERE id = :id");
            $stmt->execute([':id' => $itemId]);
            $item = $stmt->fetch();

            if (!$item) {
                throw new Exception("아이템을 찾을 수 없음");
            }

            // 게임 상태 조회
            $stmt = $conn->prepare("SELECT * FROM game_sessions WHERE session_id = :session_id FOR UPDATE");
            $stmt->execute([':session_id' => $sessionId]);
            $game = $stmt->fetch();

            if ($game['tickets'] < $item['price']) {
                throw new Exception("티켓이 부족합니다");
            }

            // 티켓 차감
            $newTickets = $game['tickets'] - $item['price'];

            $stmt = $conn->prepare("
                UPDATE game_sessions SET tickets = :tickets WHERE session_id = :session_id
            ");
            $stmt->execute([
                ':tickets' => $newTickets,
                ':session_id' => $sessionId
            ]);

            // 아이템 추가
            $stmt = $conn->prepare("
                INSERT INTO player_items (session_id, item_id, quantity)
                VALUES (:session_id, :item_id, 1)
                ON DUPLICATE KEY UPDATE quantity = quantity + 1
            ");

            $stmt->execute([
                ':session_id' => $sessionId,
                ':item_id' => $itemId
            ]);

            // 즉시 효과 적용 (bonus_money, debt_reduce)
            if ($item['effect_type'] === 'bonus_money') {
                $stmt = $conn->prepare("
                    UPDATE game_sessions SET money = money + :bonus WHERE session_id = :session_id
                ");
                $stmt->execute([
                    ':bonus' => $item['effect_value'],
                    ':session_id' => $sessionId
                ]);
            } elseif ($item['effect_type'] === 'debt_reduce') {
                $stmt = $conn->prepare("
                    UPDATE game_sessions SET debt = debt * (1 - :reduce) WHERE session_id = :session_id
                ");
                $stmt->execute([
                    ':reduce' => $item['effect_value'],
                    ':session_id' => $sessionId
                ]);
            }

            $this->db->commit();

            return [
                'success' => true,
                'item' => $item,
                'new_tickets' => $newTickets
            ];
        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'error' => $e->getMessage()];
        } finally {
            $this->db->releaseLock($lockName);
        }
    }

    /**
     * 상점 아이템 목록
     */
    public function getShopItems() {
        try {
            $conn = $this->db->connect();
            $stmt = $conn->prepare("SELECT * FROM items ORDER BY rarity, price");
            $stmt->execute();
            $items = $stmt->fetchAll();

            return [
                'success' => true,
                'items' => $items
            ];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}

// Rate Limiting 체크
$clientIP = $security->getClientIP();
if (!$security->checkRateLimit($clientIP)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => '너무 많은 요청이 발생했습니다. 잠시 후 다시 시도해주세요.']);
    exit;
}

// API 라우팅
$api = new GameAPI($security);
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// CSRF 토큰 생성 (GET 요청 시)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'get_csrf_token') {
    echo json_encode([
        'success' => true,
        'csrf_token' => $security->generateCSRFToken()
    ]);
    exit;
}

// CSRF 토큰 검증 (POST 요청 시)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $config['security']['csrf_enabled']) {
    $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

    if (!$security->validateCSRFToken($csrfToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'CSRF 토큰 검증 실패']);
        exit;
    }
}

try {
    switch ($action) {
        case 'start':
            $playerName = $_POST['player_name'] ?? '플레이어';
            echo json_encode($api->startGame($playerName));
            break;

        case 'state':
            echo json_encode($api->getGameState());
            break;

        case 'spin':
            echo json_encode($api->spinSlot());
            break;

        case 'end_round':
            echo json_encode($api->endRound());
            break;

        case 'buy_item':
            $itemId = $_POST['item_id'] ?? 0;
            echo json_encode($api->buyItem($itemId));
            break;

        case 'shop':
            echo json_encode($api->getShopItems());
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => '잘못된 액션']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => '서버 오류가 발생했습니다.']);
}
