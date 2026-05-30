<?php
error_reporting(0);
ini_set('display_errors', 0);

require_once '../includes/config.php';
require_once '../includes/auth.php';

if (ob_get_level()) ob_clean();
header('Content-Type: application/json');

class CounterSearchHandler {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    private function normalizeHeroCombo($heroesArray) {
        if (!is_array($heroesArray)) return '';
        $sorted = $heroesArray;
        sort($sorted, SORT_NUMERIC);
        return implode(',', $sorted);
    }
    
    /**
     * Получить пресеты из активного черновика
     */
	public function getDraftPresets() {
		// Находим активный черновик
		$stmt = $this->pdo->prepare("
			SELECT b.*, eg.name as enemy_guild_name
			FROM battles b
			LEFT JOIN guilds eg ON b.enemy_guild_id = eg.id
			WHERE b.status = 'draft' AND b.our_guild_id = 1
			ORDER BY b.battle_date DESC
			LIMIT 1
		");
		$stmt->execute();
		$draftBattle = $stmt->fetch();
		
		if (!$draftBattle) {
			echo json_encode(['success' => true, 'presets' => [], 'hasDraft' => false]);
			return;
		}
		
		// Получаем все записи черновика, где у противника есть 5 героев
		$stmt = $this->pdo->prepare("
			SELECT 
				bi.id,
				bi.building_name,
				bi.enemy_player_nick,
				bi.enemy_power,
				bi.enemy_heroes_json,
				bi.enemy_titan_element_id,
				bi.our_player_nick,
				bi.result
			FROM battle_items bi
			WHERE bi.battle_id = ?
				AND bi.enemy_heroes_json IS NOT NULL
				AND bi.enemy_heroes_json != '[]'
				AND bi.enemy_heroes_json != 'null'
			ORDER BY bi.slot_index
		");
		$stmt->execute([$draftBattle['id']]);
		$items = $stmt->fetchAll();
		
		$presets = [];
		
		foreach ($items as $item) {
			$heroIds = json_decode($item['enemy_heroes_json'], true);
			if (!is_array($heroIds) || count($heroIds) !== 5) continue;
			
			// Получаем данные героев
			$heroData = [];
			if (!empty($heroIds)) {
				$placeholders = implode(',', array_fill(0, count($heroIds), '?'));
				$heroStmt = $this->pdo->prepare("
					SELECT id, name, image 
					FROM heroes_catalog 
					WHERE id IN ($placeholders)
				");
				$heroStmt->execute($heroIds);
				$heroesFromDb = $heroStmt->fetchAll();
				
				// Сохраняем порядок как в сборке
				$heroMap = [];
				foreach ($heroesFromDb as $h) {
					$heroMap[$h['id']] = $h;
				}
				
				foreach ($heroIds as $hid) {
					if (isset($heroMap[$hid])) {
						$heroData[] = $heroMap[$hid];
					}
				}
			}
			
			// Статус сборки
			$isCompleted = !empty($item['result']);
			$hasOurPlayer = !empty($item['our_player_nick']);
			
			// Нормализованный ключ для группировки одинаковых сборок (для информации)
			$comboKey = $this->normalizeHeroCombo($heroIds);
			
			$presets[] = [
				'id' => $item['id'], // уникальный ID записи
				'combo_key' => $comboKey, // ключ для определения дубликатов
				'hero_ids' => $heroIds,
				'heroes' => $heroData,
				'enemy_nick' => $item['enemy_player_nick'],
				'enemy_power' => (int)$item['enemy_power'],
				'building_name' => $item['building_name'],
				'is_completed' => $isCompleted,
				'has_our_player' => $hasOurPlayer,
				'status_text' => $isCompleted ? '✅ Завершён' : ($hasOurPlayer ? '👤 Назначен' : '⚔️ Открыт')
			];
		}
		
		echo json_encode([
			'success' => true,
			'presets' => $presets,
			'hasDraft' => true,
			'battle_date' => date('d.m.Y', strtotime($draftBattle['battle_date'])),
			'enemy_guild_name' => $draftBattle['enemy_guild_name'] ?? 'Неизвестно'
		], JSON_UNESCAPED_UNICODE);
	}
	
	public function searchCounters($enemyHeroesJson, $page = 1, $limit = 20) {
		$enemyHeroes = json_decode($enemyHeroesJson, true);
		
		if (!is_array($enemyHeroes) || count($enemyHeroes) != 5) {
			echo json_encode(['success' => false, 'error' => 'Нужно указать ровно 5 героев']);
			return;
		}
		
		$targetCombo = $this->normalizeHeroCombo($enemyHeroes);
		
		$sql = "
			SELECT 
				bi.our_player_nick,
				bi.our_power,
				bi.our_heroes_json,
				bi.enemy_power,
				bi.enemy_player_nick,
				b.battle_date,
				b.enemy_guild_id,
				eg.name as enemy_guild_name,
				bi.enemy_heroes_json
			FROM battle_items bi
			JOIN battles b ON bi.battle_id = b.id
			LEFT JOIN guilds eg ON b.enemy_guild_id = eg.id
			WHERE b.status = 'completed'
				AND b.our_guild_id = 1
				AND bi.result = 'win'
				AND bi.enemy_heroes_json IS NOT NULL
				AND bi.enemy_heroes_json != '[]'
				AND bi.enemy_heroes_json != 'null'
			ORDER BY b.battle_date DESC, bi.id DESC
		";
		
		$stmt = $this->pdo->prepare($sql);
		$stmt->execute();
		$allRows = $stmt->fetchAll();
		
		// Структура: наш_игрок -> наша_сборка -> список побед
		$playerCombos = [];
		
		foreach ($allRows as $row) {
			$enemyCombo = json_decode($row['enemy_heroes_json'], true);
			if (!is_array($enemyCombo)) continue;
			if (count($enemyCombo) != 5) continue;
			
			$dbCombo = $this->normalizeHeroCombo($enemyCombo);
			
			// Проверяем совпадение сборки противника
			if ($dbCombo !== $targetCombo) continue;
			
			// Наша сборка
			$ourHeroIds = json_decode($row['our_heroes_json'], true);
			if (!is_array($ourHeroIds) || count($ourHeroIds) !== 5) continue;
			
			$ourComboKey = $this->normalizeHeroCombo($ourHeroIds);
			$ourPlayer = $row['our_player_nick'];
			
			// Инициализация структур
			if (!isset($playerCombos[$ourPlayer])) {
				$playerCombos[$ourPlayer] = [];
			}
			if (!isset($playerCombos[$ourPlayer][$ourComboKey])) {
				$playerCombos[$ourPlayer][$ourComboKey] = [
					'hero_ids' => $ourHeroIds,
					'wins' => []
				];
			}
			
			// Добавляем победу
			$playerCombos[$ourPlayer][$ourComboKey]['wins'][] = [
				'our_power' => (int)$row['our_power'],
				'enemy_power' => (int)$row['enemy_power'],
				'enemy_nick' => $row['enemy_player_nick'] ?? 'Неизвестно',
				'battle_date' => date('d.m.Y', strtotime($row['battle_date'])),
				'enemy_guild_name' => $row['enemy_guild_name'] ?? 'Неизвестно'
			];
		}
		
		// Для каждой контр-сборки каждого игрока оставляем только 2 лучшие победы (по силе противника)
		foreach ($playerCombos as $player => &$combos) {
			foreach ($combos as $comboKey => &$comboData) {
				// Сортируем победы по силе противника (по убыванию)
				usort($comboData['wins'], function($a, $b) {
					return $b['enemy_power'] - $a['enemy_power'];
				});
				
				// Оставляем только топ-2
				$comboData['wins'] = array_slice($comboData['wins'], 0, 2);
				$comboData['total_wins'] = count($comboData['wins']); // будет 1 или 2
			}
		}
		unset($combos, $comboData);
		
		// Сортируем игроков по количеству контр-сборок (кто больше разных сборок использовал — выше)
		uasort($playerCombos, function($a, $b) {
			return count($b) - count($a);
		});
		
		// Формируем финальный результат
		$result = [];
		
		foreach ($playerCombos as $player => $combos) {
			// Получаем общее количество уникальных побед игрока
			$totalPlayerWins = 0;
			foreach ($combos as $comboData) {
				$totalPlayerWins += count($comboData['wins']);
			}
			
			$playerResult = [
				'player_nick' => $player,
				'total_combos' => count($combos),
				'total_wins_shown' => $totalPlayerWins,
				'combos' => []
			];
			
			foreach ($combos as $comboKey => $comboData) {
				// Получаем данные героев
				$ourHeroes = [];
				if (!empty($comboData['hero_ids'])) {
					$placeholders = implode(',', array_fill(0, count($comboData['hero_ids']), '?'));
					$heroStmt = $this->pdo->prepare("
						SELECT id, name, image 
						FROM heroes_catalog 
						WHERE id IN ($placeholders)
					");
					$heroStmt->execute($comboData['hero_ids']);
					$heroesFromDb = $heroStmt->fetchAll();
					
					$heroMap = [];
					foreach ($heroesFromDb as $h) {
						$heroMap[$h['id']] = $h;
					}
					
					foreach ($comboData['hero_ids'] as $hid) {
						if (isset($heroMap[$hid])) {
							$ourHeroes[] = $heroMap[$hid];
						}
					}
				}
				
				$playerResult['combos'][] = [
					'combo_key' => $comboKey,
					'heroes' => $ourHeroes,
					'wins' => $comboData['wins']
				];
			}
			
			$result[] = $playerResult;
		}
		
		echo json_encode([
			'success' => true,
			'data' => $result,
			'total_players' => count($result),
			'target_combo' => $targetCombo
		], JSON_UNESCAPED_UNICODE);
	}

}

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Не авторизован']);
    exit;
}

$handler = new CounterSearchHandler($pdo);
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'get_presets':
        $handler->getDraftPresets();
        break;
        
    case 'search_counters':
        $handler->searchCounters(
            $_POST['enemy_heroes'] ?? '[]',
            $_POST['page'] ?? 1,
            $_POST['limit'] ?? 20
        );
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Неизвестное действие']);
}
?>