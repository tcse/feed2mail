<?php
// api/unsubscribe.php
require_once dirname(__DIR__) . '/config.php';

$token = $_GET['token'] ?? '';
$email = $_GET['email'] ?? '';

if (!$token || !$email) {
    die('Неверная ссылка для отписки');
}

$subscribers = loadData('subscribers');
foreach ($subscribers as &$sub) {
    if ($sub['email'] === $email && $sub['token'] === $token) {
        $sub['status'] = 'unsubscribed';
        saveData('subscribers', $subscribers);
        sendUnsubscribeConfirm($email, $token);
        echo "Вы отписаны от рассылки. Жаль :(";
        exit;
    }
}

echo "Подписчик не найден или уже отписан.";