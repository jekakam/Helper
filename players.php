<?php
require_once 'includes/auth.php';
require_once 'includes/config.php';

$guild_id = $_GET['guild_id'] ?? 0;
if (!$guild_id) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM guilds WHERE id = ?");
$stmt->execute([$guild_id]);
$guild = $stmt->fetch();

if (!$guild) {
    header('Location: index.php');
    exit;
}

$pageTitle = "Игроки: {$guild['name']}";
require_once 'includes/header.php';

// Получаем информацию о лимите для гильдии 1
$isImperialGuild = ($guild_id == 1);
if ($isImperialGuild) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as active_count FROM players WHERE guild_id = 1 AND is_active = 1");
    $stmt->execute();
    $activeCount = $stmt->fetch()['active_count'];
    $activeLimit = 15;
    $activeLimitReached = ($activeCount >= $activeLimit);
} else {
    $activeLimitReached = false;
    $activeCount = 0;
    $activeLimit = 999;
}
?>

<style>
.status-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 500;
}
.status-active {
    background: #d4edda;
    color: #155724;
}
.status-inactive {
    background: #f8d7da;
    color: #721c24;
}
.limit-warning {
    background: #fff3cd;
    border: 1px solid #ffecb5;
    border-radius: 8px;
    padding: 10px 15px;
    margin-bottom: 15px;
    font-size: 0.85rem;
}
.limit-warning i {
    color: #ffc107;
    margin-right: 8px;
}
</style>

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <a href="guilds.php" class="btn btn-outline-secondary btn-sm me-2">
                <i class="fas fa-arrow-left"></i> Назад
            </a>
            <h2 class="d-inline-block">
                <i class="fas fa-users"></i> 
                <?= htmlspecialchars($guild['name']) ?>
                <span id="playersCount" class="badge bg-secondary">0</span>
            </h2>
        </div>
        <button class="btn btn-success" onclick="openPlayerModal()" <?= $activeLimitReached ? 'disabled' : '' ?>>
            <i class="fas fa-plus"></i> Добавить игрока
        </button>
    </div>
    
    <?php if ($isImperialGuild && $activeLimitReached): ?>
    <div class="limit-warning">
        <i class="fas fa-exclamation-triangle"></i>
        <strong>Внимание!</strong> Достигнут лимит активных игроков (15). 
        Чтобы добавить нового, нужно деактивировать кого-то из существующих.
    </div>
    <?php endif; ?>
    
    <?php if ($isImperialGuild): ?>
    <div class="limit-warning">
        <i class="fas fa-info-circle"></i>
        <strong>Лимит активных игроков:</strong> <?= $activeCount ?> / <?= $activeLimit ?> активных
        <span class="text-muted ms-2">(только для гильдии Imperial)</span>
    </div>
    <?php endif; ?>

    <!-- Таблица игроков -->
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Ник</th>
                            <th>Специализация</th>
                            <th>Статус</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody id="playersTableBody">
                        <tr><td colspan="5" class="text-center text-muted">Загрузка...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно для игрока -->
<div class="modal fade" id="playerModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user"></i> Игрок</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="player_id">
                <input type="hidden" id="player_guild_id" value="<?= $guild_id ?>">
                
                <div class="mb-3">
                    <label class="form-label">Никнейм <i class="fas fa-asterisk text-danger"></i></label>
                    <input type="text" id="nickname" class="form-control" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Специализация</label>
                    <select id="specialization" class="form-select">
                        <option value="universal"><i class="fas fa-balance-scale"></i> Универсал</option>
                        <option value="anti_hero"><i class="fas fa-sword"></i> Anti-Hero</option>
                        <option value="anti_titan"><i class="fas fa-shield-alt"></i> Anti-Titan</option>
                    </select>
                </div>
                
                <?php if ($isImperialGuild): ?>
                <div class="mb-3">
                    <label class="form-label">Статус</label>
                    <select id="is_active" class="form-select">
                        <option value="1">✅ Активен</option>
                        <option value="0">⭕ Неактивен</option>
                    </select>
                    <small class="text-muted">Неактивные игроки не отображаются в списке выбора в боях</small>
                </div>
                <?php else: ?>
                <input type="hidden" id="is_active" value="1">
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times"></i> Отмена</button>
                <button type="button" class="btn btn-primary" onclick="savePlayer()"><i class="fas fa-save"></i> Сохранить</button>
            </div>
        </div>
    </div>
</div>

<script>
let isImperialGuild = <?= $isImperialGuild ? 'true' : 'false' ?>;
let activeLimitReached = <?= $activeLimitReached ? 'true' : 'false' ?>;

