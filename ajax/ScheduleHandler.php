<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
header('Content-Type: application/json');

class ScheduleHandler {
    private $pdo;
    
    private $scheduleData = [
        'bronze_1'  => ['monday' => 8,  'tuesday' => 14, 'wednesday' => 20, 'thursday' => 26, 'friday' => 2],
        'bronze_2'  => ['monday' => 25, 'tuesday' => 19, 'wednesday' => 13, 'thursday' => 7,  'friday' => 1],
        'bronze_3'  => ['monday' => 10, 'tuesday' => 16, 'wednesday' => 22, 'thursday' => 28, 'friday' => 4],
        'bronze_4'  => ['monday' => 27, 'tuesday' => 21, 'wednesday' => 15, 'thursday' => 9,  'friday' => 3],
        'bronze_5'  => ['monday' => 12, 'tuesday' => 18, 'wednesday' => 24, 'thursday' => 30, 'friday' => 6],
        'bronze_6'  => ['monday' => 29, 'tuesday' => 23, 'wednesday' => 17, 'thursday' => 11, 'friday' => 5],
        'bronze_7'  => ['monday' => 2,  'tuesday' => 20, 'wednesday' => 26, 'thursday' => 8,  'friday' => 14],
        'bronze_8'  => ['monday' => 1,  'tuesday' => 25, 'wednesday' => 19, 'thursday' => 13, 'friday' => 7],
        'bronze_9'  => ['monday' => 4,  'tuesday' => 10, 'wednesday' => 16, 'thursday' => 22, 'friday' => 28],
        'bronze_10' => ['monday' => 3,  'tuesday' => 27, 'wednesday' => 21, 'thursday' => 15, 'friday' => 9],
        'bronze_11' => ['monday' => 6,  'tuesday' => 12, 'wednesday' => 18, 'thursday' => 24, 'friday' => 30],
        'bronze_12' => ['monday' => 5,  'tuesday' => 29, 'wednesday' => 23, 'thursday' => 17, 'friday' => 11],
        'bronze_13' => ['monday' => 8,  'tuesday' => 2,  'wednesday' => 20, 'thursday' => 26, 'friday' => 14],
        'bronze_14' => ['monday' => 1,  'tuesday' => 25, 'wednesday' => 19, 'thursday' => 13, 'friday' => 7],
        'bronze_15' => ['monday' => 10, 'tuesday' => 4,  'wednesday' => 16, 'thursday' => 22, 'friday' => 28],
        'bronze_16' => ['monday' => 3,  'tuesday' => 27, 'wednesday' => 21, 'thursday' => 15, 'friday' => 9],
        'bronze_17' => ['monday' => 12, 'tuesday' => 6,  'wednesday' => 18, 'thursday' => 24, 'friday' => 30],
        'bronze_18' => ['monday' => 5,  'tuesday' => 29, 'wednesday' => 23, 'thursday' => 17, 'friday' => 11],
        'bronze_19' => ['monday' => 2,  'tuesday' => 8,  'wednesday' => 20, 'thursday' => 26, 'friday' => 14],
        'bronze_20' => ['monday' => 25, 'tuesday' => 1,  'wednesday' => 19, 'thursday' => 13, 'friday' => 7],
        'bronze_21' => ['monday' => 4,  'tuesday' => 10, 'wednesday' => 16, 'thursday' => 22, 'friday' => 28],
        'bronze_22' => ['monday' => 27, 'tuesday' => 3,  'wednesday' => 21, 'thursday' => 15, 'friday' => 9],
        'bronze_23' => ['monday' => 6,  'tuesday' => 12, 'wednesday' => 18, 'thursday' => 24, 'friday' => 30],
        'bronze_24' => ['monday' => 29, 'tuesday' => 5,  'wednesday' => 23, 'thursday' => 17, 'friday' => 11],
        'bronze_25' => ['monday' => 2,  'tuesday' => 8,  'wednesday' => 14, 'thursday' => 20, 'friday' => 26],
        'bronze_26' => ['monday' => 1,  'tuesday' => 25, 'wednesday' => 19, 'thursday' => 13, 'friday' => 7],
        'bronze_27' => ['monday' => 4,  'tuesday' => 10, 'wednesday' => 16, 'thursday' => 22, 'friday' => 28],
        'bronze_28' => ['monday' => 3,  'tuesday' => 27, 'wednesday' => 21, 'thursday' => 15, 'friday' => 9],
        'bronze_29' => ['monday' => 6,  'tuesday' => 12, 'wednesday' => 18, 'thursday' => 24, 'friday' => 30],
        'bronze_30' => ['monday' => 5,  'tuesday' => 29, 'wednesday' => 23, 'thursday' => 17, 'friday' => 11],
        'silver_1'  => ['monday' => 4, 'tuesday' => 6, 'wednesday' => 8, 'thursday' => 10, 'friday' => 2],
        'silver_2'  => ['monday' => 9, 'tuesday' => 7, 'wednesday' => 5, 'thursday' => 3,  'friday' => 1],
        'silver_3'  => ['monday' => 2, 'tuesday' => 10, 'wednesday' => 4, 'thursday' => 6, 'friday' => 8],
        'silver_4'  => ['monday' => 1, 'tuesday' => 9, 'wednesday' => 7, 'thursday' => 5, 'friday' => 3],
        'silver_5'  => ['monday' => 8, 'tuesday' => 2, 'wednesday' => 10, 'thursday' => 4, 'friday' => 6],
        'silver_6'  => ['monday' => 3, 'tuesday' => 1, 'wednesday' => 9, 'thursday' => 7, 'friday' => 5],
        'silver_7'  => ['monday' => 6, 'tuesday' => 4, 'wednesday' => 2, 'thursday' => 8, 'friday' => 10],
        'silver_8'  => ['monday' => 5, 'tuesday' => 3, 'wednesday' => 1, 'thursday' => 9, 'friday' => 7],
        'silver_9'  => ['monday' => 10, 'tuesday' => 8, 'wednesday' => 6, 'thursday' => 2, 'friday' => 4],
        'silver_10' => ['monday' => 7, 'tuesday' => 5, 'wednesday' => 3, 'thursday' => 1, 'friday' => 9],
    ];
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    private function getCurrentWeekStart() {
        $now = new DateTime();
        $now->modify('monday this week');
        return $now->format('Y-m-d');
    }
    
