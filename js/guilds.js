// guilds.js
$(document).ready(function() {
    loadGuilds();
    attachEvents();
});

function attachEvents() {
    $('#createGuildBtn').on('click', openGuildModal);
    $('#saveGuildBtn').on('click', saveGuild);
    $('#searchPlayerBtn').on('click', searchPlayers);
    $('#playerSearchInput').on('input', function() {
        // поиск при вводе (срабатывает с 1 символа)
        searchPlayers();
    });
}

function loadGuilds() {
    $('#guildsList').html('<div class="loading-placeholder"><i class="fas fa-spinner fa-spin"></i> Загрузка...</div>');
    $.ajax({
        url: 'ajax/PlayerGuildHandler.php?action=get_guilds',
        type: 'GET',
        dataType: 'json',
        success: function(guilds) {
            if (!guilds.length) {
                $('#guildsList').html('<div class="empty-placeholder">Нет гильдий. Создайте первую!</div>');
                return;
            }
            renderGuildsTable(guilds);
        },
        error: function() {
            $('#guildsList').html('<div class="empty-placeholder">Ошибка загрузки</div>');
        }
    });
}

function renderGuildsTable(guilds) {
    let html = '';
    guilds.forEach(function(guild) {
        html += `
            <div class="guild-row">
                <div class="grid-cell">${guild.id}</div>
                <div class="grid-cell"><strong>${escapeHtml(guild.name)}</strong></div>
                <div class="grid-cell guild-actions">
                    <a href="players.php?guild_id=${guild.id}" class="btn btn-primary-custom btn-sm">
                        <i class="fas fa-users"></i><span class="d-none d-sm-block"> Игроки</span>
                    </a>
                    <button class="btn btn-warning-custom btn-sm" onclick="editGuild(${guild.id}, '${escapeHtml(guild.name)}')">
                        <i class="fas fa-edit"></i>
                    </button>
                </div>
            </div>
        `;
    });
    $('#guildsList').html(html);
}

function openGuildModal() {
    $('#guild_id').val('');
    $('#guild_name').val('');
    $('#guildModal').modal('show');
}

function editGuild(id, name) {
    $('#guild_id').val(id);
    $('#guild_name').val(name);
    $('#guildModal').modal('show');
}

function saveGuild() {
    const id = $('#guild_id').val();
    const name = $('#guild_name').val().trim();
    if (!name) {
        alert('Введите название гильдии');
        return;
    }
    const action = id ? 'update_guild' : 'add_guild';
    $.ajax({
        url: 'ajax/PlayerGuildHandler.php',
        type: 'POST',
        data: { action: action, id: id, name: name },
        dataType: 'json',
        success: function(res) {
            if (res.success) {
                $('#guildModal').modal('hide');
                loadGuilds();
            } else {
                alert('Ошибка: ' + (res.error || 'Неизвестная ошибка'));
            }
        }
    });
}


function searchPlayers() {
    const nickname = $('#playerSearchInput').val().trim();
    if (nickname.length === 0) {
        $('#playerSearchResults').html('');
        return;
    }
    $('#playerSearchResults').html('<div class="loading-placeholder"><i class="fas fa-spinner fa-spin"></i> Поиск...</div>');
    $.ajax({
        url: `ajax/PlayerGuildHandler.php?action=search_players&nickname=${encodeURIComponent(nickname)}`,
        type: 'GET',
        dataType: 'json',
        success: function(res) {
            if (res.success && res.players.length) {
                renderSearchResults(res.players);
            } else {
                $('#playerSearchResults').html('<div class="empty-placeholder">Игроки не найдены</div>');
            }
        },
        error: function() {
            $('#playerSearchResults').html('<div class="empty-placeholder">Ошибка поиска</div>');
        }
    });
}

function renderSearchResults(players) {
    let html = '';
    players.forEach(p => {
        html += `
            <div class="player-result-card">
                <div class="player-result-avatar"><i class="fas fa-user-circle"></i></div>
                <div class="player-result-info">
                    <div class="player-result-nick">${escapeHtml(p.nickname)}</div>
                    <div class="player-result-guild"><i class="fas fa-users"></i> ${escapeHtml(p.guild_name || 'Нет гильдии')}</div>
                </div>
            </div>
        `;
    });
    $('#playerSearchResults').html(html);
}