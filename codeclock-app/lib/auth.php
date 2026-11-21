<?php
// Sessions, CSRF protection and login throttling.
require_once __DIR__ . '/ratelimit.php';

// A valid bcrypt hash of a throw-away password. Used when the e-mail is unknown, so the
// login takes the same time whether or not the account exists.
const DUMMY_PASSWORD_HASH = '$2y$10$nahoDpNcb.gNQam48v0oz.Cs3BdxqevT5VV0xMfjgSaZTgTBa2FX2';

if (session_status() === PHP_SESSION_NONE) {
    $isDev   = defined('APP_ENV') && APP_ENV === 'dev';
    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $idle    = defined('SESSION_IDLE_SECONDS') ? (int)SESSION_IDLE_SECONDS : 43200;   // 12 h

    session_name('codeclock_sid');                    // not the shared default PHPSESSID
    ini_set('session.use_strict_mode', '1');          // refuse session ids the server never issued
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.gc_maxlifetime', (string)$idle);

    // Private session folder (not the shared /tmp). Skipped if it does not exist.
    $savePath = defined('SESSION_SAVE_PATH') ? SESSION_SAVE_PATH : (defined('APP_PATH') ? APP_PATH . '/sessions' : '');
    if ($savePath !== '' && is_dir($savePath) && is_writable($savePath)) {
        session_save_path($savePath);
    }

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => defined('SESSION_PATH') ? SESSION_PATH : '/',
        // Production is HTTPS-only. Only 'dev' may use a cookie without the Secure flag.
        'secure'   => $isHttps || !$isDev,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    // Idle timeout: an abandoned session stops working even if the cookie survives.
    if (isset($_SESSION['last_seen']) && time() - (int)$_SESSION['last_seen'] > $idle) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['last_seen'] = time();
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
    unset($_SESSION['csrf']);      // and a new CSRF token
    $_SESSION['user_id'] = (int)$userId;
}

function logout_user()
{
    $_SESSION = [];
    // Also tell the browser to drop the cookie, not only the server-side data.
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $p['path'],
            'domain'   => $p['domain'],
            'secure'   => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => $p['samesite'] ?? 'Lax',
        ]);
    }
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
// Three counters per 15 minutes, all stored as hashes (no e-mail addresses in the table):
//   per IP            20  - stops one machine guessing at many accounts
//   per e-mail + IP    5  - stops one machine guessing at one account
//   per e-mail        50  - last-resort cap against a spread-out (botnet) attack
// The per-e-mail + IP key means a stranger can NOT lock the real owner out from their own IP.
function client_ip()
{
    return substr($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 0, 45);
}

function login_keys($email, $ip)
{
    $emailKey = substr(hash('sha256', $email), 0, 32);
    return [$emailKey, substr(hash('sha256', $email . '|' . $ip), 0, 32)];
}

// Records the attempt FIRST, then says if it may go ahead. Call it before checking the password.
function login_attempt_allowed($email, $ip)
{
    list($emailKey, $pairKey) = login_keys($email, $ip);

    return rate_limit_hit('login:ip', $ip, 20, 900)
        && rate_limit_hit('login:email', $emailKey, 50, 900)
        && rate_limit_hit('login:pair', $pairKey, 5, 900);
}

// After a correct password the owner starts again from zero.
function login_attempt_succeeded($email, $ip)
{
    list(, $pairKey) = login_keys($email, $ip);
    rate_limit_clear('login:pair', $pairKey);
}
