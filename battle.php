<?php
require_once 'includes/auth.php';
require_once 'includes/config.php';

$battleId = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;
$forceViewMode = isset($_GET['view']) && $_GET['view'] == 1; // Принудительный просмотр
$ourGuildId = 1;
$isViewMode = false;
$isLocked = false;
$lockInfo = null;
$currentAdmin = getCurrentAdmin();

if ($battleId > 0) {
    // Получаем статус и блокировку
    $stmt = $pdo->prepare("
        SELECT b.status, b.locked_by, b.locked_at, a.username 
        FROM battles b
        LEFT JOIN admin a ON b.locked_by = a.id
        WHERE b.id = ?
    ");
    $stmt->execute([$battleId]);
    $battle = $stmt->fetch();
    
    if (!$battle) {
        header('Location: index.php');
        exit;
    }
    
    // Если передан параметр view=1 - принудительный просмотр
    if ($forceViewMode) {
        $isViewMode = true;
        include 'battle-view.php';
        exit;
    }
    
    // Автоматический просмотр для завершённых боев
    $isViewMode = ($battle['status'] === 'completed');
    
    // Если бой завершён - показываем просмотр
    if ($isViewMode) {
        include 'battle-view.php';
        exit;
    }
    
    // Для черновиков - проверяем блокировку
    if ($battle['status'] === 'draft') {
        // Проверяем блокировку
        if ($battle['locked_by'] && $battle['locked_by'] != $currentAdmin['id']) {
            $isLocked = true;
            $lockInfo = $battle;
        }
        
        // Если блокировка этого админа - обновляем время
        if ($battle['locked_by'] == $currentAdmin['id']) {
            $stmt = $pdo->prepare("UPDATE battles SET locked_at = NOW() WHERE id = ?");
            $stmt->execute([$battleId]);
        }
        
        // Если блокировки нет - ставим
        if (!$battle['locked_by']) {
            $stmt = $pdo->prepare("UPDATE battles SET locked_by = ?, locked_at = NOW() WHERE id = ?");
            $stmt->execute([$currentAdmin['id'], $battleId]);
        }
    }
}

// Если бой заблокирован другим админом
if ($isLocked && $battleId > 0) {
    require_once 'includes/header.php';
    ?>
    <div class="container mt-5">
        <div class="alert alert-warning text-center">
            <i class="fas fa-lock fa-3x mb-3 d-block"></i>
            <h4>Бой редактируется другим администратором</h4>
            <p>Редактирует: <strong><?= htmlspecialchars($lockInfo['username']) ?></strong></p>
            <p>Начало: <?= date('d.m.Y H:i:s', strtotime($lockInfo['locked_at'])) ?></p>
            <a href="index.php" class="btn btn-primary mt-3">
                <i class="fas fa-arrow-left"></i> Вернуться к списку
            </a>
            <?php if (isSuperAdmin()): ?>
                <button id="forceUnlockBtn" class="btn btn-danger mt-3 ms-2">
                    <i class="fas fa-unlock-alt"></i> Принудительно снять блокировку
                </button>
            <?php endif; ?>
        </div>
    </div>
    <script>
    $('#forceUnlockBtn').on('click', function() {
        if (confirm('Снять блокировку принудительно?')) {
            $.post('ajax/BattleHandler.php', {
                action: 'force_unlock_battle',
                battle_id: <?= $battleId ?>
            }, function(res) {
                if (res.success) location.reload();
                else alert('Ошибка: ' + res.error);
            }, 'json');
        }
    });
    </script>
    <?php
    require_once 'includes/footer.php';
    exit;
}

// Режим редактирования
require_once 'includes/header.php';

// Получаем данные
$guilds = $pdo->query("SELECT id, name FROM guilds WHERE id != $ourGuildId ORDER BY name")->fetchAll();
$buildings = $pdo->query("SELECT name, unit_type FROM buildings ORDER BY sort_order")->fetchAll();
$titanElements = $pdo->query("SELECT id, name, color FROM titan_elements ORDER BY name")->fetchAll();
$players = $pdo->query("SELECT nickname FROM players WHERE guild_id = $ourGuildId AND is_active = 1 ORDER BY nickname")->fetchAll();
$heroes = $pdo->query("SELECT id, name, image FROM heroes_catalog ORDER BY name")->fetchAll();

// Если редактируем существующий бой, загружаем данные
$battleData = null;
$itemsData = [];
if ($battleId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM battles WHERE id = ?");
    $stmt->execute([$battleId]);
    $battleData = $stmt->fetch();
    
    if ($battleData) {
        $stmt = $pdo->prepare("SELECT * FROM battle_items WHERE battle_id = ? ORDER BY slot_index");
        $stmt->execute([$battleId]);
        $itemsData = $stmt->fetchAll();
    }
}

// Флаг: новый бой (ещё не создан в БД)
$isNewBattle = ($battleId == 0);

// Проверка наличия активного черновика
if ($isNewBattle) {
    $stmt = $pdo->prepare("
        SELECT b.id, b.locked_by, b.locked_at, a.username 
        FROM battles b
        LEFT JOIN admin a ON b.locked_by = a.id
        WHERE b.our_guild_id = ? AND b.status = 'draft' 
        LIMIT 1
    ");
    $stmt->execute([$ourGuildId]);
    $draft = $stmt->fetch();
    
    if ($draft) {
        // Черновик есть, проверяем блокировку
        if ($draft['locked_by'] && $draft['locked_by'] != $currentAdmin['id']) {
            // Черновик редактируется другим админом - показываем сообщение
            ?>
            <div class="container mt-5">
                <div class="alert alert-warning text-center">
                    <i class="fas fa-lock fa-3x mb-3 d-block"></i>
                    <h4>Черновик редактируется другим администратором</h4>
                    <p>Редактирует: <strong><?= htmlspecialchars($draft['username']) ?></strong></p>
                    <p>Начало редактирования: <?= date('d.m.Y H:i:s', strtotime($draft['locked_at'])) ?></p>
                    <a href="index.php" class="btn btn-primary mt-3">
                        <i class="fas fa-arrow-left"></i> Вернуться к списку
                    </a>
                    <?php if (isSuperAdmin()): ?>
                        <button id="forceUnlockDraftBtn" class="btn btn-danger mt-3 ms-2">
                            <i class="fas fa-unlock-alt"></i> Принудительно снять блокировку
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            <script>
            $('#forceUnlockDraftBtn').on('click', function() {
                if (confirm('Снять блокировку черновика принудительно?')) {
                    $.post('ajax/BattleHandler.php', {
                        action: 'force_unlock_battle',
                        battle_id: <?= $draft['id'] ?>
                    }, function(res) {
                        if (res.success) location.reload();
                        else alert('Ошибка: ' + res.error);
                    }, 'json');
                }
            });
            </script>
            <?php
            require_once 'includes/footer.php';
            exit;
        }
        
        // Черновик свободен или заблокирован текущим админом - перенаправляем
        header('Location: battle.php?id=' . $draft['id']);
        exit;
    }
}

// Если дошли сюда - черновика нет, можно создавать новый
$showDraftWarning = false;
?>

<div class="container battle-edit-container page-battle">
    <!-- Шапка -->
    <div class="battle-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
           <a href="index.php" class="btn btn-light btn-sm" id="backBtn">
				<i class="fas fa-arrow-left"></i> Назад
			</a>
            <div class="text-center">
                <div class="d-flex gap-3 align-items-center flex-wrap">
                    <div>
                        <label class="form-label mb-0 small"><i class="far fa-calendar-alt"></i> Дата</label>
                        <input type="date" id="battleDate" class="form-control form-control-sm" value="<?= ($battleData && $battleData['battle_date']) ? $battleData['battle_date'] : date('Y-m-d') ?>" <?= (!$isNewBattle) ? 'disabled' : '' ?>>
                    </div>
                    <div>
                        <label class="form-label mb-0 small"><i class="fas fa-flag"></i> Противник</label>
                        <select id="enemyGuild" class="form-select form-select-sm" <?= (!$isNewBattle) ? 'disabled' : '' ?>>
                            <option value="">Выберите гильдию</option>
                            <?php foreach ($guilds as $g): ?>
                                <option value="<?= $g['id'] ?>" <?= ($battleData && $battleData['enemy_guild_id'] == $g['id']) ? 'selected' : '' ?>><?= htmlspecialchars($g['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label mb-0 small"><i class="fas fa-shield-alt"></i> Imperial</label>
                        <input type="number" id="ourScore" class="form-control form-control-sm" value="<?= $battleData['our_score'] ?? 0 ?>" style="width: 70px" <?= $isNewBattle ? 'disabled' : '' ?>>
                    </div>
                    <div>
                        <label class="form-label mb-0 small"><i class="fas fa-skull"></i> Противник</label>
                        <input type="number" id="enemyScore" class="form-control form-control-sm" value="<?= $battleData['enemy_score'] ?? 0 ?>" style="width: 70px" <?= $isNewBattle ? 'disabled' : '' ?>>
                    </div>
                </div>
            </div>
            <div class="d-flex gap-2">
				<?php if (!$isNewBattle && !$isViewMode): ?>
					<button class="btn btn-secondary btn-sm" id="exitEditBtn">
						<i class="fas fa-sign-out-alt"></i> Выйти из редактирования
					</button>
				<?php endif; ?>
				
				<?php if ($isNewBattle): ?>
					<button class="btn btn-primary btn-sm" id="createBtn" disabled>
						<i class="fas fa-plus"></i> Создать бой
					</button>
				<?php else: ?>
					<button class="btn btn-danger btn-sm" id="completeBtn">
						<i class="fas fa-check-circle"></i> Завершить
					</button>
				<?php endif; ?>
			</div>
        </div>
    </div>

    <!-- Список зданий - показываем только если бой создан -->
    <div id="buildingsContainer" style="<?= ($isNewBattle) ? 'display: none;' : '' ?>">
        <!-- Сюда будет загружен список зданий -->
    </div>
    
    <!-- Сообщение для нового боя -->
    <?php if ($isNewBattle): ?>
        <div class="alert alert-info text-center mt-4" id="newBattleMessage">
            <i class="fas fa-info-circle fa-2x mb-2 d-block"></i>
            <h5>Создание нового боя</h5>
            <p>Выберите дату боя и гильдию противника, затем нажмите кнопку <strong>"Создать бой"</strong></p>
        </div>
    <?php endif; ?>
</div>

<!-- Модальное окно выбора героев -->
<div class="modal fade" id="heroModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-users"></i> Выбор героев (макс. 5)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="heroModalBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times"></i> Отмена</button>
                <button type="button" class="btn btn-primary" onclick="saveHeroSelection()"><i class="fas fa-save"></i> Сохранить</button>
            </div>
        </div>
    </div>
</div>

<script>
// Передаём данные из PHP в JS
window.battleId = <?= $battleId ?>;
window.isNewBattle = <?= $isNewBattle ? 'true' : 'false' ?>;
window.buildings = <?= json_encode($buildings) ?>;
window.titanElements = <?= json_encode($titanElements) ?>;
window.players = <?= json_encode($players) ?>;
window.heroes = <?= json_encode($heroes) ?>;
window.itemsData = <?= json_encode($itemsData) ?>;
window.battleData = <?= json_encode($battleData) ?>;


$(document).ready(function() {
	
    $('#exitEditBtn').on('click', function() {
		if (confirm('Выйти из редактирования? Блокировка будет снята.')) {
			$.post('ajax/BattleHandler.php', {
				action: 'unlock_battle',
				battle_id: window.battleId
			}, function(res) {
				if (res.success) {
					window.location.href = 'index.php';
				} else {
					alert('Ошибка: ' + res.error);
				}
			}, 'json');
		}
	});
	
	$('#backBtn').on('click', function(e) {
		if (window.battleId && !window.isNewBattle) {
			e.preventDefault();
			
			$.post('ajax/BattleHandler.php', {
				action: 'unlock_battle',
				battle_id: window.battleId
			}, function(res) {
				window.location.href = 'index.php';
			}, 'json');
		}
	});	
	
	// Для существующего боя - рендерим здания
    if (!window.isNewBattle && window.battleId) {
        if (typeof renderBuildings === 'function') {
            renderBuildings();
            attachEvents();
            
            if ($('#enemyGuild').val()) {
                if (typeof loadEnemyPlayers === 'function') {
                    loadEnemyPlayers();
                }
            }
        }
    }
    
    // Активируем кнопку создания (только для нового боя)
    <?php if ($isNewBattle): ?>
    function checkCreateButton() {
        const date = $('#battleDate').val();
        const enemyGuild = $('#enemyGuild').val();
        
        if (date && enemyGuild) {
            $('#createBtn').prop('disabled', false);
        } else {
            $('#createBtn').prop('disabled', true);
        }
    }
    
    $('#battleDate, #enemyGuild').on('change', checkCreateButton);
    checkCreateButton();
    
    $('#createBtn').on('click', function() {
        const enemyId = $('#enemyGuild').val();
        const battleDate = $('#battleDate').val();
        
        if (!enemyId) {
            alert('Выберите гильдию противника');
            return;
        }
        
        if (!battleDate) {
            alert('Выберите дату боя');
            return;
        }
        
        $.post('ajax/BattleHandler.php', {
            action: 'create_draft',
            date: battleDate,
            enemy_guild_id: enemyId
        }, function(res) {
            if (res.success) {
                window.location.href = 'battle.php?id=' + res.battle_id;
            } else {
                alert('Ошибка: ' + res.error);
            }
        }, 'json');
    });
    <?php endif; ?>
    
});
</script>

<script src="/js/battle.js?v=2"></script>

<?php require_once 'includes/footer.php'; ?>