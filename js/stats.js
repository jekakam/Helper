let currentChart = null;
let currentHeroData = [];
let currentTitanData = [];
let currentSort = { column: 'growth', direction: 'desc' };
let currentTab = 'heroes';

function sortData(data, column, direction) {
    return [...data].sort((a, b) => {
        let valA, valB;
        switch(column) {
            case 'nickname': 
                valA = a.nickname.toLowerCase(); 
                valB = b.nickname.toLowerCase(); 
                break;
            case 'fights': 
                valA = a.fights; 
                valB = b.fights; 
                break;
            case 'wins': 
                valA = a.wins; 
                valB = b.wins; 
                break;
            case 'win_rate': 
                valA = a.win_rate; 
                valB = b.win_rate; 
                break;
            case 'growth': 
                valA = a.growth; 
                valB = b.growth; 
                break;
            default: return 0;
        }
        if (typeof valA === 'string') {
            return direction === 'asc' ? valA.localeCompare(valB) : valB.localeCompare(valA);
        } else {
            return direction === 'asc' ? valA - valB : valB - valA;
        }
    });
}

function renderHeroTable() {
    let data = sortData(currentHeroData, currentSort.column, currentSort.direction);
    if (!data.length) { 
        $('#heroStatsContainer').html('<div class="empty-placeholder"><i class="fas fa-inbox"></i> Нет данных</div>'); 
        return; 
    }
    
    let html = '<table class="stats-table"><thead><tr>' +
        '<th class="sortable" data-column="nickname"><i class="fas fa-user"></i> Игрок <i class="fas fa-sort"></i></th>' +
        '<th><i class="fas fa-users"></i> Сборка героев</th>' +
        '<th class="sortable" data-column="fights"><i class="fas fa-fist-raised"></i> Боёв <i class="fas fa-sort"></i></th>' +
        '<th class="sortable" data-column="wins"><i class="fas fa-trophy"></i> Побед <i class="fas fa-sort"></i></th>' +
        '<th class="sortable" data-column="win_rate"><i class="fas fa-chart-line"></i> % побед <i class="fas fa-sort"></i></th>' +
        '<th class="sortable" data-column="growth"><i class="fas fa-chart-line"></i> Рост мощи <i class="fas fa-sort"></i></th>' +
        '<th><i class="fas fa-chart-line"></i></th>' +
        '</tr></thead><tbody>';
    
    for (let item of data) {
        let heroesHtml = '<div class="combo-heroes">';
        for (let hero of item.heroes) {
            heroesHtml += '<img src="' + (hero.image || '') + '" class="hero-mini" title="' + escapeHtml(hero.name) + '" onerror="this.style.display=\'none\'">';
        }
        heroesHtml += '</div>';
        
        let winRateClass = item.win_rate >= 70 ? 'win-rate-high' : (item.win_rate >= 50 ? 'win-rate-mid' : 'win-rate-low');
        let growthSign = item.growth > 0 ? '+' : '';
        let nicknameClean = escapeHtml(item.nickname);
        
        html += '<tr>' +
            '<td><strong>' + nicknameClean + '</strong></td>' +
            '<td>' + heroesHtml + '</td>' +
            '<td>' + item.fights + '</td>' +
            '<td>' + item.wins + '</td>' +
            '<td><span class="' + winRateClass + '">' + item.win_rate + '%</span></td>' +
            '<td class="trend-up">' + growthSign + item.growth + '</td>' +
            '<td><i class="fas fa-chart-line chart-icon" data-weekly=\'' + JSON.stringify(item.weekly) + '\' data-nickname=\'' + nicknameClean + '\' data-type="hero" data-heroes=\'' + JSON.stringify(item.heroes) + '\'></i></td>' +
            '</tr>';
    }
    html += '</tbody></table>';
    $('#heroStatsContainer').html(html);
    attachSortHandlers();
}

function renderTitanTable() {
    let data = sortData(currentTitanData, currentSort.column, currentSort.direction);
    if (!data.length) { 
        $('#titanStatsContainer').html('<div class="empty-placeholder"><i class="fas fa-inbox"></i> Нет данных</div>'); 
        return; 
    }
    
    let html = '<table class="stats-table"><thead><tr>' +
        '<th class="sortable" data-column="nickname"><i class="fas fa-user"></i> Игрок <i class="fas fa-sort"></i></th>' +
        '<th><i class="fas fa-dragon"></i> Стихия</th>' +
        '<th class="sortable" data-column="fights"><i class="fas fa-fist-raised"></i> Боёв <i class="fas fa-sort"></i></th>' +
        '<th class="sortable" data-column="wins"><i class="fas fa-trophy"></i> Побед <i class="fas fa-sort"></i></th>' +
        '<th class="sortable" data-column="win_rate"><i class="fas fa-chart-line"></i> % побед <i class="fas fa-sort"></i></th>' +
        '<th class="sortable" data-column="growth"><i class="fas fa-chart-line"></i> Рост мощи <i class="fas fa-sort"></i></th>' +
        '<th><i class="fas fa-chart-line"></i></th>' +
        '</tr></thead><tbody>';
    
    for (let item of data) {
        let winRateClass = item.win_rate >= 70 ? 'win-rate-high' : (item.win_rate >= 50 ? 'win-rate-mid' : 'win-rate-low');
        let growthSign = item.growth > 0 ? '+' : '';
        let nicknameClean = escapeHtml(item.nickname);
        let titanNameClean = escapeHtml(item.titan_name);
        
        html += '<tr>' +
            '<td><strong>' + nicknameClean + '</strong></td>' +
            '<td><span class="titan-color" style="background: ' + item.color + '"></span> ' + titanNameClean + '</td>' +
            '<td>' + item.fights + '</td>' +
            '<td>' + item.wins + '</td>' +
            '<td><span class="' + winRateClass + '">' + item.win_rate + '%</span></td>' +
            '<td class="trend-up">' + growthSign + item.growth + '</td>' +
            '<td><i class="fas fa-chart-line chart-icon" data-weekly=\'' + JSON.stringify(item.weekly) + '\' data-nickname=\'' + nicknameClean + '\' data-type="titan" data-titan=\'' + titanNameClean + '\'></i></td>' +
            '</tr>';
    }
    html += '</tbody></table>';
    $('#titanStatsContainer').html(html);
    attachSortHandlers();
}

