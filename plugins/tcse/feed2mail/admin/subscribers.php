<?php
// admin/subscribers.php
require_once __DIR__ . '/auth.php';  // <-- ДОБАВИТЬ ЭТУ СТРОКУ
require_once __DIR__ . '/../config.php';

$subscribers = loadData('subscribers');
$active = array_filter($subscribers, fn($s) => $s['status'] === 'active');
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Подписчики — Feed2Mail</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
    <div class="container">
        <h1>📧 Подписчики</h1>
        <p><a href="index.php">← Назад к дашборду</a></p>
        
        <table border="1" cellpadding="8" style="width:100%; border-collapse:collapse;">
            <tr>
                <th>Email</th>
                <th>Статус</th>
                <th>Дата подписки</th>
                <th>Действие</th>
            </tr>
            <?php foreach ($subscribers as $sub): ?>
            <tr>
                <td><?= htmlspecialchars($sub['email']) ?></td>
                <td><?= $sub['status'] ?></td>
                <td><?= $sub['subscribed_at'] ?></td>
                <td>
                    <a href="../api/unsubscribe.php?token=<?= $sub['token'] ?>&email=<?= urlencode($sub['email']) ?>">
                        Отписать
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($subscribers)): ?>
            <tr><td colspan="4">Нет подписчиков</td></tr>
            <?php endif; ?>
        </table>
        
        <h3>Добавить подписчика вручную</h3>
        <form method="POST" action="">
            <input type="email" name="email" required placeholder="Email">
            <button type="submit" name="add">Добавить</button>
        </form>
        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
            $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
            if ($email) {
                $subscribers[] = [
                    'id' => uniqid(),
                    'email' => $email,
                    'status' => 'active',
                    'subscribed_at' => date('Y-m-d H:i:s'),
                    'token' => generateToken()
                ];
                saveData('subscribers', $subscribers);
                echo '<p style="color:green;">Подписчик добавлен</p>';
                header('Refresh:0');
            } else {
                echo '<p style="color:red;">Некорректный email</p>';
            }
        }
        ?>
    </div>
</body>
</html>