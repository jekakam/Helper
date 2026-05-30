<?php
require_once 'includes/auth.php';
require_once 'includes/config.php';

$pageTitle = 'Анализатор сборок';
require_once 'includes/header.php';

// Получаем всех героев
$heroes = $pdo->query("SELECT id, name, image FROM heroes_catalog ORDER BY name")->fetchAll();
$titanElements = $pdo->query("SELECT id, name, color FROM titan_elements ORDER BY name")->fetchAll();
?>

<style>
.counter-container {
    max-width: 1400px;
    margin: 0 auto;
}

/* Пресеты */
.presets-section {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
}

.presets-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 15px;
}

.preset-card {
    background: white;
    border: 2px solid #e0e0e0;
    border-radius: 12px;
    padding: 12px;
    cursor: pointer;
    transition: all 0.2s;
    min-width: 250px;
    flex: 1;
}

.preset-card:hover {
    border-color: #0d6efd;
    box-shadow: 0 4px 12px rgba(13,110,253,0.15);
    transform: translateY(-2px);
}

.preset-card .preset-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.preset-card .preset-nick {
    font-weight: 600;
    color: #dc3545;
    font-size: 0.95rem;
}

.preset-card .preset-power {
    font-size: 0.85rem;
    color: #6c757d;
}

.preset-card .preset-heroes {
    display: flex;
    gap: 4px;
    margin-bottom: 8px;
}

.preset-card .preset-heroes img {
    width: 36px;
    height: 36px;
    object-fit: cover;
    border-radius: 6px;
    border: 1px solid #e0e0e0;
}

.preset-card .preset-status {
    font-size: 0.8rem;
    color: #6c757d;
}

/* Существующие стили */
.search-card {
    background: white;
    border-radius: 16px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.08);
    margin-bottom: 24px;
    overflow: hidden;
}

.search-header {
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    color: white;
    padding: 16px 20px;
}

.search-body {
    padding: 20px;
}

.enemy-heroes-section {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
}

.enemy-heroes-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    justify-content: center;
    margin: 20px 0;
}

.enemy-hero-slot {
    width: 100px;
    text-align: center;
    cursor: pointer;
    transition: transform 0.2s;
}

.enemy-hero-slot:hover {
    transform: scale(1.05);
}

.enemy-hero-slot.selected .hero-card {
    border: 3px solid #28a745;
    background: #d4edda;
}

.hero-card {
    background: white;
    border-radius: 12px;
    padding: 10px;
    border: 2px solid #e0e0e0;
    transition: all 0.2s;
}

.hero-card img {
    width: 70px;
    height: 70px;
    object-fit: cover;
    border-radius: 10px;
    margin-bottom: 8px;
}

.hero-card .hero-name {
    font-size: 0.75rem;
    font-weight: 500;
    text-align: center;
    word-break: break-word;
}

.remove-hero {
    margin-top: 5px;
    font-size: 0.7rem;
    color: #dc3545;
    cursor: pointer;
}

.search-btn {
    background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
    border: none;
    padding: 12px 30px;
    font-size: 1.1rem;
    font-weight: 600;
}

.results-card {
    background: white;
    border-radius: 16px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.08);
    overflow: hidden;
}

.results-header {
    background: #343a40;
    color: white;
    padding: 12px 20px;
}

.results-count {
    font-size: 0.9rem;
    background: rgba(255,255,255,0.2);
    padding: 4px 12px;
    border-radius: 20px;
}

/* Таблица-грид */
.results-grid {
    display: grid;
    grid-template-columns: 140px 1fr 120px 70px 70px 85px 110px;
    gap: 1px;
    background: #e0e0e0;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    overflow: hidden;
    font-size: 0.85rem;
}

.grid-header {
    background: #343a40;
    color: white;
    padding: 10px 8px;
    font-weight: 600;
    font-size: 0.8rem;
    display: flex;
    align-items: center;
}

.grid-cell {
    background: white;
    padding: 10px 8px;
    display: flex;
    align-items: center;
}

.grid-cell.player-cell {
    background: #f8f9fa;
    font-weight: 500;
}

.grid-cell.alternate {
    background: #fafbfc;
}

.player-rank {
    display: inline-block;
    width: 24px;
    height: 24px;
    line-height: 24px;
    text-align: center;
    background: #0d6efd;
    color: white;
    border-radius: 50%;
    font-size: 0.75rem;
    font-weight: 700;
    margin-right: 8px;
    flex-shrink: 0;
}

.combo-badge {
    display: inline-block;
    background: #e9ecef;
    color: #495057;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: 600;
    margin-left: 4px;
}

.hero-combo-grid {
    display: flex;
    gap: 4px;
    flex-wrap: wrap;
}

