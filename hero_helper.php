<?php
// hero_helper.php

require_once 'includes/auth.php';
require_once 'includes/config.php';

$pageTitle = 'Помощник для героев';
require_once 'includes/header.php';

// Находим активный черновик
$stmt = $pdo->prepare("
    SELECT b.*, eg.name as enemy_guild_name
    FROM battles b
    LEFT JOIN guilds eg ON b.enemy_guild_id = eg.id
    WHERE b.status = 'draft' AND b.our_guild_id = 1
    ORDER BY b.battle_date DESC
    LIMIT 1
");
$stmt->execute();
$draftBattle = $stmt->fetch();

if (!$draftBattle) {
    echo '<div class="container mt-5">
            <div class="alert alert-warning text-center">
                <h4><i class="fas fa-exclamation-triangle"></i> Нет активного черновика</h4>
                <p>Для работы помощника необходимо создать черновик боя.</p>
                <a href="battle.php?id=0" class="btn btn-primary mt-3">Создать бой</a>
            </div>
          </div>';
    require_once 'includes/footer.php';
    exit;
}

function normalizeHeroCombo($heroesArray) {
    if (!is_array($heroesArray)) return '';
    $sorted = $heroesArray;
    sort($sorted, SORT_NUMERIC);
    return implode(',', $sorted);
}

// Получаем все ГЕРОЙСКИЕ здания
$buildings = $pdo->query("SELECT name, unit_type FROM buildings WHERE unit_type = 'hero' OR unit_type IS NULL ORDER BY sort_order")->fetchAll();

// Получаем данные черновика
$stmt = $pdo->prepare("SELECT * FROM battle_items WHERE battle_id = ? ORDER BY slot_index");
$stmt->execute([$draftBattle['id']]);
$allItems = $stmt->fetchAll();

if (empty($allItems)) {
    echo '<div class="container mt-5">
            <div class="alert alert-info text-center">
                <h4><i class="fas fa-info-circle"></i> Черновик пуст</h4>
                <a href="battle.php?id=' . $draftBattle['id'] . '" class="btn btn-primary mt-3">Перейти к боевому листу</a>
            </div>
          </div>';
    require_once 'includes/footer.php';
    exit;
}

$itemsByBuilding = [];
foreach ($allItems as $item) {
    $itemsByBuilding[$item['building_name']] = $item;
}

// Подсчёт уже назначенных игроков в этом черновике
$assignedCount = [];
foreach ($allItems as $item) {
    if (!empty($item['our_player_nick'])) {
        $nick = $item['our_player_nick'];
        $assignedCount[$nick] = ($assignedCount[$nick] ?? 0) + 1;
    }
}

// Группируем здания
$groupedBuildings = [];
foreach ($buildings as $building) {
    $baseName = preg_replace('/\d+$/', '', $building['name']);
    $baseName = rtrim($baseName, '1234567890');
    if (!isset($groupedBuildings[$baseName])) {
        $groupedBuildings[$baseName] = [];
    }
    $groupedBuildings[$baseName][] = $building;
}
?>

<style>
.helper-container { max-width: 1400px; margin: 0 auto; }
.battle-info { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); color: white; border-radius: 16px; padding: 20px; margin-bottom: 24px; }
.building-card { background: white; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); overflow: hidden; }
.building-header { background: #c8d4d8; padding: 12px 16px; font-weight: 600; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; }
.battle-row { padding: 16px; display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px; border-bottom: 1px solid #eee; }
.vs { font-size: 1.2rem; font-weight: bold; color: #6c757d; min-width: 60px; text-align: center; }
.enemy { text-align: right; flex: 1; }
.our { text-align: left; flex: 1; }
.player-name { font-weight: 700; font-size: 1rem; }
.player-power { font-size: 0.8rem; color: #6c757d; }
.hero-mini { width: 32px; height: 32px; object-fit: cover; border-radius: 6px; margin-right: 4px; }
.hero-combo { display: flex; flex-wrap: wrap; gap: 4px; align-items: center; margin-top: 6px; }
.enemy .hero-combo { justify-content: flex-end; }
.our .hero-combo { justify-content: flex-start; }
.recommend-block { background: #d4edda; border-radius: 10px; padding: 12px; margin-top: 8px; }
.warning-block { background: #f8d7da; border-radius: 10px; padding: 12px; margin-top: 8px; }
.found-badge { background: #28a745; color: white; padding: 2px 8px; border-radius: 12px; font-size: 0.7rem; display: inline-block; margin-bottom: 8px; }
.counter-player { background: white; border-radius: 8px; padding: 8px 12px; margin-bottom: 8px; }
.no-data { background: #f8f9fa; border-radius: 10px; padding: 12px; text-align: center; color: #6c757d; }
@media (max-width: 768px) { .battle-row { flex-direction: column; text-align: center; } .enemy, .our { text-align: center; } .hero-combo { justify-content: center !important; } }
</style>

<div class="container helper-container">
    <div class="battle-info">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h3><i class="fas fa-user-ninja"></i> Помощник для героев</h3>
                <p><?= date('d.m.Y', strtotime($draftBattle['battle_date'])) ?> | Противник: <?= htmlspecialchars($draftBattle['enemy_guild_name'] ?? '—') ?></p>
            </div>
            <a href="battle.php?id=<?= $draftBattle['id'] ?>" class="btn btn-light btn-sm">К боевому листу</a>
        </div>
    </div>

    <?php foreach ($groupedBuildings as $baseName => $buildingsGroup): ?>
        <div class="building-card">
            <div class="building-header">
                <span><i class="fas fa-building"></i> <?= htmlspecialchars($baseName) ?> <span class="badge bg-secondary ms-1"><?= count($buildingsGroup) ?></span></span>
            </div>
            
            <?php foreach ($buildingsGroup as $building):
                $draftItem = $itemsByBuilding[$building['name']] ?? null;
                if (!$draftItem || empty($draftItem['enemy_player_nick'])) continue;
                
                $hasOurPlayer = !empty($draftItem['our_player_nick']);
                $isCompleted = !empty($draftItem['result']);
                $enemyPower = (int)($draftItem['enemy_power'] ?? 0);
                
                // Вражеские герои
                $enemyHeroes = [];
                $enemyHeroesJson = isset($draftItem['enemy_heroes_json']) ? $draftItem['enemy_heroes_json'] : null;
                if ($enemyHeroesJson && $enemyHeroesJson !== '' && $enemyHeroesJson !== '[]' && $enemyHeroesJson !== 'null') {
                    $decoded = json_decode($enemyHeroesJson, true);
                    if (is_array($decoded)) {
                        $enemyHeroes = $decoded;
                    }
                }
                
                $enemyHeroesList = [];
                if (!empty($enemyHeroes)) {
                    $placeholders = implode(',', array_fill(0, count($enemyHeroes), '?'));
                    $heroStmt = $pdo->prepare("SELECT id, name, image FROM heroes_catalog WHERE id IN ($placeholders)");
                    $heroStmt->execute($enemyHeroes);
                    $enemyHeroesList = $heroStmt->fetchAll();
                }
                
                // Наши герои если уже назначены
                $ourHeroesList = [];
                $ourHeroesJson = isset($draftItem['our_heroes_json']) ? $draftItem['our_heroes_json'] : null;
                if ($ourHeroesJson && $ourHeroesJson !== '' && $ourHeroesJson !== '[]' && $ourHeroesJson !== 'null') {
                    $ourHeroIds = json_decode($ourHeroesJson, true);
                    if (is_array($ourHeroIds) && !empty($ourHeroIds)) {
                        $placeholders = implode(',', array_fill(0, count($ourHeroIds), '?'));
                        $heroStmt = $pdo->prepare("SELECT id, name, image FROM heroes_catalog WHERE id IN ($placeholders)");
                        $heroStmt->execute($ourHeroIds);
                        $ourHeroesList = $heroStmt->fetchAll();
                    }
                }
                
                $result = $draftItem['result'] ?? null;
                $resultText = '';
                $resultClass = '';
                if ($isCompleted) {
                    switch($result) {
                        case 'win': $resultText = 'Победа'; $resultClass = 'text-success'; break;
                        case 'loss': $resultText = 'Поражение'; $resultClass = 'text-danger'; break;
                        case 'skip': $resultText = 'Пропуск'; $resultClass = 'text-secondary'; break;
                    }
                }
                
                // ПОИСК АНТИСБОРКИ
                $playersData = [];
                
                if (count($enemyHeroes) === 5 && !$hasOurPlayer && !$isCompleted) {
                    $targetCombo = normalizeHeroCombo($enemyHeroes);
                    
                    $sql = "
                        SELECT 
                            bi.our_player_nick,
                            bi.our_power,
                            bi.our_heroes_json,
                            bi.enemy_power,
                            bi.enemy_player_nick,
                            bi.result,
                            b.battle_date,
                            eg.name as enemy_guild_name,
                            bi.enemy_heroes_json
                        FROM battle_items bi
                        JOIN battles b ON bi.battle_id = b.id
                        LEFT JOIN guilds eg ON b.enemy_guild_id = eg.id
                        WHERE b.status = 'completed'
                            AND b.our_guild_id = 1
                            AND bi.result IN ('win', 'loss')
                            AND bi.enemy_heroes_json IS NOT NULL
                            AND bi.enemy_heroes_json != '[]'
                            AND bi.enemy_heroes_json != 'null'
                        ORDER BY b.battle_date DESC, bi.id DESC
                    ";
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute();
                    $allRows = $stmt->fetchAll();
                    
                    $playersData = [];
                    
                    foreach ($allRows as $row) {
                        $enemyComboJson = $row['enemy_heroes_json'] ?? null;
                        if (!$enemyComboJson) continue;
                        
                        $enemyCombo = json_decode($enemyComboJson, true);
                        if (!is_array($enemyCombo) || count($enemyCombo) != 5) continue;
                        
                        $dbCombo = normalizeHeroCombo($enemyCombo);
                        
                        if ($dbCombo !== $targetCombo) continue;
                        
                        $ourHeroIds = json_decode($row['our_heroes_json'] ?? '[]', true);
                        if (!is_array($ourHeroIds)) $ourHeroIds = [];
                        
                        $ourComboKey = !empty($ourHeroIds) ? normalizeHeroCombo($ourHeroIds) : '';
                        
                        $player = $row['our_player_nick'];
                        $power = (int)($row['our_power'] ?? 0);
                        $rowResult = $row['result'];
                        
                        if (!isset($playersData[$player])) {
                            $playersData[$player] = [
                                'wins' => [],
                                'losses' => []
                            ];
                        }
                        
                        if ($rowResult === 'win') {
                            if (!isset($playersData[$player]['wins'][$ourComboKey]) || $power > $playersData[$player]['wins'][$ourComboKey]['power']) {
                                $playersData[$player]['wins'][$ourComboKey] = [
                                    'power' => $power,
                                    'hero_ids' => $ourHeroIds,
                                    'enemy_power' => (int)($row['enemy_power'] ?? 0),
                                    'date' => date('d.m.Y', strtotime($row['battle_date'])),
                                    'guild' => $row['enemy_guild_name'] ?? '—'
                                ];
                            }
                        } elseif ($rowResult === 'loss') {
                            if (!isset($playersData[$player]['losses'][$ourComboKey]) || $power > $playersData[$player]['losses'][$ourComboKey]['power']) {
                                $playersData[$player]['losses'][$ourComboKey] = [
                                    'power' => $power,
                                    'hero_ids' => $ourHeroIds,
                                    'enemy_power' => (int)($row['enemy_power'] ?? 0),
                                    'date' => date('d.m.Y', strtotime($row['battle_date'])),
                                    'guild' => $row['enemy_guild_name'] ?? '—'
                                ];
                            }
                        }
                    }
                }
            ?>
                <div class="battle-row">
                    <div class="enemy">
                        <div class="player-name text-danger"><?= htmlspecialchars($draftItem['enemy_player_nick']) ?></div>
                        <div class="player-power"><i class="fas fa-bolt"></i> <?= $enemyPower ?></div>
                        <?php if (!empty($enemyHeroesList)): ?>
                            <div class="hero-combo">
                                <?php foreach ($enemyHeroesList as $hero): ?>
                                    <img src="<?= htmlspecialchars($hero['image'] ?? '') ?>" class="hero-mini" title="<?= htmlspecialchars($hero['name']) ?>">
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="vs">
                        <?php if ($isCompleted): ?>
                            <span title="<?= $resultText ?>" class="<?= $resultClass ?>"><i class="fas <?= $result === 'win' ? 'fa-trophy' : ($result === 'loss' ? 'fa-skull' : 'fa-forward') ?>"></i></span>
                        <?php else: ?>
                            VS
                        <?php endif; ?>
                    </div>
                    
                    <div class="our">
                        <?php if ($hasOurPlayer && !$isCompleted): ?>
                            <div class="player-name text-primary"><?= htmlspecialchars($draftItem['our_player_nick']) ?></div>
                            <div class="player-power"><i class="fas fa-bolt"></i> <?= (int)($draftItem['our_power'] ?? 0) ?></div>
                            <?php if (!empty($ourHeroesList)): ?>
                                <div class="hero-combo">
                                    <?php foreach ($ourHeroesList as $hero): ?>
                                        <img src="<?= htmlspecialchars($hero['image'] ?? '') ?>" class="hero-mini" title="<?= htmlspecialchars($hero['name']) ?>">
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        <?php elseif ($isCompleted): ?>
                            <div class="player-name text-primary"><?= htmlspecialchars($draftItem['our_player_nick'] ?? '—') ?></div>
                            <div class="player-power"><i class="fas fa-bolt"></i> <?= (int)($draftItem['our_power'] ?? 0) ?></div>
                            <?php if (!empty($ourHeroesList)): ?>
                                <div class="hero-combo">
                                    <?php foreach ($ourHeroesList as $hero): ?>
                                        <img src="<?= htmlspecialchars($hero['image'] ?? '') ?>" class="hero-mini" title="<?= htmlspecialchars($hero['name']) ?>">
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        <?php elseif (!empty($playersData)): 
                            // Отфильтровываем игроков, которые уже сделали 2 боя
                            $availablePlayers = [];
                            foreach ($playersData as $player => $data) {
                                $currentAssigned = $assignedCount[$player] ?? 0;
                                if ($currentAssigned < 2) {
                                    $availablePlayers[$player] = $data;
                                }
                            }
                            
                            if (empty($availablePlayers)): ?>
                                <div class="no-data">
                                    <i class="fas fa-info-circle"></i> Все победители уже сделали 2 боя в этом черновике
                                </div>
                            <?php else: ?>
                                <div class="recommend-block">
                                    <div class="found-badge mb-2"><i class="fas fa-check-circle"></i> Побеждали эту сборку:</div>
                                    <?php 
                                    $hasWins = false;
                                    foreach ($availablePlayers as $player => $data): 
                                        if (!empty($data['wins'])):
                                            $hasWins = true;
                                    ?>
                                        <div class="counter-player mb-2">
                                            <div class="fw-bold mb-1"><?= htmlspecialchars($player) ?></div>
                                            <?php foreach ($data['wins'] as $comboKey => $winData): ?>
                                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-1 ps-2">
                                                    <div>
                                                        <span class="text-muted"><i class="fas fa-bolt"></i> <?= $winData['power'] ?></span>
                                                        <span class="ms-1">
                                                            (<?= $winData['enemy_power'] ?>)
                                                        </span>
                                                        <?php if (!empty($winData['hero_ids'])): ?>
                                                            <div class="hero-combo mt-1">
                                                                <?php 
                                                                $heroIds = $winData['hero_ids'];
                                                                if (!empty($heroIds)) {
                                                                    $placeholders = implode(',', array_fill(0, count($heroIds), '?'));
                                                                    $heroStmt = $pdo->prepare("SELECT id, name, image FROM heroes_catalog WHERE id IN ($placeholders)");
                                                                    $heroStmt->execute($heroIds);
                                                                    $heroes = $heroStmt->fetchAll();
                                                                    foreach ($heroes as $hero): ?>
                                                                        <img src="<?= htmlspecialchars($hero['image'] ?? '') ?>" class="hero-mini" title="<?= htmlspecialchars($hero['name']) ?>">
                                                                    <?php endforeach;
                                                                } ?>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div class="small text-muted mt-1">
                                                            <i class="fas fa-calendar-alt"></i> <?= $winData['date'] ?? '—' ?> | <i class="fas fa-flag"></i> <?= htmlspecialchars($winData['guild'] ?? '—') ?>
                                                        </div>
                                                    </div>
                                                    <button class="btn btn-sm btn-success" onclick="assignHero(<?= $draftBattle['id'] ?>, '<?= htmlspecialchars($building['name']) ?>', '<?= htmlspecialchars($player) ?>', <?= json_encode($winData['hero_ids']) ?>, <?= $winData['power'] ?>)">
                                                        <i class="fas fa-check"></i> Назначить
                                                    </button>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php 
                                        endif;
                                    endforeach; 
                                    if (!$hasWins): ?>
                                        <div class="text-muted small">Нет побед</div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php 
                            // Проигравшие тоже фильтруем
                            $availableLosers = [];
                            foreach ($playersData as $player => $data) {
                                $currentAssigned = $assignedCount[$player] ?? 0;
                                if ($currentAssigned < 2 && !empty($data['losses'])) {
                                    $availableLosers[$player] = $data;
                                }
                            }
                            
                            if (!empty($availableLosers)): 
                            ?>
                                <div class="warning-block mt-2">
                                    <div class="small text-danger mb-2"><i class="fas fa-exclamation-triangle"></i> Проигрывали этой сборке:</div>
                                    <?php foreach ($availableLosers as $player => $data): ?>
                                        <div class="counter-player mb-2">
                                            <div class="fw-bold mb-1"><?= htmlspecialchars($player) ?></div>
                                            <?php foreach ($data['losses'] as $comboKey => $lossData): ?>
                                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-1 ps-2">
                                                    <div>
                                                        <span class="text-muted"><i class="fas fa-bolt"></i> <?= $lossData['power'] ?></span>
                                                        <span class="ms-1">
                                                            (<?= $lossData['enemy_power'] ?>)
                                                        </span>
                                                        <?php if (!empty($lossData['hero_ids'])): ?>
                                                            <div class="hero-combo mt-1">
                                                                <?php 
                                                                $heroIds = $lossData['hero_ids'];
                                                                if (!empty($heroIds)) {
                                                                    $placeholders = implode(',', array_fill(0, count($heroIds), '?'));
                                                                    $heroStmt = $pdo->prepare("SELECT id, name, image FROM heroes_catalog WHERE id IN ($placeholders)");
                                                                    $heroStmt->execute($heroIds);
                                                                    $heroes = $heroStmt->fetchAll();
                                                                    foreach ($heroes as $hero): ?>
                                                                        <img src="<?= htmlspecialchars($hero['image'] ?? '') ?>" class="hero-mini" title="<?= htmlspecialchars($hero['name']) ?>">
                                                                    <?php endforeach;
                                                                } ?>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div class="small text-muted mt-1">
                                                            <i class="fas fa-calendar-alt"></i> <?= $lossData['date'] ?? '—' ?> | <i class="fas fa-flag"></i> <?= htmlspecialchars($lossData['guild'] ?? '—') ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                        <?php elseif (count($enemyHeroes) === 5): ?>
                            <div class="no-data">
                                <i class="fas fa-search"></i> Нет записей о боях с этой сборкой
                            </div>
                        <?php else: ?>
                            <div class="no-data">
                                <i class="fas fa-exclamation-triangle"></i> Нет данных о героях противника
                                <div class="small mt-1"><a href="battle.php?id=<?= $draftBattle['id'] ?>">Заполните сборку</a></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
</div>

<script>
function assignHero(battleId, buildingName, playerNick, heroesArray, power) {
    $.ajax({
        url: 'ajax/BattleHandler.php',
        type: 'GET',
        data: { action: 'load_battle', battle_id: battleId },
        dataType: 'json',
        success: function(r) {
            if (r.success) {
                for (let i of r.items) {
                    if (i.building_name === buildingName) {
                        i.our_player_nick = playerNick;
                        i.our_heroes_json = JSON.stringify(heroesArray);
                        i.our_power = power;
                        i.result = null;
                        break;
                    }
                }
                $.ajax({
                    url: 'ajax/BattleHandler.php',
                    type: 'POST',
                    data: {
                        action: 'save_battle',
                        battle_id: battleId,
                        our_score: r.battle.our_score || 0,
                        enemy_score: r.battle.enemy_score || 0,
                        items: JSON.stringify(r.items),
                        complete: false
                    },
                    dataType: 'json',
                    success: function(res) {
                        if (res.success) location.reload();
                        else alert('Ошибка: ' + (res.error || '?'));
                    }
                });
            }
        }
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>