<?php
require_once __DIR__ . '/bootstrap.php';

if (current_user_id()) {
    redirect('dashboard.php');
}
redirect('login.php');