.hero-combo-grid img {
    width: 32px;
    height: 32px;
    object-fit: cover;
    border-radius: 6px;
    border: 1px solid #e0e0e0;
}

.win-badge {
    display: inline-block;
    background: #ffc107;
    color: #000;
    padding: 1px 6px;
    border-radius: 8px;
    font-size: 0.7rem;
    font-weight: 700;
    margin-right: 4px;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #6c757d;
}

.empty-state i {
    font-size: 48px;
    margin-bottom: 16px;
    opacity: 0.5;
}

@media (max-width: 900px) {
    .results-grid {
        grid-template-columns: 110px 1fr 90px 60px 60px 70px 90px;
        font-size: 0.75rem;
    }
    .hero-combo-grid img {
        width: 26px;
        height: 26px;
    }
}

@media (max-width: 768px) {
    .enemy-hero-slot { width:100px; }
    .hero-card img { width: 50px; height: 50px; }
    .hero-card .hero-name { font-size: 0.65rem; }
    .presets-grid { flex-direction: column; }
    .results-grid {
        grid-template-columns: 1fr;
        gap: 8px;
        background: transparent;
    }
    .grid-header {
        display: none;
    }
    .grid-cell {
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        margin-bottom: 8px;
        justify-content: space-between;
    }
    .grid-cell:before {
        content: attr(data-label);
        font-weight: 600;
        color: #6c757d;
        font-size: 0.7rem;
    }
    .player-rank {
        margin-right: 0;
    }
}
</style>

<div class="container counter-container">
    <!-- Пресеты из черновика -->
    <div id="presetsContainer" style="display: none;">
        <div class="presets-section">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-clipboard-list text-primary"></i> Сборки из черновика
                </h5>
                <span id="presetCount" class="badge bg-primary">0</span>
            </div>
            <div id="presetsGrid" class="presets-grid"></div>
        </div>
    </div>

    <!-- Поиск -->
    <div class="search-card">
        <div class="search-header">
            <h4 class="mb-0"><i class="fas fa-search"></i> Анализатор сборок</h4>
            <small class="opacity-75">Укажите сборку противника — найдём лучшие контр-сборки</small>
        </div>
        <div class="search-body">
            <div class="enemy-heroes-section">
                <label class="form-label fw-bold mb-3">
                    <i class="fas fa-skull text-danger"></i> Сборка противника (5 героев)
                </label>
                <div id="enemyHeroesContainer" class="enemy-heroes-grid"></div>
                <div class="text-center mt-3">
                    <button class="btn btn-outline-secondary btn-sm" onclick="openHeroSelectModal()">
                        <i class="fas fa-plus"></i> Выбрать героев
                    </button>
                    <span class="text-muted ms-2" id="heroCountHint">(выбрано 0/5)</span>
                </div>
            </div>
            
            <div class="text-center">
                <button class="btn btn-success search-btn" onclick="searchCounters()" id="searchBtn" disabled>
                    <i class="fas fa-chart-line"></i> Найти контр-сборки
                </button>
            </div>
        </div>
    </div>

    <!-- Результаты -->
    <div id="resultsContainer" style="display: none;">
        <div class="results-card">
            <div class="results-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span><i class="fas fa-chart-simple"></i> Лучшие контр-сборки</span>
                <span id="resultsCount" class="results-count">Топ-2</span>
            </div>
            <div id="counterCombosContainer" class="counter-combos"></div>
        </div>
    </div>
    
    <div id="emptyState" class="empty-state">
        <i class="fas fa-chess-board"></i>
        <h5>Выберите сборку противника</h5>
        <p class="text-muted">Добавьте 5 героев или выберите пресет из черновика</p>
    </div>
</div>

<script>
// Данные
let allHeroes = <?= json_encode($heroes) ?>;
let selectedHeroIds = [];
let currentHeroCallback = null;
let heroSelector = null;

// Инициализация HeroSelector
function initHeroSelector() {
    heroSelector = new HeroSelector({
        modalId: 'heroSelectorModal',
        maxHeroes: 5,
        onConfirm: function(selectedIds) {
            if (currentHeroCallback) {
                currentHeroCallback(selectedIds);
                currentHeroCallback = null;
            }
        },
        onCancel: function() {
            currentHeroCallback = null;
        }
    });
    heroSelector.init();
}

// Загрузка пресетов при старте
function loadPresets() {
    $.ajax({
        url: 'ajax/CounterSearchHandler.php',
        type: 'GET',
        data: { action: 'get_presets' },
        dataType: 'json',
        success: function(response) {
            if (response.success && response.hasDraft && response.presets.length > 0) {
                renderPresets(response.presets);
                $('#presetsContainer').show();
                $('#presetCount').text(response.presets.length);
            }
        }
    });
}

