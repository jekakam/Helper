<?php
require_once 'includes/auth.php';
require_once 'includes/config.php';

$pageTitle = 'Гильдии';
require_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="page-header">
        <h2><i class="fas fa-users"></i> Гильдии</h2>
        <button class="btn btn-primary-custom" id="createGuildBtn">
            <i class="fas fa-plus"></i> Новая гильдия
        </button>
    </div>

    <div class="player-search-section">
        <h5><i class="fas fa-search"></i> Поиск игрока</h5>
        <div class="search-input-group">
            <input type="text" id="playerSearchInput" class="form-control-custom" placeholder="Погоняло">
            <button class="btn btn-info-custom" id="searchPlayerBtn">
                <i class="fas fa-search"></i><span class="d-none d-sm-block"> Найти</span>
            </button>
        </div>
        <div id="playerSearchResults" class="player-search-results"></div>
    </div>

    <div class="guilds-grid-container">
        <div class="guilds-grid-header">
            <div class="grid-cell">ID</div>
            <div class="grid-cell">Гильдии</div>
            <div class="grid-cell">Действия</div>
        </div>
        <div id="guildsList" class="guilds-grid-body">
            <div class="loading-placeholder">Загрузка...</div>
        </div>
    </div>
</div>

<div class="modal fade" id="guildModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Гильдия</h5>
                <button type="button" class="btn-close-custom" data-bs-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="guild_id">
                <div class="mb-3">
                    <label class="form-label-custom">Название гильдии</label>
                    <input type="text" id="guild_name" class="form-control-custom" placeholder="Например: Imperial" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary-custom" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-primary-custom" id="saveGuildBtn">Сохранить</button>
            </div>
        </div>
    </div>
</div>

<script src="js/guilds.js"></script>
<?php require_once 'includes/footer.php'; ?>