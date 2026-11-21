<?php
// Copy this file to config.php and put your real values in it.
// config.php is in .gitignore - never commit it. On the server: chmod 640.
define('DB_HOST', 'localhost');
define('DB_USER', 'your_db_user');          // give it only SELECT, INSERT, UPDATE, DELETE
define('DB_PASS', 'your_db_password');
define('DB_NAME', 'your_db_name');
define('DB_CHARSET', 'utf8mb4');

// 'prod' is the safe default. Use 'dev' ONLY on your own computer:
// errors are shown on screen and the session cookie may be sent over plain http.
// Any value other than exactly 'dev' is treated as production.
define('APP_ENV', 'prod');

define('LOG_PATH', __DIR__ . '/../logs');

// URL path the app is served from. The session cookie is limited to it, so other PHP
// apps on the same domain cannot see or overwrite it. Local `php -S` serving
// public_html/codeclock as the site root: use '/'.
define('SESSION_PATH', '/codeclock/');

// Optional. Where session files are stored. Default: codeclock-app/sessions/ (if it exists).
// define('SESSION_SAVE_PATH', '/home/USER/codeclock-app/sessions');

// Optional. Seconds of inactivity before a login expires (default 43200 = 12 hours).
// define('SESSION_IDLE_SECONDS', 43200);

// Optional. A GitHub token with NO scopes (fine-grained, public repositories read-only)
// raises the API limit from 60 to 5000 requests/hour. Keep it out of git like the rest of this file.
define('GITHUB_TOKEN', '');

define('ENCRYPTION_KEY', '');               // reserved for the GitHub token feature
