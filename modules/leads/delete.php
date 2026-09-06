<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: list.php');
    exit;
}

csrf_verify();
$id = (int)($_POST['id'] ?? 0);

$stmt = $pdo->prepare('SELECT name, phone FROM leads WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$lead = $stmt->fetch();

if (!$lead) {
    $_SESSION['flash_error'] = 'Lead not found.';
    header('Location: list.php');
    exit;
}

$delete = $pdo->prepare('DELETE FROM leads WHERE id = ?');
$delete->execute([$id]);

log_activity($pdo, "Deleted lead: {$lead['name']} ({$lead['phone']})");
$_SESSION['flash_success'] = 'Lead deleted successfully.';
header('Location: list.php');
exit;
