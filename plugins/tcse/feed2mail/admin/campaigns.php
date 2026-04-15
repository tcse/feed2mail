<?php
// admin/campaigns.php
require_once __DIR__ . '/auth.php';
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
    $plainTextMode = isset($_POST['plain_text_mode']);
    
    if (!$subject || !$content) {
        $message = '<p style="color:red;">Заполните тему и содержание письма</p>';
    } else {
        // Если включён режим Plain Text — преобразуем переносы в <br>
        if ($plainTextMode) {
            $content = nl2br(htmlspecialchars($content));
        }
        
        $sent = 0;
        $errors = [];
        
        foreach ($activeSubscribers as $sub) {
            $unsubscribeLink = $config['site_url'] . $config['plugin_path'] . '/api/unsubscribe.php?token=' . $sub['token'] . '&email=' . urlencode($sub['email']);
            
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
        
        unset($_SESSION['rss_html']);
        unset($_SESSION['rss_subject']);
    }
}

$savedHtml = $_SESSION['rss_html'] ?? '';
$savedSubject = $_SESSION['rss_subject'] ?? '';
$plainTextMode = $_POST['plain_text_mode'] ?? false;
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
        .mode-switch {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        .mode-switch label {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-weight: normal;
            margin: 0;
        }
        .toolbar {
            background: #f8f9fa;
            padding: 8px;
            border-radius: 6px;
            margin-bottom: 10px;
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        .toolbar button {
            background: #e9ecef;
            border: 1px solid #ced4da;
            border-radius: 4px;
            padding: 5px 10px;
            cursor: pointer;
            font-size: 14px;
        }
        .toolbar button:hover {
            background: #dee2e6;
        }
        .info-text {
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
                <!-- Переключатель режимов -->
                <div class="mode-switch">
                    <label>
                        <input type="checkbox" name="plain_text_mode" id="plainTextMode" <?= $plainTextMode ? 'checked' : '' ?>>
                        🔤 Режим Plain Text (переносы строк автоматически заменяются на &lt;br&gt;)
                    </label>
                    <span style="font-size:12px; color:#666;">| HTML-теги в этом режиме будут отображаться как текст</span>
                </div>
                
                <!-- Панель инструментов (показывается только в HTML-режиме) -->
                <div id="htmlToolbar" class="toolbar" style="<?= $plainTextMode ? 'display:none;' : '' ?>">
                    <button type="button" onclick="insertTag('b')"><b>B</b></button>
                    <button type="button" onclick="insertTag('i')"><i>I</i></button>
                    <button type="button" onclick="insertTag('strong')"><b>Strong</b></button>
                    <button type="button" onclick="insertTag('em')"><i>Em</i></button>
                    <span style="color:#ccc;">|</span>
                    <button type="button" onclick="insertTag('p')">&lt;p&gt;</button>
                    <button type="button" onclick="insertTag('br')">&lt;br&gt;</button>
                    <span style="color:#ccc;">|</span>
                    <button type="button" onclick="insertLink()">🔗 Ссылка</button>
                    <button type="button" onclick="insertImage()">🖼️ Картинка</button>
                </div>
                
                <label>📄 Содержание:</label><br>
                <textarea name="content" rows="12" style="width:100%; font-family: monospace;" 
                          id="contentArea" required placeholder="Введите текст письма..."><?= htmlspecialchars($savedHtml) ?></textarea>
                <div class="char-counter">
                    <span id="charCount">0</span> символов
                    <button type="button" onclick="previewEmail()" style="float: right; background: #6c757d;">👁️ Предпросмотр</button>
                </div>
                <div class="info-text">
                    💡 Подсказка: При включённом режиме Plain Text переносы строк сохраняются автоматически.
                    При выключенном — можно использовать HTML-теги: <code>&lt;b&gt;жирный&lt;/b&gt;</code>, <code>&lt;i&gt;курсив&lt;/i&gt;</code>, <code>&lt;a href="..."&gt;ссылка&lt;/a&gt;</code>
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
        const textarea = document.getElementById('contentArea');
        const charCount = document.getElementById('charCount');
        const plainTextMode = document.getElementById('plainTextMode');
        const htmlToolbar = document.getElementById('htmlToolbar');
        
        function updateCharCount() {
            charCount.textContent = textarea.value.length;
        }
        
        textarea.addEventListener('input', updateCharCount);
        updateCharCount();
        
        // Показать/скрыть панель инструментов при переключении режима
        plainTextMode.addEventListener('change', function() {
            if (this.checked) {
                htmlToolbar.style.display = 'none';
            } else {
                htmlToolbar.style.display = 'flex';
            }
        });
        
        // Вставка HTML-тегов
        function insertTag(tag) {
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const text = textarea.value;
            const selectedText = text.substring(start, end);
            
            let replacement;
            if (tag === 'br') {
                replacement = '<br>';
            } else if (tag === 'p') {
                replacement = selectedText ? `<p>${selectedText}</p>` : '<p></p>';
            } else {
                replacement = selectedText ? `<${tag}>${selectedText}</${tag}>` : `<${tag}></${tag}>`;
            }
            
            textarea.value = text.substring(0, start) + replacement + text.substring(end);
            textarea.focus();
            textarea.setSelectionRange(start + replacement.length, start + replacement.length);
            updateCharCount();
        }
        
        function insertLink() {
            const url = prompt('Введите URL ссылки:', 'https://');
            if (!url) return;
            
            const text = prompt('Введите текст ссылки:', 'читать далее');
            if (!text) return;
            
            const link = `<a href="${url}">${text}</a>`;
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            textarea.value = textarea.value.substring(0, start) + link + textarea.value.substring(end);
            textarea.focus();
            updateCharCount();
        }
        
        function insertImage() {
            const url = prompt('Введите URL изображения:', 'https://');
            if (!url) return;
            
            const alt = prompt('Введите alt-текст:', 'изображение');
            const img = `<img src="${url}" alt="${alt || 'изображение'}" style="max-width:100%;">`;
            
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            textarea.value = textarea.value.substring(0, start) + img + textarea.value.substring(end);
            textarea.focus();
            updateCharCount();
        }
        
        function previewEmail() {
            let content = textarea.value;
            const subject = document.querySelector('input[name="subject"]').value;
            const isPlainText = plainTextMode.checked;
            
            // Если Plain Text — преобразуем переносы в <br> для предпросмотра
            if (isPlainText) {
                content = content.replace(/\n/g, '<br>');
            }
            
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
