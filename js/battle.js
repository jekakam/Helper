// battle.js - вся логика редактирования боев

let currentHeroSelector = null;
let autosaveTimeout = null;

$(document).ready(function() {
    // Инициализируем HeroSelector
    initHeroSelector();
    
    renderBuildings();
    if (window.battleId && window.battleData && window.battleData.enemy_guild_id) {
        $('#enemyGuild').val(window.battleData.enemy_guild_id);
        $('#ourScore').val(window.battleData.our_score || 0);
        $('#enemyScore').val(window.battleData.enemy_score || 0);
    }
    
    const enemyGuildId = $('#enemyGuild').val();
    if (enemyGuildId) {
        loadEnemyPlayers();
    }
    
    $(window).on('beforeunload', function() {
        if (window.battleId && !window.isNewBattle) {
            $.ajax({
                url: 'ajax/BattleHandler.php',
                type: 'POST',
                data: {
                    action: 'unlock_battle',
                    battle_id: window.battleId
                },
                async: false  
            });
        }
    });

    attachEvents();
});

// Инициализация компонента выбора героев
function initHeroSelector() {
    currentHeroSelector = new HeroSelector({
        modalId: 'heroSelectorModal',
        maxHeroes: 5,
        onConfirm: function(selectedIds) {
            if (window.currentHeroCallback) {
                window.currentHeroCallback(selectedIds);
                window.currentHeroCallback = null;
            }
        },
        onCancel: function() {
            window.currentHeroCallback = null;
        }
    });
    currentHeroSelector.init();
}

function renderBuildings() {
    const groupedBuildings = {};
    window.buildings.forEach(building => {
        const baseName = building.name.replace(/\d+$/, '').replace(/[0-9]/g, '');
        if (!groupedBuildings[baseName]) {
            groupedBuildings[baseName] = [];
        }
        groupedBuildings[baseName].push(building);
    });
    
    let html = '';
    for (const [groupName, buildingsGroup] of Object.entries(groupedBuildings)) {
        html += `
            <div class="building-card" data-group="${groupName}">
                <div class="building-header">
                    <span><i class="fas fa-building"></i> ${groupName} <span class="badge bg-secondary ms-1">${buildingsGroup.length}</span></span>
                </div>
        `;
        
        buildingsGroup.forEach(building => {
            const existingItem = window.itemsData.find(i => i.building_name === building.name);
            const isTitan = building.unit_type === 'titan';
            
            html += `
                <div class="battle-item">
                    <div class="battle-row" data-building="${building.name}" data-is-titan="${isTitan}">
                        <div class="col-left">
                            <div class="player-group-left">
                                <select class="enemy-select" data-field="enemy_player_nick" data-building="${building.name}">
                                    <option value="">Загрузка...</option>
                                </select>
                                <input type="text" class="enemy-power" data-field="enemy_power" value="${existingItem?.enemy_power || ''}" placeholder="Мощь">
                                ${isTitan ? `
                                    <select class="titan-select" data-field="enemy_titan_element_id" data-building="${building.name}" data-side="enemy">
                                        <option value="">Стихия</option>
                                        ${getTitanOptions(existingItem?.enemy_titan_element_id || '')}
                                    </select>
                                ` : ''}
                            </div>
                            ${!isTitan ? `
                                <div class="heroes-edit enemies-heroes" data-side="enemy" data-building="${building.name}" data-heroes='${existingItem?.enemy_heroes_json || "[]"}'>
                                    ${renderHeroThumbs(existingItem?.enemy_heroes_json, 'enemy', building.name)}
                                </div>
                            ` : ''}
                        </div>
                        
                        <div class="col-center">
                            <select class="result-select" data-field="result">
                                <option value="" ${!existingItem?.result ? 'selected' : ''}>Результат</option>
                                <option value="win" ${existingItem?.result === 'win' ? 'selected' : ''}>Победа</option>
                                <option value="loss" ${existingItem?.result === 'loss' ? 'selected' : ''}>Поражение</option>
                                <option value="skip" ${existingItem?.result === 'skip' ? 'selected' : ''}>Пропуск</option>
                            </select>
                        </div>
                        
                        <div class="col-right">
                            <div class="player-group-right">
                                ${isTitan ? `
                                    <select class="titan-select" data-field="our_titan_element_id" data-building="${building.name}" data-side="our">
                                        <option value="">Стихия</option>
                                        ${getTitanOptions(existingItem?.our_titan_element_id || '')}
                                    </select>
                                ` : ''}
                                <input type="text" class="our-power" data-field="our_power" value="${existingItem?.our_power || ''}" placeholder="Мощь">
                                <select class="our-select" data-field="our_player_nick" data-building="${building.name}">
                                    <option value="">Выберите</option>
                                    ${getPlayersOptions(existingItem?.our_player_nick || '')}
                                </select>
                            </div>
                            ${!isTitan ? `
                                <div class="heroes-edit our-heroes" data-side="our" data-building="${building.name}" data-heroes='${existingItem?.our_heroes_json || "[]"}'>
                                    ${renderHeroThumbs(existingItem?.our_heroes_json, 'our', building.name)}
                                </div>
                            ` : ''}
                        </div>
                    </div>
                    <div class="comment-row">
                        <textarea class="edit-comment" data-field="comment" rows="1" placeholder="Примечание">${escapeHtml(existingItem?.comment || '')}</textarea>
                    </div>
                </div>
            `;
        });
        
        html += `</div>`;
    }
    $('#buildingsContainer').html(html);
}

