<?php
require_once __DIR__ . '/../config/config.php';

// Redirect to login if not authenticated
function require_login() {
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

// Restrict a page to specific roles, e.g. require_role(['admin','manager'])
function require_role(array $roles) {
    require_login();
    if (!in_array(current_user_role(), $roles, true)) {
        http_response_code(403);
        die('<h2>403 - You do not have permission to view this page.</h2>');
    }
}

function current_user_id() {
    return $_SESSION['user_id'] ?? null;
}

function current_user_role() {
    if (isset($_SESSION['user_role']) && $_SESSION['user_role'] !== '') {
        return $_SESSION['user_role'];
    }

    $userId = current_user_id();
    if (!$userId) {
        return null;
    }

    global $pdo;
    $stmt = $pdo->prepare('SELECT role FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $role = $stmt->fetchColumn();
    if ($role === false) {
        return null;
    }

    $_SESSION['user_role'] = $role;
    return $role;
}

function is_admin() {
    return current_user_role() === 'admin';
}

function is_manager() {
    return current_user_role() === 'manager';
}

function is_counselor() {
    return current_user_role() === 'counselor';
}

// Log an activity to activity_logs table
function log_activity($pdo, $action) {
    $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
    $stmt->execute([current_user_id(), $action, $_SERVER['REMOTE_ADDR'] ?? null]);
}

// Simple helper to safely escape output
function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function money($amount) {
    return number_format((float)$amount, 0);
}
