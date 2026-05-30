// ========== ЭКРАНИРОВАНИЕ HTML ==========
function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/[&<>]/g, function(m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        return m;
    });
}

// ========== TITAN HELPER ==========

let titanPlayersData = [];
let titanAssignedCount = {};
let titanBuildingsData = [];
let titanColors = {};
let titanNames = {};
let titanMinPercent = localStorage.getItem('titan_min_percent') ? parseInt(localStorage.getItem('titan_min_percent')) : 90;
let currentAlgorithm = localStorage.getItem('titan_algorithm') || 'variant1';

// Копия назначений для текущего распределения
let currentAssignedCount = {};

function resetCurrentAssignments() {
    currentAssignedCount = {};
    for (let [nick, count] of Object.entries(titanAssignedCount)) {
        currentAssignedCount[nick] = count;
    }
}

// ========== ВАРИАНТ 1: ЖАДНЫЙ (ТЕКУЩИЙ) ==========
function selectBestPlayerVariant1(enemyPower) {
    const minRequired = Math.ceil(enemyPower * titanMinPercent / 100);
    
    let available = titanPlayersData.filter(p => {
        const assigned = currentAssignedCount[p.nickname] || 0;
        if (assigned >= 2) return false;
        if (p.last_power <= 0) return false;
        return true;
    });
    
    if (available.length === 0) return null;
    
    available.sort((a, b) => {
        const getPriority = (spec) => spec === 'anti_titan' ? 1 : (spec === 'universal' ? 2 : 3);
        const priorityA = getPriority(a.specialization);
        const priorityB = getPriority(b.specialization);
        if (priorityA !== priorityB) return priorityA - priorityB;
        return a.last_power - b.last_power;
    });
    
    for (let p of available) {
        if (p.last_power >= minRequired) {
            return p;
        }
    }
    return available[0];
}

function distributePlayersVariant1() {
    resetCurrentAssignments();
    
    let slots = [...titanBuildingsData];
    slots.sort((a, b) => {
        const reqA = Math.ceil(a.enemyPower * titanMinPercent / 100);
        const reqB = Math.ceil(b.enemyPower * titanMinPercent / 100);
        return reqB - reqA;
    });
    
    let results = new Array(titanBuildingsData.length).fill(null);
    
    for (let slot of slots) {
        const selected = selectBestPlayerVariant1(slot.enemyPower);
        if (selected) {
            results[slot.originalIndex] = selected;
            currentAssignedCount[selected.nickname] = (currentAssignedCount[selected.nickname] || 0) + 1;
        }
    }
    
    return results;
}

// ========== ВАРИАНТ 2: ЭКОНОМНЫЙ (НОВЫЙ) ==========
function selectBestPlayerVariant2(enemyPower, strongestPlayerNickname) {
    const minRequired = Math.ceil(enemyPower * titanMinPercent / 100);
    
    // Фильтруем доступных игроков (без учета топ-1 пока)
    let availableWithoutTop = titanPlayersData.filter(p => {
        const assigned = currentAssignedCount[p.nickname] || 0;
        if (assigned >= 2) return false;
        if (p.last_power <= 0) return false;
        if (p.nickname === strongestPlayerNickname) return false;
        return true;
    });
    
    // Сортируем по убыванию силы
    const sortedWithoutTop = [...availableWithoutTop].sort((a, b) => b.last_power - a.last_power);
    
    // Ищем среди них самого сильного, кто гарантированно побеждает
    let selected = null;
    for (let p of sortedWithoutTop) {
        if (p.last_power >= minRequired) {
            selected = p;
            break;
        }
    }
    
    // Если среди "обычных" нет подходящего, подключаем топ-1
    if (!selected) {
        const topPlayer = titanPlayersData.find(p => p.nickname === strongestPlayerNickname);
        if (topPlayer && topPlayer.last_power >= minRequired) {
            const assigned = currentAssignedCount[topPlayer.nickname] || 0;
            if (assigned < 2) {
                selected = topPlayer;
            }
        }
    }
    
    // Если всё равно нет никого с гарантией
    if (!selected) {
        let allAvailable = titanPlayersData.filter(p => {
            const assigned = currentAssignedCount[p.nickname] || 0;
            if (assigned >= 2) return false;
            if (p.last_power <= 0) return false;
            return true;
        });
        
        if (allAvailable.length === 0) return null;
        
        const sortedAll = [...allAvailable].sort((a, b) => b.last_power - a.last_power);
        
        if (allAvailable.length === 1) {
            selected = sortedAll[0];
        } else if (allAvailable.length === 2) {
            selected = sortedAll[0];
        } else {
            selected = sortedAll[1];
        }
    }
    
    return selected;
}

function distributePlayersVariant2() {
    resetCurrentAssignments();
    
    // Находим самого сильного игрока (которого будем исключать приоритетно)
    let strongestPlayerNickname = null;
    let strongestPower = -1;
    for (let p of titanPlayersData) {
        if (p.last_power > strongestPower) {
            strongestPower = p.last_power;
            strongestPlayerNickname = p.nickname;
        }
    }
    
    // Сортируем врагов от самого сильного к самому слабому
    let sortedSlots = [...titanBuildingsData];
    sortedSlots.sort((a, b) => b.enemyPower - a.enemyPower);
    
    let results = new Array(titanBuildingsData.length).fill(null);
    
    for (let slot of sortedSlots) {
        const selected = selectBestPlayerVariant2(slot.enemyPower, strongestPlayerNickname);
        if (selected) {
            results[slot.originalIndex] = selected;
            currentAssignedCount[selected.nickname] = (currentAssignedCount[selected.nickname] || 0) + 1;
        }
    }
    
    return results;
}

