<?php
if (!isset($hideNav)) $hideNav = false;
$Version = '1.7';

// Проверка авторизации для всех страниц (кроме login.php)
$currentFile = basename($_SERVER['PHP_SELF']);
if ($currentFile != 'login.php' && !isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Получаем роль текущего пользователя
$userRole = $_SESSION['admin_role'] ?? 'user';
$isAdmin = ($userRole == 'admin' || $userRole == 'superadmin');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? '♛ Imperial ♛' ?></title>
    <link rel="icon" href="/uploads/favicon.ico" type="image/x-icon">
    <link rel="icon" type="image/png" sizes="32x32" href="/uploads/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/uploads/favicon-16x16.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
	<link rel="stylesheet" href="/css/custom.css?v=<?= $Version ?>">
	<link rel="stylesheet" href="/css/base/variables.css?v=<?= $Version ?>">

    <?php
		$pageCss = basename($currentFile, '.php');
		$pageCss = str_replace('_', '-', $pageCss);
		$cssPath = $_SERVER['DOCUMENT_ROOT'] . "/css/pages/{$pageCss}.css";
		if (file_exists($cssPath)): ?>
			<link rel="stylesheet" href="/css/pages/<?= $pageCss ?>.css?v=<?= $Version ?>">
		<?php endif; 
	?>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0"></script>
    <script src="/js/hero-selector.js?v=<?= $Version ?>"></script>
</head>
<body>

<?php if (!$hideNav): ?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
    <div class="container">
        <a class="navbar-brand" href="/">
            <i class="fas fa-trophy"></i> Война гильдий
        </a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarMain">
            <ul class="navbar-nav ms-auto">
                
                <?php if ($isAdmin): ?>
                    <!-- Полное меню для админов -->
                    <li class="nav-item">
                        <a class="nav-link <?= $currentFile == 'index.php' ? 'active' : '' ?>" href="/index.php">
                            <i class="fas fa-history"></i> Бои
                        </a>
                    </li>
                    
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                            <i class="fas fa-tools"></i> Сервисы
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="/counter_search.php"><i class="fas fa-chart-line"></i> Примерочная</a></li>
                            <li><a class="dropdown-item" href="/titan_helper.php"><i class="fas fa-dragon"></i> Помощник титанов</a></li>
                            <li><a class="dropdown-item" href="/hero_helper.php"><i class="fas fa-user-ninja"></i> Помощник героев</a></li>
							<li><a class="dropdown-item"  href="/schedule.php"><i class="fas fa-calendar-week"></i> Расписание</a>
                    </li>
							
                        </ul>
                    </li>
                    
                    <li class="nav-item">
                        <a class="nav-link <?= $currentFile == 'guilds.php' ? 'active' : '' ?>" href="/guilds.php">
                            <i class="fas fa-users"></i> Гильдии
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentFile == 'heroes_catalog.php' ? 'active' : '' ?>" href="/heroes_catalog.php">
                            <i class="fa-solid fa-user-ninja"></i> Герои
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentFile == 'titans_catalog.php' ? 'active' : '' ?>" href="/titans_catalog.php">
                            <i class="fas fa-dragon"></i> Стихии титанов
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentFile == 'stats.php' ? 'active' : '' ?>" href="/stats.php">
                            <i class="fas fa-chart-line"></i> Статистика
                        </a>
                    </li>
                    
                <?php endif; ?>
                
                <!-- Мои бои  -->
                <li class="nav-item">
                    <a class="nav-link <?= $currentFile == 'my-battles.php' ? 'active' : '' ?>" href="/my_battles.php">
                        <i class="fas fa-user-fighter"></i> Мои бои
                    </a>
                </li>
                
                <?php if (isLoggedIn()): ?>
                <li class="nav-item">
                    <a class="nav-link text-danger" href="/logout.php">
                        <i class="fas fa-sign-out-alt"></i> Выход
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
<?php endif; ?>

<main class="py-4">