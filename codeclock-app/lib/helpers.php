<?php
// Small helper functions used on every page.

// Anything other than the exact string 'dev' is treated as production.
$ccIsDev = defined('APP_ENV') && APP_ENV === 'dev';

error_reporting(E_ALL);
ini_set('display_errors', $ccIsDev ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', LOG_PATH . '/php_errors.log');
header_remove('X-Powered-By');   // PHP's expose_php cannot be changed at runtime

// In production an uncaught exception must never show paths, SQL or stack traces.
if (!$ccIsDev && PHP_SAPI !== 'cli') {
    set_exception_handler(function ($ex) {
        error_log('Uncaught ' . get_class($ex) . ': ' . $ex->getMessage() . ' in ' . $ex->getFile() . ':' . $ex->getLine());
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
        }
        echo 'Internal error';
    });
}

// Never cache pages that can contain account data.
if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('Cache-Control: no-store');
}

// Escape text before printing it into HTML (protects from XSS).
function e($text)
{
    return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
}

// Reads a form/query value as a string. Arrays (?email[]=x) and missing keys become ''.
// Without this, trim($_POST['x']) throws a TypeError when someone sends an array.
function input_str($source, $key)
{
    $value = $source[$key] ?? '';
    return is_string($value) ? $value : '';
}

// GitHub owner / repo names: letters, digits, - _ . only, and not "." or "..".
// \z (not $) so a trailing newline cannot sneak through.
function is_valid_github_name($name)
{
    return is_string($name) && preg_match('/^(?!\.{1,2}\z)[A-Za-z0-9_.-]{1,100}\z/', $name) === 1;
}

function redirect($url)
{
    header('Location: ' . $url);
    exit;
}

function json_response($data, $status = 200)
{
    if (ob_get_length()) {
        ob_clean();
    }

    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function app_log($message)
{
    // Keep one log entry on one line, whatever the message contains.
    $message = str_replace(["\r", "\n"], ' ', (string)$message);
    error_log(date('Y-m-d H:i:s') . ' ' . $message . "\n", 3, LOG_PATH . '/app.log');
}

// 3725 seconds -> "01:02:05"
function format_duration($seconds)
{
    $seconds = max(0, (int)$seconds);
    $h = floor($seconds / 3600);
    $m = floor(($seconds % 3600) / 60);
    $s = $seconds % 60;
    return sprintf('%02d:%02d:%02d', $h, $m, $s);
}

// MySQL UTC datetime -> ISO string the browser understands.
function iso_utc($mysqlDate)
{
    return gmdate('Y-m-d\TH:i:s\Z', strtotime($mysqlDate . ' UTC'));
}
