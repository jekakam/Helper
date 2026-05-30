<?php
/*
* titan_helper.php
*/


require_once 'includes/auth.php';
require_once 'includes/config.php';

$pageTitle = 'Помощник для титанов';
require_once 'includes/header.php';


$percent = 90;

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

// ПРОВЕРКА 1: Нет активного черновика
if (!$draftBattle) {
    echo '<div class="container mt-5">
            <div class="alert alert-warning text-center">
                <h4><i class="fas fa-exclamation-triangle"></i> Нет активного черновика</h4>
                <p class="mb-0">Для работы помощника необходимо создать черновик боя.</p>
                <a href="battle.php?id=0" class="btn btn-primary mt-3">Создать бой</a>
            </div>
          </div>';
    require_once 'includes/footer.php';
    exit;
}

// Получаем все ТИТАНСКИЕ здания
$buildings = $pdo->query("SELECT name, unit_type FROM buildings WHERE unit_type = 'titan' ORDER BY sort_order")->fetchAll();

// Получаем данные черновика
$stmt = $pdo->prepare("SELECT * FROM battle_items WHERE battle_id = ? ORDER BY slot_index");
$stmt->execute([$draftBattle['id']]);
$allItems = $stmt->fetchAll();

// ПРОВЕРКА 2: Черновик существует, но нет ни одной записи в battle_items
if (empty($allItems)) {
    echo '<div class="container mt-5">
            <div class="alert alert-info text-center">
                <h4><i class="fas fa-info-circle"></i> Черновик создан, но пуст</h4>
                <p class="mb-0">В боевом листе ещё нет ни одного противника. Добавьте данные о противниках в черновик.</p>
                <a href="battle.php?id=' . $draftBattle['id'] . '" class="btn btn-primary mt-3">Перейти к боевому листу</a>
            </div>
          </div>';
    require_once 'includes/footer.php';
    exit;
}

// Индексируем по названию здания
$itemsByBuilding = [];
foreach ($allItems as $item) {
    $itemsByBuilding[$item['building_name']] = $item;
}

// Получаем уже назначенных игроков (учитываем лимит 2 на человека)
$assignedCount = [];
foreach ($allItems as $item) {
    if (!empty($item['our_player_nick'])) {
        $nick = $item['our_player_nick'];
        $assignedCount[$nick] = ($assignedCount[$nick] ?? 0) + 1;
    }
}

