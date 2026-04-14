<?php
// admin/index.php
require_once __DIR__ . '/auth.php';  // <-- ДОБАВИТЬ ЭТУ СТРОКУ
require_once __DIR__ . '/../config.php';

// В самом начале admin/index.php, после require_once config.php
if (!isset($config) || empty($config['site_url'])) {
    $configFile = __DIR__ . '/../data/config.json';
    if (file_exists($configFile)) {
        $config = json_decode(file_get_contents($configFile), true);
    }
    if (!isset($config['site_url'])) {
        $config['site_url'] = '';
    }
}

$subscribers = loadData('subscribers');
$activeCount = count(array_filter($subscribers, fn($s) => $s['status'] === 'active'));
$pendingCount = count(array_filter($subscribers, fn($s) => $s['status'] === 'pending'));
$unsubscribedCount = count(array_filter($subscribers, fn($s) => $s['status'] === 'unsubscribed'));
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Feed2Mail — Админка</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
    <div class="container">
        <h1>Feed2Mail — Управление рассылками</h1>
        
        <div class="stats">
            <div class="stat-card">Активных: <?= $activeCount ?></div>
            <div class="stat-card">Ожидают: <?= $pendingCount ?></div>
            <div class="stat-card">Отписавшихся: <?= $unsubscribedCount ?></div>
        </div>
        
        <nav>
            <a href="subscribers.php">📧 Подписчики</a>
            <a href="campaigns.php">✉️ Рассылки</a>
            <a href="rss_import.php">📡 RSS импорт</a>
            <a href="templates.php">🎨 Шаблоны</a>
            <a href="settings.php" style="background: #6c757d; color: white; padding: 5px 10px; border-radius: 4px;">⚙️ Настройки писем</a>
            <a href="logout.php" style="color: #dc3545; float: right;">🚪 Выйти</a>
        </nav>
        
        <div class="form-subscribe-example">
    <h3>Код для вставки формы подписки на сайт</h3>
    <pre>&lt;div id="feed2mail-form" 
     data-site-url="<?= $config['site_url'] ?>" 
     data-plugin-path="<?= $config['plugin_path'] ?>">&lt;/div>
&lt;script src="<?= $config['site_url'] . $config['plugin_path'] ?>/assets/embed.js">&lt;/script></pre>
</div>
    </div>
</body>
</html>