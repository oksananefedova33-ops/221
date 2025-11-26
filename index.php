<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

$error = '';

if (isset($_POST['action']) && $_POST['action'] === 'login') {
    $login    = trim((string)($_POST['login'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if (!admin_login($login, $password)) {
        $error = 'Неверный логин или пароль';
    } else {
        header('Location: index.php');
        exit;
    }
}

if (!admin_is_logged_in()) {
    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <title>Вход в админ‑панель</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="assets/admin.css">
    </head>
    <body class="admin-login-body">
    <div class="admin-login-box">
        <h1>Админ‑панель</h1>
        <?php if ($error): ?>
            <div class="admin-alert admin-alert_error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <form method="post">
            <input type="hidden" name="action" value="login">
            <div class="admin-field">
                <label>Логин</label>
                <input type="text" name="login" required>
            </div>
            <div class="admin-field">
                <label>Пароль</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit" class="admin-btn">Войти</button>
        </form>
    </div>
    </body>
    </html>
    <?php
    exit;
}

$page = $_GET['page'] ?? 'dashboard';
$page = is_string($page) ? $page : 'dashboard';

switch ($page) {
    case 'settings':
        handle_settings_page();
        break;
    case 'replace_files':
        handle_replace_files_page();
        break;
    case 'replace_links':
        handle_replace_links_page();
        break;
    case 'stats':
        handle_stats_page();
        break;
    default:
        handle_dashboard_page();
        break;
}

function handle_dashboard_page(): void
{
    $page = 'dashboard';
    $pdo  = admin_db();

    $total     = (int)$pdo->query("SELECT COUNT(*) FROM events")->fetchColumn();
    $downloads = (int)$pdo->query("SELECT COUNT(*) FROM events WHERE event_type = 'file_download'")->fetchColumn();
    $clicks    = (int)$pdo->query("SELECT COUNT(*) FROM events WHERE event_type = 'link_click'")->fetchColumn();
    $views     = (int)$pdo->query("SELECT COUNT(*) FROM events WHERE event_type = 'page_view'")->fetchColumn();

    include __DIR__ . '/templates/header.php';
    ?>
    <h1>Главная</h1>
    <p>Краткая статистика по сайту:</p>
    <div class="admin-stats-grid">
        <div class="admin-stat">
            <div class="admin-stat__label">Всего событий</div>
            <div class="admin-stat__value"><?= $total ?></div>
        </div>
        <div class="admin-stat">
            <div class="admin-stat__label">Просмотры страниц</div>
            <div class="admin-stat__value"><?= $views ?></div>
        </div>
        <div class="admin-stat">
            <div class="admin-stat__label">Скачивания файлов</div>
            <div class="admin-stat__value"><?= $downloads ?></div>
        </div>
        <div class="admin-stat">
            <div class="admin-stat__label">Переходы по ссылкам</div>
            <div class="admin-stat__value"><?= $clicks ?></div>
        </div>
    </div>
    <p>Детальная статистика — во вкладке «Статистика».</p>
    <?php
    include __DIR__ . '/templates/footer.php';
}

function handle_settings_page(): void
{
    $page    = 'settings';
    $message = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $tgToken = trim((string)($_POST['tg_bot_token'] ?? ''));
        $tgChat  = trim((string)($_POST['tg_chat_id'] ?? ''));

        admin_set_setting('tg_bot_token', $tgToken);
        admin_set_setting('tg_chat_id', $tgChat);

        $newLogin    = trim((string)($_POST['admin_login'] ?? ''));
        $newPassword = (string)($_POST['admin_password'] ?? '');

        if ($newLogin !== '') {
            admin_set_setting('admin_login', $newLogin);
        }
        if ($newPassword !== '') {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            admin_set_setting('admin_password_hash', $hash);
        }

        $message = 'Настройки сохранены';
    }

    $currentLogin = admin_get_setting('admin_login', 'admin');
    $tgToken      = admin_get_setting('tg_bot_token', '');
    $tgChat       = admin_get_setting('tg_chat_id', '');

    include __DIR__ . '/templates/header.php';
    ?>
    <h1>Настройки</h1>
    <?php if ($message): ?>
        <div class="admin-alert admin-alert_success"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <form method="post">
        <div class="admin-field">
            <label>Логин администратора</label>
            <input type="text" name="admin_login" value="<?= htmlspecialchars($currentLogin, ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="admin-field">
            <label>Новый пароль (если нужно сменить)</label>
            <input type="password" name="admin_password">
        </div>

        <hr>

        <div class="admin-field">
            <label>Telegram Bot Token</label>
            <input type="text" name="tg_bot_token" value="<?= htmlspecialchars($tgToken, ENT_QUOTES, 'UTF-8') ?>">
            <small>Токен бота, вида <code>123456:ABC-DEF...</code></small>
        </div>
        <div class="admin-field">
            <label>Telegram Chat ID</label>
            <input type="text" name="tg_chat_id" value="<?= htmlspecialchars($tgChat, ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <button type="submit" class="admin-btn">Сохранить</button>
    </form>
    <?php
    include __DIR__ . '/templates/footer.php';
}

function handle_replace_files_page(): void
{
    $page    = 'replace_files';
    $message = '';
    $error   = '';

    // Получаем список всех файлов на сайте
    $siteFiles = scan_site_files();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $search = trim((string)($_POST['search'] ?? ''));

        if ($search === '') {
            $error = 'Нужно указать, что заменять';
        } elseif (!isset($_FILES['new_file']) || $_FILES['new_file']['error'] !== UPLOAD_ERR_OK) {
            $error = 'Ошибка загрузки файла';
        } else {
            $fileName  = basename($_FILES['new_file']['name']);
            $targetDir = SITE_ROOT . '/files';

            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0775, true);
            }

            $targetPath = $targetDir . '/' . $fileName;
            if (!move_uploaded_file($_FILES['new_file']['tmp_name'], $targetPath)) {
                $error = 'Не удалось сохранить файл';
            } else {
                $relativePath = '/files/' . $fileName;
            $count = admin_search_and_replace_in_site($search, $relativePath, $fileName);
            $message = "Файл сохранён как {$relativePath}. Заменено вхождений: {$count}.";
                $siteFiles = scan_site_files(); // Обновляем список
            }
        }
    }

    include __DIR__ . '/templates/header.php';
    ?>
    <h1>Замена файлов в кнопке «файл»</h1>
    <p>Массово заменяет путь к файлу в HTML/PHP/JS/CSS. Укажите строку/путь, который нужно заменить, и загрузите новый файл.</p>

    <?php if ($error): ?>
        <div class="admin-alert admin-alert_error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($message): ?>
        <div class="admin-alert admin-alert_success"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <!-- Список всех файлов на сайте -->
    <div style="background:#fff; border:1px solid #e5e7eb; border-radius:6px; padding:12px; margin-bottom:16px;">
        <div style="font-weight:600; margin-bottom:8px;">Все файлы на сайте:</div>
        <?php if (empty($siteFiles)): ?>
            <div style="color:#6b7280;">Нет файлов на сайте</div>
        <?php else: ?>
            <div style="max-height:150px; overflow-y:auto;">
            <?php foreach ($siteFiles as $file): ?>
                <div style="padding:4px 0; border-bottom:1px solid #f3f4f6; cursor:pointer;" 
                     onclick="document.querySelector('input[name=search]').value='<?= htmlspecialchars($file['url'], ENT_QUOTES) ?>'">
                    <div style="font-size:13px;">📄 <?= htmlspecialchars($file['name'], ENT_QUOTES, 'UTF-8') ?></div>
                    <div style="font-size:11px; color:#6b7280;">
                        <?= htmlspecialchars($file['url'], ENT_QUOTES, 'UTF-8') ?>
                        — на: <?= htmlspecialchars(implode(', ', $file['pages']), ENT_QUOTES, 'UTF-8') ?>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <form method="post" enctype="multipart/form-data">
        <div class="admin-field">
            <label>Что заменить (часть href или полный путь)</label>
            <input type="text" name="search" required placeholder="/old/file.pdf">
        </div>
        <div class="admin-field">
            <label>Новый файл</label>
            <input type="file" name="new_file" required>
        </div>
        <button type="submit" class="admin-btn">Заменить</button>
    </form>
    <?php
    include __DIR__ . '/templates/footer.php';
}

