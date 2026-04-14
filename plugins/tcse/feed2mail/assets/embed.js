// assets/embed.js
(function() {
    const container = document.getElementById('feed2mail-form');
    if (!container) return;

    // Получаем базовый URL сайта и путь к плагину
    const siteUrl = container.getAttribute('data-site-url');
    let pluginPath = container.getAttribute('data-plugin-path');
    
    // Если data-plugin-path не указан, пробуем определить автоматически
    if (!pluginPath) {
        const scriptTag = document.querySelector('script[src*="feed2mail/assets/embed.js"]');
        if (scriptTag) {
            const src = scriptTag.getAttribute('src');
            const match = src.match(/(\/plugins\/[^/]+\/feed2mail)/);
            if (match) {
                pluginPath = match[1];
            }
        }
    }
    
    // Fallback путь по умолчанию
    pluginPath = pluginPath || '/plugins/tcse/feed2mail';
    
    // Формируем полный URL для API
    const apiUrl = siteUrl + pluginPath + '/api/subscribe.php';
    
    console.log('Feed2Mail: API URL =', apiUrl); // Для отладки

    container.innerHTML = `
        <form id="feed2mail-subscribe" style="margin: 0;">
            <input type="email" id="feed2mail-email" placeholder="Ваш email" required style="padding: 8px; margin-right: 5px; border: 1px solid #ccc; border-radius: 4px;">
            <button type="submit" style="padding: 8px 15px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">Подписаться</button>
            <div id="feed2mail-message" style="margin-top: 10px; font-size: 14px;"></div>
        </form>
    `;

    document.getElementById('feed2mail-subscribe').addEventListener('submit', async (e) => {
        e.preventDefault();
        const emailInput = document.getElementById('feed2mail-email');
        const email = emailInput.value;
        const messageDiv = document.getElementById('feed2mail-message');
        
        // Блокируем кнопку на время отправки
        const submitBtn = e.target.querySelector('button');
        const originalText = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.textContent = 'Отправка...';
        messageDiv.innerHTML = '';

        try {
            const response = await fetch(apiUrl, {
                method: 'POST',
                mode: 'cors',  // Явно указываем CORS режим
                headers: { 
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ email })
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const result = await response.json();
            
            if (result.success) {
                messageDiv.style.color = 'green';
                messageDiv.innerHTML = '✅ ' + result.message;
                emailInput.value = '';
            } else {
                messageDiv.style.color = 'red';
                messageDiv.innerHTML = '❌ ' + result.message;
            }
        } catch (error) {
            console.error('Feed2Mail error:', error);
            messageDiv.style.color = 'red';
            messageDiv.innerHTML = '❌ Ошибка соединения. Проверьте консоль браузера.';
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    });
})();