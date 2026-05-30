<?php
/*
* TitanHandler.php
*/
require_once '../includes/config.php';
require_once '../includes/auth.php';
header('Content-Type: application/json');

class TitanHandler {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    // Получить все стихии
    public function getElements() {
        $stmt = $this->pdo->query("SELECT * FROM titan_elements ORDER BY id");
        $result = $stmt->fetchAll();
        echo json_encode($result);
        return;
    }
    
    // Добавить стихию
    public function addElement($name, $color) {
        if (!$name) {
            echo json_encode(['success' => false, 'error' => 'Введите название стихии']);
            return;
        }
        
        try {
            $stmt = $this->pdo->prepare("INSERT INTO titan_elements (name, color) VALUES (?, ?)");
            $stmt->execute([$name, $color]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => 'Такая стихия уже существует']);
        }
    }
    
    // Удалить стихию
    public function deleteElement($id) {
        // Обнуляем стихию у игроков в battle_items
        $this->pdo->prepare("UPDATE battle_items SET our_titan_element_id = NULL WHERE our_titan_element_id = ?")->execute([$id]);
        $this->pdo->prepare("UPDATE battle_items SET enemy_titan_element_id = NULL WHERE enemy_titan_element_id = ?")->execute([$id]);
        
        // Удаляем стихию
        $stmt = $this->pdo->prepare("DELETE FROM titan_elements WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode(['success' => true]);
    }
}

// ========== ОБРАБОТКА ЗАПРОСОВ ==========

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Не авторизован']);
    exit;
}

$handler = new TitanHandler($pdo);
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_elements':
        $handler->getElements();
        break;
        
    case 'add_element':
        $handler->addElement(
            trim($_POST['name']),
            $_POST['color'] ?? '#808080'
        );
        break;
        
    case 'delete_element':
        $handler->deleteElement($_POST['id']);
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Неизвестное действие']);
}
?>