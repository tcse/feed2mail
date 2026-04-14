<?php
// admin/auth.php - защита админки
session_start();

// Загружаем конфиг для получения пароля
$configFile = dirname(__DIR__) . '/data/config.json';
$config = json_decode(file_get_contents($configFile), true);

// Пароль для доступа к админке (по умолчанию 'admin123')
$adminPassword = $config['admin_password'] ?? 'admin123';

// Проверка авторизации
function checkAuth() {
    global $adminPassword;
    
    // Если уже авторизован
    if (isset($_SESSION['feed2mail_admin']) && $_SESSION['feed2mail_admin'] === true) {
        return true;
    }
    
    // Проверка отправленного пароля
    if (isset($_POST['admin_password'])) {
        if ($_POST['admin_password'] === $adminPassword) {
            $_SESSION['feed2mail_admin'] = true;
            return true;
        } else {
            $_SESSION['auth_error'] = 'Неверный пароль!';
        }
    }
    
    return false;
}

// Показываем форму входа
function showLoginForm() {
    $error = $_SESSION['auth_error'] ?? '';
    unset($_SESSION['auth_error']);
    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Авторизация — Feed2Mail</title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }
            .login-container {
                max-width: 400px;
                width: 100%;
            }
            .login-card {
                background: white;
                border-radius: 16px;
                padding: 40px;
                box-shadow: 0 20px 40px rgba(0,0,0,0.1);
                text-align: center;
            }
            .login-icon {
                font-size: 48px;
                margin-bottom: 20px;
            }
            h1 {
                color: #333;
                font-size: 24px;
                margin-bottom: 8px;
            }
            .subtitle {
                color: #666;
                font-size: 14px;
                margin-bottom: 30px;
            }
            input[type="password"] {
                width: 100%;
                padding: 12px 16px;
                font-size: 16px;
                border: 2px solid #e0e0e0;
                border-radius: 8px;
                transition: border-color 0.3s;
                margin-bottom: 20px;
            }
            input[type="password"]:focus {
                outline: none;
                border-color: #667eea;
            }
            button {
                width: 100%;
                padding: 12px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border: none;
                border-radius: 8px;
                font-size: 16px;
                font-weight: 600;
                cursor: pointer;
                transition: transform 0.2s, box-shadow 0.2s;
            }
            button:hover {
                transform: translateY(-2px);
                box-shadow: 0 5px 15px rgba(102,126,234,0.4);
            }
            .error {
                background: #fee;
                color: #c33;
                padding: 12px;
                border-radius: 8px;
                margin-bottom: 20px;
                font-size: 14px;
                border-left: 3px solid #c33;
            }
            .hint {
                margin-top: 20px;
                font-size: 12px;
                color: #999;
            }
            .hint code {
                background: #f5f5f5;
                padding: 2px 6px;
                border-radius: 4px;
                font-family: monospace;
            }
        </style>
    </head>
    <body>
        <div class="login-container">
            <div class="login-card">
                <div class="login-icon">📧</div>
                <h1>Feed2Mail</h1>
                <div class="subtitle">Управление рассылками</div>
                
                <?php if ($error): ?>
                    <div class="error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <input type="password" name="admin_password" placeholder="Введите пароль доступа" autofocus>
                    <button type="submit">Войти в админку</button>
                </form>
                
                <div class="hint">
                    💡 Пароль можно изменить в файле <code>data/config.json</code> (параметр <code>admin_password</code>)
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Выполняем проверку
if (!checkAuth()) {
    showLoginForm();
}
?>