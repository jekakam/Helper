<?php
require_once 'includes/auth.php';
require_once 'includes/config.php';

$pageTitle = 'Помощник героев (VPS)';
require_once 'includes/header.php';

$VPS_URL = 'http://144.31.78.205:8000/predict';
$CLASS_ORDER = ['танк', 'боец', 'стрелок', 'маг', 'поддержка', 'контроль', 'целитель'];

// Кэш VPS
$vpsCache = [];
$cacheFile = __DIR__ . '/vps_cache.json';
if (file_exists($cacheFile)) {
    $vpsCache = json_decode(file_get_contents($cacheFile), true) ?: [];
}

// Кэши героев
$heroClassCache = [];
$heroSynergyCache = [];
$heroStatsCache = [];

function getHeroClass($id) {
    global $pdo, $heroClassCache;
    if (!isset($heroClassCache[$id])) {
        $stmt = $pdo->prepare("SELECT class FROM heroes_catalog WHERE id = ?");
        $stmt->execute([$id]);
        $heroClassCache[$id] = $stmt->fetchColumn() ?: 'боец';
    }
    return $heroClassCache[$id];
}

function getHeroSynergy($id) {
    global $pdo, $heroSynergyCache;
    if (!isset($heroSynergyCache[$id])) {
        $stmt = $pdo->prepare("SELECT synergy FROM heroes_catalog WHERE id = ?");
        $stmt->execute([$id]);
        $val = $stmt->fetchColumn();
        $heroSynergyCache[$id] = $val ? json_decode($val, true) : [];
    }
    return $heroSynergyCache[$id];
}

