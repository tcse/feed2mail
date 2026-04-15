<?php
// admin/rss_import.php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config.php';

$generatedHtml = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rss_url'])) {
    $rssUrl = filter_var(trim($_POST['rss_url']), FILTER_SANITIZE_URL);
    $itemLimit = (int)($_POST['item_limit'] ?? 5);
    $showTitle = isset($_POST['show_title']);
    $showDate = isset($_POST['show_date']);
    $showDesc = isset($_POST['show_desc']);
    $showImage = isset($_POST['show_image']);
    $enableDescLimit = isset($_POST['enable_desc_limit']);
    $descLimit = (int)($_POST['desc_limit'] ?? 200);
    $addReadMore = isset($_POST['add_readmore']);
    $centerImages = isset($_POST['center_images']);
    
    if (!filter_var($rssUrl, FILTER_VALIDATE_URL)) {
        $error = "Некорректный URL фида";
    } else {
        $rss = @simplexml_load_file($rssUrl);
        if ($rss === false) {
            $error = "Не удалось загрузить RSS-ленту. Проверьте URL и доступность фида.";
        } else {
            $items = [];
            $count = 0;
            foreach ($rss->channel->item as $item) {
                if ($count >= $itemLimit) break;
                
                $title = (string)$item->title;
                $link = (string)$item->link;
                $date = date('d.m.Y', strtotime((string)$item->pubDate));
                
                // Получаем описание как есть (с HTML-тегами)
                $descriptionRaw = (string)$item->description;
                
                // Пытаемся найти изображение
                $imageUrl = '';
                if ($showImage) {
                    if ($item->enclosure && $item->enclosure['url']) {
                        $imageUrl = (string)$item->enclosure['url'];
                    }
                    elseif ($item->children('media', true)->content && $item->children('media', true)->content['url']) {
                        $imageUrl = (string)$item->children('media', true)->content['url'];
                    }
                    elseif (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $descriptionRaw, $matches)) {
                        $imageUrl = $matches[1];
                    }
                }
                
                $items[] = [
                    'title' => $title,
                    'link' => $link,
                    'date' => $date,
                    'description_raw' => $descriptionRaw,
                    'image' => $imageUrl
                ];
                $count++;
            }
            
            // Собираем HTML письма
            $html = '<!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <style>
                    body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
                    .email-container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 8px; overflow: hidden; }
                    .post-item { margin-bottom: 30px; border-bottom: 1px solid #eee; padding-bottom: 20px; }
                    .post-title { margin: 0 0 10px 0; font-size: 20px; }
                    .post-title a { color: #007bff; text-decoration: none; }
                    .post-meta { color: #999; font-size: 12px; margin-bottom: 10px; }
                    .post-description { line-height: 1.5; margin-bottom: 15px; color: #333; }
                    .post-description a { color: #007bff; text-decoration: none; }
                    .read-more { display: inline-block; background: #007bff; color: white; padding: 8px 15px; text-decoration: none; border-radius: 4px; }
                </style>
            </head>
            <body>
                <div class="email-container">
                    <div style="padding: 20px;">';
            
            foreach ($items as $item) {
                $html .= '<div class="post-item">';
                
                if ($showTitle && $item['title']) {
                    $html .= '<h2 class="post-title"><a href="' . htmlspecialchars($item['link']) . '" style="color: #007bff; text-decoration: none;">' . htmlspecialchars($item['title']) . '</a></h2>';
                }
                
                if ($showDate && $item['date']) {
                    $html .= '<div class="post-meta">📅 ' . htmlspecialchars($item['date']) . '</div>';
                }
                
                if ($showImage && $item['image']) {
                    if ($centerImages) {
                        $html .= '<div style="text-align: center; margin-bottom: 15px;">';
                        $html .= '<img src="' . htmlspecialchars($item['image']) . '" style="max-width: 100%; height: auto; display: inline-block;" alt="' . htmlspecialchars($item['title']) . '">';
                        $html .= '</div>';
                    } else {
                        $html .= '<img src="' . htmlspecialchars($item['image']) . '" style="max-width: 100%; height: auto; margin-bottom: 15px;" alt="' . htmlspecialchars($item['title']) . '">';
                    }
                }
                
                if ($showDesc && $item['description_raw']) {
                    $description = $item['description_raw'];
                    
                    if ($enableDescLimit && $descLimit > 0) {
                        // РЕЖИМ ОБРЕЗКИ: удаляем все теги, заменяем пробелы
                        // Заменяем <br> на пробелы
                        $description = preg_replace('/<br\s*\/?>/i', ' ', $description);
                        // Удаляем все остальные HTML-теги
                        $description = strip_tags($description);
                        // Убираем лишние пробелы
                        $description = preg_replace('/\s+/', ' ', $description);
                        $description = trim($description);
                        // Обрезаем
                        if (mb_strlen($description) > $descLimit) {
                            $description = mb_substr($description, 0, $descLimit) . '…';
                        }
                        $description = nl2br(htmlspecialchars($description));
                    } else {
                        // РЕЖИМ "КАК ЕСТЬ": оставляем HTML как есть
                        // Но экранируем опасные символы, чтобы не сломать письмо
                        // Используем htmlspecialchars с флагами, но сохраняем теги через strip_tags с разрешёнными
                        $allowedTags = '<a><b><i><strong><em><p><br><div><ul><ol><li><h1><h2><h3><h4><h5><h6><span><img><table><tr><td><th>';
                        $description = strip_tags($description, $allowedTags);
                        // Декодируем HTML-сущности обратно (для правильного отображения)
                        $description = html_entity_decode($description, ENT_QUOTES, 'UTF-8');
                    }
                    
                    $html .= '<div class="post-description">' . $description . '</div>';
                }
                
                if ($addReadMore && $item['link']) {
                    $html .= '<p><a href="' . htmlspecialchars($item['link']) . '" class="read-more">Читать далее →</a></p>';
                }
                
                $html .= '</div>';
            }
            
            $html .= '</div></div></body></html>';
            
            $generatedHtml = $html;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>RSS → Email</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .info-text { font-size: 12px; color: #666; margin-top: 5px; }
        label { font-weight: bold; display: block; margin-top: 10px; }
        input, select, textarea { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        button { background: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
        .btn-secondary { background: #6c757d; }
        .btn-primary { background: #007bff; }
        fieldset { margin: 15px 0; padding: 10px; border-radius: 6px; border: 1px solid #ddd; }
        legend { font-weight: bold; padding: 0 10px; }
    </style>
</head>
<body>
<div class="container">
    <h1>📡 Создать рассылку из RSS</h1>
    <p><a href="index.php">← Назад к дашборду</a></p>
    
    <form method="POST">
        <div>
            <label>🔗 URL RSS-фида:</label><br>
            <input type="url" name="rss_url" style="width:100%; padding:8px;" required 
                   placeholder="https://example.com/rss.xml" value="<?= htmlspecialchars($_POST['rss_url'] ?? '') ?>">
        </div>
        <div style="margin-top:10px;">
            <label>📄 Количество записей:</label><br>
            <input type="number" name="item_limit" value="<?= $_POST['item_limit'] ?? 5 ?>" min="1" max="50" style="width:100%; padding:8px;">
        </div>

        <div style="margin-top:10px;">
            <label>📝 Тема письма (для рассылки):</label><br>
            <input type="text" name="subject_override" style="width:100%; padding:8px;" 
                   placeholder="Новости сайта за неделю" 
                   value="📰 Новости сайта <?= date('d.m.Y') ?>">
        </div>
        
        <fieldset>
            <legend>📝 Включать в письмо:</legend>
            <label><input type="checkbox" name="show_title" <?= isset($_POST['show_title']) ? 'checked' : '' ?>> Заголовок (ссылка)</label><br>
            <label><input type="checkbox" name="show_date" <?= isset($_POST['show_date']) ? 'checked' : '' ?>> Дату публикации</label><br>
            <label><input type="checkbox" name="show_desc" <?= isset($_POST['show_desc']) ? 'checked' : '' ?>> Краткое описание</label><br>
            <label><input type="checkbox" name="show_image" <?= isset($_POST['show_image']) ? 'checked' : '' ?>> Изображение (первое из фида)</label><br>
        </fieldset>
        
        <fieldset>
            <legend>⚙️ Дополнительные опции:</legend>
            
            <label style="display: block; margin-bottom: 10px;">
                <input type="checkbox" name="enable_desc_limit" <?= isset($_POST['enable_desc_limit']) ? 'checked' : '' ?>> 
                Обрезать описание до 
                <input type="number" name="desc_limit" value="<?= $_POST['desc_limit'] ?? 200 ?>" style="width:70px; display: inline-block;" <?= !isset($_POST['enable_desc_limit']) ? 'disabled' : '' ?>> 
                символов
                <div class="info-text">При включении: все HTML-теги удаляются, <br> заменяются на пробелы</div>
            </label>
            
            <label><input type="checkbox" name="add_readmore" <?= isset($_POST['add_readmore']) ? 'checked' : '' ?>> Добавить кнопку «Читать далее»</label><br>
            <label><input type="checkbox" name="center_images" <?= isset($_POST['center_images']) ? 'checked' : '' ?>> Центрировать изображения</label>
        </fieldset>

        <div style="margin-top: 15px;">
            <button type="button" class="btn-secondary" onclick="previewEmail()">👁️ Предпросмотр</button>
            <button type="submit" style="margin-left: 10px;">📊 Сгенерировать письмо</button>
        </div>
    </form>
    
    <?php if ($error): ?>
        <div style="color:red; margin-top:20px; padding:10px; background:#fee; border-radius:6px;"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <?php if ($generatedHtml): ?>
        <div style="margin-top:30px;">
            <h3>📧 Предпросмотр письма:</h3>
            <div style="border:1px solid #ccc; padding:15px; background:#f9f9f9; max-height:500px; overflow:auto; border-radius:8px;">
                <?= $generatedHtml ?>
            </div>
            
            <h3>📋 HTML-код для отправки:</h3>
            <textarea rows="15" style="width:100%; font-family:monospace;" id="htmlCode"><?= htmlspecialchars($generatedHtml) ?></textarea>
            
            <div style="margin-top: 10px;">
                <button type="button" class="btn-secondary" onclick="copyToClipboard()">📋 Копировать код</button>
                <button type="button" class="btn-primary" onclick="sendAsCampaign()" style="margin-left: 10px;">✉️ Использовать в рассылке</button>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
// Активация/деактивация поля обрезки при клике на чекбокс
const descLimitCheckbox = document.querySelector('input[name="enable_desc_limit"]');
const descLimitInput = document.querySelector('input[name="desc_limit"]');

if (descLimitCheckbox) {
    descLimitCheckbox.addEventListener('change', function() {
        if (this.checked) {
            descLimitInput.removeAttribute('disabled');
        } else {
            descLimitInput.setAttribute('disabled', 'disabled');
        }
    });
}

function copyToClipboard() {
    var copyText = document.getElementById("htmlCode");
    copyText.select();
    copyText.setSelectionRange(0, 99999);
    document.execCommand("copy");
    alert("HTML-код скопирован в буфер обмена");
}

function sendAsCampaign() {
    const htmlContent = document.getElementById("htmlCode").value;
    const subjectInput = document.querySelector('input[name="subject_override"]');
    const subject = subjectInput ? subjectInput.value : '';
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'campaigns.php';
    form.target = '_blank';
    
    const htmlInput = document.createElement('input');
    htmlInput.type = 'hidden';
    htmlInput.name = 'rss_html';
    htmlInput.value = htmlContent;
    form.appendChild(htmlInput);
    
    if (subject) {
        const subjectField = document.createElement('input');
        subjectField.type = 'hidden';
        subjectField.name = 'rss_subject';
        subjectField.value = subject;
        form.appendChild(subjectField);
    }
    
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

function previewEmail() {
    const htmlContent = document.getElementById("htmlCode").value;
    const previewWindow = window.open();
    previewWindow.document.write(htmlContent);
    previewWindow.document.close();
}
</script>
</body>
</html>
