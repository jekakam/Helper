<?php
require_once 'includes/auth.php';
require_once 'includes/config.php';

$battleId = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$battleId) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT b.*, eg.name as enemy_guild_name
    FROM battles b
    LEFT JOIN guilds eg ON b.enemy_guild_id = eg.id
    WHERE b.id = ?
");
$stmt->execute([$battleId]);
$battle = $stmt->fetch();

if (!$battle) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT * FROM battle_items 
    WHERE battle_id = ? 
    ORDER BY slot_index
");
$stmt->execute([$battleId]);
$items = $stmt->fetchAll();
$stmt->execute([$battleId]);
$items = $stmt->fetchAll();

$pageTitle = "Просмотр боя от " . date('d.m.Y', strtotime($battle['battle_date']));
require_once 'includes/header.php';

$ourScore = $battle['our_score'] ?? 0;
$enemyScore = $battle['enemy_score'] ?? 0;

if ($ourScore > $enemyScore) {
    $resultIcon = 'fa-trophy';
} elseif ($ourScore < $enemyScore) {
    $resultIcon = 'fa-skull';
} else {
    $resultIcon = 'fa-handshake';
}

$isDraft = ($battle['status'] === 'draft');
$isCompleted = ($battle['status'] === 'completed');


?>

<style>
.empty-row {
    background: #f8f9fa !important;
    opacity: 0.7;
}

.battle-view-container {
    max-width: 1400px;
    margin: 0 auto;
}

.battle-header {
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    color: white;
    border-radius: 12px;
    padding: 15px 20px;
    margin-bottom: 25px;
}

.score-badge {
    font-size: 1.3rem;
    font-weight: 700;
    background: rgba(255,255,255,0.15);
    padding: 5px 18px;
    border-radius: 50px;
}

.building-card {
    background: white;
    border-radius: 10px;
    margin-bottom: 15px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    overflow: hidden;
}

.building-header {
    background: #c8d4d8;
    padding: 8px 15px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #e0e0e0;
    font-weight: 600;
    font-size: 0.95rem;
}

.battle-row {
    display: flex;
    align-items: flex-start;
    padding: 7px;
    border-bottom: 1px solid #f0f0f0;
}

.battle-row:nth-child(even) { background-color: #ededed; }
.battle-row:nth-child(odd) { background-color: #ffffff; }


.battle-row:last-child {
    border-bottom: none;
}

.col-left {
    flex: 1;
    text-align: right;
    padding-right: 15px;
}

.col-center {
    width: 120px;
    text-align: center;
    flex-shrink: 0;
}

.col-right {
    flex: 1;
    padding-left: 15px;
}

/* Противник (слева) - текст справа, титан справа от имени */
.battle-info-left {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    justify-content: flex-end;
}

/* Imperial (справа) - текст слева, титан слева от имени */
.battle-info-right {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    justify-content: flex-start;
}

/* Группа имя+мощность+титан для противника */
.player-group-left {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    justify-content: flex-end;
}

/* Группа имя+мощность+титан для Imperial */
.player-group-right {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    justify-content: flex-start;
}

.titan-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 15px;
    font-size: 0.8rem;
    font-weight: 500;
    color: white;
    white-space: nowrap;
}

.player-name {
    font-weight: 600;
    white-space: nowrap;
}

.player-name-left {
    color: #dc3545;
}

.player-name-right {
    color: #0d6efd;
}

.player-power {
    font-size: 0.95rem;
    color: #6c757d;
    white-space: nowrap;
}

/* Герои - под именем */
.heroes-list-left {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
    justify-content: flex-end;
    margin-top: 8px;
}

.heroes-list-right {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
    justify-content: flex-start;
    margin-top: 8px;
}

.hero-icon {
    width: 32px;
    height: 32px;
    object-fit: cover;
    border-radius: 6px;
    cursor: pointer;
    transition: transform 0.2s;
}

.hero-icon:hover {
    transform: scale(1.1);
}

.result-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    white-space: nowrap;
}

.result-win {
    background:#39c95b;
    color: #155724;
}

.result-loss {
    background: #cd737b;
    color: #721c24;
}

