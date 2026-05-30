<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
header('Content-Type: application/json');

class PlayerGuildHandler {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Проверяет лимит активных игроков в гильдии 1
     */
    private function checkActiveLimit($guildId, $excludePlayerId = null) {
        if ($guildId != 1) return true;
        
        $sql = "SELECT COUNT(*) as active_count FROM players WHERE guild_id = ? AND is_active = 1";
        $params = [$guildId];
        
        if ($excludePlayerId) {
            $sql .= " AND id != ?";
            $params[] = $excludePlayerId;
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $activeCount = $stmt->fetch()['active_count'];
        
        return $activeCount < 15;
    }
    
    // ========== ГИЛЬДИИ ==========
    
    public function getGuilds() {
        $stmt = $this->pdo->query("SELECT * FROM guilds ORDER BY id");
        $result = $stmt->fetchAll();
        echo json_encode($result);
        return;
    }
    
    public function addGuild($name) {
        if (!$name) {
            echo json_encode(['success' => false, 'error' => 'Введите название гильдии']);
            return;
        }
        
        try {
            $stmt = $this->pdo->prepare("INSERT INTO guilds (name) VALUES (?)");
            $stmt->execute([$name]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => 'Такая гильдия уже существует']);
        }
    }
    
    public function updateGuild($id, $name) {
        if (!$name) {
            echo json_encode(['success' => false, 'error' => 'Введите название гильдии']);
            return;
        }
        
        try {
            $stmt = $this->pdo->prepare("UPDATE guilds SET name = ? WHERE id = ?");
            $stmt->execute([$name, $id]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => 'Гильдия с таким именем уже существует']);
        }
    }
      
    // ========== ИГРОКИ ==========
    
    public function getPlayers($guild_id) {
        if (!$guild_id) {
            echo json_encode(['success' => false, 'error' => 'Guild ID required']);
            return;
        }
        
        $sql = "SELECT id, guild_id, nickname, specialization, is_active, created_at
                FROM players 
                WHERE guild_id = ?
                ORDER BY is_active DESC, nickname";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$guild_id]);
        $result = $stmt->fetchAll();
        echo json_encode($result);
        return;
    }
    
    public function getGuildPlayers($guildId) {
        if (!$guildId) {
            echo json_encode(['success' => false, 'error' => 'Guild ID required']);
            return;
        }
        
        $stmt = $this->pdo->prepare("SELECT id, nickname, is_active FROM players WHERE guild_id = ? ORDER BY nickname");
        $stmt->execute([$guildId]);
        $players = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'players' => $players]);
        return;
    }
    
    public function getPlayerData($id) {
        $stmt = $this->pdo->prepare("SELECT id, nickname, specialization, is_active FROM players WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        echo json_encode($result);
        return;
    }
    
    public function addPlayer($guild_id, $nickname, $specialization, $is_active = 1) {
        if (!$nickname) {
            echo json_encode(['success' => false, 'error' => 'Введите никнейм']);
            return;
        }
        
        // Проверка на дубликат
        $check = $this->pdo->prepare("SELECT id FROM players WHERE guild_id = ? AND nickname = ?");
        $check->execute([$guild_id, $nickname]);
        if ($check->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Игрок с таким ником уже есть в этой гильдии']);
            return;
        }
        
        // Если игрок активный - проверяем лимит для гильдии 1
        if ($is_active == 1 && !$this->checkActiveLimit($guild_id)) {
            $activeCount = $this->getActiveCount($guild_id);
            echo json_encode(['success' => false, 'error' => "Достигнут лимит активных игроков (15). Сейчас активно: {$activeCount}"]);
            return;
        }
        
        try {
            $stmt = $this->pdo->prepare("INSERT INTO players (guild_id, nickname, specialization, is_active) VALUES (?, ?, ?, ?)");
            $stmt->execute([$guild_id, $nickname, $specialization, $is_active]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => 'Ошибка БД: ' . $e->getMessage()]);
        }
    }
    
    public function updatePlayer($id, $nickname, $specialization, $is_active = 1) {
        if (!$nickname) {
            echo json_encode(['success' => false, 'error' => 'Введите никнейм']);
            return;
        }
        
        // Получаем текущую гильдию игрока
        $stmt = $this->pdo->prepare("SELECT guild_id, is_active FROM players WHERE id = ?");
        $stmt->execute([$id]);
        $current = $stmt->fetch();
        
        if (!$current) {
            echo json_encode(['success' => false, 'error' => 'Игрок не найден']);
            return;
        }
        
        $guild_id = $current['guild_id'];
        $current_active = $current['is_active'];
        
        // Проверка на дубликат
        $check = $this->pdo->prepare("
            SELECT id FROM players 
            WHERE guild_id = ? AND nickname = ? AND id != ?
        ");
        $check->execute([$guild_id, $nickname, $id]);
        if ($check->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Игрок с таким ником уже есть в этой гильдии']);
            return;
        }
        
        // Если пытаемся активировать игрока - проверяем лимит для гильдии 1
        if ($is_active == 1 && $current_active == 0 && !$this->checkActiveLimit($guild_id, $id)) {
            $activeCount = $this->getActiveCount($guild_id, $id);
            echo json_encode(['success' => false, 'error' => "Достигнут лимит активных игроков (15). Сейчас активно: {$activeCount}"]);
            return;
        }
        
        try {
            $stmt = $this->pdo->prepare("UPDATE players SET nickname=?, specialization=?, is_active=? WHERE id=?");
            $stmt->execute([$nickname, $specialization, $is_active, $id]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => 'Ошибка БД: ' . $e->getMessage()]);
        }
    }
    
    private function getActiveCount($guild_id, $excludePlayerId = null) {
        $sql = "SELECT COUNT(*) as count FROM players WHERE guild_id = ? AND is_active = 1";
        $params = [$guild_id];
        
        if ($excludePlayerId) {
            $sql .= " AND id != ?";
            $params[] = $excludePlayerId;
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch()['count'];
    }
    
    public function deletePlayer($id) {
        $stmt = $this->pdo->prepare("DELETE FROM players WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
    }
	
	
	// Поиск игрока
	public function searchPlayers($nickname) {
		if (strlen($nickname) < 1) {
			echo json_encode(['success' => false, 'error' => 'Слишком короткий запрос']);
			return;
		}
		$stmt = $this->pdo->prepare("
			SELECT p.id, p.nickname, g.name as guild_name
			FROM players p
			LEFT JOIN guilds g ON p.guild_id = g.id
			WHERE p.nickname LIKE :nick
			ORDER BY p.nickname
			LIMIT 20
		");
		$stmt->execute(['nick' => "%$nickname%"]);
		$players = $stmt->fetchAll();
		echo json_encode(['success' => true, 'players' => $players]);
	}
	
	
	
}

// ========== ОБРАБОТКА ЗАПРОСОВ ==========

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Не авторизован']);
    exit;
}

$handler = new PlayerGuildHandler($pdo);
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_guilds':
        $handler->getGuilds();
        break;
    case 'add_guild':
        $handler->addGuild(trim($_POST['name']));
        break;
    case 'update_guild':
        $handler->updateGuild($_POST['id'], trim($_POST['name']));
        break;
    case 'delete_guild':
        $handler->deleteGuild($_POST['id']);
        break;
    case 'get_players':
        $handler->getPlayers($_GET['guild_id'] ?? 0);
        break;
    case 'get_guild_players':
        $handler->getGuildPlayers($_GET['guild_id'] ?? 0);
        break;
    case 'get_player_data':
        $handler->getPlayerData($_POST['id']);
        break;
    case 'add_player':
        $handler->addPlayer(
            $_POST['guild_id'],
            trim($_POST['nickname']),
            $_POST['specialization'],
            $_POST['is_active'] ?? 1
        );
        break;
    case 'update_player':
        $handler->updatePlayer(
            $_POST['id'],
            trim($_POST['nickname']),
            $_POST['specialization'],
            $_POST['is_active'] ?? 1
        );
        break;
    case 'delete_player':
        $handler->deletePlayer($_POST['id']);
        break;
	case 'search_players':
		$handler->searchPlayers($_GET['nickname'] ?? '');
		break;	
		
    default:
        echo json_encode(['success' => false, 'error' => 'Неизвестное действие']);
}
?>