<?php
// admin/campaigns.php
require_once __DIR__ . '/auth.php';  // <-- ДОБАВИТЬ ЭТУ СТРОКУ
require_once __DIR__ . '/../config.php';

// Если перешли из RSS-генератора
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['rss_html'])) {
        $rssHtml = $_POST['rss_html'];
        $_SESSION['rss_html'] = $rssHtml;
    }
    if (isset($_POST['rss_subject'])) {
        $_SESSION['rss_subject'] = trim($_POST['rss_subject']);
    }
}

$subscribers = loadData('subscribers');
$activeSubscribers = array_filter($subscribers, fn($s) => $s['status'] === 'active');

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subject'], $_POST['content'])) {
    $subject = trim($_POST['subject']);
    $content = $_POST['content'];
    
    if (!$subject || !$content) {
        $message = '<p style="color:red;">Заполните тему и содержание письма</p>';
    } else {
        $sent = 0;
        $errors = [];
        
        // В цикле отправки писем (в campaigns.php)
        foreach ($activeSubscribers as $sub) {
            $unsubscribeLink = $config['site_url'] . '/api/unsubscribe.php?token=' . $sub['token'] . '&email=' . urlencode($sub['email']);
            
            // Оборачиваем содержимое в шапку и подвал
            $fullHtml = wrapEmailTemplate($content, $unsubscribeLink);
            
            if (sendMail($sub['email'], $subject, $fullHtml)) {
                $sent++;
            } else {
                $errors[] = $sub['email'];
            }
        }
        
        $message = "<p style=\"color:green;\">✅ Рассылка отправлена {$sent} подписчикам</p>";
        if (!empty($errors)) {
            $message .= "<p style=\"color:orange;\">⚠️ Не удалось отправить: " . implode(', ', $errors) . "</p>";
        }
        
        // Очищаем сессию после отправки
        unset($_SESSION['rss_html']);
        unset($_SESSION['rss_subject']);
    }
}

// Получаем значения из сессии для подстановки в форму
$savedHtml = $_SESSION['rss_html'] ?? '';
$savedSubject = $_SESSION['rss_subject'] ?? '';
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Рассылки — Feed2Mail</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .preview-btn {
            background: #6c757d;
            margin-left: 10px;
        }
        .char-counter {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>✉️ Рассылки</h1>
        <p><a href="index.php">← Назад к дашборду</a></p>
        
        <div style="background: #e3f2fd; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
            <strong>📊 Статистика:</strong> Активных подписчиков: <?= count($activeSubscribers) ?>
        </div>
        
        <?= $message ?>
        
        <form method="POST" id="campaignForm">
            <div>
                <label>📝 Тема письма:</label><br>
                <input type="text" name="subject" style="width:100%; padding:8px;" 
                       value="<?= htmlspecialchars($savedSubject) ?>" required
                       placeholder="Например: Новости за неделю">
            </div>
            <div style="margin-top:15px;">
                <label>📄 Содержание (HTML):</label><br>
                <textarea name="content" rows="12" style="width:100%; font-family: monospace;" 
                          id="contentArea" required placeholder="Введите HTML-код письма..."><?= htmlspecialchars($savedHtml) ?></textarea>
                <div class="char-counter">
                    <span id="charCount">0</span> символов
                    <button type="button" onclick="previewEmail()" style="float: right; background: #6c757d;">👁️ Предпросмотр</button>
                </div>
            </div>
            <button type="submit" style="margin-top:15px; padding:10px 20px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer;">
                📧 Отправить всем (<?= count($activeSubscribers) ?>)
            </button>
            <button type="button" onclick="clearForm()" style="margin-top:15px; padding:10px 20px; background: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer; margin-left: 10px;">
                🗑️ Очистить форму
            </button>
        </form>
    </div>
    
    <script>
        // Подсчёт символов
        const textarea = document.getElementById('contentArea');
        const charCount = document.getElementById('charCount');
        
        function updateCharCount() {
            charCount.textContent = textarea.value.length;
        }
        
        textarea.addEventListener('input', updateCharCount);
        updateCharCount();
        
        function previewEmail() {
            const content = textarea.value;
            const subject = document.querySelector('input[name="subject"]').value;
            const previewWindow = window.open();
            previewWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="UTF-8">
                    <title>${subject || 'Предпросмотр письма'}</title>
                </head>
                <body>
                    ${content}
                </body>
                </html>
            `);
            previewWindow.document.close();
        }
        
        function clearForm() {
            if (confirm('Очистить форму? Все несохранённые данные будут потеряны.')) {
                document.querySelector('input[name="subject"]').value = '';
                textarea.value = '';
                updateCharCount();
            }
        }
    </script>
</body>
</html>