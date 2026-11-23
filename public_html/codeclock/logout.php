<?php
require_once __DIR__ . '/bootstrap.php';

// Session already expired (idle timeout): nothing to log out, and the old CSRF token is gone.
if (!current_user_id()) {
    redirect('login.php');
}
require_csrf_post();
logout_user();
redirect('login.php');
