<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
header('Content-Type: application/json');

class HeroHandler {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function getHeroes() {
        $stmt = $this->pdo->query("
            SELECT id, name, image, faction, class, synergy, damage, defense, support, healing 
            FROM heroes_catalog 
            ORDER BY name
        ");
        $result = $stmt->fetchAll();
        
        foreach ($result as &$hero) {
            if ($hero['synergy']) {
                $hero['synergy'] = json_decode($hero['synergy'], true);
                if (!is_array($hero['synergy'])) {
                    $hero['synergy'] = [];
                }
            } else {
                $hero['synergy'] = [];
            }
        }
        
        echo json_encode($result);
    }
    
    public function getHeroesSorted() {
        $stmt = $this->pdo->query("
            SELECT id, name, image, faction, class, synergy, damage, defense, support, healing 
            FROM heroes_catalog
        ");
        $heroes = $stmt->fetchAll();
        
        foreach ($heroes as &$hero) {
            if ($hero['synergy']) {
                $hero['synergy'] = json_decode($hero['synergy'], true);
                if (!is_array($hero['synergy'])) $hero['synergy'] = [];
            } else {
                $hero['synergy'] = [];
            }
        }
        
        $usageCounts = [];
        $stmt = $this->pdo->query("
            SELECT our_heroes_json, enemy_heroes_json 
            FROM battle_items 
            WHERE our_heroes_json IS NOT NULL OR enemy_heroes_json IS NOT NULL
        ");
        while ($row = $stmt->fetch()) {
            if ($row['our_heroes_json']) {
                $heroesList = json_decode($row['our_heroes_json'], true);
                if (is_array($heroesList)) {
                    foreach ($heroesList as $heroId) {
                        $usageCounts[$heroId] = ($usageCounts[$heroId] ?? 0) + 1;
                    }
                }
            }
            if ($row['enemy_heroes_json']) {
                $heroesList = json_decode($row['enemy_heroes_json'], true);
                if (is_array($heroesList)) {
                    foreach ($heroesList as $heroId) {
                        $usageCounts[$heroId] = ($usageCounts[$heroId] ?? 0) + 1;
                    }
                }
            }
        }
        
        foreach ($heroes as &$hero) {
            $hero['usage_count'] = $usageCounts[$hero['id']] ?? 0;
        }
        
        usort($heroes, function($a, $b) {
            if ($b['usage_count'] !== $a['usage_count']) {
                return $b['usage_count'] - $a['usage_count'];
            }
            return strcasecmp($a['name'], $b['name']);
        });
        
        echo json_encode($heroes);
    }
    
    public function addHero($name, $faction, $class, $damage, $defense, $support, $healing, $imageFile) {
        if (!$name) {
            echo json_encode(['success' => false, 'error' => 'Введите имя героя']);
            return;
        }
        
        $damage = max(0, min(5, (int)$damage));
        $defense = max(0, min(5, (int)$defense));
        $support = max(0, min(5, (int)$support));
        $healing = max(0, min(5, (int)$healing));
        
        $imagePath = null;
        
        if ($imageFile && $imageFile['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../uploads/heroes/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $ext = strtolower(pathinfo($imageFile['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (!in_array($ext, $allowed)) {
                echo json_encode(['success' => false, 'error' => 'Неверный формат файла']);
                return;
            }
            
            $filename = time() . '_' . md5($name) . '.' . $ext;
            $destination = $uploadDir . $filename;
            
            if (move_uploaded_file($imageFile['tmp_name'], $destination)) {
                $imagePath = '/uploads/heroes/' . $filename;
            } else {
                echo json_encode(['success' => false, 'error' => 'Ошибка загрузки файла']);
                return;
            }
        }
        
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO heroes_catalog 
                (name, faction, class, synergy, damage, defense, support, healing, image) 
                VALUES (?, ?, ?, NULL, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $name, 
                $faction ?: null, 
                $class ?: null, 
                $damage, 
                $defense, 
                $support, 
                $healing, 
                $imagePath
            ]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => 'Ошибка БД: ' . $e->getMessage()]);
        }
    }
    
