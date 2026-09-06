<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM courses WHERE id = ?');
$stmt->execute([$id]);
$course = $stmt->fetch();
if (!$course) { http_response_code(404); die('Course not found.'); }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $name = trim($_POST['name'] ?? '');
    $duration = trim($_POST['duration'] ?? '');
    $fee = max(0, (float)($_POST['fee'] ?? 0));
    $description = trim($_POST['description'] ?? '');
    $status = ($_POST['status'] ?? '') === 'inactive' ? 'inactive' : 'active';
    if ($name === '') { $error = 'Course name is required.'; }
    else {
        $update = $pdo->prepare('UPDATE courses SET name = ?, duration = ?, fee = ?, description = ?, status = ? WHERE id = ?');
        $update->execute([$name, $duration, $fee, $description, $status, $id]);
        log_activity($pdo, "Updated course: $name");
        $_SESSION['flash_success'] = 'Course updated successfully.';
        header('Location: list.php'); exit;
    }
}
$pageTitle = 'Edit Course'; require __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3"><h4>Edit Course</h4><a href="list.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back</a></div>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<div class="card card-stat form-card p-4"><form method="POST"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><div class="mb-3"><label class="form-label">Course Name *</label><input name="name" class="form-control" value="<?= e($course['name']) ?>" required></div><div class="row g-3"><div class="col-md-6"><label class="form-label">Duration</label><input name="duration" class="form-control" value="<?= e($course['duration']) ?>"></div><div class="col-md-6"><label class="form-label">Fee</label><input type="number" step="0.01" min="0" name="fee" class="form-control" value="<?= e($course['fee']) ?>" required></div><div class="col-md-6"><label class="form-label">Status</label><select name="status" class="form-select"><option value="active" <?= $course['status'] === 'active' ? 'selected' : '' ?>>Active</option><option value="inactive" <?= $course['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option></select></div></div><div class="mt-3"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="4"><?= e($course['description']) ?></textarea></div><div class="mt-4"><button class="btn btn-primary"><i class="bi bi-check2"></i> Save Changes</button> <a href="list.php" class="btn btn-outline-secondary">Cancel</a></div></form></div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