function renderPresets(presets) {
    const grid = $('#presetsGrid');
    grid.empty();
    
    presets.forEach(function(preset) {
        let heroesHtml = '';
        preset.heroes.forEach(function(hero) {
            heroesHtml += `<img src="${hero.image || ''}" title="${escapeHtml(hero.name)}" onerror="this.style.display='none'">`;
        });
        
        grid.append(`
            <div class="preset-card" onclick="usePreset(${JSON.stringify(preset.hero_ids).replace(/"/g, '&quot;')})">
                <div class="preset-header">
                    <span class="preset-nick">${escapeHtml(preset.enemy_nick)} ${preset.building_name ? `(<small>${escapeHtml(preset.building_name)}</small>)` : ''}</span>
                    <span class="preset-power"><i class="fas fa-bolt"></i> ${preset.enemy_power}</span>
                </div>
                <div class="preset-heroes">${heroesHtml}</div>
                <div class="preset-status">${escapeHtml(preset.status_text)}</div>
            </div>
        `);
    });
}

function usePreset(heroIds) {
    // Проверяем, что heroIds - массив
    if (typeof heroIds === 'string') {
        try {
            heroIds = JSON.parse(heroIds);
        } catch(e) {
            heroIds = heroIds.split(',').map(Number);
        }
    }
    
    selectedHeroIds = heroIds;
    renderEnemySlots();
    
    // Автоматически запускаем поиск
    if (selectedHeroIds.length === 5) {
        searchCounters();
    }
}

// Рендер слотов выбранных героев
function renderEnemySlots() {
    const container = $('#enemyHeroesContainer');
    container.empty();
    
    for (let i = 0; i < 5; i++) {
        const hero = selectedHeroIds[i] ? allHeroes.find(h => h.id == selectedHeroIds[i]) : null;
        
        container.append(`
            <div class="enemy-hero-slot ${hero ? 'selected' : ''}" data-slot="${i}" onclick="openHeroSelectModal(${i})">
                <div class="hero-card">
                    ${hero ? `
                        <img src="${hero.image || ''}" onerror="this.src='data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%2270%22%20height%3D%2270%22%20viewBox%3D%220%200%2070%2070%22%3E%3Crect%20width%3D%2270%22%20height%3D%2270%22%20fill%3D%22%236c757d%22%2F%3E%3Ctext%20x%3D%2235%22%20y%3D%2235%22%20text-anchor%3D%22middle%22%20dy%3D%22.3em%22%20fill%3D%22white%22%20font-size%3D%2220%22%3E${hero.name.charAt(0)}%3C%2Ftext%3E%3C%2Fsvg%3E'">
                        <div class="hero-name">${escapeHtml(hero.name)}</div>
                        <div class="remove-hero" onclick="event.stopPropagation(); removeHeroFromSlot(${i})">
                            <i class="fas fa-times-circle"></i> Удалить
                        </div>
                    ` : `
                        <div style="width:76px;height:76px;background:#e9ecef;border-radius:10px;display:flex;align-items:center;justify-content:center;margin-bottom:8px;">
                            <i class="fas fa-plus" style="font-size:24px;color:#6c757d;"></i>
                        </div>
                        <div class="hero-name">Слот ${i+1}</div>
                    `}
                </div>
            </div>
        `);
    }
    
    $('#heroCountHint').text(`(выбрано ${selectedHeroIds.length}/5)`);
    $('#searchBtn').prop('disabled', selectedHeroIds.length !== 5);
}

function removeHeroFromSlot(slot) {
    if (slot >= 0 && slot < selectedHeroIds.length) {
        selectedHeroIds.splice(slot, 1);
        renderEnemySlots();
    }
}

function openHeroSelectModal(slot = null) {
    currentHeroCallback = (newSelectedIds) => {
        selectedHeroIds = newSelectedIds;
        renderEnemySlots();
        
        if (selectedHeroIds.length === 5) {
            searchCounters();
        }
    };
    
    heroSelector.setSelectedIds(selectedHeroIds);
    heroSelector.open();
}

function searchCounters() {
    if (selectedHeroIds.length !== 5) {
        alert('Выберите ровно 5 героев противника!');
        return;
    }
    
    $('#resultsContainer').hide();
    $('#emptyState').hide();
    
    $('.search-btn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Поиск...');
    
    $.ajax({
        url: 'ajax/CounterSearchHandler.php',
        type: 'POST',
        data: {
            action: 'search_counters',
            enemy_heroes: JSON.stringify(selectedHeroIds)
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                if (response.data.length === 0) {
                    $('#emptyState').html(`
                        <i class="fas fa-frown"></i>
                        <h5>Ничего не найдено</h5>
                        <p>Нет записей о победах над этой сборкой</p>
                    `).show();
                    $('#resultsContainer').hide();
                } else {
                    $('#emptyState').hide();
                    $('#resultsContainer').show();
                    renderCounterResults(response.data, response.total_players);
                }
            } else {
                alert('Ошибка: ' + response.error);
            }
        },
        error: function(xhr, status, error) {
            alert('Ошибка запроса: ' + error);
        },
        complete: function() {
            $('.search-btn').prop('disabled', false).html('<i class="fas fa-chart-line"></i> Найти контр-сборки');
        }
    });
}