function getPlayersOptions(selected) {
    let html = '';
    window.players.forEach(p => {
        html += `<option value="${escapeHtml(p.nickname)}" ${selected === p.nickname ? 'selected' : ''}>${escapeHtml(p.nickname)}</option>`;
    });
    return html;
}

function getTitanOptions(selected) {
    let html = '';
    window.titanElements.forEach(t => {
        html += `<option value="${t.id}" ${selected == t.id ? 'selected' : ''}>${escapeHtml(t.name)}</option>`;
    });
    return html;
}

function renderHeroThumbs(heroesJson, side, buildingName) {
    if (!heroesJson || heroesJson === '[]' || heroesJson === 'null') {
        return `<div class="hero-thumb-placeholder" onclick="openHeroModal('${side}', '${buildingName}', [])"><i class="fas fa-plus"></i></div>`;
    }
    let heroIds = [];
    try {
        heroIds = JSON.parse(heroesJson);
        if (!Array.isArray(heroIds)) heroIds = [];
    } catch(e) {
        return `<div class="hero-thumb-placeholder" onclick="openHeroModal('${side}', '${buildingName}', [])"><i class="fas fa-plus"></i></div>`;
    }
    if (heroIds.length === 0) {
        return `<div class="hero-thumb-placeholder" onclick="openHeroModal('${side}', '${buildingName}', [])"><i class="fas fa-plus"></i></div>`;
    }
    let html = '';
    heroIds.forEach(id => {
        const hero = window.heroes.find(h => h.id == id);
        if (hero) {
            html += `<img src="${hero.image || ''}" class="hero-thumb" title="${escapeHtml(hero.name)}" data-hero-id="${id}" onclick="openHeroModal('${side}', '${buildingName}', ${JSON.stringify(heroIds)})" onerror="this.style.display='none'">`;
        }
    });
    html += `<div class="hero-thumb-placeholder" onclick="openHeroModal('${side}', '${buildingName}', ${JSON.stringify(heroIds)})"><i class="fas fa-plus"></i></div>`;
    return html;
}

function loadEnemyPlayers() {
    const guildId = $('#enemyGuild').val();
    if (!guildId) return;
    
    $.get('ajax/PlayerGuildHandler.php', { action: 'get_guild_players', guild_id: guildId }, function(res) {
        if (res.success) {
            $('.enemy-select').each(function() {
                const $select = $(this);
                const buildingName = $select.data('building');
                const existingItem = window.itemsData.find(i => i.building_name === buildingName);
                const savedValue = existingItem?.enemy_player_nick || '';
                
                let html = '<option value="">Выберите</option>';
                res.players.forEach(p => {
                    const selected = (savedValue === p.nickname) ? 'selected' : '';
                    html += `<option value="${escapeHtml(p.nickname)}" ${selected}>${escapeHtml(p.nickname)}</option>`;
                });
                $select.html(html);
            });
        }
    }, 'json');
}

