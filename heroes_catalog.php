<?php
require_once 'includes/auth.php';
require_once 'includes/config.php';

$pageTitle = 'Управление героями';
require_once 'includes/header.php';

// Получаем фракции и классы для выпадающих списков
$factions = ['Вечность', 'Природа', 'Честь', 'Хаос', 'Прогресс', 'Таинство'];
$classes = ['танк', 'боец', 'стрелок', 'маг', 'поддержка', 'контроль', 'целитель'];
?>

<div class="container mt-4 page-heroes-catalog">
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0">
                <i class="fa-solid fa-user-ninja"></i> 
                Герои
                <span id="heroesCount" class="badge bg-light text-dark ms-2">0</span>
            </h4>
        </div>
        <div class="card-body">
            <!-- Форма добавления -->
            <form id="addHeroForm" enctype="multipart/form-data">
                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-4">
                        <input type="text" id="newHeroName" class="form-control" placeholder="Имя героя" required>
                    </div>
                    <div class="col-12 col-md-2">
                        <select id="newHeroFaction" class="form-select">
                            <option value="">Фракция</option>
                            <?php foreach ($factions as $f): ?>
                                <option value="<?= $f ?>"><?= $f ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-2">
                        <select id="newHeroClass" class="form-select">
                            <option value="">Класс</option>
                            <?php foreach ($classes as $c): ?>
                                <option value="<?= $c ?>"><?= $c ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-4">
                        <input type="file" id="newHeroImage" class="form-control" accept="image/*">
                    </div>
                </div>
                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-3">
                        <label class="form-label small">Урон (0-5)</label>
                        <select id="newHeroDamage" class="form-select">
                            <?php for ($i = 0; $i <= 5; $i++): ?>
                                <option value="<?= $i ?>" <?= $i == 3 ? 'selected' : '' ?>><?= $i ?> ⭐</option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label small">Защита (0-5)</label>
                        <select id="newHeroDefense" class="form-select">
                            <?php for ($i = 0; $i <= 5; $i++): ?>
                                <option value="<?= $i ?>" <?= $i == 3 ? 'selected' : '' ?>><?= $i ?> ⭐</option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label small">Поддержка (0-5)</label>
                        <select id="newHeroSupport" class="form-select">
                            <?php for ($i = 0; $i <= 5; $i++): ?>
                                <option value="<?= $i ?>" <?= $i == 3 ? 'selected' : '' ?>><?= $i ?> ⭐</option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label small">Исцеление (0-5)</label>
                        <select id="newHeroHealing" class="form-select">
                            <?php for ($i = 0; $i <= 5; $i++): ?>
                                <option value="<?= $i ?>" <?= $i == 3 ? 'selected' : '' ?>><?= $i ?> ⭐</option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                <div class="row g-3 mb-4">
                    <div class="col-12">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-plus"></i> Добавить героя
                        </button>
                    </div>
                </div>
            </form>
            
            <!-- Сортировка и поиск -->
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label"><i class="fas fa-sort"></i> Сортировка</label>
                    <select id="sortType" class="form-select">
                        <option value="name">По имени (А-Я)</option>
                        <option value="name_desc">По имени (Я-А)</option>
                        <option value="popularity" selected>По популярности (↓)</option>
                        <option value="popularity_asc">По популярности (↑)</option>
                        <option value="faction">По фракции</option>
                        <option value="class">По классу</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label"><i class="fas fa-filter"></i> Фракция</label>
                    <select id="factionFilter" class="form-select">
                        <option value="">Все фракции</option>
                        <?php foreach ($factions as $f): ?>
                            <option value="<?= $f ?>"><?= $f ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label"><i class="fas fa-filter"></i> Класс</label>
                    <select id="classFilter" class="form-select">
                        <option value="">Все классы</option>
                        <?php foreach ($classes as $c): ?>
                            <option value="<?= $c ?>"><?= $c ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-12">
                    <label class="form-label"><i class="fas fa-search"></i> Поиск</label>
                    <input type="text" id="searchHero" class="form-control" placeholder="Введите имя героя...">
                </div>
            </div>
            
            <!-- Список героев -->
            <div id="heroesList" class="row g-3">
                <div class="col-12 text-center text-muted">Загрузка...</div>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно для редактирования героя -->
