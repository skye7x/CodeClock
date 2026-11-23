<?php
// on the server: chmod 640.
define('DB_HOST', 'localhost');
define('DB_USER', '');
define('DB_PASS', '');
define('DB_NAME', '');
define('DB_CHARSET', '');
// 'prod' is the safe default. Use 'dev' ONLY on your own computer:

define('APP_ENV', 'prod');

define('LOG_PATH', __DIR__ . '/../logs');

define('SESSION_PATH', '/codeclock/');
// Optional. A GitHub token with NO scopes (fine-grained, public repositories read-only)
define('GITHUB_TOKEN', '');

define('ENCRYPTION_KEY', '');               // reserved for the GitHub token feature
