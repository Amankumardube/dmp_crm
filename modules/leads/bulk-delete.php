<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: list.php');
    exit;
}

csrf_verify();
$ids = array_values(array_unique(array_filter(
    array_map('intval', (array)($_POST['lead_ids'] ?? [])),
    static fn (int $id): bool => $id > 0
)));

if (!$ids) {
    $_SESSION['flash_error'] = 'Select at least one lead to delete.';
    header('Location: list.php');
    exit;
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$delete = $pdo->prepare("DELETE FROM leads WHERE id IN ($placeholders)");
$delete->execute($ids);

$deletedCount = $delete->rowCount();
log_activity($pdo, "Deleted {$deletedCount} lead(s) in bulk");
$_SESSION['flash_success'] = $deletedCount . ' lead' . ($deletedCount === 1 ? '' : 's') . ' deleted successfully.';
header('Location: list.php');
exit;
