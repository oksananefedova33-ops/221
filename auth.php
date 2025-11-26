<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function admin_is_logged_in(): bool
{
    return !empty($_SESSION['admin_logged_in']);
}

function admin_require_login(): void
{
    if (!admin_is_logged_in()) {
        header('Location: index.php');
        exit;
    }
}

function admin_login(string $login, string $password): bool
{
    $storedLogin = admin_get_setting('admin_login', 'admin');
    $hash        = admin_get_setting('admin_password_hash');

    if ($login !== $storedLogin) {
        return false;
    }

    if ($hash === null) {
        // Если хэш ещё не установлен — устанавливаем при первом логине
        $hash = password_hash($password, PASSWORD_DEFAULT);
        admin_set_setting('admin_password_hash', $hash);
        $_SESSION['admin_logged_in'] = true;
        return true;
    }

    if (!password_verify($password, $hash)) {
        return false;
    }

    $_SESSION['admin_logged_in'] = true;
    return true;
}

function admin_logout(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}