// ========== ДИСПЕТЧЕР ==========
function distributePlayers() {
    if (currentAlgorithm === 'variant1') {
        return distributePlayersVariant1();
    } else {
        return distributePlayersVariant2();
    }
}

// ========== ОБНОВЛЕНИЕ UI ==========
function updateTitanRecommendations() {
    if (titanBuildingsData.length === 0) return;
    
    // Отладка в консоль
    if (currentAlgorithm === 'variant2') {
        console.log('=== Вариант 2: порядок обработки врагов (от сильного к слабому) ===');
        const sortedForDebug = [...titanBuildingsData].sort((a, b) => b.enemyPower - a.enemyPower);
        sortedForDebug.forEach((s, idx) => {
            console.log(`${idx+1}. ${s.name} — сила ${s.enemyPower}`);
        });
    }
    
    const slotsWithIndex = titanBuildingsData.map((b, idx) => ({
        ...b,
        originalIndex: idx
    }));
    
    const originalBuildings = titanBuildingsData;
    titanBuildingsData = slotsWithIndex;
    const assignedPlayers = distributePlayers();
    titanBuildingsData = originalBuildings;
    
    for (let i = 0; i < titanBuildingsData.length; i++) {
        const b = titanBuildingsData[i];
        const container = document.getElementById('rec-' + i);
        if (!container) continue;
        
        const best = assignedPlayers[i];
        const minRequired = Math.ceil(b.enemyPower * titanMinPercent / 100);
        
        if (best) {
            let assignedNumber = 1;
            for (let j = 0; j < i; j++) {
                if (assignedPlayers[j] && assignedPlayers[j].nickname === best.nickname) {
                    assignedNumber++;
                }
            }
            
            const isGuaranteed = best.last_power >= minRequired;
            const guaranteeIcon = isGuaranteed ? '<i class="fas fa-check-circle text-success" title="гарантия"></i>' : '<i class="fas fa-exclamation-triangle text-warning" title="риск"></i>';
            
            container.innerHTML = `
                <div class="recommend-player">
                    <div>
                        <strong>${escapeHtml(best.nickname)}(${assignedNumber}/2)</strong>
                        <span class="badge-spec">${best.specialization === 'anti_titan' ? '<i class="fas fa-star"></i> Anti-Titan' : 'Universal'}</span>
                        ${guaranteeIcon} 
                    </div>
                    <div class="small mt-1">
                        Требуется: <i class="fas fa-bolt"></i> ${minRequired} (сила ${best.last_power})
                        ${best.last_element ? `| <span class="titan-badge" style="background: ${titanColors[best.last_element] || '#808080'}">${escapeHtml(titanNames[best.last_element] || '?')}</span>` : ''}
                    </div>
                </div>
            `;
        } else {
            container.innerHTML = `
                <div class="recommend-title">Рекомендация:</div>
                <div class="text-muted"><i class="fas fa-exclamation-triangle"></i> Нет доступных игроков</div>
            `;
        }
    }
}

function updateTitanPercentUI() {
    const slider = document.getElementById('minPercent');
    const valueSpan = document.getElementById('percentValue');
    if (slider) slider.value = titanMinPercent;
    if (valueSpan) valueSpan.textContent = titanMinPercent + '%';
    localStorage.setItem('titan_min_percent', titanMinPercent);
    updateTitanRecommendations();
}

// ========== ИНИЦИАЛИЗАЦИЯ ==========
function initTitanHelper(data) {
    titanPlayersData = data.players || [];
    titanAssignedCount = data.assignedCount || {};
    titanBuildingsData = data.buildings || [];
    titanColors = data.titanColors || {};
    titanNames = data.titanNames || {};
    
    // Ползунок процента
    const slider = document.getElementById('minPercent');
    const resetBtn = document.getElementById('resetPercent');
    
    if (slider) {
        const newSlider = slider.cloneNode(true);
        slider.parentNode.replaceChild(newSlider, slider);
        newSlider.addEventListener('input', function(e) {
            titanMinPercent = parseInt(e.target.value);
            updateTitanPercentUI();
        });
    }
    
    if (resetBtn) {
        const newResetBtn = resetBtn.cloneNode(true);
        resetBtn.parentNode.replaceChild(newResetBtn, resetBtn);
        newResetBtn.addEventListener('click', function() {
            titanMinPercent = 90;
            updateTitanPercentUI();
        });
    }
    
    // Переключатель алгоритмов
    const radioVariant1 = document.getElementById('algo_variant1');
    const radioVariant2 = document.getElementById('algo_variant2');
    
    if (radioVariant1 && radioVariant2) {
        if (currentAlgorithm === 'variant1') {
            radioVariant1.checked = true;
        } else {
            radioVariant2.checked = true;
        }
        
        radioVariant1.addEventListener('change', function() {
            if (this.checked) {
                currentAlgorithm = 'variant1';
                localStorage.setItem('titan_algorithm', 'variant1');
                updateTitanRecommendations();
            }
        });
        
        radioVariant2.addEventListener('change', function() {
            if (this.checked) {
                currentAlgorithm = 'variant2';
                localStorage.setItem('titan_algorithm', 'variant2');
                updateTitanRecommendations();
            }
        });
    }
    
    updateTitanPercentUI();
}