function handle_replace_links_page(): void
{
    $page    = 'replace_links';
    $message = '';
    $error   = '';

    // Получаем список всех ссылок на сайте
    $siteLinks = scan_site_links();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $search  = trim((string)($_POST['search'] ?? ''));
        $replace = trim((string)($_POST['replace'] ?? ''));

        if ($search === '' || $replace === '') {
            $error = 'Нужно указать обе строки';
        } else {
            $count   = admin_search_and_replace_in_site($search, $replace);
            $message = "Заменено вхождений: {$count}.";
            $siteLinks = scan_site_links(); // Обновляем список
        }
    }

    include __DIR__ . '/templates/header.php';
    ?>
    <h1>Замена ссылок в кнопке «ссылка»</h1>
    <p>Массовая замена любых ссылок/фрагментов текста в файлах сайта (HTML, PHP, JS и т.д.).</p>

    <?php if ($error): ?>
        <div class="admin-alert admin-alert_error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($message): ?>
        <div class="admin-alert admin-alert_success"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <!-- Список всех ссылок на сайте -->
    <div style="background:#fff; border:1px solid #e5e7eb; border-radius:6px; padding:12px; margin-bottom:16px;">
        <div style="font-weight:600; margin-bottom:8px;">Все ссылки на сайте:</div>
        <?php if (empty($siteLinks)): ?>
            <div style="color:#6b7280;">Нет внешних ссылок на сайте</div>
        <?php else: ?>
            <div style="max-height:150px; overflow-y:auto;">
            <?php foreach ($siteLinks as $link): ?>
                <div style="padding:4px 0; border-bottom:1px solid #f3f4f6; cursor:pointer;" 
                     onclick="document.querySelector('input[name=search]').value='<?= htmlspecialchars($link['url'], ENT_QUOTES) ?>'">
                    <div style="font-size:13px; word-break:break-all;">🔗 <?= htmlspecialchars($link['url'], ENT_QUOTES, 'UTF-8') ?></div>
                    <div style="font-size:11px; color:#6b7280;">
                        на: <?= htmlspecialchars(implode(', ', $link['pages']), ENT_QUOTES, 'UTF-8') ?>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <form method="post">
        <div class="admin-field">
            <label>Что заменить (старый URL или его часть)</label>
            <input type="text" name="search" required placeholder="https://old-site.ru">
        </div>
        <div class="admin-field">
            <label>На что заменить (новый URL)</label>
            <input type="text" name="replace" required placeholder="https://new-site.ru">
        </div>
        <button type="submit" class="admin-btn">Заменить</button>
    </form>
    <?php
    include __DIR__ . '/templates/footer.php';
}

