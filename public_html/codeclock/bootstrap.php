<?php
// Every public page includes this file first.
// It is the ONLY place that knows where codeclock-app/ lives.
// Local:   .../CodeClock/public_html/codeclock  -> ../../codeclock-app
// Hostido: /home/user/public_html/codeclock     -> ../../codeclock-app
define('APP_PATH', __DIR__ . '/../../codeclock-app');

require_once APP_PATH . '/lib/db.php';
require_once APP_PATH . '/lib/helpers.php';
require_once APP_PATH . '/lib/auth.php';
