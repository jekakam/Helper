<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
header('Content-Type: application/json');

class BattlesListHandler {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function getBattles($ourGuildId, $page = 1, $period = 14, $guildId = null) {
        if (!isLoggedIn()) {
            echo json_encode(['success' => false, 'error' => 'Не авторизован']);
            return;
        }
        
        $limit = 20;
        $offset = ((int)$page - 1) * $limit;
        
        try {
            // Базовый запрос
            $sql = "
                SELECT b.*, eg.name as enemy_guild_name
                FROM battles b
                LEFT JOIN guilds eg ON b.enemy_guild_id = eg.id
                WHERE b.status = 'completed' AND b.our_guild_id = ?
            ";
            $params = [$ourGuildId];
            
            // Фильтр по дате
            if ($period !== 'all' && is_numeric($period)) {
                $sql .= " AND b.battle_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)";
                $params[] = (int)$period;
            }
            
            // Фильтр по гильдии
            if (!empty($guildId) && is_numeric($guildId)) {
                $sql .= " AND b.enemy_guild_id = ?";
                $params[] = (int)$guildId;
            }
            
            // Сортировка
            $sql .= " ORDER BY b.battle_date DESC, b.id DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $battles = $stmt->fetchAll();
            
            // Получаем общее количество для пагинации
            $countSql = "
                SELECT COUNT(*) as total
                FROM battles b
                WHERE b.status = 'completed' AND b.our_guild_id = ?
            ";
            $countParams = [$ourGuildId];
            
            if ($period !== 'all' && is_numeric($period)) {
                $countSql .= " AND b.battle_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)";
                $countParams[] = (int)$period;
            }
            
            if (!empty($guildId) && is_numeric($guildId)) {
                $countSql .= " AND b.enemy_guild_id = ?";
                $countParams[] = (int)$guildId;
            }
            
            $countStmt = $this->pdo->prepare($countSql);
            $countStmt->execute($countParams);
            $total = $countStmt->fetch()['total'];
            
            $hasMore = ($offset + $limit) < $total;
            $nextPage = $page + 1;
            
            echo json_encode([
                'success' => true,
                'battles' => $battles,
                'has_more' => $hasMore,
                'next_page' => $nextPage,
                'total' => $total,
                'current_page' => $page,
                'offset' => $offset,
                'limit' => $limit
            ]);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
}

// ========== ОБРАБОТКА ЗАПРОСОВ ==========

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Не авторизован']);
    exit;
}

$handler = new BattlesListHandler($pdo);
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'get_battles':
        $handler->getBattles(
            (int)($_POST['our_guild_id'] ?? 1),
            (int)($_POST['page'] ?? 1),
            $_POST['period'] ?? 14,
            $_POST['guild_id'] ?? null
        );
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Неизвестное действие']);
}
?>