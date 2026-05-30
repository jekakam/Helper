<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
header('Content-Type: application/json');

class BattleHandler {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    // Создать черновик боя
    public function createDraftBattle($ourGuildId, $date, $enemyGuildId) {
        if (!$enemyGuildId) {
            echo json_encode(['success' => false, 'error' => 'Не выбрана гильдия противника']);
            return;
        }
        
        try {
            $this->pdo->beginTransaction();
            
            // Проверяем существующий черновик на эту дату
            $stmt = $this->pdo->prepare("SELECT id FROM battles WHERE our_guild_id = ? AND battle_date = ? AND status = 'draft'");
            $stmt->execute([$ourGuildId, $date]);
            $existing = $stmt->fetch();
        
            if ($existing) {
                $this->pdo->commit();
                echo json_encode(['success' => true, 'battle_id' => $existing['id'], 'is_new' => false]);
                return;
            }
            
            // Создаём бой
            $stmt = $this->pdo->prepare("INSERT INTO battles (battle_date, our_guild_id, enemy_guild_id, status) VALUES (?, ?, ?, 'draft')");
            $stmt->execute([$date, $ourGuildId, $enemyGuildId]);
            $battleId = $this->pdo->lastInsertId();
            
            // Получаем все здания
            $buildings = $this->pdo->query("SELECT name, unit_type FROM buildings ORDER BY sort_order")->fetchAll();
            
            // Создаём записи battle_items для каждого здания
            $insertStmt = $this->pdo->prepare("
                INSERT INTO battle_items (
                    battle_id, building_name, slot_index,
                    our_player_nick, our_power, our_heroes_json, our_titan_element_id,
                    enemy_player_nick, enemy_power, enemy_heroes_json, enemy_titan_element_id,
                    result, comment
                ) VALUES (?, ?, ?, '', '', NULL, NULL, '', '', NULL, NULL, NULL, '')
            ");
            
            $slotIndex = 0;
            foreach ($buildings as $building) {
                $insertStmt->execute([$battleId, $building['name'], $slotIndex]);
                $slotIndex++;
            }
            
            $this->pdo->commit();
            
            echo json_encode(['success' => true, 'battle_id' => $battleId, 'is_new' => true]);
        } catch (Exception $e) {
            $this->pdo->rollBack();
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    // Загрузить бой
    public function loadBattle($battleId) {
        $stmt = $this->pdo->prepare("SELECT * FROM battles WHERE id = ?");
        $stmt->execute([$battleId]);
        $battle = $stmt->fetch();
        
        if (!$battle) {
            echo json_encode(['success' => false, 'error' => 'Бой не найден']);
            return;
        }
        
        $stmt = $this->pdo->prepare("SELECT * FROM battle_items WHERE battle_id = ? ORDER BY slot_index");
        $stmt->execute([$battleId]);
        $items = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'battle' => $battle,
            'items' => $items
        ]);
    }
    
    // Сохранить бой
    public function saveBattle($battleId, $ourScore, $enemyScore, $items, $complete = false) {
        try {
            $this->pdo->beginTransaction();
            
            // Обновляем счёт
            $stmt = $this->pdo->prepare("UPDATE battles SET our_score = ?, enemy_score = ? WHERE id = ?");
            $stmt->execute([$ourScore, $enemyScore, $battleId]);
            
            // UPDATE для каждой записи
            $updateStmt = $this->pdo->prepare("
                UPDATE battle_items SET
                    our_player_nick = ?,
                    our_power = ?,
                    our_heroes_json = ?,
                    our_titan_element_id = ?,
                    enemy_player_nick = ?,
                    enemy_power = ?,
                    enemy_heroes_json = ?,
                    enemy_titan_element_id = ?,
                    result = ?,
                    comment = ?
                WHERE battle_id = ? AND building_name = ?
            ");
            
            foreach ($items as $item) {
                $ourHeroesJson = !empty($item['our_heroes_json']) ? (is_array($item['our_heroes_json']) ? json_encode($item['our_heroes_json']) : $item['our_heroes_json']) : null;
                $enemyHeroesJson = !empty($item['enemy_heroes_json']) ? (is_array($item['enemy_heroes_json']) ? json_encode($item['enemy_heroes_json']) : $item['enemy_heroes_json']) : null;
                $ourTitanId = !empty($item['our_titan_element_id']) ? $item['our_titan_element_id'] : null;
                $enemyTitanId = !empty($item['enemy_titan_element_id']) ? $item['enemy_titan_element_id'] : null;
                $result = !empty($item['result']) ? $item['result'] : null;
                
                $updateStmt->execute([
                    $item['our_player_nick'],
                    $item['our_power'],
                    $ourHeroesJson,
                    $ourTitanId,
                    $item['enemy_player_nick'],
                    $item['enemy_power'],
                    $enemyHeroesJson,
                    $enemyTitanId,
                    $result,
                    $item['comment'],
                    $battleId,
                    $item['building_name']
                ]);
            }
           
            // Если завершаем бой
            if ($complete) {
                $stmt = $this->pdo->prepare("
                    UPDATE battles 
                    SET status = 'completed', 
                        completed_at = NOW(),
                        locked_by = NULL,
                        locked_at = NULL
                    WHERE id = ?
                ");
                $stmt->execute([$battleId]);
            }
            
            $this->pdo->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $this->pdo->rollBack();
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    // Получить последнюю композицию игрока (только для активных игроков)
	public function getLastComposition($nickname, $unitType, $guildId) {
		if (!isLoggedIn()) {
			echo json_encode(['success' => false, 'error' => 'Не авторизован']);
			return;
		}
		
		if (!$nickname) {
			echo json_encode(['success' => false, 'error' => 'Ник не указан']);
			return;
		}
		
		try {
			if ($unitType === 'titan') {
				if ($guildId == 1) {
					// Наша гильдия (Imperial) - ищем только среди активных игроков
					$stmt = $this->pdo->prepare("
						SELECT bi.our_titan_element_id as titan_element_id, bi.our_power
						FROM battle_items bi
						INNER JOIN players p ON bi.our_player_nick = p.nickname AND p.guild_id = ?
						INNER JOIN battles b ON bi.battle_id = b.id
						WHERE bi.our_player_nick = ? COLLATE utf8_bin
							AND bi.our_titan_element_id IS NOT NULL
							AND p.is_active = 1
							AND b.status = 'completed'
						ORDER BY b.battle_date DESC, bi.id DESC
						LIMIT 1
					");
					$stmt->execute([$guildId, $nickname]);
				} else {
					// Гильдия противника - ищем только в указанной гильдии, регистрозависимо
					$stmt = $this->pdo->prepare("
						SELECT bi.enemy_titan_element_id as titan_element_id, bi.enemy_power as our_power
						FROM battle_items bi
						INNER JOIN battles b ON bi.battle_id = b.id
						WHERE bi.enemy_player_nick = ? COLLATE utf8_bin
							AND bi.enemy_titan_element_id IS NOT NULL
							AND b.enemy_guild_id = ?
							AND b.status = 'completed'
						ORDER BY b.battle_date DESC, bi.id DESC
						LIMIT 1
					");
					$stmt->execute([$nickname, $guildId]);
				}
				$result = $stmt->fetch();
				
				echo json_encode([
					'success' => true,
					'titan_element_id' => $result['titan_element_id'] ?? '',
					'power' => $result['our_power'] ?? ''
				]);
			} else {
				if ($guildId == 1) {
					// Наша гильдия (Imperial) - ищем только среди активных игроков
					$stmt = $this->pdo->prepare("
						SELECT bi.our_heroes_json as heroes, bi.our_power
						FROM battle_items bi
						INNER JOIN players p ON bi.our_player_nick = p.nickname AND p.guild_id = ?
						INNER JOIN battles b ON bi.battle_id = b.id
						WHERE bi.our_player_nick = ? COLLATE utf8_bin
							AND bi.our_heroes_json IS NOT NULL
							AND bi.our_heroes_json != '[]'
							AND p.is_active = 1
							AND b.status = 'completed'
						ORDER BY b.battle_date DESC, bi.id DESC
						LIMIT 1
					");
					$stmt->execute([$guildId, $nickname]);
				} else {
					// Гильдия противника - ищем только в указанной гильдии, регистрозависимо
					$stmt = $this->pdo->prepare("
						SELECT bi.enemy_heroes_json as heroes, bi.enemy_power as our_power
						FROM battle_items bi
						INNER JOIN battles b ON bi.battle_id = b.id
						WHERE bi.enemy_player_nick = ? COLLATE utf8_bin
							AND bi.enemy_heroes_json IS NOT NULL
							AND bi.enemy_heroes_json != '[]'
							AND b.enemy_guild_id = ?
							AND b.status = 'completed'
						ORDER BY b.battle_date DESC, bi.id DESC
						LIMIT 1
					");
					$stmt->execute([$nickname, $guildId]);
				}
				$result = $stmt->fetch();
				
				$heroes = [];
				if ($result && $result['heroes']) {
					$heroes = json_decode($result['heroes'], true);
					if (!is_array($heroes)) $heroes = [];
				}
				
				echo json_encode([
					'success' => true,
					'heroes' => $heroes,
					'power' => $result['our_power'] ?? ''
				]);
			}
		} catch (Exception $e) {
			echo json_encode(['success' => false, 'error' => $e->getMessage()]);
		}
	}
    
    // Снять блокировку с боя
    public function unlockBattle($battleId) {
        if (!isLoggedIn()) {
            echo json_encode(['success' => false, 'error' => 'Не авторизован']);
            return;
        }
        
        try {
            $stmt = $this->pdo->prepare("
                UPDATE battles 
                SET locked_by = NULL, locked_at = NULL
                WHERE id = ? AND locked_by = ?
            ");
            $stmt->execute([$battleId, $_SESSION['admin_id']]);
            
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    // Принудительное снятие блокировки (только для суперадмина)
    public function forceUnlockBattle($battleId) {
        if (!isLoggedIn() || !isSuperAdmin()) {
            echo json_encode(['success' => false, 'error' => 'Доступ запрещён']);
            return;
        }
        
        try {
            $stmt = $this->pdo->prepare("
                UPDATE battles 
                SET locked_by = NULL, locked_at = NULL 
                WHERE id = ?
            ");
            $stmt->execute([$battleId]);
            
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
}

// ========== ОБРАБОТКА ЗАПРОСОВ ==========

$handler = new BattleHandler($pdo);
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'create_draft':
        $handler->createDraftBattle(1, $_POST['date'] ?? date('Y-m-d'), $_POST['enemy_guild_id'] ?? 0);
        break;
        
    case 'load_battle':
        $handler->loadBattle($_GET['battle_id'] ?? 0);
        break;
        
    case 'save_battle':
        $complete = ($_POST['complete'] ?? '') === 'true';
        $items = json_decode($_POST['items'] ?? '[]', true);
        $handler->saveBattle(
            $_POST['battle_id'] ?? 0,
            $_POST['our_score'] ?? 0,
            $_POST['enemy_score'] ?? 0,
            $items,
            $complete
        );
        break;
        
    case 'get_last_composition':
        $handler->getLastComposition(
            $_POST['nickname'] ?? '',
            $_POST['unit_type'] ?? 'hero',
            $_POST['guild_id'] ?? null
        );
        break;
        
    case 'unlock_battle':
        $handler->unlockBattle($_POST['battle_id'] ?? 0);
        break;
        
    case 'force_unlock_battle':
        $handler->forceUnlockBattle($_POST['battle_id'] ?? 0);
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Неизвестное действие']);
}
?>