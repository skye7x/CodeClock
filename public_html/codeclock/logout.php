<?php
require_once __DIR__ . '/bootstrap.php';

require_csrf_post();
logout_user();
redirect('login.php');
