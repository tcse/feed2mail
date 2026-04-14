<?php
// includes/functions.php

function loadData($filename) {
    $file = DATA_PATH . '/' . $filename . '.json';
    if (!file_exists($file)) {
        return [];
    }
    $content = file_get_contents($file);
    return json_decode($content, true) ?: [];
}

function saveData($filename, $data) {
    $file = DATA_PATH . '/' . $filename . '.json';
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

function sendSubscriptionConfirmation($email, $token) {
    global $config;
    $subject = 'Подтверждение подписки';
    
    // ИСПРАВЛЕНО: правильный путь к API с учётом плагина
    $pluginPath = $config['plugin_path'] ?? '/plugins/tcse/feed2mail';
    $confirmUrl = $config['site_url'] . $pluginPath . '/api/subscribe.php?token=' . $token . '&email=' . urlencode($email);
    
    // ИСПРАВЛЕНО: название сайта из настроек шаблона
    $templateSettingsFile = DATA_PATH . '/template_settings.json';
    $siteNameForEmail = $config['site_name'] ?? 'Новостная рассылка';
    
    if (file_exists($templateSettingsFile)) {
        $settings = json_decode(file_get_contents($templateSettingsFile), true);
        if (!empty($settings['site_name_in_email'])) {
            $siteNameForEmail = $settings['site_name_in_email'];
        }
    }
    
    $body = "
        <h2>Подтверждение подписки</h2>
        <p>Вы получили это письмо, потому что кто-то (надеемся, что вы) подписался на рассылку сайта <strong>{$siteNameForEmail}</strong>.</p>
        <p>Чтобы подтвердить подписку, перейдите по ссылке:</p>
        <p><a href=\"{$confirmUrl}\">{$confirmUrl}</a></p>
        <p>Если вы не подписывались — просто проигнорируйте это письмо.</p>
        <hr>
        <p style=\"font-size: 12px; color: #666;\">Это автоматическое письмо, пожалуйста, не отвечайте на него.</p>
    ";
    return sendMail($email, $subject, $body);
}

function sendUnsubscribeConfirm($email, $token) {
    global $config;
    $subject = 'Вы отписались от рассылки';
    $body = "
        <h2>Вы отписались от рассылки</h2>
        <p>Вы больше не будете получать письма от {$config['site_name']}.</p>
        <p>Если это была ошибка, вы можете <a href=\"{$config['site_url']}/api/subscribe.php?token={$token}&email={$email}\">подписаться снова</a>.</p>
    ";
    return sendMail($email, $subject, $body);
}

function getSubscriberByToken($token) {
    $subscribers = loadData('subscribers');
    foreach ($subscribers as $sub) {
        if ($sub['token'] === $token) {
            return $sub;
        }
    }
    return null;
}

function updateSubscriberStatus($email, $status) {
    $subscribers = loadData('subscribers');
    foreach ($subscribers as &$sub) {
        if ($sub['email'] === $email) {
            $sub['status'] = $status;
            break;
        }
    }
    saveData('subscribers', $subscribers);
}

/**
 * Оборачивает содержимое письма в шапку и подвал
 */
function wrapEmailTemplate($content, $unsubscribeLink = '') {
    global $config;
    
    $templateSettingsFile = DATA_PATH . '/template_settings.json';
    if (!file_exists($templateSettingsFile)) {
        // Если настроек нет, возвращаем как есть
        return $content;
    }
    
    $settings = json_decode(file_get_contents($templateSettingsFile), true);
    
    $year = date('Y');
    $logoUrl = $settings['logo_url'] ?? '';
    
    // Используем site_name_in_email из настроек, если есть
    $siteName = $settings['site_name_in_email'] ?? ($config['site_name'] ?? 'Новостная рассылка');
    
    // Подготавливаем переменные для замены
    $replacements = [
        '{{SITE_NAME}}' => $siteName,
        '{{SITE_URL}}' => $config['site_url'] ?? '',
        '{{YEAR}}' => $year,
        '{{LOGO_URL}}' => $logoUrl,
        '{{UNSUBSCRIBE_LINK}}' => $unsubscribeLink
    ];
    
    $html = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <style>
            body { font-family: Arial, sans-serif; margin: 0; padding: 0; background: #f5f5f5; }
            .email-container { max-width: 600px; margin: 0 auto; background: #ffffff; }
            .content { padding: 20px; }
            img { max-width: 100%; height: auto; }
        </style>
    </head>
    <body>
        <div class="email-container">';
    
    // Добавляем шапку
    if ($settings['use_header'] ?? true) {
        $header = $settings['header'] ?? '';
        foreach ($replacements as $key => $value) {
            $header = str_replace($key, $value, $header);
        }
        $html .= '<div class="email-header">' . $header . '</div>';
    }
    
    // Добавляем логотип сверху
    $logoPosition = $settings['logo_position'] ?? 'top';
    if ($logoUrl && ($logoPosition === 'top' || $logoPosition === 'both')) {
        $html .= '<div style="text-align: center; padding: 20px 0 0;">';
        $html .= '<img src="' . $logoUrl . '" alt="Logo" style="max-width: 200px;">';
        $html .= '</div>';
    }
    
    // Основной контент
    $html .= '<div class="content">' . $content . '</div>';
    
    // Добавляем логотип снизу
    if ($logoUrl && ($logoPosition === 'bottom' || $logoPosition === 'both')) {
        $html .= '<div style="text-align: center; padding: 0 0 20px;">';
        $html .= '<img src="' . $logoUrl . '" alt="Logo" style="max-width: 150px;">';
        $html .= '</div>';
    }
    
    // Добавляем подвал
    if ($settings['use_footer'] ?? true) {
        $footer = $settings['footer'] ?? '';
        foreach ($replacements as $key => $value) {
            $footer = str_replace($key, $value, $footer);
        }
        $html .= '<div class="email-footer">' . $footer . '</div>';
    }
    
    $html .= '</div></body></html>';
    
    return $html;
}