function handle_stats_page(): void
{
    $page = 'stats';
    $pdo  = admin_db();

    $stmt = $pdo->query("SELECT event_type, COUNT(*) AS cnt FROM events GROUP BY event_type");
    $counters = [
        'page_view'    => 0,
        'file_download'=> 0,
        'link_click'   => 0,
    ];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $type          = (string)$row['event_type'];
        $cnt           = (int)$row['cnt'];
        $counters[$type] = $cnt;
    }

    $stmt   = $pdo->query("SELECT * FROM events ORDER BY id DESC LIMIT 100");
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    include __DIR__ . '/templates/header.php';
    ?>
    <h1>Статистика</h1>

    <div class="admin-stats-grid">
        <div class="admin-stat">
            <div class="admin-stat__label">Просмотры страниц</div>
            <div class="admin-stat__value"><?= (int)$counters['page_view'] ?></div>
        </div>
        <div class="admin-stat">
            <div class="admin-stat__label">Скачивания файлов</div>
            <div class="admin-stat__value"><?= (int)$counters['file_download'] ?></div>
        </div>
        <div class="admin-stat">
            <div class="admin-stat__label">Переходы по ссылкам</div>
            <div class="admin-stat__value"><?= (int)$counters['link_click'] ?></div>
        </div>
    </div>

    <h2>Последние события</h2>
    <table class="admin-table">
        <thead>
        <tr>
            <th>ID</th>
            <th>Тип</th>
            <th>Страница</th>
            <th>Цель</th>
            <th>Текст</th>
            <th>IP</th>
            <th>Дата</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($events as $event): ?>
            <tr>
                <td><?= (int)$event['id'] ?></td>
                <td><?= htmlspecialchars((string)$event['event_type'], ENT_QUOTES, 'UTF-8') ?></td>
                <td>
                    <?php if (!empty($event['page_url'])): ?>
                        <a href="<?= htmlspecialchars((string)$event['page_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank">страница</a>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (!empty($event['target_url'])): ?>
                        <a href="<?= htmlspecialchars((string)$event['target_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank">ссылка</a>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars((string)$event['link_text'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string)$event['ip'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string)$event['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php
    include __DIR__ . '/templates/footer.php';
}

/**
 * Сканирует HTML файлы и находит все кнопки-файлы (download)
 */
function scan_site_files(): array
{
    $files = [];
    $htmlFiles = glob(SITE_ROOT . '/*.html');
    
    foreach ($htmlFiles as $htmlFile) {
        $content = file_get_contents($htmlFile);
        $pageName = basename($htmlFile);
        
        $patterns = [
            '/<a[^>]+href=["\']([^"\']+)["\'][^>]*download=["\']([^"\']*)["\'][^>]*>/i',
            '/<a[^>]+download=["\']([^"\']*)["\'][^>]*href=["\']([^"\']+)["\'][^>]*>/i'
        ];
        
        foreach ($patterns as $idx => $pattern) {
            if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $url = $idx === 0 ? $match[1] : $match[2];
                    $fileName = $idx === 0 ? $match[2] : $match[1];
                    if (!$fileName) $fileName = basename($url);
                    
                    if (!isset($files[$url])) {
                        $files[$url] = ['url' => $url, 'name' => $fileName, 'pages' => []];
                    }
                    if (!in_array($pageName, $files[$url]['pages'])) {
                        $files[$url]['pages'][] = $pageName;
                    }
                }
            }
        }
    }
    return array_values($files);
}

