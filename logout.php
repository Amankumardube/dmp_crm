<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
if (!empty($_SESSION['user_id'])) {
    log_activity($pdo, 'Logged out');
}
$_SESSION = [];
session_destroy();
header('Location: ' . BASE_URL . '/login.php');
exit;
