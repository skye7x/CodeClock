<?php
// Sessions, CSRF protection and login throttling.

if (session_status() === PHP_SESSION_NONE) {
    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ---------- CSRF ----------
function csrf_token()
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field()
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_valid($sent)
{
    return is_string($sent) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $sent);
}

// Call at the top of any form handler.
function require_csrf_post()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_valid($_POST['csrf'] ?? '')) {
        http_response_code(400);
        exit('Bad request');
    }
}

// ---------- Login state ----------
function current_user_id()
{
    return $_SESSION['user_id'] ?? null;
}

function require_login()
{
    if (!current_user_id()) {
        redirect('login.php');
    }
}

function login_user($userId)
{
    session_regenerate_id(true);   // new session id after login
    $_SESSION['user_id'] = (int)$userId;
}

function logout_user()
{
    $_SESSION = [];
    session_destroy();
}

// ---------- JSON endpoints ----------
// Every API file that changes data starts with: $userId = api_guard();
function api_guard()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(['error' => 'POST only'], 405);
    }
    if (!current_user_id()) {
        json_response(['error' => 'Not logged in'], 401);
    }
    if (!csrf_valid($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        json_response(['error' => 'Bad CSRF token'], 403);
    }
    return current_user_id();
}

// ---------- Login throttling ----------
function client_ip()
{
    return substr($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 0, 45);
}

function too_many_attempts($email, $ip)
{
    $st = db()->prepare(
        'SELECT COUNT(*) FROM login_attempts
         WHERE email = ? AND attempted_at > (UTC_TIMESTAMP() - INTERVAL 15 MINUTE)'
    );
    $st->execute([$email]);
    if ($st->fetchColumn() >= 5) {
        return true;
    }

    $st = db()->prepare(
        'SELECT COUNT(*) FROM login_attempts
         WHERE ip_address = ? AND attempted_at > (UTC_TIMESTAMP() - INTERVAL 15 MINUTE)'
    );
    $st->execute([$ip]);
    return $st->fetchColumn() >= 20;
}

function record_failed_attempt($email, $ip)
{
    $st = db()->prepare('INSERT INTO login_attempts (email, ip_address) VALUES (?, ?)');
    $st->execute([$email, $ip]);
}

function clear_attempts($email)
{
    $st = db()->prepare('DELETE FROM login_attempts WHERE email = ?');
    $st->execute([$email]);
}