function loadPlayerLastComposition($select, side, buildingName) {
    const playerNick = $select.val();
    if (!playerNick) return;
    
    const $battleItem = $select.closest('.battle-item');
    const isTitan = $battleItem.find('.battle-row').data('is-titan');
    const $powerInput = side === 'our' ? $battleItem.find('.our-power') : $battleItem.find('.enemy-power');
    
    let guildId = null;
    if (side === 'our') {
        guildId = 1;
    } else {
        guildId = $('#enemyGuild').val();
    }
    
    $.post('ajax/BattleHandler.php', { 
        action: 'get_last_composition',
        nickname: playerNick,
        guild_id: guildId,
        unit_type: isTitan ? 'titan' : 'hero' 
    }, function(res) {
        if (res.success) {
            if (res.power && res.power > 0) {
                $powerInput.val(res.power);
            }
            
            if (isTitan) {
                const $titanSelect = $battleItem.find(`.titan-select[data-side="${side}"]`);
                if (res.titan_element_id && $titanSelect.length) {
                    $titanSelect.val(res.titan_element_id);
                }
            } else {
                const containerClass = side === 'our' ? 'our-heroes' : 'enemies-heroes';
                const $heroesContainer = $battleItem.find(`.${containerClass}[data-building="${buildingName}"]`);
                if (res.heroes && res.heroes.length) {
                    $heroesContainer.attr('data-heroes', JSON.stringify(res.heroes));
                    $heroesContainer.html(renderHeroThumbs(JSON.stringify(res.heroes), side, buildingName));
                }
            }
            
            saveBattleImmediate();
        }
    }, 'json');
}

function attachEvents() {
    $('#battleDate, #enemyGuild, #ourScore, #enemyScore').on('change', function() {
        if (window.battleId) {
            clearTimeout(autosaveTimeout);
            autosaveTimeout = setTimeout(() => saveBattle(true), 500);
        }
    });
    
    $(document).on('change', '.enemy-select, .our-select, .enemy-power, .our-power, .result-select, .edit-comment, .titan-select', function() {
        if (window.battleId) {
            clearTimeout(autosaveTimeout);
            autosaveTimeout = setTimeout(() => saveBattle(true), 500);
        }
    });
    
    $(document).on('change', '.our-select', function() {
        const buildingName = $(this).data('building');
        loadPlayerLastComposition($(this), 'our', buildingName);
    });
    
    $(document).on('change', '.enemy-select', function() {
        const buildingName = $(this).data('building');
        loadPlayerLastComposition($(this), 'enemy', buildingName);
    });
    
    $('#enemyGuild').on('change', loadEnemyPlayers);
    
    $('#completeBtn').off('click').on('click', completeBattle);
    $('#createBtn').off('click').on('click', createNewBattle);
}

function saveBattleImmediate() {
    if (!window.battleId) return;
    clearTimeout(autosaveTimeout);
    autosaveTimeout = setTimeout(() => saveBattle(true), 500);
}

