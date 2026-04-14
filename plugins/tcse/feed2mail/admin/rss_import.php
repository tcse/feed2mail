<?php
// admin/rss_import.php
require_once __DIR__ . '/auth.php';  // <-- ДОБАВИТЬ ЭТУ СТРОКУ
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
    $descLimit = (int)($_POST['desc_limit'] ?? 0);
    $addReadMore = isset($_POST['add_readmore']);
    
    if (!filter_var($rssUrl, FILTER_VALIDATE_URL)) {
        $error = "Некорректный URL фида";
    } else {
        // Подавляем warnings от simplexml_load_file
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
                // Берём описание, удаляем лишние теги
                $description = trim(strip_tags((string)$item->description));
                
                if ($descLimit > 0 && mb_strlen($description) > $descLimit) {
                    $description = mb_substr($description, 0, $descLimit) . '…';
                }
                
                // Пытаемся найти изображение
                $imageUrl = '';
                if ($showImage) {
                    // Из <enclosure>
                    if ($item->enclosure && $item->enclosure['url']) {
                        $imageUrl = (string)$item->enclosure['url'];
                    }
                    // Из <media:content>
                    elseif ($item->children('media', true)->content && $item->children('media', true)->content['url']) {
                        $imageUrl = (string)$item->children('media', true)->content['url'];
                    }
                    // Ищем <img> в описании (как запасной вариант)
                    elseif (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', (string)$item->description, $matches)) {
                        $imageUrl = $matches[1];
                    }
                }
                
                $items[] = [
                    'title' => $title,
                    'link' => $link,
                    'date' => $date,
                    'description' => $description,
                    'image' => $imageUrl
                ];
                $count++;
            }
            
            // Собираем HTML письма
            $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family: Arial, sans-serif; padding: 20px;">';
            foreach ($items as $item) {
                $html .= '<div style="margin-bottom: 30px; border-bottom: 1px solid #eee; padding-bottom: 20px;">';
                
                if ($showTitle && $item['title']) {
                    $html .= '<h2 style="margin:0 0 10px 0;"><a href="' . htmlspecialchars($item['link']) . '" style="color: #007bff; text-decoration: none;">' . htmlspecialchars($item['title']) . '</a></h2>';
                }
                
                if ($showDate && $item['date']) {
                    $html .= '<div style="color: #999; font-size: 12px; margin-bottom: 10px;">' . htmlspecialchars($item['date']) . '</div>';
                }
                
                if ($showImage && $item['image']) {
                    $html .= '<img src="' . htmlspecialchars($item['image']) . '" style="max-width: 100%; height: auto; margin-bottom: 15px; margin-left: auto !important; margin-right: auto !important;"><br>';
                }
                
                if ($showDesc && $item['description']) {
                    $html .= '<p style="line-height: 1.5; margin-bottom: 15px;">' . nl2br(htmlspecialchars($item['description'])) . '</p>';
                }
                
                if ($addReadMore && $item['link']) {
                    $html .= '<p><a href="' . htmlspecialchars($item['link']) . '" style="display: inline-block; background: #007bff; color: white; padding: 8px 15px; text-decoration: none; border-radius: 4px;">Читать далее →</a></p>';
                }
                
                $html .= '</div>';
            }
            $html .= '</body></html>';
            
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
</head>
<body>
<div class="container">
    <h1>📡 Создать рассылку из RSS</h1>
    <p><a href="index.php">← Назад к дашборду</a></p>
    
    <form method="POST">
        <div>
            <label>URL RSS-фида:</label><br>
            <input type="url" name="rss_url" style="width:100%; padding:8px;" required 
                   placeholder="https://example.com/rss.xml" value="<?= htmlspecialchars($_POST['rss_url'] ?? '') ?>">
        </div>
        <div style="margin-top:10px;">
            <label>Количество записей:</label><br>
            <input type="number" name="item_limit" value="<?= $_POST['item_limit'] ?? 5 ?>" min="1" max="50">
        </div>

        <div style="margin-top:10px;">
            <label>Тема письма (для рассылки):</label><br>
            <input type="text" name="subject_override" style="width:100%; padding:8px;" 
                   placeholder="Новости сайта за неделю" 
                   value="📰 Новости сайта <?= date('d.m.Y') ?>">
        </div>
        
        <fieldset style="margin:15px 0; padding:10px;">
            <legend>Включать в письмо:</legend>
            <label><input type="checkbox" name="show_title" <?= isset($_POST['show_title']) ? 'checked' : '' ?>> Заголовок (ссылка)</label><br>
            <label><input type="checkbox" name="show_date" <?= isset($_POST['show_date']) ? 'checked' : '' ?>> Дату публикации</label><br>
            <label><input type="checkbox" name="show_desc" <?= isset($_POST['show_desc']) ? 'checked' : '' ?>> Краткое описание</label><br>
            <label><input type="checkbox" name="show_image" <?= isset($_POST['show_image']) ? 'checked' : '' ?>> Изображение (первое из фида)</label><br>
        </fieldset>
        
        <fieldset style="margin:15px 0; padding:10px;">
            <legend>Дополнительные опции:</legend>
            <label>Обрезать описание до <input type="number" name="desc_limit" value="<?= $_POST['desc_limit'] ?? 200 ?>" style="width:70px;"> символов</label><br>
            <label><input type="checkbox" name="add_readmore" <?= isset($_POST['add_readmore']) ? 'checked' : '' ?>> Добавить кнопку «Читать далее»</label>
        </fieldset>

        <button type="button" onclick="previewEmail()" style="margin-top:10px;">👁️ Предпросмотр</button>
        
        <button type="submit">Сгенерировать письмо</button>
    </form>
    
    <?php if ($error): ?>
        <div style="color:red; margin-top:20px;"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <?php if ($generatedHtml): ?>
        <div style="margin-top:30px;">
            <h3>Предпросмотр письма:</h3>
            <div style="border:1px solid #ccc; padding:15px; background:#f9f9f9; max-height:400px; overflow:auto;">
                <?= $generatedHtml ?>
            </div>
            
            <h3>HTML-код для отправки:</h3>
            <textarea rows="15" style="width:100%; font-family:monospace;" id="htmlCode"><?= htmlspecialchars($generatedHtml) ?></textarea>
            <button onclick="copyToClipboard()" style="margin-top:10px;">📋 Копировать код</button>
            <button onclick="sendAsCampaign()" style="margin-top:10px;">✉️ Использовать в рассылке</button>
        </div>
    <?php endif; ?>
</div>

<script>
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
    
    // Создаём временную форму
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'campaigns.php';
    form.target = '_blank'; // открываем в новой вкладке
    
    // Добавляем HTML контент
    const htmlInput = document.createElement('input');
    htmlInput.type = 'hidden';
    htmlInput.name = 'rss_html';
    htmlInput.value = htmlContent;
    form.appendChild(htmlInput);
    
    // Добавляем тему, если указана
    if (subject) {
        const subjectInput = document.createElement('input');
        subjectInput.type = 'hidden';
        subjectInput.name = 'rss_subject';
        subjectInput.value = subject;
        form.appendChild(subjectInput);
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