function loadPlayers() {
    $.ajax({
        url: 'ajax/PlayerGuildHandler.php',
        type: 'GET',
        data: { 
            action: 'get_players',
            guild_id: <?= $guild_id ?> 
        },
        dataType: 'json',
        success: function(players) {
            $('#playersCount').text(players.length);
            
            if (players.length === 0) {
                $('#playersTableBody').html('<tr><td colspan="5" class="text-center">Нет игроков</td></tr>');
                return;
            }
            
            let html = '';
            let rowNumber = 1;
            
            players.forEach(function(p) {
                let specIcon = '';
                let specText = '';
                let specClass = '';
                
                switch(p.specialization) {
                    case 'anti_hero':
                        specIcon = '<i class="fas fa-sword"></i>';
                        specText = 'Anti-Hero';
                        specClass = 'bg-info';
                        break;
                    case 'anti_titan':
                        specIcon = '<i class="fas fa-shield-alt"></i>';
                        specText = 'Anti-Titan';
                        specClass = 'bg-warning';
                        break;
                    default:
                        specIcon = '<i class="fas fa-balance-scale"></i>';
                        specText = 'Universal';
                        specClass = 'bg-secondary';
                }
                
                let statusHtml = '';
                if (isImperialGuild) {
                    statusHtml = p.is_active == 1 
                        ? '<span class="status-badge status-active"><i class="fas fa-check-circle"></i> Активен</span>'
                        : '<span class="status-badge status-inactive"><i class="fas fa-circle"></i> Неактивен</span>';
                } else {
                    statusHtml = '<span class="status-badge status-active"><i class="fas fa-check-circle"></i> Активен</span>';
                }
                
                html += `
                    <tr>
                        <td>${rowNumber++}</td>
                        <td><strong>${escapeHtml(p.nickname)}</strong></td>
                        <td><span class="badge ${specClass}">${specIcon} ${specText}</span></td>
                        <td>${statusHtml}</td>
                        <td>
                            <button class="btn btn-sm btn-warning" onclick="editPlayer(${p.id})">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="deletePlayer(${p.id})">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });
            
            $('#playersTableBody').html(html);
        },
        error: function(xhr, status, error) {
            console.error('Ошибка:', error);
            $('#playersTableBody').html('<tr><td colspan="5" class="text-center text-danger">Ошибка загрузки</td></tr>');
        }
    });
}

function openPlayerModal() {
    if (activeLimitReached) {
        alert('Достигнут лимит активных игроков (15). Деактивируйте кого-то из существующих, чтобы добавить нового.');
        return;
    }
    $('#player_id').val('');
    $('#nickname').val('');
    $('#specialization').val('universal');
    $('#is_active').val('1');
    $('#playerModal').modal('show');
}

function editPlayer(playerId) {
    $.ajax({
        url: 'ajax/PlayerGuildHandler.php',
        type: 'POST',
        data: { 
            action: 'get_player_data',
            id: playerId 
        },
        dataType: 'json',
        success: function(data) {
            $('#player_id').val(data.id);
            $('#nickname').val(data.nickname);
            $('#specialization').val(data.specialization);
            if (isImperialGuild) {
                $('#is_active').val(data.is_active !== undefined ? data.is_active : 1);
            }
            $('#playerModal').modal('show');
        }
    });
}

function savePlayer() {
    const playerId = $('#player_id').val();
    const guildId = $('#player_guild_id').val();
    const nickname = $('#nickname').val().trim();
    const specialization = $('#specialization').val();
    const isActive = $('#is_active').val();
    
    if (!nickname) {
        alert('Введите никнейм');
        return;
    }
    
    // Проверка лимита для активных игроков перед сохранением
    if (isImperialGuild && isActive == 1) {
        // Проверяем, увеличивается ли количество активных
        if (!playerId) {
            // Новый игрок - проверка на лету
            $.ajax({
                url: 'ajax/PlayerGuildHandler.php',
                type: 'GET',
                data: { 
                    action: 'get_players',
                    guild_id: guildId 
                },
                async: false,
                success: function(players) {
                    let activeCount = players.filter(p => p.is_active == 1).length;
                    if (activeCount >= 15) {
                        alert('Достигнут лимит активных игроков (15). Деактивируйте кого-то из существующих.');
                        return false;
                    }
                }
            });
        }
    }
    
    const action = playerId ? 'update_player' : 'add_player';
    
    $.ajax({
        url: 'ajax/PlayerGuildHandler.php',
        type: 'POST',
        data: {
            action: action,
            id: playerId,
            guild_id: guildId,
            nickname: nickname,
            specialization: specialization,
            is_active: isActive
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $('#playerModal').modal('hide');
                loadPlayers();
                // Обновляем страницу для отображения актуального лимита
                if (isImperialGuild) {
                    location.reload();
                }
            } else {
                alert('Ошибка: ' + response.error);
            }
        },
        error: function(xhr, status, error) {
            alert('Ошибка запроса: ' + error);
        }
    });
}

function deletePlayer(playerId) {
    if (confirm('Удалить игрока?')) {
        $.ajax({
            url: 'ajax/PlayerGuildHandler.php',
            type: 'POST',
            data: { 
                action: 'delete_player',
                id: playerId 
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    loadPlayers();
                    if (isImperialGuild) {
                        location.reload();
                    }
                } else {
                    alert('Ошибка: ' + response.error);
                }
            }
        });
    }
}

$(document).ready(function() {
    loadPlayers();
});
</script>

<?php require_once 'includes/footer.php'; ?>