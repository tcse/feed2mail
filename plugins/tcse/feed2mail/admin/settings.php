<?php
// admin/settings.php
require_once __DIR__ . '/auth.php';  // <-- ДОБАВИТЬ ЭТУ СТРОКУ
require_once __DIR__ . '/../config.php';

$message = '';
$error = '';

// Создаём директорию для загрузки логотипов
$uploadDir = ROOT_PATH . '/assets/uploads/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Загружаем настройки шаблона
$templateSettingsFile = DATA_PATH . '/template_settings.json';
if (!file_exists($templateSettingsFile)) {
    $defaultSettings = [
        'site_name_in_email' => $config['site_name'] ?? 'Мой сайт',
        'header' => '<div style="background: #1a1a2e; padding: 20px; text-align: center;">
                        <h1 style="color: #fff; margin: 0;">{{SITE_NAME}}</h1>
                        <p style="color: #aaa; margin: 5px 0 0;">{{SITE_URL}}</p>
                     </div>',
        'footer' => '<div style="background: #f5f5f5; padding: 20px; text-align: center; font-size: 12px; color: #666;">
                        <p>© {{YEAR}} {{SITE_NAME}}. Все права защищены.</p>
                        <p>Вы получили это письмо, потому что подписаны на нашу рассылку.</p>
                        <p><a href="{{UNSUBSCRIBE_LINK}}" style="color: #007bff;">Отписаться от рассылки</a></p>
                     </div>',
        'logo_url' => '',
        'logo_position' => 'top',
        'use_header' => true,
        'use_footer' => true
    ];
    file_put_contents($templateSettingsFile, json_encode($defaultSettings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    $templateSettings = $defaultSettings;
} else {
    $templateSettings = json_decode(file_get_contents($templateSettingsFile), true);
    // Добавляем поле site_name_in_email, если его нет (обратная совместимость)
    if (!isset($templateSettings['site_name_in_email'])) {
        $templateSettings['site_name_in_email'] = $config['site_name'] ?? 'Мой сайт';
    }
    // Добавляем поля use_header/use_footer, если их нет
    if (!isset($templateSettings['use_header'])) {
        $templateSettings['use_header'] = true;
    }
    if (!isset($templateSettings['use_footer'])) {
        $templateSettings['use_footer'] = true;
    }
}

// Обработка загрузки логотипа
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $fileType = mime_content_type($_FILES['logo']['tmp_name']);
        
        if (in_array($fileType, $allowedTypes)) {
            $ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
            $filename = 'logo_' . time() . '.' . $ext;
            $uploadPath = $uploadDir . $filename;
            
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $uploadPath)) {
                // Удаляем старый логотип, если есть
                if (!empty($templateSettings['logo_url'])) {
                    $oldFile = ROOT_PATH . '/' . str_replace($config['site_url'] . $config['plugin_path'] . '/', '', $templateSettings['logo_url']);
                    if (file_exists($oldFile)) {
                        unlink($oldFile);
                    }
                }
                // Формируем правильный URL с учётом пути плагина
                $pluginPath = $config['plugin_path'] ?? '/plugins/tcse/feed2mail_v2';
                $templateSettings['logo_url'] = rtrim($config['site_url'], '/') . $pluginPath . '/assets/uploads/' . $filename;
                $message = "Логотип успешно загружен!";
            } else {
                $error = "Ошибка при сохранении файла";
            }
        } else {
            $error = "Разрешены только JPG, PNG, GIF, WEBP";
        }
    }
    
    // Сохраняем текстовые настройки
    $templateSettings['site_name_in_email'] = trim($_POST['site_name_in_email'] ?? $config['site_name'] ?? 'Мой сайт');
    $templateSettings['header'] = $_POST['header'] ?? '';
    $templateSettings['footer'] = $_POST['footer'] ?? '';
    $templateSettings['logo_position'] = $_POST['logo_position'] ?? 'top';
    $templateSettings['use_header'] = isset($_POST['use_header']);
    $templateSettings['use_footer'] = isset($_POST['use_footer']);
    
    file_put_contents($templateSettingsFile, json_encode($templateSettings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    $message = "Настройки сохранены!";
}

// Удаление логотипа
if (isset($_GET['delete_logo'])) {
    if (!empty($templateSettings['logo_url'])) {
        // Правильно извлекаем путь из URL
        $pluginPath = $config['plugin_path'] ?? '/plugins/tcse/feed2mail_v2';
        $relativePath = str_replace($config['site_url'] . $pluginPath . '/', '', $templateSettings['logo_url']);
        $oldFile = ROOT_PATH . '/' . $relativePath;
        if (file_exists($oldFile)) {
            unlink($oldFile);
        }
        $templateSettings['logo_url'] = '';
        file_put_contents($templateSettingsFile, json_encode($templateSettings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $message = "Логотип удалён";
    }
    header('Location: settings.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Настройки писем — Feed2Mail</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .settings-section {
            background: #f9f9f9;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            border: 1px solid #ddd;
        }
        .logo-preview {
            max-width: 200px;
            max-height: 100px;
            margin-top: 10px;
            border: 1px solid #ddd;
            padding: 5px;
        }
        label {
            font-weight: bold;
            display: block;
            margin-top: 10px;
        }
        textarea {
            width: 100%;
            font-family: monospace;
            min-height: 150px;
        }
        .help-text {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        input[type="text"], input[type="url"] {
            width: 100%;
            padding: 8px;
            margin-top: 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .button-group {
            margin-top: 15px;
        }
        .btn-primary {
            background: #28a745;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-secondary {
            background: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🎨 Настройки шаблонов писем</h1>
        <p><a href="index.php">← Назад к дашборду</a></p>
        
        <?php if ($message): ?>
            <div style="background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin-bottom: 20px;">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div style="background: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin-bottom: 20px;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" enctype="multipart/form-data">
            <div class="settings-section">
                <h3>🏷️ Основные настройки письма</h3>
                <label>Название сайта для писем:</label>
                <input type="text" name="site_name_in_email" 
                       value="<?= htmlspecialchars($templateSettings['site_name_in_email']) ?>" 
                       placeholder="Например: Мой блог или Донгфенг">
                <div class="help-text">Будет подставляться вместо <code>{{SITE_NAME}}</code> в шапке и подвале</div>
            </div>
            
            <div class="settings-section">
                <h3>🖼️ Логотип</h3>
                <?php if (!empty($templateSettings['logo_url'])): ?>
                    <div>
                        <strong>Текущий логотип:</strong><br>
                        <img src="<?= $templateSettings['logo_url'] ?>" class="logo-preview" alt="Logo">
                        <br>
                        <a href="?delete_logo=1" onclick="return confirm('Удалить логотип?')" style="color: red;">🗑️ Удалить логотип</a>
                    </div>
                <?php endif; ?>
                
                <label>Загрузить новый логотип:</label>
                <input type="file" name="logo" accept="image/jpeg,image/png,image/gif,image/webp">
                
                <label>Позиция логотипа:</label>
                <select name="logo_position">
                    <option value="top" <?= $templateSettings['logo_position'] === 'top' ? 'selected' : '' ?>>Вверху письма</option>
                    <option value="bottom" <?= $templateSettings['logo_position'] === 'bottom' ? 'selected' : '' ?>>Внизу письма</option>
                    <option value="both" <?= $templateSettings['logo_position'] === 'both' ? 'selected' : '' ?>>И вверху, и внизу</option>
                </select>
                <div class="help-text">Логотип будет вставлен в письмо как изображение по прямой ссылке</div>
            </div>
            
            <div class="settings-section">
                <h3>📌 Шапка письма</h3>
                <label>
                    <input type="checkbox" name="use_header" <?= $templateSettings['use_header'] ? 'checked' : '' ?>> 
                    Включить шапку
                </label>
                <textarea name="header" rows="8"><?= htmlspecialchars($templateSettings['header']) ?></textarea>
                <div class="help-text">
                    Доступные переменные: <code>{{SITE_NAME}}</code>, <code>{{SITE_URL}}</code>, <code>{{YEAR}}</code>, <code>{{LOGO_URL}}</code>
                </div>
            </div>
            
            <div class="settings-section">
                <h3>🔻 Подвал письма</h3>
                <label>
                    <input type="checkbox" name="use_footer" <?= $templateSettings['use_footer'] ? 'checked' : '' ?>> 
                    Включить подвал
                </label>
                <textarea name="footer" rows="8"><?= htmlspecialchars($templateSettings['footer']) ?></textarea>
                <div class="help-text">
                    Доступные переменные: <code>{{SITE_NAME}}</code>, <code>{{SITE_URL}}</code>, <code>{{YEAR}}</code>, <code>{{UNSUBSCRIBE_LINK}}</code>, <code>{{LOGO_URL}}</code>
                </div>
            </div>
            
            <div class="button-group">
                <button type="submit" class="btn-primary">💾 Сохранить настройки</button>
            </div>
        </form>
        
        <div class="settings-section" style="margin-top: 20px;">
            <h3>🔍 Предпросмотр письма с текущими настройками</h3>
            <button type="button" class="btn-secondary" onclick="previewTemplate()">👁️ Показать предпросмотр</button>
            <div id="preview" style="border: 1px solid #ddd; padding: 20px; margin-top: 15px; display: none; background: white;"></div>
        </div>
    </div>
    
    <script>
    function previewTemplate() {
        const header = document.querySelector('textarea[name="header"]').value;
        const footer = document.querySelector('textarea[name="footer"]').value;
        const useHeader = document.querySelector('input[name="use_header"]').checked;
        const useFooter = document.querySelector('input[name="use_footer"]').checked;
        const siteName = document.querySelector('input[name="site_name_in_email"]').value;
        const logoUrl = "<?= $templateSettings['logo_url'] ?>";
        const logoPosition = document.querySelector('select[name="logo_position"]').value;
        const currentYear = new Date().getFullYear();
        
        let processedHeader = header.replace(/{{SITE_NAME}}/g, siteName);
        processedHeader = processedHeader.replace(/{{YEAR}}/g, currentYear);
        processedHeader = processedHeader.replace(/{{LOGO_URL}}/g, logoUrl);
        
        let processedFooter = footer.replace(/{{SITE_NAME}}/g, siteName);
        processedFooter = processedFooter.replace(/{{YEAR}}/g, currentYear);
        processedFooter = processedFooter.replace(/{{LOGO_URL}}/g, logoUrl);
        processedFooter = processedFooter.replace(/{{UNSUBSCRIBE_LINK}}/g, '#');
        
        let previewHtml = '<div style="border: 2px dashed #ccc; padding: 20px; background: #f5f5f5;">';
        previewHtml += '<div style="max-width: 600px; margin: 0 auto; background: white;">';
        
        if (useHeader) {
            previewHtml += '<div style="background: #f0f0f0; padding: 10px; border-bottom: 1px solid #ddd;">';
            previewHtml += '<strong>📌 ШАПКА:</strong><br>' + processedHeader;
            previewHtml += '</div>';
        }
        
        if (logoUrl && (logoPosition === 'top' || logoPosition === 'both')) {
            previewHtml += '<div style="text-align: center; padding: 20px 0 0;">';
            previewHtml += '<img src="' + logoUrl + '" style="max-width: 200px;" onerror="this.style.display=\'none\'">';
            previewHtml += '</div>';
        }
        
        previewHtml += '<div style="padding: 20px;">';
        previewHtml += '<div style="background: #e8f4f8; padding: 15px; border-left: 3px solid #007bff;">';
        previewHtml += '<strong>📄 ПРИМЕР КОНТЕНТА:</strong><br><br>';
        previewHtml += '<p>Здесь будет содержимое вашей рассылки — новости, статьи, обновления.</p>';
        previewHtml += '<p style="color: #666; font-size: 14px;">Это демонстрация того, как будет выглядеть письмо с текущими настройками шапки и подвала.</p>';
        previewHtml += '</div></div>';
        
        if (logoUrl && (logoPosition === 'bottom' || logoPosition === 'both')) {
            previewHtml += '<div style="text-align: center; padding: 0 0 20px;">';
            previewHtml += '<img src="' + logoUrl + '" style="max-width: 150px;" onerror="this.style.display=\'none\'">';
            previewHtml += '</div>';
        }
        
        if (useFooter) {
            previewHtml += '<div style="background: #f0f0f0; padding: 10px; border-top: 1px solid #ddd;">';
            previewHtml += '<strong>🔻 ПОДВАЛ:</strong><br>' + processedFooter;
            previewHtml += '</div>';
        }
        
        previewHtml += '</div></div>';
        
        const previewDiv = document.getElementById('preview');
        previewDiv.innerHTML = previewHtml;
        previewDiv.style.display = 'block';
    }
    </script>
</body>
</html>