// Получаем всех АКТИВНЫХ игроков (только anti_titan и universal)
$players = $pdo->query("
    SELECT p.nickname, p.specialization, 
           COALESCE(bi.our_power, 0) as last_power,
           bi.our_titan_element_id as last_element
    FROM players p
    LEFT JOIN (
        SELECT our_player_nick, our_power, our_titan_element_id
        FROM battle_items 
        WHERE our_player_nick IS NOT NULL AND our_titan_element_id IS NOT NULL
        ORDER BY id DESC
    ) bi ON p.nickname = bi.our_player_nick
    WHERE p.guild_id = 1 
        AND p.is_active = 1
        AND (p.specialization = 'anti_titan' OR p.specialization = 'universal')
    GROUP BY p.nickname
    ORDER BY FIELD(p.specialization, 'anti_titan', 'universal'), p.nickname
")->fetchAll();

// Формируем массив игроков для JS
$playersJson = [];
foreach ($players as $p) {
    $playersJson[] = [
        'nickname' => $p['nickname'],
        'specialization' => $p['specialization'],
        'last_power' => (int)$p['last_power'],
        'last_element' => $p['last_element']
    ];
}

// Стихии титанов
$titans = $pdo->query("SELECT id, name, color FROM titan_elements")->fetchAll();
$titanColors = [];
$titanNames = [];
foreach ($titans as $t) {
    $titanColors[$t['id']] = $t['color'];
    $titanNames[$t['id']] = $t['name'];
}

// ГРУППИРУЕМ ЗДАНИЯ ПО БАЗОВОМУ НАЗВАНИЮ
$groupedBuildings = [];
foreach ($buildings as $building) {
    $baseName = preg_replace('/\d+$/', '', $building['name']);
    $baseName = rtrim($baseName, '1234567890');
    
    if (!isset($groupedBuildings[$baseName])) {
        $groupedBuildings[$baseName] = [];
    }
    $groupedBuildings[$baseName][] = $building;
}

// Собираем данные для JS (только для пустых слотов)
$buildingsData = [];
$globalSlotIndex = 0;
$slotToRecIndex = [];

// ПРОВЕРКА 3: Считаем титановые слоты и их статусы
$totalTitanSlots = 0;
$completedTitanSlots = 0;
$filledTitanSlots = 0;
$emptyTitanSlots = 0;

foreach ($groupedBuildings as $baseName => $buildingsGroup) {
    foreach ($buildingsGroup as $building) {
        $draftItem = $itemsByBuilding[$building['name']] ?? null;
        if (!$draftItem || empty($draftItem['enemy_player_nick'])) continue;
        
        $totalTitanSlots++;
        $hasOurPlayer = !empty($draftItem['our_player_nick']);
        $result = $draftItem['result'] ?? null;
        $isCompleted = $result !== null && $result !== '';
        
        if ($isCompleted) {
            $completedTitanSlots++;
        } elseif ($hasOurPlayer) {
            $filledTitanSlots++;
        } else {
            $emptyTitanSlots++;
        }
        
        if (!$hasOurPlayer && !$isCompleted) {
            $buildingsData[] = [
                'index' => $globalSlotIndex,
                'name' => $building['name'],
                'baseName' => $baseName,
                'enemyPower' => (int)($draftItem['enemy_power'] ?? 0)
            ];
            $slotToRecIndex[$building['name']] = $globalSlotIndex;
            $globalSlotIndex++;
        }
    }
}

// ПРОВЕРКА 4: Нет титановых слотов с противниками
if ($totalTitanSlots === 0) {
    echo '<div class="container mt-5">
            <div class="alert alert-info text-center">
                <h4><i class="fas fa-info-circle"></i> Нет битв для титанов</h4>
                <p class="mb-0">В черновике не заполнены титаны противника.</p>
                <a href="battle.php?id=' . $draftBattle['id'] . '" class="btn btn-primary mt-3">Перейти к боевому листу</a>
            </div>
          </div>';
    require_once 'includes/footer.php';
    exit;
}

// ПРОВЕРКА 5: Все титановые битвы завершены
if ($completedTitanSlots === $totalTitanSlots) {
    echo '<div class="container mt-5">
            <div class="alert alert-success text-center">
                <h4><i class="fas fa-check-circle"></i> Все битвы с титанами завершены</h4>
                <p class="mb-0">Помощник больше не нужен — все результаты уже внесены.</p>
                <a href="battle.php?view=1&id=' . $draftBattle['id'] . '" class="btn btn-primary mt-3">Просмотреть результаты</a>
            </div>
          </div>';
    require_once 'includes/footer.php';
  //  exit;
}

// ПРОВЕРКА 6: Все свободные слоты уже заполнены (но не завершены)
if ($emptyTitanSlots === 0 && $completedTitanSlots < $totalTitanSlots) {
    echo '<div class="container mt-5">
            <div class="alert alert-success text-center">
                <h4><i class="fas fa-check-circle"></i> Все битвы с титанами уже назначекны!</h4>
                <p class="mb-0">Осталось только дождаться результатов.</p>
                <a href="battle.php?id=' . $draftBattle['id'] . '" class="btn btn-primary mt-3">Перейти к боевому листу</a>
            </div>
          </div>';
    require_once 'includes/footer.php';
 //   exit;
}

// ПРОВЕРКА 7: Есть пустые слоты, но нет доступных игроков
if (empty($playersJson)) {
    echo '<div class="container mt-5">
            <div class="alert alert-warning text-center">
                <h4><i class="fas fa-user-slash"></i> Нет доступных игроков</h4>
                <p class="mb-0">Нет активных игроков со специализацией "anti_titan" или "universal". Добавьте игроков в гильдию.</p>
                <a href="players.php?guild_id=1" class="btn btn-primary mt-3">Управление игроками</a>
            </div>
          </div>';
    require_once 'includes/footer.php';
    exit;
}
?>

<div class="container titan-helper">
    <div class="battle-info">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h3><i class="fas fa-dragon"></i> Помощник для титанов</h3>
                <p><?= date('d.m.Y', strtotime($draftBattle['battle_date'])) ?> | Противник: <?= htmlspecialchars($draftBattle['enemy_guild_name'] ?? '—') ?></p>
            </div>
            <a href="battle.php?id=<?= $draftBattle['id'] ?>" class="btn btn-light btn-sm">К боевому листу</a>
        </div>
    </div>
    
    <!-- Статистика -->
    <div class="stats-bar mb-3">
        <span class="badge bg-secondary">Всего титанов: <?= $totalTitanSlots ?></span>
        <span class="badge bg-warning text-dark">Ожидают: <?= $filledTitanSlots ?></span>
        <span class="badge bg-danger">Свободны: <?= $emptyTitanSlots ?></span>
        <span class="badge bg-success">Завершены: <?= $completedTitanSlots ?></span>
    </div>
    
    <?php if (!empty($buildingsData)): ?>
    <div class="percent-control">
        <label><i class="fas fa-percent"></i> Минимальный порог силы:</label>
        <input type="range" id="minPercent" min="50" max="100" step="1" value="<?php echo $percent; ?>">
        <span class="percent-value" id="percentValue"><?php echo $percent; ?>%</span>
        <span class="text-muted small">(от силы противника)</span>
        <button id="resetPercent" class="btn btn-sm btn-outline-secondary ms-2">Сброс</button>
    </div>
    <?php endif; ?>
	
	<div class="algorithm-selector mt-2">
		<label><i class="fas fa-code-branch"></i> Алгоритм распределения:</label>
		<div class="btn-group btn-group-sm ms-2" role="group">
			<input type="radio" class="btn-check" name="algorithm" id="algo_variant1" value="variant1" checked>
			<label class="btn btn-outline-secondary" for="algo_variant1">Вариант 1</label>
			
			<input type="radio" class="btn-check" name="algorithm" id="algo_variant2" value="variant2">
			<label class="btn btn-outline-secondary" for="algo_variant2">Вариант 2</label>
		</div>
	</div>
		

    <div id="recommendationsContainer">
        <?php 
        $recIndex = 0;
        foreach ($groupedBuildings as $baseName => $buildingsGroup): 
            $hasAny = false;
            foreach ($buildingsGroup as $building) {
                $draftItem = $itemsByBuilding[$building['name']] ?? null;
                if ($draftItem && !empty($draftItem['enemy_player_nick'])) {
                    $hasAny = true;
                    break;
                }
            }
            if (!$hasAny) continue;
        ?>
            <div class="building-card" data-group="<?= htmlspecialchars($baseName) ?>">
                <div class="building-header">
                    <span><i class="fas fa-building"></i> <?= htmlspecialchars($baseName) ?> <span class="badge bg-secondary ms-1"><?= count($buildingsGroup) ?></span></span>
                </div>
                
                <?php foreach ($buildingsGroup as $building):
                    $draftItem = $itemsByBuilding[$building['name']] ?? null;
                    if (!$draftItem || empty($draftItem['enemy_player_nick'])) continue;
                    
                    $enemyPower = (int)($draftItem['enemy_power'] ?? 0);
                    $enemyTitanId = $draftItem['enemy_titan_element_id'] ?? null;
                    $hasOurPlayer = !empty($draftItem['our_player_nick']);
                    $ourPlayerNick = $draftItem['our_player_nick'] ?? '';
                    $ourPower = (int)($draftItem['our_power'] ?? 0);
                    $ourTitanId = $draftItem['our_titan_element_id'] ?? null;
                    $result = $draftItem['result'] ?? null;
                    
                    $isCompleted = $result !== null && $result !== '';
                    $resultText = '';
                    $resultClass = '';
                    if ($isCompleted) {
                        switch($result) {
                            case 'win': $resultText = 'Победа'; $resultClass = 'result-win'; break;
                            case 'loss': $resultText = 'Поражение'; $resultClass = 'result-loss'; break;
                            case 'skip': $resultText = 'Пропуск'; $resultClass = 'result-skip'; break;
                        }
                    }
                ?>
				<div class="battle-row" 
					 data-building-name="<?= htmlspecialchars($building['name']) ?>"
					 data-enemy-power="<?= $enemyPower ?>"
					 data-has-our-player="<?= $hasOurPlayer ? 'true' : 'false' ?>">
					
					<div class="enemy">
						<div class="player-name text-danger"><?= htmlspecialchars($draftItem['enemy_player_nick']) ?></div>
						<div class="player-power"><i class="fas fa-bolt"></i> <?= $enemyPower ?></div>
						<?php if ($enemyTitanId): ?>
							<div><span class="titan-badge" style="background: <?= $titanColors[$enemyTitanId] ?? '#808080' ?>"><?= $titanNames[$enemyTitanId] ?? '?' ?></span></div>
						<?php endif; ?>
					</div>
					
					<div class="vs <?= $hasOurPlayer && $isCompleted ? ($result === 'win' ? 'vs-win' : ($result === 'loss' ? 'vs-loss' : 'vs-skip')) : '' ?>">
						<?php if ($hasOurPlayer && $isCompleted): ?>
							<i class="fas <?= $result === 'win' ? 'fa-trophy' : ($result === 'loss' ? 'fa-skull' : 'fa-forward') ?>"></i>
						<?php else: ?>
							VS
						<?php endif; ?>
					</div>
					
					<div class="our">
						<?php if ($hasOurPlayer && !$isCompleted): ?>
							<?php if ($ourTitanId): ?>
								<div><span class="titan-badge" style="background: <?= $titanColors[$ourTitanId] ?? '#808080' ?>"><?= $titanNames[$ourTitanId] ?? '?' ?></span></div>
							<?php endif; ?>
							<div class="player-power"><i class="fas fa-bolt"></i> <?= $ourPower ?></div>
							<div class="player-name text-primary"><?= htmlspecialchars($ourPlayerNick) ?></div>
						<?php elseif ($hasOurPlayer && $isCompleted): ?>
							<?php if ($ourTitanId): ?>
								<div><span class="titan-badge" style="background: <?= $titanColors[$ourTitanId] ?? '#808080' ?>"><?= $titanNames[$ourTitanId] ?? '?' ?></span></div>
							<?php endif; ?>
							<div class="player-power"><i class="fas fa-bolt"></i> <?= $ourPower ?></div>
							<div class="player-name text-primary"><?= htmlspecialchars($ourPlayerNick) ?></div>
						<?php else: ?>
							<div class="recommend-block" id="rec-<?= $recIndex ?>">
								<div class="recommend-title">Рекомендация:</div>
								<div class="text-muted"><i class="fas fa-spinner fa-spin"></i> Загрузка...</div>
							</div>
							<?php $recIndex++; ?>
						<?php endif; ?>
					</div>
				</div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
        
        <?php if ($recIndex === 0 && $emptyTitanSlots === 0): ?>
            <div class="alert alert-success text-center">
                <i class="fas fa-check-circle"></i> Все битвы с титанами уже укомплектованы!
            </div>
        <?php elseif ($recIndex === 0 && $emptyTitanSlots > 0): ?>
            <div class="alert alert-warning text-center">
                <i class="fas fa-exclamation-triangle"></i> Есть <?= $emptyTitanSlots ?> свободных слотов, но рекомендации не могут быть сформированы.
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
const titanHelperData = {
    players: <?= json_encode($playersJson) ?>,
    assignedCount: <?= json_encode($assignedCount) ?>,
    buildings: <?= json_encode($buildingsData) ?>,
    titanColors: <?= json_encode($titanColors) ?>,
    titanNames: <?= json_encode($titanNames) ?>,
	percent: <?php echo $percent; ?>
};

document.addEventListener('DOMContentLoaded', function() {
    if (typeof initTitanHelper === 'function') {
        initTitanHelper(titanHelperData);
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>