function getHeroStats($id) {
    global $pdo, $heroStatsCache;
    if (!isset($heroStatsCache[$id])) {
        $stmt = $pdo->prepare("SELECT damage, defense, support, healing FROM heroes_catalog WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $heroStatsCache[$id] = $row ?: ['damage' => 3, 'defense' => 3, 'support' => 3, 'healing' => 3];
    }
    return $heroStatsCache[$id];
}

function countSynergy($ids) {
    if (!is_array($ids) || count($ids) != 5) return 0;
    $c = 0;
    for ($i = 0; $i < 5; $i++) {
        $syn = getHeroSynergy($ids[$i]);
        for ($j = $i + 1; $j < 5; $j++) {
            if (in_array($ids[$j], $syn)) $c++;
        }
    }
    return $c;
}

function avgStats($ids) {
    if (count($ids) != 5) return [3, 3, 3, 3];
    $sum = ['damage' => 0, 'defense' => 0, 'support' => 0, 'healing' => 0];
    foreach ($ids as $id) {
        $s = getHeroStats($id);
        $sum['damage'] += $s['damage'];
        $sum['defense'] += $s['defense'];
        $sum['support'] += $s['support'];
        $sum['healing'] += $s['healing'];
    }
    return [$sum['damage'] / 5, $sum['defense'] / 5, $sum['support'] / 5, $sum['healing'] / 5];
}

function buildFeatures($ourIds, $ourPower, $enemyIds, $enemyPower) {
    global $CLASS_ORDER;
    
    $ourClasses = array_map('getHeroClass', $ourIds);
    $enemyClasses = array_map('getHeroClass', $enemyIds);
    
    $ourVec = array_fill(0, 7, 0);
    foreach ($ourClasses as $c) {
        $idx = array_search($c, $CLASS_ORDER);
        if ($idx !== false) $ourVec[$idx]++;
    }
    
    $enemyVec = array_fill(0, 7, 0);
    foreach ($enemyClasses as $c) {
        $idx = array_search($c, $CLASS_ORDER);
        if ($idx !== false) $enemyVec[$idx]++;
    }
    
    $ourSyn = countSynergy($ourIds);
    $enemySyn = countSynergy($enemyIds);
    $ourSt = avgStats($ourIds);
    $enemySt = avgStats($enemyIds);
    
    return [
        (float)$ourPower, (float)$ourPower / 5,
        ...$ourVec,
        (float)$ourSyn,
        $ourSt[0], $ourSt[1], $ourSt[2], $ourSt[3],
        (float)$enemyPower, (float)$enemyPower / 5,
        ...$enemyVec,
        (float)$enemySyn,
        $enemySt[0], $enemySt[1], $enemySt[2], $enemySt[3],
        (float)$ourPower - (float)$enemyPower,
        (float)$ourSyn - (float)$enemySyn
    ];
}

function callVps($features) {
    global $VPS_URL, $vpsCache, $cacheFile;
    
    $cacheKey = md5(json_encode($features));
    if (isset($vpsCache[$cacheKey])) {
        return $vpsCache[$cacheKey];
    }
    
    $ch = curl_init($VPS_URL);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['features' => $features]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $prob = 0.5;
    if ($code == 200 && $resp) {
        $data = json_decode($resp, true);
        $prob = $data['win_probability'] ?? 0.5;
    }
    
    $vpsCache[$cacheKey] = $prob;
    file_put_contents($cacheFile, json_encode($vpsCache));
    
    return $prob;
}

// Находим черновик
$stmt = $pdo->prepare("SELECT b.*, eg.name as enemy_name FROM battles b LEFT JOIN guilds eg ON b.enemy_guild_id = eg.id WHERE b.status = 'draft' AND b.our_guild_id = 1 ORDER BY b.battle_date DESC LIMIT 1");
$stmt->execute();
$draft = $stmt->fetch();

if (!$draft) {
    echo '<div class="alert alert-warning">Нет черновика</div>';
    require_once 'includes/footer.php';
    exit;
}

// Геройские здания
$buildings = $pdo->query("SELECT name FROM buildings WHERE unit_type = 'hero' ORDER BY sort_order")->fetchAll();

// Данные черновика
$stmt = $pdo->prepare("SELECT * FROM battle_items WHERE battle_id = ? ORDER BY slot_index");
$stmt->execute([$draft['id']]);
$items = $stmt->fetchAll();
$itemsByBuilding = [];
foreach ($items as $item) {
    $itemsByBuilding[$item['building_name']] = $item;
}

// Текущие назначения
$assigned = [];
foreach ($items as $item) {
    if (!empty($item['our_player_nick'])) {
        $assigned[$item['our_player_nick']] = ($assigned[$item['our_player_nick']] ?? 0) + 1;
    }
}

// Все активные игроки
$allPlayers = $pdo->query("SELECT nickname, specialization FROM players WHERE guild_id = 1 AND is_active = 1 ORDER BY nickname")->fetchAll();

// Собираем открытые слоты (где нет нашего игрока и нет результата)
$openSlots = [];
foreach ($buildings as $b) {
    $item = $itemsByBuilding[$b['name']] ?? null;
    if (!$item) continue;
    if (!empty($item['our_player_nick'])) continue;
    if (!empty($item['result'])) continue;
    
    $enemyHeroes = json_decode($item['enemy_heroes_json'] ?? '[]', true);
    if (count($enemyHeroes) != 5) continue;
    
    $openSlots[] = [
        'building' => $b['name'],
        'enemy_power' => (int)$item['enemy_power'],
        'enemy_heroes' => $enemyHeroes
    ];
}

// Сортируем слоты от самого сильного врага к слабому
usort($openSlots, fn($a, $b) => $b['enemy_power'] - $a['enemy_power']);

// Жадное распределение
$slotAssignments = [];
$playerUsage = $assigned;

foreach ($openSlots as $slot) {
    $bestPlayer = null;
    $bestProb = 0;
    $bestHeroes = [];
    $bestPower = 0;
    
    foreach ($allPlayers as $p) {
        // Проверяем лимит
        $used = $playerUsage[$p['nickname']] ?? 0;
        if ($used >= 2) continue;
        
        // Проверяем специализацию
        if (!in_array($p['specialization'], ['anti_hero', 'universal'])) continue;
        
        // Последняя пачка игрока
        $lastStmt = $pdo->prepare("SELECT our_heroes_json, our_power FROM battle_items WHERE our_player_nick = ? AND our_heroes_json IS NOT NULL AND our_heroes_json != '[]' ORDER BY id DESC LIMIT 1");
        $lastStmt->execute([$p['nickname']]);
        $last = $lastStmt->fetch();
        if (!$last) continue;
        
        $ourHeroes = json_decode($last['our_heroes_json'], true);
        if (count($ourHeroes) != 5) continue;
        
        $features = buildFeatures($ourHeroes, (int)$last['our_power'], $slot['enemy_heroes'], $slot['enemy_power']);
        $prob = callVps($features);
        
        if ($prob > $bestProb) {
            $bestProb = $prob;
            $bestPlayer = $p;
            $bestHeroes = $ourHeroes;
            $bestPower = (int)$last['our_power'];
        }
    }
    
    if ($bestPlayer) {
        $slotAssignments[$slot['building']] = [
            'player' => $bestPlayer,
            'prob' => $bestProb,
            'heroes' => $bestHeroes,
            'power' => $bestPower
        ];
        $playerUsage[$bestPlayer['nickname']] = ($playerUsage[$bestPlayer['nickname']] ?? 0) + 1;
    }
}

// Группировка зданий для вывода
$groups = [];
foreach ($buildings as $b) {
    $base = preg_replace('/\d+$/', '', $b['name']);
    $base = rtrim($base, '1234567890');
    $groups[$base][] = $b['name'];
}
?>

<style>
.card { margin-bottom: 20px; }
.card-header { background: #6c757d; color: white; font-weight: bold; }
.slot-card { border: 1px solid #ddd; border-radius: 8px; padding: 12px; margin-bottom: 12px; background: #fff; }
.enemy-name { font-weight: bold; color: #dc3545; }
.enemy-power { font-size: 0.85rem; color: #6c757d; }
.recommend { margin-top: 10px; padding: 10px; background: #d4edda; border-radius: 6px; }
.prob-high { background: #28a745; color: white; padding: 2px 8px; border-radius: 12px; font-size: 0.8rem; }
.btn-sm { padding: 4px 12px; font-size: 0.75rem; }
</style>

<div class="container">
    <h3>🤖 Помощник героев (VPS)</h3>
    <p>📅 <?= date('d.m.Y', strtotime($draft['battle_date'])) ?> | 🛡️ vs <?= htmlspecialchars($draft['enemy_name'] ?? '?') ?></p>
    <p class="text-muted small">⚠️ Назначенные игроки: <?= count($assigned) ?>/15 | Лимит: 2 боя на игрока</p>
    
    <?php foreach ($groups as $groupName => $buildingNames): ?>
        <div class="card">
            <div class="card-header">🏢 <?= htmlspecialchars($groupName) ?></div>
            <div class="card-body">
                <?php foreach ($buildingNames as $bname):
                    $item = $itemsByBuilding[$bname] ?? null;
                    if (!$item || empty($item['enemy_player_nick'])) continue;
                    
                    $enemyHeroes = json_decode($item['enemy_heroes_json'] ?? '[]', true);
                    if (count($enemyHeroes) != 5) continue;
                    
                    $hasOur = !empty($item['our_player_nick']);
                    $isDone = !empty($item['result']);
                    $assignment = $slotAssignments[$bname] ?? null;
                ?>
                    <div class="slot-card">
                        <div><strong><?= htmlspecialchars($bname) ?></strong></div>
                        <div class="enemy-name">👤 <?= htmlspecialchars($item['enemy_player_nick']) ?> <span class="enemy-power">(⚡ <?= $item['enemy_power'] ?>)</span></div>
                        
                        <?php if ($hasOur || $isDone): ?>
                            <div class="mt-2">
                                ✅ Наш: <strong><?= htmlspecialchars($item['our_player_nick'] ?? '—') ?></strong> (⚡ <?= $item['our_power'] ?? 0 ?>)
                                <?php if ($isDone): ?>
                                    <span class="badge bg-secondary"><?= $item['result'] ?></span>
                                <?php endif; ?>
                            </div>
                        <?php elseif ($assignment): 
                            $pct = round($assignment['prob'] * 100);
                            $color = $assignment['prob'] >= 0.7 ? '#28a745' : ($assignment['prob'] >= 0.5 ? '#ffc107' : '#dc3545');
                        ?>
                            <div class="recommend">
                                <strong>⭐ Рекомендация:</strong> 
                                <strong><?= htmlspecialchars($assignment['player']['nickname']) ?></strong>
                                <span class="badge bg-secondary"><?= $assignment['player']['specialization'] ?></span>
                                <span class="badge" style="background: <?= $color ?>;">🎯 <?= $pct ?>% победы</span>
                                <div class="small text-muted mt-1">⚡ <?= $assignment['power'] ?> силы</div>
                                <button class="btn btn-sm btn-success mt-2" onclick="assign(<?= $draft['id'] ?>, '<?= htmlspecialchars($bname) ?>', '<?= htmlspecialchars($assignment['player']['nickname']) ?>', <?= json_encode($assignment['heroes']) ?>, <?= $assignment['power'] ?>)">
                                    ✅ Назначить
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="text-muted mt-2">❌ Нет доступных игроков</div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<script>
function assign(battleId, buildingName, nick, heroes, power) {
    $.getJSON('ajax/BattleHandler.php', { action: 'load_battle', battle_id: battleId }, function(r) {
        if (r.success) {
            for (let i of r.items) {
                if (i.building_name === buildingName) {
                    i.our_player_nick = nick;
                    i.our_heroes_json = JSON.stringify(heroes);
                    i.our_power = power;
                    i.result = null;
                    break;
                }
            }
            $.post('ajax/BattleHandler.php', {
                action: 'save_battle',
                battle_id: battleId,
                our_score: r.battle.our_score || 0,
                enemy_score: r.battle.enemy_score || 0,
                items: JSON.stringify(r.items),
                complete: false
            }, function(res) {
                if (res.success) location.reload();
                else alert('Ошибка: ' + (res.error || '?'));
            });
        }
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>