<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    http_response_code(204);
    exit;
}

$type = isset($data['type']) ? (string)$data['type'] : '';
$allowed = ['page_view', 'file_download', 'link_click'];
if (!in_array($type, $allowed, true)) {
    http_response_code(204);
    exit;
}

$payload = isset($data['payload']) && is_array($data['payload']) ? $data['payload'] : [];

$pageUrl   = isset($payload['url']) ? (string)$payload['url'] : '';
$targetUrl = isset($payload['target']) ? (string)$payload['target'] : '';
$linkText  = isset($payload['text']) ? (string)$payload['text'] : '';

$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
$ip = $_SERVER['REMOTE_ADDR'] ?? '';

$pdo = admin_db();
$stmt = $pdo->prepare('
    INSERT INTO events (event_type, page_url, target_url, link_text, user_agent, ip, created_at)
    VALUES (:t, :p, :tu, :lt, :ua, :ip, :dt)
');
$stmt->execute([
    ':t'  => $type,
    ':p'  => $pageUrl,
    ':tu' => $targetUrl,
    ':lt' => $linkText,
    ':ua' => $ua,
    ':ip' => $ip,
    ':dt' => date('c'),
]);

// TG уведомления (только для скачиваний и кликов по ссылкам)
$botToken = admin_get_setting('tg_bot_token');
$chatId   = admin_get_setting('tg_chat_id');

if ($botToken && $chatId && $type !== 'page_view') {
    $msg = "Событие: {$type}\n" .
           "Страница: {$pageUrl}\n";

    if ($targetUrl) {
        $msg .= "Цель: {$targetUrl}\n";
    }
    if ($linkText) {
        $msg .= "Текст: {$linkText}\n";
    }
    $msg .= "IP: {$ip}";

    send_tg_message($botToken, $chatId, $msg);
}

http_response_code(204);

function send_tg_message(string $botToken, string $chatId, string $text): void
{
    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";

    $postData = [
        'chat_id' => $chatId,
        'text'    => $text,
    ];

    $opts = [
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query($postData),
            'timeout' => 2,
        ],
    ];

    @file_get_contents($url, false, stream_context_create($opts));
}
