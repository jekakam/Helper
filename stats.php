<?php
require_once 'includes/auth.php';
require_once 'includes/config.php';

$pageTitle = 'Статистика гильдии';
require_once 'includes/header.php';
?>

<div class="container stats-container ">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h2><i class="fas fa-chart-line"></i> Статистика гильдии</h2>
        <button class="btn btn-sm btn-outline-secondary" onclick="refreshAllStats()">
            <i class="fas fa-sync-alt"></i> Обновить
        </button>
    </div>

    <div class="stats-tabs">
        <button class="tab-btn active" data-tab="heroes"><i class="fas fa-user-ninja"></i> Герои</button>
        <button class="tab-btn" data-tab="titans"><i class="fas fa-dragon"></i> Титаны</button>
    </div>

    <div id="tab-heroes" class="tab-content active">
        <div class="stats-card">
            <div class="card-header-stats">
                <i class="fas fa-chart-simple"></i>
                <h3><i class="fas fa-chart-line"></i> Прогресс и эффективность сборок героев</h3>
            </div>
            <div class="card-body-stats" id="heroStatsContainer">
                <div class="loading-placeholder"><i class="fas fa-spinner fa-spin"></i> Загрузка...</div>
            </div>
        </div>
    </div>

    <div id="tab-titans" class="tab-content">
        <div class="stats-card">
            <div class="card-header-stats">
                <i class="fas fa-chart-line"></i>
                <h3><i class="fas fa-bolt"></i> Прогресс титанов</h3>
            </div>
            <div class="card-body-stats" id="titanStatsContainer">
                <div class="loading-placeholder"><i class="fas fa-spinner fa-spin"></i> Загрузка...</div>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно для графика -->
<div class="modal fade" id="chartModal" tabindex="-1">
    <div class="modal-dialog modal-chart">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="chartModalTitle">Динамика</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="chart-container">
                    <canvas id="statsChart"></canvas>
                </div>
                <div id="weeklyDetails" class="weekly-stats"></div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0"></script>
<script src="/js/stats.js"></script>

<?php require_once 'includes/footer.php'; ?>