<?php
require_once 'includes/auth.php';
require_once 'includes/config.php';

$pageTitle = 'Мои бои';
require_once 'includes/header.php';

$userId = $_SESSION['admin_id'] ?? 0;
if (!$userId) {
    echo '<div class="container"><div class="alert alert-danger">Ошибка: пользователь не авторизован</div></div>';
    require_once 'includes/footer.php';
    exit;
}

$heroes = $pdo->query("SELECT id, name, image FROM heroes_catalog ORDER BY name")->fetchAll();
?>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4><i class="fas fa-history"></i> История боёв</h4>
        <button id="toggleFormBtn" class="btn btn-primary">
            <i class="fas fa-plus-circle"></i> Добавить бой
        </button>
    </div>

    <div id="battleFormContainer" style="display: none;">
        <div class="card shadow mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Новый бой</h5>
            </div>
            <div class="card-body">
                <form id="battleForm">
                    <input type="hidden" id="userId" value="<?= $userId ?>">
                    
                    <div class="row mb-4">
                        <div class="col-6">
                            <label class="form-label">Тип боя</label>
                            <select id="attack" class="form-select">
                                <option value="1">Атака</option>
                                <option value="0">Защита</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Результат</label>
                            <select id="result" class="form-select">
                                <option value="win">Победа</option>
                                <option value="loss">Поражение</option>
                            </select>
                        </div>
                    </div>

                    <!-- Сетка 3x7 -->
                    <div class="battlefield-grid">
                        <!-- Ряд 1 (y=1) -->
                        <div class="grid-row">
                            <div class="cell empty"></div>
                            <!-- (1,2) - наш герой 3 -->
                            <div class="cell hero-slot" data-side="our" data-pos="3">
                                <div class="slot-placeholder">+ Герой 3</div>
                                <div class="slot-filled" style="display:none;"></div>
                                <div class="slot-params" style="display:none;"></div>
                            </div>
                            <!-- (1,3) - наш герой 1 -->
                            <div class="cell hero-slot" data-side="our" data-pos="1">
                                <div class="slot-placeholder">+ Герой 1</div>
                                <div class="slot-filled" style="display:none;"></div>
                                <div class="slot-params" style="display:none;"></div>
                            </div>
                            <div class="cell empty"></div>
                            <!-- (1,5) - враг герой 1 (enemy) -->
                            <div class="cell hero-slot" data-side="enemy" data-pos="1">
                                <div class="slot-placeholder">+ Герой 1</div>
                                <div class="slot-filled" style="display:none;"></div>
                                <div class="slot-params" style="display:none;"></div>
                            </div>
                            <!-- (1,6) - враг герой 2 (enemy) -->
                            <div class="cell hero-slot" data-side="enemy" data-pos="2">
                                <div class="slot-placeholder">+ Герой 2</div>
                                <div class="slot-filled" style="display:none;"></div>
                                <div class="slot-params" style="display:none;"></div>
                            </div>
                            <div class="cell empty"></div>
                        </div>

                        <!-- Ряд 2 (y=2) -->
                        <div class="grid-row">
                            <!-- (2,1) - наш герой 5 -->
                            <div class="cell hero-slot" data-side="our" data-pos="5">
                                <div class="slot-placeholder">+ Герой 5</div>
                                <div class="slot-filled" style="display:none;"></div>
                                <div class="slot-params" style="display:none;"></div>
                            </div>
                            <div class="cell empty"></div>
                            <div class="cell empty"></div>
                            <div class="cell vs">VS</div>
                            <div class="cell empty"></div>
                            <div class="cell empty"></div>
                            <!-- (2,7) - враг герой 5 (enemy) -->
                            <div class="cell hero-slot" data-side="enemy" data-pos="5">
                                <div class="slot-placeholder">+ Герой 5</div>
                                <div class="slot-filled" style="display:none;"></div>
                                <div class="slot-params" style="display:none;"></div>
                            </div>
                        </div>

                        <!-- Ряд 3 (y=3) -->
                        <div class="grid-row">
                            <div class="cell empty"></div>
                            <!-- (3,2) - наш герой 4 -->
                            <div class="cell hero-slot" data-side="our" data-pos="4">
                                <div class="slot-placeholder">+ Герой 4</div>
                                <div class="slot-filled" style="display:none;"></div>
                                <div class="slot-params" style="display:none;"></div>
                            </div>
                            <!-- (3,3) - наш герой 2 -->
                            <div class="cell hero-slot" data-side="our" data-pos="2">
                                <div class="slot-placeholder">+ Герой 2</div>
                                <div class="slot-filled" style="display:none;"></div>
                                <div class="slot-params" style="display:none;"></div>
                            </div>
                            <div class="cell empty"></div>
                            <!-- (3,5) - враг герой 3 (enemy) -->
                            <div class="cell hero-slot" data-side="enemy" data-pos="3">
                                <div class="slot-placeholder">+ Герой 3</div>
                                <div class="slot-filled" style="display:none;"></div>
                                <div class="slot-params" style="display:none;"></div>
                            </div>
                            <!-- (3,6) - враг герой 4 (enemy) -->
                            <div class="cell hero-slot" data-side="enemy" data-pos="4">
                                <div class="slot-placeholder">+ Герой 4</div>
                                <div class="slot-filled" style="display:none;"></div>
                                <div class="slot-params" style="display:none;"></div>
                            </div>
                            <div class="cell empty"></div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-success w-100 mt-3">Сохранить бой</button>
                </form>
            </div>
        </div>
    </div>

    <div class="card shadow">
        <div class="card-header bg-secondary text-white">
            <h5 class="mb-0">История боёв</h5>
        </div>
        <div class="card-body">
            <div class="row g-2 mb-3">
                <div class="col-6 col-sm-4 col-md-3">
                    <select id="filterResult" class="form-select form-select-sm">
                        <option value="">Все результаты</option>
                        <option value="win">Победы</option>
                        <option value="loss">Поражения</option>
                    </select>
                </div>
                <div class="col-6 col-sm-4 col-md-3">
                    <select id="filterAttack" class="form-select form-select-sm">
                        <option value="">Все типы</option>
                        <option value="1">Атака</option>
                        <option value="0">Защита</option>
                    </select>
                </div>
            </div>
            <div id="battlesList"></div>
            <div id="pagination" class="mt-3 text-center"></div>
        </div>
    </div>
