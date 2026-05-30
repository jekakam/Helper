<?php
require_once 'includes/auth.php';
require_once 'includes/config.php';

$pageTitle = 'История боёв';
require_once 'includes/header.php';

$ourGuildId = 1;

// Проверяем наличие активного черновика (незавершённого боя)
$draftStmt = $pdo->prepare("
    SELECT b.*, eg.name as enemy_guild_name
    FROM battles b
    LEFT JOIN guilds eg ON b.enemy_guild_id = eg.id
    WHERE b.status = 'draft' AND b.our_guild_id = ?
    ORDER BY b.battle_date DESC
");
$draftStmt->execute([$ourGuildId]);
$draft = $draftStmt->fetchAll();

$hasActiveDraft = !empty($draft);

// Завершённые бои
$completed = $pdo->prepare("
    SELECT b.*, eg.name as enemy_guild_name
    FROM battles b
    LEFT JOIN guilds eg ON b.enemy_guild_id = eg.id
    WHERE b.status = 'completed' AND b.our_guild_id = ?
    ORDER BY b.battle_date DESC, b.id DESC
");
$completed->execute([$ourGuildId]);
$completed = $completed->fetchAll();

// Получаем все гильдии для фильтра
$guilds = $pdo->query("SELECT id, name FROM guilds WHERE id != $ourGuildId ORDER BY name")->fetchAll();
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h4 class="mb-0"><i class="fas fa-history"></i> Бои</h4>
        
        <?php if ($hasActiveDraft): ?>
            <a href="battle.php?id=<?= $draft[0]['id'] ?>" class="btn btn-warning btn-sm">
                <i class="fas fa-play"></i> Продолжить бой (черновик)
            </a>
        <?php else: ?>
            <a href="battle.php?id=0" class="btn btn-primary btn-sm">
                <i class="fas fa-plus"></i> Создать бой
            </a>
        <?php endif; ?>
    </div>

	<!-- Черновики -->
	<?php if (!empty($draft)): ?>
		<h5 class="mb-3 mt-3">
			<i class="fas fa-pencil-alt text-warning"></i> Черновик
		</h5>
		<div class="battles-list">
			<div class="battles-header">
				<div>Дата</div>
				<div>Противник</div>
				<div>Счёт</div>
				<div>Статус</div>
				<div>Действия</div>
			</div>
			<?php foreach ($draft as $b): ?>
				<div class="battles-row">
					<div data-label="Дата"><?= date('d.m.Y', strtotime($b['battle_date'])) ?></div>
					<div data-label="Противник"><?= htmlspecialchars($b['enemy_guild_name'] ?? '—') ?></div>
					<div data-label="Счёт">— : —</div>
					<div data-label="Статус"><span class="badge-draft"><i class="fas fa-pencil-alt"></i> Черновик</span></div>
					<div data-label="Действия" class="battle-actions">
						<a href="battle.php?id=<?= $b['id'] ?>" class="btn btn-warning btn-sm-custom" title="Редактировать">
							<i class="fas fa-edit"></i> <span class="battle-actions-mobile-label">Ред.</span>
						</a>
						<a href="battle.php?id=<?= $b['id'] ?>&view=1" class="btn btn-secondary btn-sm-custom" title="Просмотр">
							<i class="fas fa-eye"></i> <span class="battle-actions-mobile-label">Просм.</span>
						</a>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

    <!-- Фильтр для завершённых боёв -->
    <div class="filter-bar" id="filterBar" style="<?= empty($completed) ? 'display: none;' : '' ?>">
        <div class="row g-3 align-items-end">
            <div class="col-12 col-md-4">
                <label class="form-label mb-1"><i class="fas fa-filter"></i> Фильтр по периоду</label>
                <select id="periodFilter" class="form-select">
                    <option value="14">Последние 14 дней</option>
                    <option value="30">Последние 30 дней</option>
                    <option value="90">Последние 90 дней</option>
                    <option value="all">Всё время</option>
                </select>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label mb-1"><i class="fas fa-flag"></i> Фильтр по гильдии</label>
                <select id="guildFilter" class="form-select">
                    <option value="">Все гильдии</option>
                    <?php foreach ($guilds as $g): ?>
                        <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-4">
                <button id="applyFilterBtn" class="btn btn-primary w-100">
                    <i class="fas fa-search"></i> Применить
                </button>
            </div>
        </div>
    </div>

    <!-- Таблица завершённых боёв (с пагинацией) -->
    <div class="battles-list" id="completedBattlesList">
        <div class="battles-header" id="battlesHeader">
            <div>Дата</div>
            <div>Противник</div>
            <div>Счёт</div>
            <div>Результат</div>
            <div></div>
        </div>
        <div id="battlesContainer"></div>
    </div>

    <!-- Кнопка загрузки ещё -->
    <div class="load-more-btn" id="loadMoreBtn" style="display: none;">
        <button class="btn btn-outline-primary" onclick="loadMoreBattles()">
            <i class="fas fa-download"></i> Загрузить ещё
        </button>
    </div>

    <!-- Спиннер загрузки -->
    <div class="loading-spinner" id="loadingSpinner">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Загрузка...</span>
        </div>
    </div>
</div>

<script>
let currentPage = 1;
let currentPeriod = 14;
let currentGuildId = '';
let isLoading = false;
let hasMore = true;

function formatDate(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleDateString('ru-RU');
}

function getResultHtml(ourScore, enemyScore) {
    if (ourScore > enemyScore) {
        return '<span class="score-win"><i class="fas fa-trophy"></i> Победа</span>';
    } else if (ourScore < enemyScore) {
        return '<span class="score-loss"><i class="fas fa-skull"></i> Поражение</span>';
    } else {
        return '<span class="score-draw"><i class="fas fa-handshake"></i> Ничья</span>';
    }
}

function renderBattles(battles, isAppend = false) {
    if (!isAppend) {
        $('#battlesContainer').empty();
    }
    
    if (battles.length === 0 && !isAppend) {
        $('#battlesContainer').html('<div class="alert alert-info text-center">Нет завершённых боёв с выбранными параметрами</div>');
        return;
    }
    
    let html = '';
    battles.forEach(function(b) {
        const ourScore = b.our_score || 0;
        const enemyScore = b.enemy_score || 0;
        const dateFormatted = formatDate(b.battle_date);
        const resultHtml = getResultHtml(ourScore, enemyScore);
        
        html += `
            <div class="battles-row">
                <div data-label="Дата">${dateFormatted}</div>
                <div data-label="Противник">${escapeHtml(b.enemy_guild_name || '—')}</div>
                <div data-label="Счёт" class="battle-score">${ourScore} : ${enemyScore}</div>
                <div data-label="Результат">${resultHtml}</div>
                <div data-label="" class="battle-actions">
                    <a href="battle.php?id=${b.id}" class="btn btn-info btn-sm-custom" title="Просмотр">
                        <i class="fas fa-eye"></i> <span class="battle-actions-mobile-label">Просмотр</span>
                    </a>
                </div>
            </div>
        `;
    });
    
    if (isAppend) {
        $('#battlesContainer').append(html);
    } else {
        $('#battlesContainer').html(html);
    }
}

function loadBattles(reset = true) {
    if (isLoading) return;
    
    if (reset) {
        currentPage = 1;
        hasMore = true;
        $('#loadMoreBtn').hide();
    }
    
    isLoading = true;
    $('#loadingSpinner').show();
    
    $.ajax({
        url: 'ajax/BattlesListHandler.php',
        type: 'POST',
        data: {
            action: 'get_battles',
            page: currentPage,
            period: currentPeriod,
            guild_id: currentGuildId,
            our_guild_id: 1
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                if (reset) {
                    renderBattles(response.battles, false);
                } else {
                    renderBattles(response.battles, true);
                }
                
                hasMore = response.has_more;
                if (hasMore) {
                    $('#loadMoreBtn').show();
                } else {
                    $('#loadMoreBtn').hide();
                }
                
                currentPage = response.next_page;
            } else {
                $('#battlesContainer').html('<div class="alert alert-danger">Ошибка: ' + escapeHtml(response.error) + '</div>');
            }
        },
        error: function() {
            $('#battlesContainer').html('<div class="alert alert-danger">Ошибка загрузки данных</div>');
        },
        complete: function() {
            isLoading = false;
            $('#loadingSpinner').hide();
        }
    });
}

function loadMoreBattles() {
    if (!hasMore || isLoading) return;
    loadBattles(false);
}

function applyFilters() {
    currentPeriod = parseInt($('#periodFilter').val());
    currentGuildId = $('#guildFilter').val();
    
    if (isNaN(currentPeriod)) {
        currentPeriod = 'all';
    }
    
    loadBattles(true);
}

$(document).ready(function() {
    // Загружаем завершённые бои
    loadBattles(true);
    
    // Обработчик кнопки "Применить"
    $('#applyFilterBtn').on('click', applyFilters);
    
    // Обработчик Enter в фильтрах
    $('#periodFilter, #guildFilter').on('keypress', function(e) {
        if (e.which === 13) {
            applyFilters();
        }
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>