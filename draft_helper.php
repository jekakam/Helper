<?php
require_once 'includes/auth.php';
require_once 'includes/config.php';

$pageTitle = 'Помощник для черновика';
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
                <h4>Нет активного черновика</h4>
                <a href="battle.php?id=0" class="btn btn-primary">Создать бой</a>
            </div>
          </div>';
    require_once 'includes/footer.php';
    exit;
}

// Получаем здания
$buildings = $pdo->query("SELECT name, unit_type FROM buildings ORDER BY sort_order")->fetchAll();

// Получаем данные черновика
$stmt = $pdo->prepare("SELECT * FROM battle_items WHERE battle_id = ? ORDER BY slot_index");
$stmt->execute([$draftBattle['id']]);
$draftItems = $stmt->fetchAll();

$itemsByBuilding = [];
foreach ($draftItems as $item) {
    $itemsByBuilding[$item['building_name']] = $item;
}

// Получаем всех игроков Imperial
$players = $pdo->query("SELECT nickname, specialization FROM players WHERE guild_id = 1 ORDER BY nickname")->fetchAll();

// Стихии титанов
$titans = $pdo->query("SELECT id, name, color FROM titan_elements")->fetchAll();
$titanColors = [];
$titanNames = [];
foreach ($titans as $t) {
    $titanColors[$t['id']] = $t['color'];
    $titanNames[$t['id']] = $t['name'];
}
?>