<div class="modal fade" id="editHeroModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit"></i> Редактирование героя</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editHeroId">
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Имя героя</label>
                        <input type="text" id="editHeroName" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Фракция</label>
                        <select id="editHeroFaction" class="form-select">
                            <option value="">Выберите фракцию</option>
                            <?php foreach ($factions as $f): ?>
                                <option value="<?= $f ?>"><?= $f ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="row g-3 mt-2">
                    <div class="col-md-6">
                        <label class="form-label">Класс</label>
                        <select id="editHeroClass" class="form-select">
                            <option value="">Выберите класс</option>
                            <?php foreach ($classes as $c): ?>
                                <option value="<?= $c ?>"><?= $c ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Синергия</label>
                        <div class="input-group">
                            <input type="text" id="editHeroSynergyText" class="form-control" readonly placeholder="Не выбрано">
                            <button class="btn btn-outline-primary" type="button" onclick="openSynergySelector()">
                                <i class="fas fa-users"></i> Выбрать
                            </button>
                            <button class="btn btn-outline-danger" type="button" onclick="clearSynergy()">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <small class="text-muted">Герои, с которыми есть синергия</small>
                    </div>
                </div>
                
                <div class="row g-3 mt-2">
                    <div class="col-md-3">
                        <label class="form-label">Урон (0-5)</label>
                        <select id="editHeroDamage" class="form-select">
                            <?php for ($i = 0; $i <= 5; $i++): ?>
                                <option value="<?= $i ?>"><?= $i ?> ⭐</option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Защита (0-5)</label>
                        <select id="editHeroDefense" class="form-select">
                            <?php for ($i = 0; $i <= 5; $i++): ?>
                                <option value="<?= $i ?>"><?= $i ?> ⭐</option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Поддержка (0-5)</label>
                        <select id="editHeroSupport" class="form-select">
                            <?php for ($i = 0; $i <= 5; $i++): ?>
                                <option value="<?= $i ?>"><?= $i ?> ⭐</option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Исцеление (0-5)</label>
                        <select id="editHeroHealing" class="form-select">
                            <?php for ($i = 0; $i <= 5; $i++): ?>
                                <option value="<?= $i ?>"><?= $i ?> ⭐</option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                
                <div class="row g-3 mt-2">
                    <div class="col-md-12">
                        <label class="form-label">Изображение</label>
                        <input type="file" id="editHeroImage" class="form-control" accept="image/*">
                        <div class="mt-2 text-center" id="editImagePreview"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" onclick="deleteHeroFromModal()">
                    <i class="fas fa-trash"></i> Удалить
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> Отмена
                </button>
                <button type="button" class="btn btn-primary" onclick="saveHeroChanges()">
                    <i class="fas fa-save"></i> Сохранить
                </button>
            </div>
        </div>
    </div>
</div>

<script src="/js/hero-selector.js"></script>

<script>
let allHeroes = [];
let maxPopularity = 0;
let synergySelector = null;
let currentSynergyIds = [];

// ========== ФУНКЦИИ ПОПУЛЯРНОСТИ ==========
function getPopularityColor(count) {
    if (maxPopularity === 0 || count === 0) return '#6c757d';
    
    let percent = (count / maxPopularity);
    
    // Плавный переход от серого (#6c757d) к красному (#dc3545)
    let r = Math.round(108 + (220 - 108) * percent);
    let g = Math.round(117 + (53 - 117) * percent);
    let b = Math.round(125 + (69 - 125) * percent);
    
    return `rgb(${r}, ${g}, ${b})`;
}

function getPopularityPercent(count) {
    if (maxPopularity === 0 || count === 0) return 0;
    return Math.round((count / maxPopularity) * 100);
}

function getStarsByPopularity(count) {
    if (maxPopularity === 0 || count === 0) {
        return '<i class="far fa-star"></i><i class="far fa-star"></i><i class="far fa-star"></i><i class="far fa-star"></i><i class="far fa-star"></i>';
    }
    let starCount = (count / maxPopularity) * 5;
    let fullStars = Math.floor(starCount);
    let hasHalfStar = (starCount - fullStars) >= 0.5;
    
    let stars = '';
    for (let i = 0; i < fullStars; i++) stars += '<i class="fas fa-star" style="color:#ffc107;"></i>';
    if (hasHalfStar) stars += '<i class="fas fa-star-half-alt" style="color:#ffc107;"></i>';
    let emptyStars = 5 - fullStars - (hasHalfStar ? 1 : 0);
    for (let i = 0; i < emptyStars; i++) stars += '<i class="far fa-star" style="color:#ffc107;"></i>';
    return stars;
}