function saveBattle(isAuto = true) {
    if (!window.battleId) return;
    
    const ourScore = parseInt($('#ourScore').val()) || 0;
    const enemyScore = parseInt($('#enemyScore').val()) || 0;
    
    const items = [];
    $('.battle-item').each(function() {
        const $item = $(this);
        const $battleRow = $item.find('.battle-row');
        const buildingName = $battleRow.data('building');
        const isTitan = $battleRow.data('is-titan');
        
        items.push({
            building_name: buildingName,
            our_player_nick: $item.find('.our-select').val() || '',
            our_power: parseInt($item.find('.our-power').val()) || 0,
            our_heroes_json: !isTitan ? $item.find('.our-heroes').attr('data-heroes') : null,
            our_titan_element_id: isTitan ? $item.find('.titan-select[data-side="our"]').val() : null,
            enemy_player_nick: $item.find('.enemy-select').val() || '',
            enemy_power: parseInt($item.find('.enemy-power').val()) || 0,
            enemy_heroes_json: !isTitan ? $item.find('.enemies-heroes').attr('data-heroes') : null,
            enemy_titan_element_id: isTitan ? $item.find('.titan-select[data-side="enemy"]').val() : null,
            result: $item.find('.result-select').val(),
            comment: $item.find('.edit-comment').val()
        });
    });
    
    $.post('ajax/BattleHandler.php', {
        action: 'save_battle',
        battle_id: window.battleId,
        our_score: ourScore,
        enemy_score: enemyScore,
        items: JSON.stringify(items),
        complete: false
    }, function(res) {
        if (!res.success && !isAuto) {
            alert('Ошибка: ' + res.error);
        }
    }, 'json');
}

function createNewBattle() {
    const enemyId = $('#enemyGuild').val();
    const battleDate = $('#battleDate').val();
    
    if (!enemyId) {
        alert('Выберите гильдию противника');
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
            alert(res.error);
        }
    }, 'json');
}

function completeBattle() {
    if (!confirm('Завершить бой?')) return;
    
    $('#completeBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Завершение...');
    
    const ourScore = parseInt($('#ourScore').val()) || 0;
    const enemyScore = parseInt($('#enemyScore').val()) || 0;
    
    const items = [];
    $('.battle-item').each(function() {
        const $item = $(this);
        const $battleRow = $item.find('.battle-row');
        const buildingName = $battleRow.data('building');
        const isTitan = $battleRow.data('is-titan');
        
        items.push({
            building_name: buildingName,
            our_player_nick: $item.find('.our-select').val() || '',
            our_power: parseInt($item.find('.our-power').val()) || 0,
            our_heroes_json: !isTitan ? $item.find('.our-heroes').attr('data-heroes') : null,
            our_titan_element_id: isTitan ? $item.find('.titan-select[data-side="our"]').val() : null,
            enemy_player_nick: $item.find('.enemy-select').val() || '',
            enemy_power: parseInt($item.find('.enemy-power').val()) || 0,
            enemy_heroes_json: !isTitan ? $item.find('.enemies-heroes').attr('data-heroes') : null,
            enemy_titan_element_id: isTitan ? $item.find('.titan-select[data-side="enemy"]').val() : null,
            result: $item.find('.result-select').val(),
            comment: $item.find('.edit-comment').val()
        });
    });
    
    $.post('ajax/BattleHandler.php', {
        action: 'save_battle',
        battle_id: window.battleId,
        our_score: ourScore,
        enemy_score: enemyScore,
        items: JSON.stringify(items),
        complete: true
    }, function(res) {
        if (res.success) {
            window.location.href = 'index.php';  
        } else {
            alert('Ошибка: ' + res.error);
            $('#completeBtn').prop('disabled', false).html('<i class="fas fa-check-circle"></i> Завершить');
        }
    }, 'json').fail(function() {
        alert('Ошибка соединения');
        $('#completeBtn').prop('disabled', false).html('<i class="fas fa-check-circle"></i> Завершить');
    });
}

// Обновленная функция openHeroModal с использованием HeroSelector
function openHeroModal(side, buildingName, currentIds = []) {
    window.currentHeroCallback = (selectedIds) => {
        const containerClass = side === 'our' ? 'our-heroes' : 'enemies-heroes';
        const $container = $(`.${containerClass}[data-building="${buildingName}"]`);
        
        if ($container.length) {
            $container.attr('data-heroes', JSON.stringify(selectedIds));
            $container.html(renderHeroThumbs(JSON.stringify(selectedIds), side, buildingName));
            saveBattleImmediate();
        }
    };
    
    currentHeroSelector.setSelectedIds(currentIds);
    currentHeroSelector.open();
}