.result-skip {
    background:#91ade3;
    color: #383d41;
}

.comment-icon {
    cursor: pointer;
    color: #6c757d;
    font-size: 0.8rem;
    margin-left: 6px;
    transition: color 0.2s;
}

.comment-icon:hover {
    color: #0d6efd;
}

.has-comment {
    cursor: pointer;
    color: #6c757d;
}

.has-comment:hover {
    color: #0d6efd;
}

/* Попап */
.comment-popup {
    display: none;
    position: fixed;
    background: #2d2d2d;
    color: white;
    border-radius: 8px;
    padding: 10px 15px;
    max-width: 300px;
    font-size: 0.85rem;
    z-index: 1000;
    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
    word-wrap: break-word;
}

.comment-popup::after {
    content: '';
    position: absolute;
    top: -8px;
    left: 50%;
    transform: translateX(-50%) rotate(180deg);
    border-left: 8px solid transparent;
    border-right: 8px solid transparent;
    border-top: 8px solid #2d2d2d;
}

.comment-popup.active {
    display: block;
}

/* Мобильная версия */
@media (max-width: 767px) {
    .battle-row {
        flex-direction: column;
        align-items: stretch;
    }
	
	.score-badge {width: 100%;text-align: center;}

   
    .col-left {text-align: center;}
    .col-center {padding: 6px 0;width: auto;}
    .col-right {padding-left: 0;text-align: center;}
	
	.result{display:none;}
	
	.battle-row.titan {display: grid;grid-template-columns: 1fr 35px 1fr;}
	.titan .col-right span:first-child {order: 3;}
	.titan .col-left {text-align: center;padding-right: 0;padding-bottom: 0;border-bottom: none;}
	
	.titan .col-right {padding-top:0;}
	.titan .player-group-left,.titan .player-group-right {flex-direction: column;font-size: small;}
	
	
    
    .battle-info-left,
    .battle-info-right {
        justify-content: center;
    }
    
    .player-group-left,
    .player-group-right {
        justify-content: center;
    }
    
    .heroes-list-left,.heroes-list-right {justify-content: center;gap: 2px;margin-top: 0;}
	
}
</style>

