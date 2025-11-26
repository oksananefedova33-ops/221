<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function admin_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $pdo = new PDO('sqlite:' . ADMIN_DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $pdo;
}

function admin_get_setting(string $key, ?string $default = null): ?string
{
    $pdo = admin_db();
    $stmt = $pdo->prepare('SELECT value FROM settings WHERE key = :k');
    $stmt->execute([':k' => $key]);
    $value = $stmt->fetchColumn();

    if ($value === false) {
        return $default;
    }

    return (string)$value;
}

function admin_set_setting(string $key, string $value): void
{
    $pdo = admin_db();
    $stmt = $pdo->prepare('INSERT OR REPLACE INTO settings (key, value) VALUES (:k, :v)');
    $stmt->execute([':k' => $key, ':v' => $value]);
}
