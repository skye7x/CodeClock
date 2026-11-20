<?php
// Small helper functions used on every page.

if (APP_ENV === 'dev') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}
ini_set('log_errors', '1');
ini_set('error_log', LOG_PATH . '/php_errors.log');

// Escape text before printing it into HTML (protects from XSS).
function e($text)
{
    return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
}

function redirect($url)
{
    header('Location: ' . $url);
    exit;
}

function json_response($data, $status = 200)
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function app_log($message)
{
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
