<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: list.php'); exit; }
csrf_verify();
$id = (int)($_POST['id'] ?? 0);
$stmt = $pdo->prepare('SELECT p.receipt_no, a.student_name FROM payments p JOIN admissions a ON a.id = p.admission_id WHERE p.id = ?');
$stmt->execute([$id]);
$payment = $stmt->fetch();
if (!$payment) { $_SESSION['flash_error'] = 'Payment not found.'; header('Location: list.php'); exit; }
$delete = $pdo->prepare('DELETE FROM payments WHERE id = ?');
$delete->execute([$id]);
log_activity($pdo, "Deleted receipt {$payment['receipt_no']} for {$payment['student_name']}");
$_SESSION['flash_success'] = 'Receipt deleted successfully.';
header('Location: list.php');
exit;