function renderCounterResults(players, totalPlayers) {
    $('#resultsCount').text(`${totalPlayers} игроков`);
    
    const container = $('#counterCombosContainer');
    container.empty();
    
    if (!players || players.length === 0) {
        container.html('<div class="text-center text-muted p-4">Нет данных</div>');
        return;
    }
    
    // Заголовок
    let html = `
        <div class="results-grid">
            <div class="grid-header">Игрок</div>
            <div class="grid-header">Контр-сборка</div>
            <div class="grid-header">Противник</div>
            <div class="grid-header">⚡Враг</div>
            <div class="grid-header">⚡Наша</div>
            <div class="grid-header">Дата</div>
            <div class="grid-header">Гильдия</div>
    `;
    
    let rowCounter = 0;
    
    players.forEach(function(player, playerIndex) {
        if (!player.combos || !Array.isArray(player.combos)) return;
        
        let isFirstForPlayer = true;
        let playerRowCount = 0;
        
        // Считаем общее количество строк для игрока
        player.combos.forEach(function(combo) {
            if (combo.wins && combo.wins.length > 0) {
                playerRowCount += combo.wins.length;
            } else {
                playerRowCount += 1;
            }
        });
        
        player.combos.forEach(function(combo, comboIndex) {
            if (!combo.heroes || !Array.isArray(combo.heroes)) return;
            
            // Рендер героев
            let heroesHtml = '';
            combo.heroes.forEach(function(hero) {
                heroesHtml += `<img src="${hero.image || ''}" title="${escapeHtml(hero.name)}" onerror="this.style.display='none'">`;
            });
            
            if (combo.wins && combo.wins.length > 0) {
                combo.wins.forEach(function(win, winIndex) {
                    const rowClass = rowCounter % 2 === 0 ? '' : 'alternate';
                    
                    html += `
                        ${isFirstForPlayer ? `
                        <div class="grid-cell player-cell ${rowClass}" style="grid-row: span ${playerRowCount};" data-label="Игрок">
                            <span class="player-rank">${playerIndex + 1}</span>
                            <div>
                                <strong>${escapeHtml(player.player_nick)}</strong>
                                <br><span class="combo-badge">${player.total_combos} сб.</span>
                            </div>
                        </div>` : ''}
                        <div class="grid-cell ${rowClass}" data-label="Контр-сборка">
                            <div class="hero-combo-grid">${heroesHtml}</div>
                        </div>
                        <div class="grid-cell ${rowClass}" data-label="Противник">
                            <span class="win-badge">${winIndex + 1}</span>
                            ${escapeHtml(win.enemy_nick || '—')}
                        </div>
                        <div class="grid-cell ${rowClass} power-enemy" data-label="⚡Враг">${win.enemy_power || 0}</div>
                        <div class="grid-cell ${rowClass} power-value" data-label="⚡Наша">${win.our_power || 0}</div>
                        <div class="grid-cell ${rowClass}" data-label="Дата">${win.battle_date || '—'}</div>
                        <div class="grid-cell ${rowClass}" data-label="Гильдия">${escapeHtml(win.enemy_guild_name || '—')}</div>
                    `;
                    
                    isFirstForPlayer = false;
                    rowCounter++;
                });
            } else {
                const rowClass = rowCounter % 2 === 0 ? '' : 'alternate';
                html += `
                    ${isFirstForPlayer ? `
                    <div class="grid-cell player-cell ${rowClass}" style="grid-row: span ${playerRowCount};" data-label="Игрок">
                        <span class="player-rank">${playerIndex + 1}</span>
                        <div>
                            <strong>${escapeHtml(player.player_nick)}</strong>
                            <br><span class="combo-badge">${player.total_combos} сб.</span>
                        </div>
                    </div>` : ''}
                    <div class="grid-cell ${rowClass}" data-label="Контр-сборка">
                        <div class="hero-combo-grid">${heroesHtml}</div>
                    </div>
                    <div class="grid-cell ${rowClass} text-muted" data-label="Инфо" style="grid-column: span 5;">Нет данных о победах</div>
                `;
                isFirstForPlayer = false;
                rowCounter++;
            }
        });
    });
    
    html += '</div>';
    container.html(html);
}

$(document).ready(function() {
    initHeroSelector();
    renderEnemySlots();
    loadPresets();
});
</script>

<?php require_once 'includes/footer.php'; ?>