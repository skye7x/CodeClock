<?php
// Copy this file to config.php and put your real values in it.
// config.php is in .gitignore - never commit it.
define('DB_HOST', 'localhost');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
define('DB_NAME', 'your_db_name');
define('APP_ENV', 'dev');            // 'dev' locally, 'prod' on the server
define('ENCRYPTION_KEY', '');        // reserved for the GitHub token feature
define('LOG_PATH', __DIR__ . '/../logs');
define('DB_CHARSET', 'utf8mb4');
