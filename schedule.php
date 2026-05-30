<?php
require_once 'includes/auth.php';
require_once 'includes/config.php';

$pageTitle = 'Расписание боёв';
require_once 'includes/header.php';

$weekStart = (new DateTime())->modify('monday this week')->format('Y-m-d');
$isSunday = (date('N') == 7);

// Проверяем, есть ли данные на текущую неделю (в какой лиге)
$stmt = $pdo->prepare("SELECT league, our_position, monday, tuesday, wednesday, thursday, friday FROM weekly_schedule WHERE week_start_date = ?");
$stmt->execute([$weekStart]);
$existing = $stmt->fetch();

$hasSchedule = ($existing !== false);
$currentLeague = $hasSchedule ? $existing['league'] : null;
?>

<div class="container mt-4" style="max-width: 700px;">
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0"><i class="fas fa-calendar-alt"></i> Расписание на неделю <?= date('d.m.Y', strtotime($weekStart)) ?> - <?= date('d.m.Y', strtotime($weekStart . ' +4 days')) ?></h4>
        </div>
        <div class="card-body">
            
            <?php if ($hasSchedule): ?>
                <!-- ===== ЕСТЬ РАСПИСАНИЕ ===== -->
                <div class="mb-4">
                    <div class="bg-<?= $currentLeague == 'bronze' ? 'warning text-dark' : 'secondary text-white' ?> p-2 rounded-top">
                        <i class="fas fa-medal"></i> <strong><?= $currentLeague == 'bronze' ? 'Бронзовая' : 'Серебряная' ?> лига</strong>
                        <span class="float-end">Наше место: <strong><?= $existing['our_position'] ?></strong></span>
                    </div>
                    <div class="border rounded-bottom p-3">
                        <div class="row text-center g-2">
                            <div class="col"><span class="badge bg-primary">Пн</span><br><span class="fs-4 fw-bold text-success"><?= $existing['monday'] ?></span></div>
                            <div class="col"><span class="badge bg-primary">Вт</span><br><span class="fs-4 fw-bold text-success"><?= $existing['tuesday'] ?></span></div>
                            <div class="col"><span class="badge bg-primary">Ср</span><br><span class="fs-4 fw-bold text-success"><?= $existing['wednesday'] ?></span></div>
                            <div class="col"><span class="badge bg-primary">Чт</span><br><span class="fs-4 fw-bold text-success"><?= $existing['thursday'] ?></span></div>
                            <div class="col"><span class="badge bg-primary">Пт</span><br><span class="fs-4 fw-bold text-success"><?= $existing['friday'] ?></span></div>
                        </div>
                    </div>
                </div>
                
            <?php elseif ($isSunday): ?>
                <!-- ===== НЕТ РАСПИСАНИЯ, НО СЕГОДНЯ ВОСКРЕСЕНЬЕ ===== -->
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Сегодня воскресенье — можно создать расписание на эту неделю.
                </div>
                
                <div class="mb-4">
                    <label class="form-label"><i class="fas fa-trophy"></i> Выберите лигу и наше место</label>
                    <div class="row g-2">
                        <div class="col-6">
                            <select id="leagueSelect" class="form-select">
                                <option value="bronze">🥉 Бронзовая лига (1-30)</option>
                                <option value="silver">🥈 Серебряная лига (1-10)</option>
                            </select>
                        </div>
                        <div class="col-4">
                            <input type="number" id="ourPosition" class="form-control" placeholder="Место" min="1" max="30">
                        </div>
                        <div class="col-2">
                            <button id="createBtn" class="btn btn-success w-100">
                                <i class="fas fa-save"></i>
                            </button>
                        </div>
                    </div>
                    <div id="message" class="mt-2 small"></div>
                </div>
                
            <?php else: ?>
                <!-- ===== НЕТ РАСПИСАНИЯ И НЕ ВОСКРЕСЕНЬЕ ===== -->
                <div class="alert alert-danger text-center">
                    <i class="fas fa-exclamation-triangle fa-2x d-block mb-2"></i>
                    <h5>Расписание на эту неделю не создано</h5>
                    <p>Создание расписания возможно только в <strong>воскресенье</strong>.</p>
                    <p class="mb-0 text-muted">Вы опоздали, расписание на эту неделю уже зафиксировано.</p>
                </div>
            <?php endif; ?>
            
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#leagueSelect').on('change', function() {
        let max = $(this).val() === 'bronze' ? 30 : 10;
        $('#ourPosition').attr('max', max).attr('placeholder', '1-' + max);
    });
    
    $('#createBtn').on('click', function() {
        let league = $('#leagueSelect').val();
        let position = $('#ourPosition').val();
        let maxPos = league === 'bronze' ? 30 : 10;
        
        if (!position || position < 1 || position > maxPos) {
            $('#message').html('<span class="text-danger">Введите место от 1 до ' + maxPos + '</span>');
            return;
        }
        
        $('#message').html('<span class="text-info"><i class="fas fa-spinner fa-spin"></i> Создание...</span>');
        $('#createBtn').prop('disabled', true);
        
        $.ajax({
            url: 'ajax/ScheduleHandler.php',
            type: 'POST',
            data: {
                action: 'get_schedule',
                league: league,
                our_position: position
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    $('#message').html('<span class="text-danger">' + response.error + '</span>');
                    $('#createBtn').prop('disabled', false);
                }
            },
            error: function() {
                $('#message').html('<span class="text-danger">Ошибка сервера</span>');
                $('#createBtn').prop('disabled', false);
            }
        });
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>