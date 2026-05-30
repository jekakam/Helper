<?php
require_once 'includes/config.php';

error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="training_data.csv"');

// Получаем классы всех героев
$classes = [];
$stmt = $pdo->query("SELECT id, class FROM heroes_catalog WHERE class IS NOT NULL");
while ($row = $stmt->fetch()) {
    $classes[$row['id']] = $row['class'];
}

// Получаем синергию всех героев
$synergy = [];
$stmt = $pdo->query("SELECT id, synergy FROM heroes_catalog");
while ($row = $stmt->fetch()) {
    if ($row['synergy']) {
        $synergy[$row['id']] = json_decode($row['synergy'], true);
    } else {
        $synergy[$row['id']] = [];
    }
}

// Получаем характеристики героев
$stats = [];
$stmt = $pdo->query("SELECT id, damage, defense, support, healing FROM heroes_catalog");
while ($row = $stmt->fetch()) {
    $stats[$row['id']] = [
        'damage' => (int)($row['damage'] ?? 3),
        'defense' => (int)($row['defense'] ?? 3),
        'support' => (int)($row['support'] ?? 3),
        'healing' => (int)($row['healing'] ?? 3)
    ];
}

function countSynergyPairs($heroIds, $synergyMap) {
    if (count($heroIds) != 5) return 0;
    $count = 0;
    for ($i = 0; $i < 5; $i++) {
        for ($j = $i + 1; $j < 5; $j++) {
            if (isset($synergyMap[$heroIds[$i]]) && in_array($heroIds[$j], $synergyMap[$heroIds[$i]])) {
                $count++;
            }
        }
    }
    return $count;
}

function getAvgStats($heroIds, $statsMap) {
    if (count($heroIds) != 5) return [3, 3, 3, 3];
    $sum = ['damage' => 0, 'defense' => 0, 'support' => 0, 'healing' => 0];
    foreach ($heroIds as $hid) {
        $sum['damage'] += $statsMap[$hid]['damage'];
        $sum['defense'] += $statsMap[$hid]['defense'];
        $sum['support'] += $statsMap[$hid]['support'];
        $sum['healing'] += $statsMap[$hid]['healing'];
    }
    return [
        $sum['damage'] / 5,
        $sum['defense'] / 5,
        $sum['support'] / 5,
        $sum['healing'] / 5
    ];
}

$stmt = $pdo->prepare("
    SELECT 
        bi.our_power,
        bi.enemy_power,
        bi.result,
        bi.our_heroes_json,
        bi.enemy_heroes_json
    FROM battle_items bi
    JOIN battles b ON bi.battle_id = b.id
    WHERE b.status = 'completed'
        AND b.our_guild_id = 1
        AND bi.our_heroes_json IS NOT NULL
        AND bi.enemy_heroes_json IS NOT NULL
        AND bi.our_heroes_json != '[]'
        AND bi.enemy_heroes_json != '[]'
        AND bi.result IN ('win', 'loss')
");

$stmt->execute();

$out = fopen('php://output', 'w');
fputcsv($out, [
    'our_power', 'enemy_power', 'result',
    'our_heroes_json', 'enemy_heroes_json',
    'our_classes_json', 'enemy_classes_json',
    'our_synergy', 'enemy_synergy',
    'our_avg_damage', 'our_avg_defense', 'our_avg_support', 'our_avg_healing',
    'enemy_avg_damage', 'enemy_avg_defense', 'enemy_avg_support', 'enemy_avg_healing'
]);

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    // Наша пачка
    $ourHeroes = json_decode($row['our_heroes_json'], true);
    $ourClasses = [];
    $ourSynergy = 0;
    $ourAvgStats = [3, 3, 3, 3];
    if (is_array($ourHeroes) && count($ourHeroes) == 5) {
        foreach ($ourHeroes as $hid) {
            $ourClasses[] = isset($classes[$hid]) ? $classes[$hid] : 'боец';
        }
        $ourSynergy = countSynergyPairs($ourHeroes, $synergy);
        $ourAvgStats = getAvgStats($ourHeroes, $stats);
    }
    
    // Вражеская пачка
    $enemyHeroes = json_decode($row['enemy_heroes_json'], true);
    $enemyClasses = [];
    $enemySynergy = 0;
    $enemyAvgStats = [3, 3, 3, 3];
    if (is_array($enemyHeroes) && count($enemyHeroes) == 5) {
        foreach ($enemyHeroes as $hid) {
            $enemyClasses[] = isset($classes[$hid]) ? $classes[$hid] : 'боец';
        }
        $enemySynergy = countSynergyPairs($enemyHeroes, $synergy);
        $enemyAvgStats = getAvgStats($enemyHeroes, $stats);
    }
    
    fputcsv($out, [
        $row['our_power'],
        $row['enemy_power'],
        $row['result'] == 'win' ? 1 : 0,
        $row['our_heroes_json'],
        $row['enemy_heroes_json'],
        json_encode($ourClasses, JSON_UNESCAPED_UNICODE),
        json_encode($enemyClasses, JSON_UNESCAPED_UNICODE),
        $ourSynergy,
        $enemySynergy,
        $ourAvgStats[0], $ourAvgStats[1], $ourAvgStats[2], $ourAvgStats[3],
        $enemyAvgStats[0], $enemyAvgStats[1], $enemyAvgStats[2], $enemyAvgStats[3]
    ]);
}

fclose($out);
?>