<?php
// api/subscribe.php
require_once dirname(__DIR__) . '/config.php';

// ===== ДОБАВИТЬ ЭТОТ БЛОК =====
// Разрешаем кросс-доменные запросы для виджета
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

// Обрабатываем preflight OPTIONS запрос (браузер отправляет его перед POST)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
// ===== КОНЕЦ БЛОКА =====

// GET запрос — подтверждение подписки
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['token'], $_GET['email'])) {
    // Явно указываем кодировку UTF-8 для браузера
    header('Content-Type: text/html; charset=utf-8');
    $email = filter_var($_GET['email'], FILTER_VALIDATE_EMAIL);
    $token = $_GET['token'];
    
    if (!$email) {
        die('Некорректный email');
    }
    
    $subscriber = getSubscriberByToken($token);
    if ($subscriber && $subscriber['email'] === $email) {
        updateSubscriberStatus($email, 'active');
        echo "✅ Подписка подтверждена! Спасибо.";
    } else {
        echo "❌ Неверный токен или email.";
    }
    exit;
}

// POST запрос — новая подписка
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $email = filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL);
    
    if (!$email) {
        echo json_encode(['success' => false, 'message' => 'Некорректный email']);
        exit;
    }
    
    $subscribers = loadData('subscribers');
    
    // Проверяем, нет ли уже
    foreach ($subscribers as $sub) {
        if ($sub['email'] === $email) {
            echo json_encode(['success' => false, 'message' => 'Этот email уже подписан']);
            exit;
        }
    }
    
    $token = generateToken();
    $subscribers[] = [
        'id' => uniqid(),
        'email' => $email,
        'status' => 'pending',
        'subscribed_at' => date('Y-m-d H:i:s'),
        'token' => $token
    ];
    
    saveData('subscribers', $subscribers);
    
    // Отправляем письмо с подтверждением
    sendSubscriptionConfirmation($email, $token);
    
    echo json_encode(['success' => true, 'message' => 'Письмо с подтверждением отправлено']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Метод не поддерживается']);