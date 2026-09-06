<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: list.php'); exit; }
csrf_verify();
$id = (int)($_POST['id'] ?? 0);
$stmt = $pdo->prepare('SELECT name FROM courses WHERE id = ?'); $stmt->execute([$id]); $course = $stmt->fetch();
if (!$course) { $_SESSION['flash_error'] = 'Course not found.'; header('Location: list.php'); exit; }
$check = $pdo->prepare('SELECT COUNT(*) FROM admissions WHERE course_id = ?'); $check->execute([$id]);
if ((int)$check->fetchColumn() > 0) { $_SESSION['flash_error'] = 'This course cannot be deleted because admissions are linked to it. Mark it inactive instead.'; header('Location: list.php'); exit; }
$delete = $pdo->prepare('DELETE FROM courses WHERE id = ?'); $delete->execute([$id]);
log_activity($pdo, "Deleted course: {$course['name']}"); $_SESSION['flash_success'] = 'Course deleted successfully.'; header('Location: list.php'); exit;