<div class="container battle-view-container">
    <!-- Шапка -->
	<div class="battle-header">
		<div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
			<a href="index.php" class="btn btn-light btn-sm">
				<i class="fas fa-arrow-left"></i> Назад
			</a>
			<div class="text-center">
				<?php if ($isDraft): ?>
					<span class="badge bg-warning text-dark mb-2">
						<i class="fas fa-pencil-alt"></i> ЧЕРНОВИК (редактируется)
					</span>
				<?php elseif ($isCompleted): ?>
					<span class="badge bg-success mb-2">
						<i class="fas fa-check-circle"></i> ЗАВЕРШЁН
					</span>
				<?php endif; ?>
				<div><i class="far fa-calendar-alt"></i> <?= date('d.m.Y', strtotime($battle['battle_date'])) ?></div>
				<small class="opacity-75">Imperial VS <?= htmlspecialchars($battle['enemy_guild_name'] ?? '—') ?></small>
			</div>
			<div class="score-badge">
				<i class="fas <?= $resultIcon ?>"></i> <?= $battle['our_score'] ?? 0 ?> : <?= $battle['enemy_score'] ?? 0 ?>
			</div>
		</div>
		
		<?php if ($isDraft && !$isCompleted): ?>
			<div class="alert alert-warning text-center mt-3 mb-0">
				<i class="fas fa-info-circle"></i> 
				Этот бой находится в режиме редактирования. Данные могут быть неполными.
			</div>
		<?php endif; ?>
	</div>

    <?php 
    $currentBuilding = '';
    $buildingItems = [];
    
    foreach ($items as $item):
        $baseName = preg_replace('/\d+$/', '', $item['building_name']);
        $baseName = rtrim($baseName, '1234567890');
        
        if ($currentBuilding !== $baseName && $currentBuilding !== ''):
            renderBuildingCard($currentBuilding, $buildingItems, $pdo);
            $buildingItems = [];
        endif;
        
        $currentBuilding = $baseName;
        $buildingItems[] = $item;
    endforeach;
    
    if (!empty($buildingItems)):
        renderBuildingCard($currentBuilding, $buildingItems, $pdo);
    endif;
    
    function renderBuildingCard($buildingName, $items, $pdo) {
        $hasComment = false;
        foreach ($items as $item) {
            if (!empty($item['comment'])) $hasComment = true;
        }
    ?>
        <div class="building-card">
            <div class="building-header">
                <span><i class="fas fa-building"></i> <?= htmlspecialchars($buildingName) ?> <span class="badge bg-secondary ms-1"><?= count($items) ?></span></span>
            </div>
            
            <?php foreach ($items as $item):
                $ourHeroes = [];
                $enemyHeroes = [];
                
                $stmtBuilding = $pdo->prepare("SELECT unit_type FROM buildings WHERE name = ?");
                $stmtBuilding->execute([$item['building_name']]);
                $building = $stmtBuilding->fetch();
                $isTitan = ($building && $building['unit_type'] === 'titan');
                
				$isEmpty = (
					empty($item['our_player_nick']) && 
					empty($item['enemy_player_nick']) &&
					!$item['our_power'] && 
					!$item['enemy_power']
				);

				if ($isEmpty) {
					?>
					<div class="battle-row empty-row">
						<div class="col-12 text-center text-muted py-2">
							<i class="fas fa-minus-circle"></i> -
						</div>
					</div>
					<?php
					continue;
				}
				
				
				
                if (!$isTitan && $item['our_heroes_json']) {
                    $ourHeroIds = json_decode($item['our_heroes_json'], true);
                    if (!empty($ourHeroIds)) {
                        $placeholders = implode(',', array_fill(0, count($ourHeroIds), '?'));
                        $stmt = $pdo->prepare("SELECT id, name, image FROM heroes_catalog WHERE id IN ($placeholders)");
                        $stmt->execute($ourHeroIds);
                        $ourHeroes = $stmt->fetchAll();
                    }
                }
                
                if (!$isTitan && $item['enemy_heroes_json']) {
                    $enemyHeroIds = json_decode($item['enemy_heroes_json'], true);
                    if (!empty($enemyHeroIds)) {
                        $placeholders = implode(',', array_fill(0, count($enemyHeroIds), '?'));
                        $stmt = $pdo->prepare("SELECT id, name, image FROM heroes_catalog WHERE id IN ($placeholders)");
                        $stmt->execute($enemyHeroIds);
                        $enemyHeroes = $stmt->fetchAll();
                    }
                }
                
                $resultClass = '';
                $resultText = '';
                switch($item['result']) {
                    case 'win': $resultClass = 'result-win'; $resultText = '<i class="fas fa-trophy"></i> <span class="result">Победа</span>'; break;
                    case 'loss': $resultClass = 'result-loss'; $resultText = '<i class="fas fa-skull"></i> <span class="result">Поражение</span>'; break;
                    case 'skip': $resultClass = 'result-skip'; $resultText = '<i class="fas fa-forward"></i> <span class="result">Пропуск</span>'; break;
                    default: $resultText = '—';
                }
                
                $ourTitan = null;
                $enemyTitan = null;
                if ($isTitan) {
                    if ($item['our_titan_element_id']) {
                        $stmt = $pdo->prepare("SELECT name, color FROM titan_elements WHERE id = ?");
                        $stmt->execute([$item['our_titan_element_id']]);
                        $ourTitan = $stmt->fetch();
                    }
                    if ($item['enemy_titan_element_id']) {
                        $stmt = $pdo->prepare("SELECT name, color FROM titan_elements WHERE id = ?");
                        $stmt->execute([$item['enemy_titan_element_id']]);
                        $enemyTitan = $stmt->fetch();
                    }
                }
            ?>
                <div class="battle-row<?php if ($isTitan) {echo ' titan';} else {echo ' heroes';};?>">
                    <div class="col-left">
                        <div class="battle-info-left">
                            <div class="player-group-left">
                                <span class="player-name player-name-left"><?= htmlspecialchars($item['enemy_player_nick'] ?? '—') ?></span>
                                <span class="player-power">(<?= $item['enemy_power'] ?? 0 ?>)</span>
                                <?php if ($isTitan && $enemyTitan): ?>
                                    <span class="titan-badge" style="background: <?= $enemyTitan['color'] ?>"><?= htmlspecialchars($enemyTitan['name']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if (!$isTitan && !empty($enemyHeroes)): ?>
                            <div class="heroes-list-left">
                                <?php foreach ($enemyHeroes as $hero): ?>
                                    <img src="<?= $hero['image'] ?: '' ?>" class="hero-icon" title="<?= htmlspecialchars($hero['name']) ?>" onerror="this.style.display='none'">
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    

                    <div class="col-center">
                        <span class="result-badge <?= $resultClass ?>"><?= $resultText ?></span>
                        <?php if (!empty($item['comment'])): ?>
                            <i class="fas fa-comment comment-icon" data-comment="<?= htmlspecialchars($item['comment']) ?>"></i>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-right">
                        <div class="battle-info-right">
                            <div class="player-group-right">
                                <?php if ($isTitan && $ourTitan): ?>
                                    <span class="titan-badge" style="background: <?= $ourTitan['color'] ?>"><?= htmlspecialchars($ourTitan['name']) ?></span>
                                <?php endif; ?>
                                <span class="player-name player-name-right"><?= htmlspecialchars($item['our_player_nick'] ?? '—') ?></span>
                                <span class="player-power">(<?= $item['our_power'] ?? 0 ?>)</span>
                            </div>
                        </div>
                        <?php if (!$isTitan && !empty($ourHeroes)): ?>
                            <div class="heroes-list-right">
                                <?php foreach ($ourHeroes as $hero): ?>
                                    <img src="<?= $hero['image'] ?: '' ?>" class="hero-icon" title="<?= htmlspecialchars($hero['name']) ?>" onerror="this.style.display='none'">
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php
    }
    ?>
</div>

<!-- Попап для комментария -->
<div id="commentPopup" class="comment-popup"></div>

<script>
const popup = document.getElementById('commentPopup');
let activeIcon = null;

function showPopup(event, text) {
    if (!text) return;
    
    popup.innerHTML = text;
    popup.classList.add('active');
    
    const rect = event.target.getBoundingClientRect();
    const popupRect = popup.getBoundingClientRect();
    
    let left = rect.left + (rect.width / 2) - (popupRect.width / 2);
    let top = rect.bottom + 8;
    
    if (left < 10) left = 10;
    if (left + popupRect.width > window.innerWidth - 10) {
        left = window.innerWidth - popupRect.width - 10;
    }
    
    popup.style.left = left + 'px';
    popup.style.top = top + 'px';
    
    activeIcon = event.target;
}

function hidePopup() {
    popup.classList.remove('active');
    activeIcon = null;
}

document.addEventListener('click', function(e) {
    if (e.target.classList.contains('comment-icon')) {
        e.stopPropagation();
        const comment = e.target.getAttribute('data-comment');
        if (comment) {
            showPopup(e, comment);
        }
    } else if (e.target.classList.contains('has-comment')) {
        e.stopPropagation();
        showPopup(e, '📝 Есть комментарии в этой группе');
    } else {
        hidePopup();
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        hidePopup();
    }
});
</script>

<script>
$(document).ready(function() {
    <?php if ($isDraft && !$isCompleted): ?>
        // Для черновика данные загружаем отдельно, если в items пусто
        <?php if (empty($items)): ?>
            $.ajax({
                url: 'ajax/BattleHandler.php',
                method: 'POST',
                data: {
                    action: 'load_battle',
                    battle_id: <?= $battleId ?>
                },
                dataType: 'json',
                success: function(res) {
                    if (res.success && res.items && res.items.length) {
                        if (!window.location.href.includes('reload=1')) {
                            window.location.href = window.location.href + '&reload=1';
                        }
                    }
                }
            });
        <?php endif; ?>
    <?php endif; ?>
});
</script>

<?php require_once 'includes/footer.php'; ?>