/**
 * Сканирует HTML файлы и находит все внешние ссылки
 */
function scan_site_links(): array
{
    $links = [];
    $htmlFiles = glob(SITE_ROOT . '/*.html');
    $excludeDomains = ['fonts.googleapis.com', 'fonts.gstatic.com', 'cdnjs.cloudflare.com'];
    
    foreach ($htmlFiles as $htmlFile) {
        $content = file_get_contents($htmlFile);
        $pageName = basename($htmlFile);
        
        if (preg_match_all('/<a[^>]+href=["\'](https?:\/\/[^"\']+)["\'][^>]*>/i', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $url = $match[1];
                
                $skip = false;
                foreach ($excludeDomains as $domain) {
                    if (stripos($url, $domain) !== false) { $skip = true; break; }
                }
                if ($skip) continue;
                if (preg_match('/<a[^>]+href=["\']' . preg_quote($url, '/') . '["\'][^>]*download/i', $content)) continue;
                
                if (!isset($links[$url])) {
                    $links[$url] = ['url' => $url, 'pages' => []];
                }
                if (!in_array($pageName, $links[$url]['pages'])) {
                    $links[$url]['pages'][] = $pageName;
                }
            }
        }
    }
    return array_values($links);
}

/**
 * Массовый поиск/замена по файлам сайта.
 * Возвращает количество заменённых вхождений.
 */
function admin_search_and_replace_in_site(string $search, string $replace, string $newFileName = ''): int
{
    $countTotal = 0;

    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(SITE_ROOT, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($it as $file) {
        /** @var SplFileInfo $file */
        if ($file->isDir()) {
            continue;
        }

        $path = $file->getPathname();

        // Не лезем в админку
        if (strpos($path, DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR) !== false) {
            continue;
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($ext, ['html', 'htm', 'php', 'txt', 'js', 'css'], true)) {
            continue;
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            continue;
        }

        $newContents = str_replace($search, $replace, $contents, $count);
        
        // Если это замена файла и указано новое имя — обновляем атрибуты download и data-file-name
        if ($count > 0 && $newFileName !== '') {
            // Ищем теги <a> где href совпадает с новым путём и обновляем download
            $newContents = preg_replace_callback(
                '/<a([^>]*href=["\']' . preg_quote($replace, '/') . '["\'])([^>]*)>/i',
                function($m) use ($newFileName) {
                    $before = $m[1];
                    $after = $m[2];
                    
                    // Заменяем download="старое"
                    $after = preg_replace('/download=["\'][^"\']*["\']/', 'download="' . $newFileName . '"', $after);
                    
                    // Заменяем data-file-name="старое"
                    $after = preg_replace('/data-file-name=["\'][^"\']*["\']/', 'data-file-name="' . $newFileName . '"', $after);
                    
                    return '<a' . $before . $after . '>';
                },
                $newContents
            );
        }
        
        if ($contents !== $newContents) {
            file_put_contents($path, $newContents);
            $countTotal += $count;
        }
    }

    return $countTotal;
}
