<?php
session_start();
require_once 'config.php';

function isLoggedIn() {
    return isset($_SESSION['admin_id']) && isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function getCurrentAdmin() {
    if (!isLoggedIn()) return null;
    return [
        'id' => $_SESSION['admin_id'],
        'username' => $_SESSION['admin_username'],
        'role' => $_SESSION['admin_role']
    ];
}

function isSuperAdmin() {
    return isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'superadmin';
}

function login($username, $password) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT * FROM admin 
        WHERE username = ? AND is_active = 1
    ");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();
    
    if ($admin && md5($password) === $admin['password_hash']) {
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        $_SESSION['admin_role'] = $admin['role'];
        $_SESSION['admin_logged_in'] = true;
        
        // Обновляем время последнего входа
        $update = $pdo->prepare("
            UPDATE admin SET last_login = NOW(), last_ip = ? WHERE id = ?
        ");
        $update->execute([$_SERVER['REMOTE_ADDR'], $admin['id']]);
        
        return true;
    }
    return false;
}

function logout() {
    global $pdo;
    
    // Снимаем все блокировки текущего админа
    if (isset($_SESSION['admin_id'])) {
        $stmt = $pdo->prepare("
            UPDATE battles 
            SET locked_by = NULL, locked_at = NULL 
            WHERE locked_by = ?
        ");
        $stmt->execute([$_SESSION['admin_id']]);
    }
    
    $_SESSION = array();
    session_destroy();
}

// Проверить, не истекла ли блокировка (10 минут)
function isLockExpired($lockedAt) {
    if (!$lockedAt) return true;
    $lockTime = strtotime($lockedAt);
    $currentTime = time();
    return ($currentTime - $lockTime) > 600; // 10 минут
}

// Снять блокировку с боя (для использования в других файлах)
function unlockBattle($battleId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        UPDATE battles 
        SET locked_by = NULL, locked_at = NULL
        WHERE id = ?
    ");
    return $stmt->execute([$battleId]);
}
?>