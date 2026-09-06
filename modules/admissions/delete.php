<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: list.php'); exit; }
csrf_verify();
$id = (int)($_POST['id'] ?? 0);
$stmt = $pdo->prepare('SELECT admission_code, student_name FROM admissions WHERE id = ?'); $stmt->execute([$id]); $admission = $stmt->fetch();
if (!$admission) { $_SESSION['flash_error'] = 'Admission not found.'; header('Location: list.php'); exit; }
$check = $pdo->prepare('SELECT COUNT(*) FROM payments WHERE admission_id = ?'); $check->execute([$id]);
if ((int)$check->fetchColumn() > 0) { $_SESSION['flash_error'] = 'This admission cannot be deleted because it has payment history.'; header('Location: view.php?id=' . $id); exit; }
$delete = $pdo->prepare('DELETE FROM admissions WHERE id = ?'); $delete->execute([$id]);
log_activity($pdo, "Deleted admission {$admission['admission_code']} for {$admission['student_name']}"); $_SESSION['flash_success'] = 'Admission deleted successfully.'; header('Location: list.php'); exit;
