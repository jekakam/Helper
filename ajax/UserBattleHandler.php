<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
header('Content-Type: application/json');

class UserBattleHandler {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function saveBattle($battleData) {
        $data = json_decode($battleData, true);
        
        if (!$data || !isset($data['user_id'])) {
            echo json_encode(['success' => false, 'error' => 'Неверные данные']);
            return;
        }
        
        try {
            $this->pdo->beginTransaction();
            
            $stmt = $this->pdo->prepare("
                INSERT INTO user_battles (user_id, attack, result, note) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['user_id'],
                $data['attack'],
                $data['result'],
                $data['note'] ?? ''
            ]);
            $battleId = $this->pdo->lastInsertId();
            
            $stmt = $this->pdo->prepare("
                INSERT INTO user_battle_heroes 
                (battle_id, side, position, hero_id, hero_level, talisman_level, relic_level, power) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            foreach ($data['heroes'] as $hero) {
                $stmt->execute([
                    $battleId,
                    $hero['side'],
                    $hero['position'],
                    $hero['hero_id'],
                    $hero['hero_level'],
                    $hero['talisman_level'],
                    $hero['relic_level'],
                    $hero['power']
                ]);
            }
            
            $this->pdo->commit();
            echo json_encode(['success' => true]);
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    public function getBattles($page = 1, $result = '', $attack = '') {
        $userId = $_SESSION['admin_id'] ?? 0;
        
        if (!$userId) {
            echo json_encode(['success' => false, 'error' => 'Не авторизован']);
            return;
        }
        
        $limit = 10;
        $offset = ((int)$page - 1) * $limit;
        
        $sql = "SELECT * FROM user_battles WHERE user_id = ?";
        $params = [$userId];
        
        if ($result) {
            $sql .= " AND result = ?";
            $params[] = $result;
        }
        
        if ($attack !== '') {
            $sql .= " AND attack = ?";
            $params[] = $attack;
        }
        
        $countSql = str_replace("SELECT *", "SELECT COUNT(*) as total", $sql);
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = $countStmt->fetch()['total'];
        
        $sql .= " ORDER BY battle_date DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $battles = $stmt->fetchAll();
        
        foreach ($battles as &$b) {
            $heroStmt = $this->pdo->prepare("
                SELECT * FROM user_battle_heroes WHERE battle_id = ? AND side = 'our'
            ");
            $heroStmt->execute([$b['id']]);
            $b['our_heroes'] = $heroStmt->fetchAll();
        }
        
        echo json_encode([
            'success' => true,
            'battles' => $battles,
            'current_page' => (int)$page,
            'total_pages' => ceil($total / $limit),
            'total' => $total
        ]);
    }
}

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Не авторизован']);
    exit;
}

$handler = new UserBattleHandler($pdo);
$action = $_POST['action'] ?? '';

switch ($action) {
    case 'save_battle':
        $handler->saveBattle($_POST['battle_data'] ?? '');
        break;
    case 'get_battles':
        $handler->getBattles(
            $_POST['page'] ?? 1,
            $_POST['result'] ?? '',
            $_POST['attack'] ?? ''
        );
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Неизвестное действие']);
}
?>