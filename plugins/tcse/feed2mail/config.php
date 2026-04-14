<?php
// config.php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('ROOT_PATH', dirname(__FILE__));
define('DATA_PATH', ROOT_PATH . '/data');
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('TEMPLATES_PATH', ROOT_PATH . '/templates');

// === НОВЫЕ ФУНКЦИИ ДЛЯ АВТООПРЕДЕЛЕНИЯ ===

/**
 * Автоматически определяет путь к плагину от корня сайта
 * Пример: /plugins/tcse/feed2mail
 */
function getPluginPath() {
    $scriptPath = $_SERVER['SCRIPT_NAME'];
    // Ищем позицию '/plugins/' в пути
    if (preg_match('#/plugins/[^/]+/feed2mail#', $scriptPath, $matches)) {
        return $matches[0];
    }
    // fallback: определяем относительно документа root
    $docRoot = $_SERVER['DOCUMENT_ROOT'];
    $pluginDir = str_replace($docRoot, '', ROOT_PATH);
    return rtrim($pluginDir, '/');
}

/**
 * Автоматически определяет базовый URL сайта
 */
function getSiteUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    return $protocol . '://' . $host;
}

/**
 * Автоматически определяет email отправителя на основе домена
 */
function getAutoFromEmail() {
    $host = $_SERVER['HTTP_HOST'];
    // Убираем www, поддомены, оставляем основной домен
    $domain = preg_replace('/^www\./', '', $host);
    // Для localhost используем fallback
    if ($domain === 'localhost' || filter_var($domain, FILTER_VALIDATE_IP)) {
        return 'noreply@localhost';
    }
    return 'noreply@' . $domain;
}

$configFile = DATA_PATH . '/config.json';

// Если файла нет — создаём с настройками по умолчанию
if (!file_exists($configFile)) {
    $autoSiteUrl = getSiteUrl();
    $autoFromEmail = getAutoFromEmail();
    $autoPluginPath = getPluginPath();
    
    $defaultConfig = [
        'mail_method' => 'php_mail',
        'smtp' => [
            'host' => 'smtp.masterhost.ru',
            'port' => 465,
            'encryption' => 'ssl',
            'username' => 'forum@dongfeng-aeolus.ru',
            'password' => 'EWqSmC6jrAuPM5dr'
        ],
        'from' => [
            'email' => $autoFromEmail,
            'name' => 'Новостная рассылка',
            'auto_detect' => true  // флаг автоопределения
        ],
        'site_name' => $_SERVER['HTTP_HOST'] ?? 'Мой сайт',
        'site_url' => $autoSiteUrl,
        'plugin_path' => $autoPluginPath,
        'auto_detect_url' => true,   // автоопределение URL сайта
        'auto_detect_path' => true   // автоопределение пути плагина
    ];
    file_put_contents($configFile, json_encode($defaultConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    $config = $defaultConfig;
} else {
    $config = json_decode(file_get_contents($configFile), true);
    if (!is_array($config)) {
        $config = [];
    }
    
    // Автоопределение URL, если включено или поле пустое
    if (($config['auto_detect_url'] ?? true) || empty($config['site_url'])) {
        $config['site_url'] = getSiteUrl();
    }
    
    // Автоопределение пути плагина
    if (($config['auto_detect_path'] ?? true) || empty($config['plugin_path'])) {
        $config['plugin_path'] = getPluginPath();
    }
    
    // Автоопределение email отправителя
    if (($config['from']['auto_detect'] ?? true) || empty($config['from']['email'])) {
        $config['from']['email'] = getAutoFromEmail();
    }
    
    // Добавляем mail_method, если его нет
    if (!isset($config['mail_method'])) {
        $config['mail_method'] = 'smtp';
    }
}

require_once INCLUDES_PATH . '/functions.php';
require_once INCLUDES_PATH . '/mailer.php';