function renderRatingStars(value) {
    if (value === 0 || value === null || value === undefined) {
        return '<span class="text-muted small" style="font-size:0.7rem;">0/5</span>';
    }
    let stars = '';
    for (let i = 1; i <= 5; i++) {
        if (i <= value) {
            stars += '<i class="fas fa-star" style="color:#ffc107; font-size:0.7rem;"></i>';
        } else {
            stars += '<i class="far fa-star" style="color:#ffc107; font-size:0.7rem;"></i>';
        }
    }
    return stars;
}

// ========== СИНЕРГИЯ ==========
function initSynergySelector() {
    synergySelector = new HeroSelector({
        modalId: 'synergySelectorModal',
        maxHeroes: 50,
        allHeroes: allHeroes,
        onConfirm: function(selectedIds) {
            currentSynergyIds = selectedIds;
            updateSynergyDisplay();
        },
        onCancel: function() {}
    });
    synergySelector.init();
}

function openSynergySelector() {
    if (synergySelector) {
        synergySelector.allHeroes = allHeroes;
        synergySelector.heroesWithStats = allHeroes;
    }
    synergySelector.setSelectedIds(currentSynergyIds);
    synergySelector.open();
}

function updateSynergyDisplay() {
    if (!currentSynergyIds || currentSynergyIds.length === 0) {
        $('#editHeroSynergyText').val('');
        $('#editHeroSynergyText').attr('placeholder', 'Не выбрано');
    } else {
        const selectedHeroes = allHeroes.filter(h => currentSynergyIds.includes(h.id));
        const names = selectedHeroes.map(h => h.name).join(', ');
        $('#editHeroSynergyText').val(names);
    }
}

function clearSynergy() {
    currentSynergyIds = [];
    updateSynergyDisplay();
}

// ========== ЗАГРУЗКА И ОТОБРАЖЕНИЕ ==========
function loadHeroes() {
    $.ajax({
        url: 'ajax/HeroHandler.php?action=get_heroes_sorted',
        type: 'GET',
        dataType: 'json',
        success: function(heroes) {
            allHeroes = heroes;
            maxPopularity = Math.max(...heroes.map(h => h.usage_count || 0), 1);
            $('#heroesCount').text(heroes.length);
            
            if (synergySelector) {
                synergySelector.allHeroes = allHeroes;
                synergySelector.heroesWithStats = allHeroes;
                synergySelector.maxPopularity = maxPopularity;
            }
            
            filterAndSortHeroes();
        },
        error: function() {
            $('#heroesList').html('<div class="col-12"><div class="alert alert-danger">Ошибка загрузки</div></div>');
        }
    });
}

