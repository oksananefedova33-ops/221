<?php
declare(strict_types=1);

/**
 * Копирует админку в экспорт, инициализирует БД и трекер.
 *
 * @param string $exportDir Путь к директории экспорта (корень сайта)
 * @param string $adminDir  Директория админки относительно корня (например, "admin")
 * @param array  $options   ['login' => string, 'password' => string]
 */
function installAdminPanel(string $exportDir, string $adminDir, array $options = []): void
{
    $adminDir = trim($adminDir, "/");
    if ($adminDir === '') {
        $adminDir = 'admin';
    }

    $exportDir = rtrim($exportDir, '/');
    $adminPath = $exportDir . '/' . $adminDir;
    $source    = __DIR__ . '/admin-skel';

    if (!is_dir($source)) {
        throw new RuntimeException('Admin skeleton not found: ' . $source);
    }

    // 1) Копируем скелет админки
    copyDirectoryRecursive($source, $adminPath);

    // 2) Создаём data/ и SQLite БД
    $dataDir = $adminPath . '/data';
    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0775, true);
    }

    $dbFile = $dataDir . '/admin.sqlite';
    initAdminDatabase($dbFile, $options);

    // 3) Генерируем config.local.php с уникальным именем сессии
    $configLocal = "<?php\n"
        . "declare(strict_types=1);\n"
        . "define('ADMIN_SESSION_NAME', 'site_admin_" . md5($adminDir) . "');\n";

    file_put_contents($adminPath . '/config.local.php', $configLocal);

    // 4) Внедряем трекер статистики в HTML файлы (если не отключили)
    $statsEnabled = isset($options['stats']) ? (bool)$options['stats'] : true;
    if ($statsEnabled) {
        injectAdminTracker($exportDir, $adminDir);
    }
}


/**
 * Рекурсивное копирование директории.
 */
function copyDirectoryRecursive(string $src, string $dst): void
{
    $dir = opendir($src);
    @mkdir($dst, 0775, true);

    while (false !== ($file = readdir($dir))) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        $srcPath = $src . '/' . $file;
        $dstPath = $dst . '/' . $file;

        if (is_dir($srcPath)) {
            copyDirectoryRecursive($srcPath, $dstPath);
        } else {
            copy($srcPath, $dstPath);
        }
    }

    closedir($dir);
}

/**
 * Инициализация SQLite БД админки.
 */
function initAdminDatabase(string $dbFile, array $options): void
{
    $pdo = new PDO('sqlite:' . $dbFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS settings (
            key   TEXT PRIMARY KEY,
            value TEXT
        );
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS events (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            event_type TEXT NOT NULL,
            page_url   TEXT,
            target_url TEXT,
            link_text  TEXT,
            user_agent TEXT,
            ip         TEXT,
            created_at TEXT NOT NULL
        );
    ");

    $login    = isset($options['login']) && $options['login'] !== '' ? (string)$options['login'] : 'admin';
    $password = isset($options['password']) && $options['password'] !== '' ? (string)$options['password'] : bin2hex(random_bytes(4));
    $hash     = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare('INSERT OR REPLACE INTO settings (key, value) VALUES (:k, :v)');

    $stmt->execute([':k' => 'admin_login',         ':v' => $login]);
    $stmt->execute([':k' => 'admin_password_hash', ':v' => $hash]);
    $stmt->execute([':k' => 'tg_bot_token',        ':v' => '']);
    $stmt->execute([':k' => 'tg_chat_id',          ':v' => '']);
    $stmt->execute([':k' => 'created_at',          ':v' => date('c')]);
}

/**
 * Внедрение <script src="/admin/assets/tracker.js"> в HTML.
 */
function injectAdminTracker(string $exportDir, string $adminDir): void
{
    $adminDir = trim($adminDir, '/');

    $snippet = "\n<!-- admin stats tracker -->\n"
        . '<script src="/' . htmlspecialchars($adminDir, ENT_QUOTES) . '/assets/tracker.js"></script>' . "\n";

    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($exportDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($it as $fileInfo) {
        /** @var SplFileInfo $fileInfo */
        if ($fileInfo->isDir()) {
            continue;
        }

        $path = $fileInfo->getPathname();

        // Не трогаем саму админку
        if (strpos($path, DIRECTORY_SEPARATOR . $adminDir . DIRECTORY_SEPARATOR) !== false) {
            continue;
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($ext, ['html', 'htm'], true)) {
            continue;
        }

        $html = file_get_contents($path);
        if ($html === false) {
            continue;
        }

        // Если уже внедряли — пропускаем
        if (strpos($html, 'admin stats tracker') !== false || strpos($html, '/' . $adminDir . '/assets/tracker.js') !== false) {
            continue;
        }

        if (stripos($html, '</body>') !== false) {
            $html = preg_replace('~</body>~i', $snippet . '</body>', $html, 1);
        } else {
            $html .= $snippet;
        }

        file_put_contents($path, $html);
    }
}