    private function isSunday() {
        return date('N') == 7;
    }
    
    public function getSchedule($league, $ourPosition) {
        if (!in_array($league, ['bronze', 'silver'])) {
            echo json_encode(['success' => false, 'error' => 'Неверная лига']);
            return;
        }
        
        $ourPosition = (int)$ourPosition;
        $weekStart = $this->getCurrentWeekStart();
        $maxPos = ($league === 'bronze') ? 30 : 10;
        
        if ($ourPosition < 1 || $ourPosition > $maxPos) {
            echo json_encode(['success' => false, 'error' => "Место должно быть от 1 до {$maxPos}"]);
            return;
        }
        
        // Проверяем в БД
        $stmt = $this->pdo->prepare("
            SELECT monday, tuesday, wednesday, thursday, friday 
            FROM weekly_schedule 
            WHERE league = ? AND week_start_date = ?
        ");
        $stmt->execute([$league, $weekStart]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            echo json_encode([
                'success' => true,
                'from_db' => true,
                'schedule' => $existing
            ]);
            return;
        }
        
        // Нет в БД - проверяем, можно ли создать
        if (!$this->isSunday()) {
            echo json_encode([
                'success' => false, 
                'error' => 'Создание расписания возможно только в воскресенье! Вы опоздали, расписание на эту неделю уже зафиксировано.'
            ]);
            return;
        }
        
        // Берём данные из массива
        $key = $league . '_' . $ourPosition;
        if (!isset($this->scheduleData[$key])) {
            echo json_encode(['success' => false, 'error' => 'Расписание для этой позиции не найдено']);
            return;
        }
        
        $schedule = $this->scheduleData[$key];
        
        // Сохраняем в БД
        $stmt = $this->pdo->prepare("
            INSERT INTO weekly_schedule (league, week_start_date, our_position, monday, tuesday, wednesday, thursday, friday)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $league, $weekStart, $ourPosition,
            $schedule['monday'], $schedule['tuesday'], $schedule['wednesday'],
            $schedule['thursday'], $schedule['friday']
        ]);
        
        echo json_encode([
            'success' => true,
            'from_db' => false,
            'schedule' => $schedule
        ]);
    }
}

// ========== ОБРАБОТКА ЗАПРОСОВ ==========

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Не авторизован']);
    exit;
}

$handler = new ScheduleHandler($pdo);
$action = $_POST['action'] ?? '';

switch ($action) {
    case 'get_schedule':
        $handler->getSchedule($_POST['league'] ?? '', $_POST['our_position'] ?? 0);
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Неизвестное действие']);
}
?>