<style>
.helper-container { max-width: 1400px; margin: 0 auto; }
.battle-info { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); color: white; border-radius: 16px; padding: 20px; margin-bottom: 24px; }
.building-card { background: white; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); overflow: hidden; }
.building-header { background: #c8d4d8; padding: 12px 16px; font-weight: 600; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; }
.battle-row { padding: 16px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; border-bottom: 1px solid #eee; }
.vs { font-size: 1.2rem; font-weight: bold; color: #6c757d; }
.enemy { text-align: right; }
.our { text-align: left; }
.player-name { font-weight: 700; font-size: 1.1rem; }
.player-power { font-size: 0.8rem; color: #6c757d; }
.titan-badge { display: inline-block; padding: 2px 8px; border-radius: 15px; font-size: 0.7rem; color: white; }
.hero-mini { width: 32px; height: 32px; object-fit: cover; border-radius: 6px; margin-right: 4px; }
.hero-combo { display: flex; flex-wrap: wrap; gap: 4px; align-items: center; margin-top: 6px; }
.recommend-block { background: #f8f9fa; border-radius: 10px; padding: 12px; margin-top: 8px; }
.recommend-title { font-weight: 600; margin-bottom: 10px; font-size: 0.85rem; }
.player-row { display: flex; justify-content: space-between; align-items: center; padding: 8px 12px; background: white; border-radius: 8px; margin-bottom: 6px; border: 1px solid #e0e0e0; }
.btn-sm { padding: 4px 12px; font-size: 0.75rem; }
.history-note { font-size: 0.7rem; color: #6c757d; margin-top: 6px; padding-top: 6px; border-top: 1px dashed #ddd; }
@media (max-width: 768px) { .battle-row { flex-direction: column; text-align: center; } .enemy, .our { text-align: center; } }
</style>

<div class="container helper-container">
    <div class="battle-info">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h3><i class="fas fa-pencil-alt"></i> Черновик боя</h3>
                <p><?= date('d.m.Y', strtotime($draftBattle['battle_date'])) ?> | Противник: <?= htmlspecialchars($draftBattle['enemy_guild_name'] ?? '—') ?></p>
            </div>
            <a href="battle.php?id=<?= $draftBattle['id'] ?>" class="btn btn-light btn-sm">Редактировать</a>
        </div>
    </div>

    <?php foreach ($buildings as $building):
        $draftItem = $itemsByBuilding[$building['name']] ?? null;
        $isTitan = ($building['unit_type'] === 'titan');
        
        if (!$draftItem || empty($draftItem['enemy_player_nick'])) continue;
        
        $hasOurPlayer = !empty($draftItem['our_player_nick']);
    ?>
        <div class="building-card">
            <div class="building-header">
                <span>
                    <i class="fas fa-building"></i> <?= htmlspecialchars($building['name']) ?>
                    <span class="badge ms-2"><?= $isTitan ? 'Титаны' : 'Герои' ?></span>
                </span>
                <?php if (!empty($draftItem['result'])): ?>
                    <span class="badge <?= $draftItem['result'] === 'win' ? 'bg-success' : ($draftItem['result'] === 'loss' ? 'bg-danger' : 'bg-secondary') ?>">
                        <?= $draftItem['result'] === 'win' ? 'Победа' : ($draftItem['result'] === 'loss' ? 'Поражение' : 'Пропуск') ?>
                    </span>
                <?php endif; ?>
            </div>
            
            <div class="battle-row">
                <div class="enemy">
                    <div class="player-name text-danger"><?= htmlspecialchars($draftItem['enemy_player_nick']) ?></div>
                    <div class="player-power">⚡ <?= (int)($draftItem['enemy_power'] ?? 0) ?></div>
                    <?php if ($isTitan && !empty($draftItem['enemy_titan_element_id'])): ?>
                        <div><span class="titan-badge" style="background: <?= $titanColors[$draftItem['enemy_titan_element_id']] ?? '#808080' ?>"><?= $titanNames[$draftItem['enemy_titan_element_id']] ?? '?' ?></span></div>
                    <?php endif; ?>
                </div>
                
                <div class="vs">VS</div>
                
                <div class="our">
                    <?php if ($hasOurPlayer): ?>
                        <div class="player-name text-primary"><?= htmlspecialchars($draftItem['our_player_nick']) ?></div>
                        <div class="player-power">⚡ <?= (int)($draftItem['our_power'] ?? 0) ?></div>
                        <?php if ($isTitan && !empty($draftItem['our_titan_element_id'])): ?>
                            <div><span class="titan-badge" style="background: <?= $titanColors[$draftItem['our_titan_element_id']] ?? '#808080' ?>"><?= $titanNames[$draftItem['our_titan_element_id']] ?? '?' ?></span></div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="text-muted">— не назначен —</div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (!$hasOurPlayer): ?>
                <div class="recommend-block">
                    <?php if ($isTitan): ?>
                        <div class="recommend-title">Рекомендуемые игроки (Anti-Titan)</div>
                        <?php foreach ($players as $p): 
                            if ($p['specialization'] != 'anti_titan' && $p['specialization'] != 'universal') continue;
                        ?>
                            <div class="player-row">
                                <span><?= htmlspecialchars($p['nickname']) ?> <?= $p['specialization'] === 'anti_titan' ? '⭐' : '' ?></span>
                                <button class="btn btn-sm btn-success" onclick="assignPlayer(<?= $draftBattle['id'] ?>, '<?= htmlspecialchars($building['name']) ?>', '<?= htmlspecialchars($p['nickname']) ?>')">Назначить</button>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <?php
                        // ========== ТОЛЬКО ДЛЯ ГЕРОЕВ - ПОИСК В ИСТОРИИ ==========
                        $found = null;
                        
                        // Получаем героев противника из черновика
                        $enemyHeroes = [];
                        if (array_key_exists('enemy_heroes_json', $draftItem)) {
							$val = $draftItem['enemy_heroes_json'];
							if (!is_null($val) && $val !== '' && $val !== '[]' && $val !== 'null') {
								$tmp = json_decode($val, true);
								if (is_array($tmp) && count($tmp) > 0) {
									$enemyHeroes = $tmp;
								}
							}
						}
                        
                        if (count($enemyHeroes) == 5) {
                            // Приводим искомую пачку к нормальному виду
                            $searchCombo = $enemyHeroes;
                            sort($searchCombo);
                            $searchComboStr = implode(',', $searchCombo);
                            
                            // Ищем в истории побед
                            $searchSql = "
                                SELECT 
                                    bi.our_player_nick,
                                    bi.our_power,
                                    bi.our_heroes_json,
                                    bi.enemy_power,
                                    b.battle_date,
                                    eg.name as enemy_guild
                                FROM battle_items bi
                                JOIN battles b ON bi.battle_id = b.id
                                LEFT JOIN guilds eg ON b.enemy_guild_id = eg.id
                                WHERE b.status = 'completed'
                                    AND b.our_guild_id = 1
                                    AND bi.result = 'win'
                                    AND bi.enemy_heroes_json IS NOT NULL
                                    AND bi.enemy_heroes_json != '[]'
                                ORDER BY b.battle_date DESC
                            ";
                            
                            $searchStmt = $pdo->prepare($searchSql);
                            $searchStmt->execute();
                            
                            foreach ($searchStmt->fetchAll() as $row) {
                                $rowCombo = json_decode($row['enemy_heroes_json'], true);
                                if (!is_array($rowCombo) || count($rowCombo) != 5) continue;
                                
                                sort($rowCombo);
                                $rowComboStr = implode(',', $rowCombo);
                                
                                if ($rowComboStr === $searchComboStr) {
                                    // Нашли!
                                    $heroesList = [];
                                    if (!empty($row['our_heroes_json']) && $row['our_heroes_json'] != '[]') {
                                        $heroIds = json_decode($row['our_heroes_json'], true);
                                        if (is_array($heroIds)) {
                                            $ph = implode(',', array_fill(0, count($heroIds), '?'));
                                            $heroStmt = $pdo->prepare("SELECT name, image FROM heroes_catalog WHERE id IN ($ph)");
                                            $heroStmt->execute($heroIds);
                                            $heroesList = $heroStmt->fetchAll();
                                        }
                                    }
                                    
                                    $found = [
                                        'player' => $row['our_player_nick'],
                                        'power' => $row['our_power'],
                                        'heroes' => $heroesList,
                                        'enemy_power' => $row['enemy_power'],
                                        'date' => date('d.m.Y', strtotime($row['battle_date'])),
                                        'guild' => $row['enemy_guild']
                                    ];
                                    break; // берём первую (самую свежую)
                                }
                            }
                        }
                        ?>
                        
                        <?php if ($found): ?>
                            <div class="recommend-title" style="color: #28a745;">✅ Найдена контр-сборка!</div>
                            <div class="player-row">
                                <div>
                                    <strong><?= htmlspecialchars($found['player']) ?></strong> (⚡ <?= $found['power'] ?>)
                                    <?php if (!empty($found['heroes'])): ?>
                                        <div class="hero-combo mt-1">
                                            <?php foreach ($found['heroes'] as $h): ?>
                                                <img src="<?= htmlspecialchars($h['image'] ?? '') ?>" class="hero-mini" title="<?= htmlspecialchars($h['name']) ?>">
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="history-note">Победа над <?= htmlspecialchars($found['guild'] ?? '') ?> (<?= $found['date'] ?>, сила врага: <?= $found['enemy_power'] ?>)</div>
                                </div>
                                <button class="btn btn-sm btn-success" onclick="assignHero(<?= $draftBattle['id'] ?>, '<?= htmlspecialchars($building['name']) ?>', '<?= htmlspecialchars($found['player']) ?>', <?= json_encode(array_column($found['heroes'], 'id')) ?>, <?= $found['power'] ?>)">
                                    Использовать
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="recommend-title text-muted">❌ Нет истории побед над этой сборкой</div>
                            <div class="text-muted small">Придётся подбирать вручную → <a href="battle.php?id=<?= $draftBattle['id'] ?>">редактировать бой</a></div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<script>
function assignPlayer(battleId, buildingName, playerNick) {
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