    public function updateHero($id, $name, $faction, $class, $synergy, $damage, $defense, $support, $healing, $imageFile = null) {
        if (!$id) {
            echo json_encode(['success' => false, 'error' => 'ID не указан']);
            return;
        }
        
        if (!$name) {
            echo json_encode(['success' => false, 'error' => 'Введите имя героя']);
            return;
        }
        
        $damage = max(0, min(5, (int)$damage));
        $defense = max(0, min(5, (int)$defense));
        $support = max(0, min(5, (int)$support));
        $healing = max(0, min(5, (int)$healing));
        
        $synergyJson = null;
        if ($synergy) {
            $synergyArray = is_array($synergy) ? $synergy : json_decode($synergy, true);
            if (is_array($synergyArray) && !empty($synergyArray)) {
                $synergyJson = json_encode($synergyArray);
            }
        }
        
        $updateImage = false;
        $imagePath = null;
        
        if ($imageFile && $imageFile['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../uploads/heroes/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $ext = strtolower(pathinfo($imageFile['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (!in_array($ext, $allowed)) {
                echo json_encode(['success' => false, 'error' => 'Неверный формат файла']);
                return;
            }
            
            $filename = time() . '_' . md5($id . $name) . '.' . $ext;
            $destination = $uploadDir . $filename;
            
            if (move_uploaded_file($imageFile['tmp_name'], $destination)) {
                $imagePath = '/uploads/heroes/' . $filename;
                $updateImage = true;
                
                $stmt = $this->pdo->prepare("SELECT image FROM heroes_catalog WHERE id = ?");
                $stmt->execute([$id]);
                $old = $stmt->fetch();
                if ($old && $old['image'] && file_exists('../' . $old['image'])) {
                    unlink('../' . $old['image']);
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'Ошибка сохранения файла']);
                return;
            }
        }
        
        try {
            if ($updateImage) {
                $stmt = $this->pdo->prepare("
                    UPDATE heroes_catalog 
                    SET name = ?, faction = ?, class = ?, synergy = ?, 
                        damage = ?, defense = ?, support = ?, healing = ?, image = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $name, $faction ?: null, $class ?: null, $synergyJson,
                    $damage, $defense, $support, $healing, $imagePath, $id
                ]);
            } else {
                $stmt = $this->pdo->prepare("
                    UPDATE heroes_catalog 
                    SET name = ?, faction = ?, class = ?, synergy = ?, 
                        damage = ?, defense = ?, support = ?, healing = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $name, $faction ?: null, $class ?: null, $synergyJson,
                    $damage, $defense, $support, $healing, $id
                ]);
            }
            
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => 'Ошибка БД: ' . $e->getMessage()]);
        }
    }
    
    public function updateHeroImage($id, $imageFile) {
        if (!$id) {
            echo json_encode(['success' => false, 'error' => 'ID не указан']);
            return;
        }
        
        if (!$imageFile || $imageFile['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'error' => 'Выберите файл']);
            return;
        }
        
        $uploadDir = '../uploads/heroes/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $ext = strtolower(pathinfo($imageFile['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (!in_array($ext, $allowed)) {
            echo json_encode(['success' => false, 'error' => 'Неверный формат файла']);
            return;
        }
        
        $stmt = $this->pdo->prepare("SELECT image FROM heroes_catalog WHERE id = ?");
        $stmt->execute([$id]);
        $old = $stmt->fetch();
        if ($old && $old['image'] && file_exists('../' . $old['image'])) {
            unlink('../' . $old['image']);
        }
        
        $filename = time() . '_' . md5($id) . '.' . $ext;
        $destination = $uploadDir . $filename;
        
        if (!move_uploaded_file($imageFile['tmp_name'], $destination)) {
            echo json_encode(['success' => false, 'error' => 'Ошибка сохранения файла']);
            return;
        }
        
        $imagePath = '/uploads/heroes/' . $filename;
        
        $stmt = $this->pdo->prepare("UPDATE heroes_catalog SET image = ? WHERE id = ?");
        $stmt->execute([$imagePath, $id]);
        
        echo json_encode(['success' => true]);
    }
    
    public function deleteHero($id) {
        $likePattern = '%"' . $id . '"%';
        
        $check = $this->pdo->prepare("
            SELECT id FROM battle_items 
            WHERE our_heroes_json LIKE ? OR enemy_heroes_json LIKE ? 
            LIMIT 1
        ");
        $check->execute([$likePattern, $likePattern]);
        if ($check->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Герой используется в боях']);
            return;
        }
        
        $checkSynergy = $this->pdo->prepare("
            SELECT id FROM heroes_catalog 
            WHERE synergy LIKE ? AND id != ?
            LIMIT 1
        ");
        $checkSynergy->execute([$likePattern, $id]);
        if ($checkSynergy->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Герой используется в синергии']);
            return;
        }
        
        $stmt = $this->pdo->prepare("SELECT image FROM heroes_catalog WHERE id = ?");
        $stmt->execute([$id]);
        $hero = $stmt->fetch();
        if ($hero && $hero['image'] && file_exists('../' . $hero['image'])) {
            unlink('../' . $hero['image']);
        }
        
        $stmt = $this->pdo->prepare("DELETE FROM heroes_catalog WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode(['success' => true]);
    }
}

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Не авторизован']);
    exit;
}

$handler = new HeroHandler($pdo);
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_heroes':
        $handler->getHeroes();
        break;
    case 'get_heroes_sorted':
        $handler->getHeroesSorted();
        break;
    case 'add_hero':
        $handler->addHero(
            trim($_POST['name'] ?? ''),
            $_POST['faction'] ?? null,
            $_POST['class'] ?? null,
            (int)($_POST['damage'] ?? 3),
            (int)($_POST['defense'] ?? 3),
            (int)($_POST['support'] ?? 3),
            (int)($_POST['healing'] ?? 3),
            $_FILES['image'] ?? null
        );
        break;
    case 'update_hero':
        $handler->updateHero(
            $_POST['id'] ?? 0,
            trim($_POST['name'] ?? ''),
            $_POST['faction'] ?? null,
            $_POST['class'] ?? null,
            $_POST['synergy'] ?? null,
            (int)($_POST['damage'] ?? 0),
            (int)($_POST['defense'] ?? 0),
            (int)($_POST['support'] ?? 0),
            (int)($_POST['healing'] ?? 0),
            $_FILES['image'] ?? null
        );
        break;
    case 'update_hero_image':
        $handler->updateHeroImage($_POST['id'] ?? 0, $_FILES['image'] ?? null);
        break;
    case 'delete_hero':
        $handler->deleteHero($_POST['id'] ?? 0);
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Неизвестное действие']);
}
?>