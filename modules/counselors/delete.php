<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin','manager']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: list.php');
    exit;
}
csrf_verify();
$id = (int)($_POST['id'] ?? 0);

$stmt = $pdo->prepare("SELECT name FROM users WHERE id = ? AND role = 'counselor' LIMIT 1");
$stmt->execute([$id]);
$counselor = $stmt->fetch();
if (!$counselor) {
    $_SESSION['flash_error'] = 'Counselor not found.';
    header('Location: list.php');
    exit;
}

$delete = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'counselor'");
$delete->execute([$id]);
log_activity($pdo, "Deleted counselor: {$counselor['name']}");
$_SESSION['flash_success'] = 'Counselor deleted successfully. Linked leads and admissions are now unassigned.';
header('Location: list.php');
exit;