</div>

<!-- Модальное окно выбора героя (без HeroSelector, своя сетка) -->
<div class="modal fade" id="heroSelectModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Выбор героя</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row hero-grid">
                    <?php foreach ($heroes as $hero): ?>
                    <div class="col-md-3 col-sm-4 col-6 mb-2">
                        <div class="hero-card" data-id="<?= $hero['id'] ?>" data-name="<?= htmlspecialchars($hero['name']) ?>" data-image="<?= $hero['image'] ?>">
                            <img src="<?= $hero['image'] ?>" onerror="this.style.display='none'">
                            <span><?= htmlspecialchars($hero['name']) ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
            </div>
        </div>
    </div>
</div>

<style>
.battlefield-grid {
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    border-radius: 16px;
    padding: 20px;
}
.grid-row {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 8px;
    margin-bottom: 8px;
}
.cell {
    min-height: 100px;
    border-radius: 8px;
}
.cell.empty {
    background: transparent;
    min-height: auto;
}
.cell.vs {
    background: rgba(255,255,255,0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 24px;
    color: white;
}
.hero-slot {
    background: rgba(255,255,255,0.1);
    cursor: pointer;
    transition: all 0.2s;
    padding: 8px;
    position: relative;
}
.hero-slot:hover {
    background: rgba(255,255,255,0.2);
}
.slot-placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: #aaa;
    font-size: 12px;
}
.slot-filled {
    text-align: center;
    position: relative;
}
.slot-filled img {
    width: 50px;
    height: 50px;
    object-fit: cover;
    border-radius: 8px;
    margin-bottom: 4px;
}
.slot-filled .hero-name {
    font-size: 11px;
    color: white;
    font-weight: bold;
}
.edit-icon {
    position: absolute;
    top: 5px;
    right: 5px;
    font-size: 12px;
    color: #ffc107;
    cursor: pointer;
}
.slot-params {
    display: flex;
    gap: 4px;
    margin-top: 8px;
    flex-wrap: wrap;
    border-top: 1px solid rgba(255,255,255,0.2);
    padding-top: 8px;
}
.slot-params input {
    width: 45px;
    padding: 3px;
    font-size: 10px;
    text-align: center;
    border-radius: 4px;
    border: none;
}
.battle-history-item {
    border-left: 4px solid;
    padding: 8px;
    margin-bottom: 8px;
    border-radius: 6px;
    font-size: 12px;
}
.win-border { border-left-color: #28a745; background: #f0fff0; }
.loss-border { border-left-color: #dc3545; background: #fff0f0; }
.hero-mini { width: 24px; height: 24px; object-fit: cover; border-radius: 4px; }
.hero-grid .hero-card {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 8px;
    cursor: pointer;
    text-align: center;
    transition: all 0.2s;
}
.hero-grid .hero-card:hover {
    background: #e9ecef;
    transform: scale(1.02);
}
.hero-grid .hero-card img {
    width: 60px;
    height: 60px;
    object-fit: cover;
    border-radius: 8px;
    margin-bottom: 4px;
}
.hero-grid .hero-card span {
    display: block;
    font-size: 12px;
}
@media (max-width: 768px) {
    .grid-row { gap: 4px; }
    .slot-params input { width: 38px; }
    .slot-filled img { width: 35px; height: 35px; }
    .cell { min-height: 70px; }
}
</style>

<script>
let heroesData = <?= json_encode($heroes) ?>;
let currentSlot = null;

function setHeroToSlot(slotElement, hero) {
    let $slot = $(slotElement);
    $slot.data('hero-id', hero.id);
    $slot.data('hero-name', hero.name);
    $slot.data('hero-image', hero.image);

    $slot.find('.slot-placeholder').hide();
    $slot.find('.slot-filled').html(`
        <img src="${hero.image || ''}" onerror="this.style.display='none'">
        <div class="hero-name">${escapeHtml(hero.name)}</div>
        <i class="fas fa-edit edit-icon"></i>
    `).show();

    $slot.find('.slot-params').html(`
        <input type="number" placeholder="Ур" class="stat-level" value="0">
        <input type="number" placeholder="Тал" class="stat-talisman" value="0">
        <input type="number" placeholder="Рел" class="stat-relic" value="0">
        <input type="number" placeholder="Мощь" class="stat-power" value="0">
    `).show();
}

function openHeroSelector(slotElement) {
    currentSlot = slotElement;
    $('#heroSelectModal').modal('show');
}

$(document).ready(function() {
    // Клик по пустому слоту
    $(document).on('click', '.hero-slot .slot-placeholder', function(e) {
        e.stopPropagation();
        openHeroSelector($(this).closest('.hero-slot')[0]);
    });

    // Клик по иконке редактирования (замена героя)
    $(document).on('click', '.edit-icon', function(e) {
        e.stopPropagation();
        openHeroSelector($(this).closest('.hero-slot')[0]);
    });

    // Выбор героя в модалке
    $(document).on('click', '.hero-card', function() {
        if (currentSlot) {
            let hero = {
                id: $(this).data('id'),
                name: $(this).data('name'),
                image: $(this).data('image')
            };
            setHeroToSlot(currentSlot, hero);
            $('#heroSelectModal').modal('hide');
            currentSlot = null;
        }
    });

    // Тоггл формы
    $('#toggleFormBtn').on('click', function() {
        $('#battleFormContainer').slideToggle();
    });

    // Сохранение боя
    $('#battleForm').on('submit', function(e) {
        e.preventDefault();

        let battleData = {
            user_id: $('#userId').val(),
            attack: $('#attack').val(),
            result: $('#result').val(),
            note: '',
            heroes: []
        };

        $('.hero-slot').each(function() {
            let heroId = $(this).data('hero-id');
            if (heroId && heroId > 0) {
                battleData.heroes.push({
                    side: $(this).data('side'),
                    position: $(this).data('pos'),
                    hero_id: heroId,
                    hero_level: $(this).find('.stat-level').val() || 0,
                    talisman_level: $(this).find('.stat-talisman').val() || 0,
                    relic_level: $(this).find('.stat-relic').val() || 0,
                    power: $(this).find('.stat-power').val() || 0
                });
            }
        });

        if (battleData.heroes.length === 0) {
            alert('Добавьте хотя бы одного героя');
            return;
        }

        $.ajax({
            url: 'ajax/UserBattleHandler.php',
            type: 'POST',
            data: {
                action: 'save_battle',
                battle_data: JSON.stringify(battleData)
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) location.reload();
                else alert('Ошибка: ' + response.error);
            }
        });
    });

    // Загрузка истории боёв
    function loadBattles(page = 1) {
        $.ajax({
            url: 'ajax/UserBattleHandler.php',
            type: 'POST',
            data: {
                action: 'get_battles',
                page: page,
                result: $('#filterResult').val(),
                attack: $('#filterAttack').val()
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    let html = '';
                    response.battles.forEach(function(b) {
                        let borderClass = b.result === 'win' ? 'win-border' : 'loss-border';
                        let resultText = b.result === 'win' ? 'Победа' : 'Поражение';
                        let attackText = b.attack == 1 ? 'Атака' : 'Защита';
                        let date = new Date(b.battle_date).toLocaleDateString();

                        let heroesImg = '';
                        if (b.our_heroes) {
                            b.our_heroes.forEach(function(h) {
                                let hero = heroesData.find(hh => hh.id == h.hero_id);
                                if (hero && hero.image) {
                                    heroesImg += `<img src="${hero.image}" class="hero-mini" title="${hero.name}">`;
                                }
                            });
                        }

                        html += `
                            <div class="battle-history-item ${borderClass}">
                                <div class="d-flex justify-content-between">
                                    <strong>${resultText}</strong>
                                    <small>${date}</small>
                                </div>
                                <div class="small">${attackText}</div>
                                <div class="mt-1">${heroesImg}</div>
                            </div>
                        `;
                    });
                    $('#battlesList').html(html || '<div class="text-center text-muted">Нет боёв</div>');

                    if (response.total_pages > 1) {
                        let pages = '';
                        for (let i = 1; i <= response.total_pages; i++) {
                            pages += `<a href="#" data-page="${i}" class="btn btn-sm ${i == response.current_page ? 'btn-primary' : 'btn-light'} mx-1">${i}</a>`;
                        }
                        $('#pagination').html(pages);
                        $('[data-page]').on('click', function(e) {
                            e.preventDefault();
                            loadBattles($(this).data('page'));
                        });
                    } else {
                        $('#pagination').empty();
                    }
                }
            }
        });
    }

    $('#filterResult, #filterAttack').on('change', function() { loadBattles(1); });
    loadBattles();
});
</script>

<?php require_once 'includes/footer.php'; ?>