function attachSortHandlers() {
    $('.sortable').off('click').on('click', function() {
        let column = $(this).data('column');
        if (currentSort.column === column) {
            currentSort.direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
        } else {
            currentSort.column = column;
            currentSort.direction = 'asc';
        }
        
        if (currentTab === 'heroes') {
            renderHeroTable();
        } else {
            renderTitanTable();
        }
    });
}

function loadHeroStats() {
    $('#heroStatsContainer').html('<div class="loading-placeholder"><i class="fas fa-spinner fa-spin"></i> Загрузка...</div>');
    $.ajax({
        url: 'ajax/StatsHandler.php?action=hero_stats',
        dataType: 'json',
        success: function(res) {
            if (res.success && res.data.length) {
                currentHeroData = res.data;
                renderHeroTable();
            } else {
                $('#heroStatsContainer').html('<div class="empty-placeholder"><i class="fas fa-inbox"></i> Нет данных по сборкам героев (минимум 2 боя с одинаковой сборкой)</div>');
            }
        },
        error: function() { 
            $('#heroStatsContainer').html('<div class="empty-placeholder"><i class="fas fa-exclamation-triangle"></i> Ошибка загрузки</div>'); 
        }
    });
}

function loadTitanStats() {
    $('#titanStatsContainer').html('<div class="loading-placeholder"><i class="fas fa-spinner fa-spin"></i> Загрузка...</div>');
    $.ajax({
        url: 'ajax/StatsHandler.php?action=titan_stats',
        dataType: 'json',
        success: function(res) {
            if (res.success && res.data.length) {
                currentTitanData = res.data;
                renderTitanTable();
            } else {
                $('#titanStatsContainer').html('<div class="empty-placeholder"><i class="fas fa-inbox"></i> Нет данных по титанам (чистые стихии, минимум 2 боя)</div>');
            }
        },
        error: function() { 
            $('#titanStatsContainer').html('<div class="empty-placeholder"><i class="fas fa-exclamation-triangle"></i> Ошибка загрузки</div>'); 
        }
    });
}

function showChart(weeklyData, nickname, type, titanName, heroes) {
    let title = '';
    if (type === 'hero' && heroes && heroes.length) {
        let heroNames = heroes.map(h => h.name).join(', ');
        title = nickname + ' \u2014 ' + heroNames;
    } else if (type === 'titan' && titanName) {
        title = nickname + ' \u2014 ' + titanName;
    } else {
        title = nickname + ' \u2014 динамика';
    }
    $('#chartModalTitle').html('<i class="fas fa-chart-line"></i> ' + title);
    
    let weeks = weeklyData.map(w => w.week);
    let powers = weeklyData.map(w => w.power);
    
    let detailsHtml = '<i class="fas fa-calendar-week"></i> <strong>Динамика по неделям:</strong><br>';
    for (let i = 0; i < weeklyData.length; i++) {
        let diff = i > 0 ? powers[i] - powers[i-1] : 0;
        let diffText = diff > 0 ? ' (+' + diff + ')' : (diff < 0 ? ' (' + diff + ')' : '');
        detailsHtml += '<span> ' + weeklyData[i].week + ': ' + powers[i] + diffText + ' |</span>';
    }
    $('#weeklyDetails').html(detailsHtml);
    
    let ctx = document.getElementById('statsChart').getContext('2d');
    if (currentChart) currentChart.destroy();
    
    currentChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: weeks,
            datasets: [{
                label: 'Мощность',
                data: powers,
                borderColor: '#4a90e2',
                backgroundColor: 'rgba(74, 144, 226, 0.1)',
                tension: 0.3,
                fill: true,
                pointRadius: 5,
                pointHoverRadius: 7
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(ctx) {
                            let val = ctx.raw;
                            let prev = ctx.dataset.data[ctx.dataIndex - 1];
                            if (prev) {
                                let diff = val - prev;
                                return 'Мощь: ' + val + ' (' + (diff > 0 ? '+' : '') + diff + ' от прошлой недели)';
                            }
                            return 'Мощь: ' + val;
                        }
                    }
                }
            }
        }
    });
    
    $('#chartModal').modal('show');
}

function refreshAllStats() { 
    loadHeroStats(); 
    loadTitanStats(); 
}

$(document).ready(function() {
    loadHeroStats();
    loadTitanStats();
    
    $('.tab-btn').on('click', function() {
        currentTab = $(this).data('tab');
        $('.tab-btn').removeClass('active');
        $(this).addClass('active');
        $('.tab-content').removeClass('active');
        $('#tab-' + currentTab).addClass('active');
    });
    
    // Обработчик клика по иконке графика
    $(document).on('click', '.chart-icon', function() {
        let weeklyData = $(this).data('weekly');
        let nickname = $(this).data('nickname');
        let type = $(this).data('type');
        
        if (type === 'hero') {
            let heroes = $(this).data('heroes');
            showChart(weeklyData, nickname, 'hero', null, heroes);
        } else {
            let titanName = $(this).data('titan');
            showChart(weeklyData, nickname, 'titan', titanName, null);
        }
    });
});