function renderHeroes(heroes) {
    if (heroes.length === 0) {
        $('#heroesList').html('<div class="col-12"><div class="alert alert-info">Нет героев. Добавьте первого!</div></div>');
        return;
    }
    
    let html = '';
    heroes.forEach(function(hero) {
        let imageHtml = '';
        if (hero.image && hero.image !== '') {
            imageHtml = `<img src="${hero.image}" class="hero-avatar" onerror="this.src='data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%2250%22%20height%3D%2250%22%20viewBox%3D%220%200%2050%2050%22%3E%3Crect%20width%3D%2250%22%20height%3D%2250%22%20fill%3D%22%236c757d%22%2F%3E%3Ctext%20x%3D%2250%25%22%20y%3D%2250%25%22%20text-anchor%3D%22middle%22%20dy%3D%22.3em%22%20fill%3D%22white%22%20font-size%3D%2220%22%3E${escapeHtml(hero.name.charAt(0).toUpperCase())}%3C%2Ftext%3E%3C%2Fsvg%3E'">`;
        } else {
            imageHtml = `<div class="hero-avatar d-flex align-items-center justify-content-center text-white fw-bold" style="background-color: #6c757d;">${escapeHtml(hero.name.charAt(0).toUpperCase())}</div>`;
        }
        
        const popularityColor = getPopularityColor(hero.usage_count);
        const popularityPercent = getPopularityPercent(hero.usage_count);
        const stars = getStarsByPopularity(hero.usage_count);
        
        html += `
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div class="d-flex align-items-center gap-3">
                                ${imageHtml}
                                <div>
                                    <span class="fw-bold">${escapeHtml(hero.name)}</span>
                                    ${hero.faction ? `<span class="badge bg-secondary ms-1">${escapeHtml(hero.faction)}</span>` : ''}
                                    ${hero.class ? `<span class="badge bg-info ms-1">${escapeHtml(hero.class)}</span>` : ''}
                                </div>
                            </div>
                            <div class="action">
                                <button class="btn btn-sm btn-warning" onclick="editHero(${hero.id})" title="Редактировать">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </div>
                        </div>
                        <!-- ИЗМЕНЕНО: теперь просто цифры, без звёзд -->
                        <div class="hero-stats mt-2 small">
                            <div class="d-flex justify-content-between flex-wrap gap-3">
                                <span><i class="fas fa-fist-raised"></i> Урон: ${hero.damage !== undefined && hero.damage !== null ? hero.damage : 0}</span>
                                <span><i class="fas fa-shield-alt"></i> Защита: ${hero.defense !== undefined && hero.defense !== null ? hero.defense : 0}</span>
                                <span><i class="fas fa-hands-helping"></i> Поддержка: ${hero.support !== undefined && hero.support !== null ? hero.support : 0}</span>
                                <span><i class="fas fa-heartbeat"></i> Исцеление: ${hero.healing !== undefined && hero.healing !== null ? hero.healing : 0}</span>
                            </div>
                        </div>
                        <div class="hero-popularity mt-2">
                            <span class="popularity-badge" style="background-color: ${popularityColor}; color: white;">
                                <i class="fas fa-chart-line"></i> ${popularityPercent}% (${hero.usage_count})
                            </span>
                            <span class="star-rating ms-2">
                                ${stars}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    $('#heroesList').html(html);
}

function filterAndSortHeroes() {
    let filtered = [...allHeroes];
    
    const factionFilter = $('#factionFilter').val();
    if (factionFilter) {
        filtered = filtered.filter(h => h.faction === factionFilter);
    }
    
    const classFilter = $('#classFilter').val();
    if (classFilter) {
        filtered = filtered.filter(h => h.class === classFilter);
    }
    
    const searchText = $('#searchHero').val().toLowerCase();
    if (searchText) {
        filtered = filtered.filter(h => h.name.toLowerCase().includes(searchText));
    }
    
    const sortType = $('#sortType').val();
    switch(sortType) {
        case 'name':
            filtered.sort((a, b) => a.name.localeCompare(b.name));
            break;
        case 'name_desc':
            filtered.sort((a, b) => b.name.localeCompare(a.name));
            break;
        case 'popularity':
            filtered.sort((a, b) => b.usage_count - a.usage_count);
            break;
        case 'popularity_asc':
            filtered.sort((a, b) => a.usage_count - b.usage_count);
            break;
        case 'faction':
            filtered.sort((a, b) => (a.faction || '').localeCompare(b.faction || ''));
            break;
        case 'class':
            filtered.sort((a, b) => (a.class || '').localeCompare(b.class || ''));
            break;
    }
    
    renderHeroes(filtered);
}

// ========== ДОБАВЛЕНИЕ ==========
$('#addHeroForm').on('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData();
    formData.append('action', 'add_hero');
    formData.append('name', $('#newHeroName').val().trim());
    formData.append('faction', $('#newHeroFaction').val());
    formData.append('class', $('#newHeroClass').val());
    formData.append('damage', $('#newHeroDamage').val());
    formData.append('defense', $('#newHeroDefense').val());
    formData.append('support', $('#newHeroSupport').val());
    formData.append('healing', $('#newHeroHealing').val());
    formData.append('image', $('#newHeroImage')[0].files[0]);
    
    $.ajax({
        url: 'ajax/HeroHandler.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $('#newHeroName').val('');
                $('#newHeroFaction').val('');
                $('#newHeroClass').val('');
                $('#newHeroDamage').val('3');
                $('#newHeroDefense').val('3');
                $('#newHeroSupport').val('3');
                $('#newHeroHealing').val('3');
                $('#newHeroImage').val('');
                loadHeroes();
            } else {
                alert('Ошибка: ' + response.error);
            }
        }
    });
});

// ========== РЕДАКТИРОВАНИЕ ==========
function editHero(id) {
    const hero = allHeroes.find(h => h.id == id);
    if (!hero) {
        console.error('Герой не найден', id);
        return;
    }
    
    $('#editHeroId').val(hero.id);
    $('#editHeroName').val(hero.name);
    $('#editHeroFaction').val(hero.faction || '');
    $('#editHeroClass').val(hero.class || '');
    $('#editHeroDamage').val(hero.damage !== undefined && hero.damage !== null ? hero.damage : 0);
    $('#editHeroDefense').val(hero.defense !== undefined && hero.defense !== null ? hero.defense : 0);
    $('#editHeroSupport').val(hero.support !== undefined && hero.support !== null ? hero.support : 0);
    $('#editHeroHealing').val(hero.healing !== undefined && hero.healing !== null ? hero.healing : 0);
    
    // Загружаем синергию
    if (hero.synergy) {
        if (Array.isArray(hero.synergy)) {
            currentSynergyIds = [...hero.synergy];
        } else if (typeof hero.synergy === 'string') {
            try {
                const parsed = JSON.parse(hero.synergy);
                currentSynergyIds = Array.isArray(parsed) ? parsed : [];
            } catch(e) {
                currentSynergyIds = [];
            }
        } else {
            currentSynergyIds = [];
        }
    } else {
        currentSynergyIds = [];
    }
    
    updateSynergyDisplay();
    
    // Превью картинки
    if (hero.image && hero.image !== '') {
        $('#editImagePreview').html(`<img src="${hero.image}" class="hero-preview" style="max-width:100px;">`);
    } else {
        $('#editImagePreview').html('<p class="text-muted">Нет изображения</p>');
    }
    
    $('#editHeroImage').val('');
    $('#editHeroModal').modal('show');
}

function saveHeroChanges() {
    const formData = new FormData();
    formData.append('action', 'update_hero');
    formData.append('id', $('#editHeroId').val());
    formData.append('name', $('#editHeroName').val().trim());
    formData.append('faction', $('#editHeroFaction').val());
    formData.append('class', $('#editHeroClass').val());
    formData.append('synergy', JSON.stringify(currentSynergyIds));
    formData.append('damage', $('#editHeroDamage').val());
    formData.append('defense', $('#editHeroDefense').val());
    formData.append('support', $('#editHeroSupport').val());
    formData.append('healing', $('#editHeroHealing').val());
    
    if ($('#editHeroImage')[0].files[0]) {
        formData.append('image', $('#editHeroImage')[0].files[0]);
    }
    
    $.ajax({
        url: 'ajax/HeroHandler.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $('#editHeroModal').modal('hide');
                loadHeroes();
            } else {
                alert('Ошибка: ' + response.error);
            }
        }
    });
}

function deleteHeroFromModal() {
    const id = $('#editHeroId').val();
    if (confirm('Удалить героя? Он пропадёт из составов игроков и синергии.')) {
        $.ajax({
            url: 'ajax/HeroHandler.php',
            type: 'POST',
            data: { 
                action: 'delete_hero',
                id: id 
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#editHeroModal').modal('hide');
                    loadHeroes();
                } else {
                    alert('Ошибка: ' + response.error);
                }
            }
        });
    }
}

// ========== INIT ==========
$(document).ready(function() {
    initSynergySelector();
    loadHeroes();
    
    $('#sortType, #factionFilter, #classFilter').on('change', filterAndSortHeroes);
    $('#searchHero').on('keyup', filterAndSortHeroes);
});
</script>

<style>
.hero-avatar {
    width: 50px;
    height: 50px;
    border-radius: 8px;
    object-fit: cover;
    background-color: #6c757d;
}
.hero-preview {
    width: 100px;
    height: 100px;
    object-fit: cover;
    border-radius: 10px;
    border: 1px solid #ddd;
}
.popularity-badge {
    font-size: 0.8rem;
    padding: 4px 10px;
    border-radius: 20px;
    font-weight: 500;
    transition: all 0.3s ease;
}
</style>

<?php require_once 'includes/footer.php'; ?>