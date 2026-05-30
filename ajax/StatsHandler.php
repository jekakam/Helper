<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
header('Content-Type: application/json');

class StatsHandler {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
	public function getHeroStats($ourGuildId = 1) {
		$stmt = $this->pdo->prepare("
			SELECT 
				bi.our_player_nick as nickname,
				bi.our_heroes_json,
				bi.our_power,
				bi.result,
				b.battle_date,
				WEEK(b.battle_date, 1) as week_num,
				YEAR(b.battle_date) as year_num
			FROM battle_items bi
			JOIN battles b ON bi.battle_id = b.id
			WHERE b.status = 'completed' 
				AND b.our_guild_id = ?
				AND bi.our_heroes_json IS NOT NULL
				AND bi.our_heroes_json != '[]'
				AND bi.our_heroes_json != 'null'
				AND bi.our_power > 0
		");
		$stmt->execute([$ourGuildId]);
		$rows = $stmt->fetchAll();
		
		// Временное хранилище: ключ -> массив с данными
		$temp = [];
		
		foreach ($rows as $row) {
			$heroes = json_decode($row['our_heroes_json'], true);
			if (!is_array($heroes) || count($heroes) != 5) {
				continue;
			}
			
			$heroes = array_map('intval', $heroes);
			$heroes = array_unique($heroes);
			sort($heroes);
			
			if (count($heroes) != 5) {
				continue;
			}
			
			$heroesKey = implode(',', $heroes);
			$nickname = trim($row['nickname']);
			$groupKey = $nickname . '|' . $heroesKey;
			
			// Инициализация
			if (!isset($temp[$groupKey])) {
				$temp[$groupKey] = [
					'nickname' => $nickname,
					'heroes_ids' => $heroes, // прямой массив, без ссылок
					'heroes_key' => $heroesKey,
					'min_power' => (int)$row['our_power'],
					'max_power' => (int)$row['our_power'],
					'fights' => 0,
					'wins' => 0,
					'weekly' => []
				];
			}
			
			// Обновление (без ссылок!)
			$temp[$groupKey]['fights']++;
			$temp[$groupKey]['min_power'] = min($temp[$groupKey]['min_power'], (int)$row['our_power']);
			$temp[$groupKey]['max_power'] = max($temp[$groupKey]['max_power'], (int)$row['our_power']);
			if ($row['result'] === 'win') {
				$temp[$groupKey]['wins']++;
			}
			
			$weekKey = $row['year_num'] . '-W' . str_pad($row['week_num'], 2, '0', STR_PAD_LEFT);
			if (!isset($temp[$groupKey]['weekly'][$weekKey]) || (int)$row['our_power'] > $temp[$groupKey]['weekly'][$weekKey]) {
				$temp[$groupKey]['weekly'][$weekKey] = (int)$row['our_power'];
			}
		}
		
		// Формируем результат
		$result = [];
		foreach ($temp as $data) {
			if ($data['fights'] < 2) {
				continue;
			}
			
			ksort($data['weekly']);
			$weeklyData = [];
			foreach ($data['weekly'] as $week => $power) {
				$weeklyData[] = ['week' => $week, 'power' => $power];
			}
			
			$heroNames = $this->getHeroNamesByIds($data['heroes_ids']);
			
			$result[] = [
				'nickname' => $data['nickname'],
				'heroes' => $heroNames,
				'fights' => $data['fights'],
				'wins' => $data['wins'],
				'win_rate' => round(($data['wins'] / $data['fights']) * 100, 1),
				'min_power' => $data['min_power'],
				'max_power' => $data['max_power'],
				'growth' => $data['max_power'] - $data['min_power'],
				'weekly' => $weeklyData
			];
		}
		
		usort($result, function($a, $b) {
			return $b['growth'] - $a['growth'];
		});
		
		echo json_encode(['success' => true, 'data' => $result]);
	}

	public function getTitanStats($ourGuildId = 1) {
		$stmt = $this->pdo->prepare("
			SELECT 
				bi.our_player_nick as nickname,
				te.name as titan_name,
				te.color,
				bi.our_power,
				bi.result,
				b.battle_date,
				WEEK(b.battle_date, 1) as week_num,
				YEAR(b.battle_date) as year_num
			FROM battle_items bi
			JOIN battles b ON bi.battle_id = b.id
			JOIN titan_elements te ON bi.our_titan_element_id = te.id
			WHERE b.status = 'completed' 
				AND b.our_guild_id = ?
				AND bi.our_titan_element_id IS NOT NULL
				AND te.name != 'Сброд'
				AND bi.our_power > 0
		");
		$stmt->execute([$ourGuildId]);
		$rows = $stmt->fetchAll();
		
		$temp = [];
		
		foreach ($rows as $row) {
			$nickname = trim($row['nickname']);
			$titanName = trim($row['titan_name']);
			$groupKey = $nickname . '|' . $titanName;
			
			if (!isset($temp[$groupKey])) {
				$temp[$groupKey] = [
					'nickname' => $nickname,
					'titan_name' => $titanName,
					'color' => $row['color'],
					'min_power' => (int)$row['our_power'],
					'max_power' => (int)$row['our_power'],
					'fights' => 0,
					'wins' => 0,
					'weekly' => []
				];
			}
			
			// Обновляем без ссылок
			$temp[$groupKey]['fights']++;
			$temp[$groupKey]['min_power'] = min($temp[$groupKey]['min_power'], (int)$row['our_power']);
			$temp[$groupKey]['max_power'] = max($temp[$groupKey]['max_power'], (int)$row['our_power']);
			if ($row['result'] === 'win') {
				$temp[$groupKey]['wins']++;
			}
			
			$weekKey = $row['year_num'] . '-W' . str_pad($row['week_num'], 2, '0', STR_PAD_LEFT);
			if (!isset($temp[$groupKey]['weekly'][$weekKey]) || (int)$row['our_power'] > $temp[$groupKey]['weekly'][$weekKey]['power']) {
				$temp[$groupKey]['weekly'][$weekKey] = [
					'power' => (int)$row['our_power'],
					'date' => $row['battle_date']
				];
			}
		}
		
		$result = [];
		foreach ($temp as $data) {
			if ($data['fights'] < 2) {
				continue;
			}
			
			ksort($data['weekly']);
			$weeklyData = [];
			foreach ($data['weekly'] as $week => $weekData) {
				$weeklyData[] = [
					'week' => $week,
					'power' => $weekData['power']
				];
			}
			
			$result[] = [
				'nickname' => $data['nickname'],
				'titan_name' => $data['titan_name'],
				'color' => $data['color'],
				'fights' => $data['fights'],
				'wins' => $data['wins'],
				'win_rate' => round(($data['wins'] / $data['fights']) * 100, 1),
				'min_power' => $data['min_power'],
				'max_power' => $data['max_power'],
				'growth' => $data['max_power'] - $data['min_power'],
				'weekly' => $weeklyData
			];
		}
		
		usort($result, function($a, $b) {
			return $b['growth'] - $a['growth'];
		});
		
		echo json_encode(['success' => true, 'data' => $result]);
	}
  
    private function getHeroNamesByIds($ids) {
        if (empty($ids)) return [];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("SELECT id, name, image FROM heroes_catalog WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        return $stmt->fetchAll();
    }
}

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Не авторизован']);
    exit;
}

$handler = new StatsHandler($pdo);
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'hero_stats':
        $handler->getHeroStats($_GET['guild_id'] ?? 1);
        break;
    case 'titan_stats':
        $handler->getTitanStats($_GET['guild_id'] ?? 1);
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Неизвестное действие']);
}
?>