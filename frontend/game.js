/**
 * CloverPit - 게임 클라이언트 로직
 * API와 통신하여 게임 상태 관리 및 UI 업데이트
 */

class CloverPitGame {
    constructor() {
        this.apiUrl = '../backend/api.php';
        this.gameState = null;
        this.isSpinning = false;
        this.init();
    }

    init() {
        // 이벤트 리스너 등록
        document.getElementById('start-btn').addEventListener('click', () => this.startGame());
        document.getElementById('spin-btn').addEventListener('click', () => this.spinSlot());
        document.getElementById('shop-btn').addEventListener('click', () => this.openShop());
        document.getElementById('close-shop-btn').addEventListener('click', () => this.closeShop());
        document.getElementById('end-round-btn').addEventListener('click', () => this.endRound());
        document.getElementById('restart-btn').addEventListener('click', () => this.restart());

        // Enter 키로 게임 시작
        document.getElementById('player-name').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                this.startGame();
            }
        });
    }

    /**
     * API 호출 헬퍼
     */
    async callAPI(action, data = {}) {
        this.showLoading(true);

        try {
            const formData = new FormData();
            formData.append('action', action);

            for (const key in data) {
                formData.append(key, data[key]);
            }

            const response = await fetch(this.apiUrl, {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (!result.success) {
                this.showToast(result.error || '오류가 발생했습니다', 'error');
            }

            return result;
        } catch (error) {
            console.error('API 오류:', error);
            this.showToast('서버 연결 오류', 'error');
            return { success: false, error: error.message };
        } finally {
            this.showLoading(false);
        }
    }

    /**
     * 게임 시작
     */
    async startGame() {
        const playerName = document.getElementById('player-name').value.trim() || '플레이어';
        const result = await this.callAPI('start', { player_name: playerName });

        if (result.success) {
            this.gameState = result;
            document.getElementById('player-name-display').textContent = result.player_name;
            this.updateUI();
            this.switchScreen('game-screen');
            this.showToast('게임 시작! 행운을 빌어요...', 'success');
        }
    }

    /**
     * 슬롯 스핀
     */
    async spinSlot() {
        if (this.isSpinning) return;

        this.isSpinning = true;
        const spinBtn = document.getElementById('spin-btn');
        spinBtn.disabled = true;

        // 스핀 애니메이션
        await this.animateSpin();

        // 서버에서 결과 가져오기
        const result = await this.callAPI('spin');

        if (result.success) {
            // 결과 표시
            this.displaySpinResult(result.result);

            // 승리 라인 표시
            if (result.win_lines && result.win_lines.length > 0) {
                await this.sleep(300);
                this.displayWinLines(result.win_lines);
            }

            await this.sleep(500);

            // 결과 메시지
            const resultMsg = document.getElementById('result-message');
            if (result.win_amount > 0) {
                resultMsg.className = 'result-message win';
                let message = `🎉 당첨! ${result.win_amount}원 획득! (${result.net_change >= 0 ? '+' : ''}${result.net_change}원)`;

                if (result.win_lines && result.win_lines.length > 1) {
                    message += ` - ${result.win_lines.length}줄 당첨!`;
                }

                resultMsg.textContent = message;

                if (result.win_amount >= 500) {
                    this.showToast('🎰 대박! 큰 당첨입니다!', 'success');
                }
            } else {
                resultMsg.className = 'result-message lose';
                resultMsg.textContent = `😢 꽝... -10원`;
            }

            if (result.tickets_earned > 0) {
                this.showToast(`🎫 티켓 ${result.tickets_earned}개 획득!`, 'success');
            }

            // 게임 상태 업데이트
            await this.refreshGameState();
        }

        this.isSpinning = false;
        spinBtn.disabled = false;
    }

    /**
     * 스핀 애니메이션 - 열별로 순차적으로 회전
     */
    async animateSpin() {
        const symbols = ['🍒', '🍋', '🍊', '🔔', '💎', '⭐', '7️⃣'];
        const columns = 5; // 5개의 열
        const rows = 3; // 3개의 행
        const columnDelay = 200; // 각 열이 멈추는 간격 (ms)
        const spinDuration = 1500; // 각 열의 기본 회전 시간 (ms)
        const interval = 100; // 심볼 변경 간격 (ms)

        // 각 열을 비동기적으로 회전
        const columnPromises = [];

        for (let col = 0; col < columns; col++) {
            const promise = this.spinColumn(col, rows, symbols, spinDuration + (col * columnDelay), interval);
            columnPromises.push(promise);
            await this.sleep(columnDelay / 2); // 각 열을 약간의 시차를 두고 시작
        }

        // 모든 열의 회전이 끝날 때까지 대기
        await Promise.all(columnPromises);
    }

    /**
     * 특정 열을 회전시키는 함수
     */
    async spinColumn(colIndex, rows, symbols, duration, interval) {
        // 해당 열의 모든 셀 가져오기
        const columnCells = [];
        for (let row = 0; row < rows; row++) {
            const cell = document.querySelector(`.symbol-cell[data-row="${row}"][data-col="${colIndex}"]`);
            if (cell) {
                columnCells.push(cell);
                cell.classList.add('spinning');
            }
        }

        // 회전 애니메이션
        const iterations = duration / interval;
        for (let i = 0; i < iterations; i++) {
            columnCells.forEach(cell => {
                cell.textContent = symbols[Math.floor(Math.random() * symbols.length)];
            });
            await this.sleep(interval);
        }

        // spinning 클래스 제거
        columnCells.forEach(cell => {
            cell.classList.remove('spinning');
        });
    }

    /**
     * 스핀 결과 표시
     */
    displaySpinResult(spinResult) {
        const cells = document.querySelectorAll('.symbol-cell');

        // 먼저 모든 winning 클래스 제거
        cells.forEach(cell => {
            cell.classList.remove('winning');
        });

        // 결과를 5x3 그리드에 표시 (result는 2D 배열: [[row0], [row1], [row2]])
        if (Array.isArray(spinResult) && spinResult.length === 3) {
            spinResult.forEach((row, rowIndex) => {
                if (Array.isArray(row) && row.length === 5) {
                    row.forEach((symbol, colIndex) => {
                        const cell = document.querySelector(`.symbol-cell[data-row="${rowIndex}"][data-col="${colIndex}"]`);
                        if (cell) {
                            cell.textContent = symbol;
                        }
                    });
                }
            });
        }
    }

    /**
     * 승리 라인 표시
     */
    displayWinLines(winLines) {
        const indicator = document.getElementById('win-lines-indicator');
        indicator.innerHTML = '';

        if (!winLines || winLines.length === 0) {
            return;
        }

        // 승리한 셀들에 winning 클래스 추가
        const cells = document.querySelectorAll('.symbol-cell');
        cells.forEach(cell => {
            cell.classList.remove('winning');
        });

        winLines.forEach(line => {
            // 승리 라인 배지 추가
            const badge = document.createElement('div');
            badge.className = 'win-line-badge';
            badge.textContent = `${line.name}: ${line.symbol} x3`;
            indicator.appendChild(badge);

            // 해당 라인의 셀들에 winning 클래스 추가
            if (line.positions) {
                line.positions.forEach(pos => {
                    const cell = document.querySelector(`.symbol-cell[data-row="${pos.row}"][data-col="${pos.col}"]`);
                    if (cell) {
                        cell.classList.add('winning');
                    }
                });
            }
        });
    }

    /**
     * 라운드 종료
     */
    async endRound() {
        const confirmed = confirm('라운드를 종료하고 빚을 갚으시겠습니까?\n빚을 갚지 못하면 게임 오버됩니다!');
        if (!confirmed) return;

        const result = await this.callAPI('end_round');

        if (result.success) {
            if (result.game_over) {
                this.showGameOver(result.final_round);
            } else {
                this.showToast(result.message, 'success');
                await this.refreshGameState();
            }
        }
    }

    /**
     * 상점 열기
     */
    async openShop() {
        const result = await this.callAPI('shop');

        if (result.success) {
            this.displayShopItems(result.items);
            this.switchScreen('shop-screen');
        }
    }

    /**
     * 상점 아이템 표시
     */
    displayShopItems(items) {
        const shopGrid = document.getElementById('shop-items');
        shopGrid.innerHTML = '';

        items.forEach(item => {
            const itemCard = document.createElement('div');
            itemCard.className = `shop-item ${item.rarity}`;
            itemCard.innerHTML = `
                <div class="item-name">${item.name}</div>
                <div class="item-description">${item.description}</div>
                <div class="item-price">🎫 ${item.price} 티켓</div>
            `;

            itemCard.addEventListener('click', () => this.buyItem(item.id, item.name, item.price));
            shopGrid.appendChild(itemCard);
        });
    }

    /**
     * 아이템 구매
     */
    async buyItem(itemId, itemName, price) {
        const currentTickets = parseInt(document.getElementById('tickets-display').textContent);

        if (currentTickets < price) {
            this.showToast('티켓이 부족합니다!', 'error');
            return;
        }

        const confirmed = confirm(`${itemName}을(를) ${price} 티켓에 구매하시겠습니까?`);
        if (!confirmed) return;

        const result = await this.callAPI('buy_item', { item_id: itemId });

        if (result.success) {
            this.showToast(`${itemName} 구매 완료!`, 'success');
            await this.refreshGameState();
        }
    }

    /**
     * 상점 닫기
     */
    closeShop() {
        this.switchScreen('game-screen');
    }

    /**
     * 게임 상태 새로고침
     */
    async refreshGameState() {
        const result = await this.callAPI('state');

        if (result.success) {
            this.gameState = result.game;
            this.updateUI();
            this.displayPlayerItems(result.items);
        }
    }

    /**
     * UI 업데이트
     */
    updateUI() {
        if (!this.gameState) return;

        const game = this.gameState;

        document.getElementById('money-display').textContent = Math.floor(game.money);
        document.getElementById('debt-display').textContent = Math.floor(game.debt);
        document.getElementById('tickets-display').textContent = game.tickets || 0;
        document.getElementById('round-display').textContent = game.round || 1;

        // 돈 부족 시 스핀 버튼 비활성화
        const spinBtn = document.getElementById('spin-btn');
        if (game.money < 10) {
            spinBtn.disabled = true;
            spinBtn.textContent = '돈이 부족합니다';
        } else {
            spinBtn.disabled = false;
            spinBtn.textContent = '슬롯 돌리기 (10원)';
        }

        // 통계 색상 업데이트
        this.updateStatColors();
    }

    /**
     * 통계 색상 업데이트
     */
    updateStatColors() {
        const moneyEl = document.getElementById('money-display');
        const debtEl = document.getElementById('debt-display');
        const money = parseFloat(moneyEl.textContent);
        const debt = parseFloat(debtEl.textContent);

        // 돈이 빚보다 적으면 경고
        if (money < debt) {
            moneyEl.style.color = '#ff3333';
            debtEl.style.color = '#ff3333';
        } else {
            moneyEl.style.color = '#ffd700';
            debtEl.style.color = '#ffd700';
        }
    }

    /**
     * 플레이어 아이템 표시
     */
    displayPlayerItems(items) {
        const itemsGrid = document.getElementById('player-items');

        if (!items || items.length === 0) {
            itemsGrid.innerHTML = '<p class="no-items">아이템이 없습니다</p>';
            return;
        }

        itemsGrid.innerHTML = '';

        items.forEach(item => {
            const itemCard = document.createElement('div');
            itemCard.className = `item-card ${item.rarity}`;
            itemCard.innerHTML = `
                <div class="item-name">
                    ${item.name}
                    <span class="item-quantity">x${item.quantity}</span>
                </div>
                <div class="item-description">${item.description}</div>
            `;
            itemsGrid.appendChild(itemCard);
        });
    }

    /**
     * 게임 오버 표시
     */
    showGameOver(finalRound) {
        document.getElementById('gameover-message').textContent = '빚을 갚지 못했습니다!';
        document.getElementById('final-round').textContent = finalRound;
        this.switchScreen('gameover-screen');
    }

    /**
     * 재시작
     */
    restart() {
        this.gameState = null;
        this.isSpinning = false;
        document.getElementById('player-name').value = '플레이어';
        document.getElementById('result-message').textContent = '';
        this.switchScreen('start-screen');
    }

    /**
     * 화면 전환
     */
    switchScreen(screenId) {
        document.querySelectorAll('.screen').forEach(screen => {
            screen.classList.remove('active');
        });
        document.getElementById(screenId).classList.add('active');
    }

    /**
     * 로딩 표시
     */
    showLoading(show) {
        const overlay = document.getElementById('loading-overlay');
        if (show) {
            overlay.classList.add('active');
        } else {
            overlay.classList.remove('active');
        }
    }

    /**
     * 토스트 알림
     */
    showToast(message, type = 'info') {
        const toast = document.getElementById('toast');
        toast.textContent = message;
        toast.style.borderColor = type === 'error' ? '#ff3333' : type === 'success' ? '#33ff33' : '#ffd700';
        toast.classList.add('active');

        setTimeout(() => {
            toast.classList.remove('active');
        }, 3000);
    }

    /**
     * Sleep 헬퍼
     */
    sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }
}

// 게임 초기화
document.addEventListener('DOMContentLoaded', () => {
    const game = new CloverPitGame();
    console.log('🍀 CloverPit 게임이 로드되었습니다');
});
