<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM admissions WHERE id = ?'); $stmt->execute([$id]); $admission = $stmt->fetch();
if (!$admission) { http_response_code(404); die('Admission not found.'); }
$courses = $pdo->query("SELECT id, name, fee FROM courses ORDER BY name")->fetchAll();
$counselors = $pdo->query("SELECT id, name FROM users WHERE role = 'counselor' ORDER BY name")->fetchAll();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $student = trim($_POST['student_name'] ?? ''); $phone = preg_replace('/\D+/', '', $_POST['phone'] ?? ''); $email = trim($_POST['email'] ?? '');
    $course = (int)($_POST['course_id'] ?? 0); $counselor = (int)($_POST['counselor_id'] ?? 0) ?: null;
    $date = $_POST['admission_date'] ?? ''; $total = max(0, (float)($_POST['total_fee'] ?? 0)); $discount = max(0, (float)($_POST['discount'] ?? 0));
    $status = in_array($_POST['status'] ?? '', ['pending-docs','confirmed','cancelled'], true) ? $_POST['status'] : 'pending-docs';
    if ($student === '' || $phone === '' || !$course || $date === '') { $error = 'Student name, phone, course and admission date are required.'; }
    elseif ($discount > $total) { $error = 'Discount cannot be greater than the total fee.'; }
    else {
        $update = $pdo->prepare('UPDATE admissions SET student_name = ?, phone = ?, email = ?, course_id = ?, counselor_id = ?, admission_date = ?, total_fee = ?, discount = ?, status = ? WHERE id = ?');
        $update->execute([$student, $phone, $email, $course, $counselor, $date, $total, $discount, $status, $id]);
        log_activity($pdo, "Updated admission {$admission['admission_code']} for $student"); $_SESSION['flash_success'] = 'Admission updated successfully.';
        header('Location: view.php?id=' . $id); exit;
    }
}
$pageTitle = 'Edit Admission'; require __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3"><h4>Edit Admission <small class="text-muted"><?= e($admission['admission_code']) ?></small></h4><a href="view.php?id=<?= $id ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back</a></div>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<div class="card card-stat form-card p-4"><form method="POST"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><div class="row g-3"><div class="col-md-6"><label class="form-label">Student Name *</label><input name="student_name" class="form-control" value="<?= e($admission['student_name']) ?>" required></div><div class="col-md-6"><label class="form-label">Phone *</label><input name="phone" class="form-control" inputmode="numeric" pattern="[0-9]*" oninput="this.value=this.value.replace(/\D/g,'')" value="<?= e($admission['phone']) ?>" required></div><div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?= e($admission['email']) ?>"></div><div class="col-md-6"><label class="form-label">Admission Date *</label><input type="date" name="admission_date" class="form-control" value="<?= e($admission['admission_date']) ?>" required></div><div class="col-md-6"><label class="form-label">Course *</label><select name="course_id" class="form-select" required><?php foreach ($courses as $course): ?><option value="<?= (int)$course['id'] ?>" <?= (int)$admission['course_id'] === (int)$course['id'] ? 'selected' : '' ?>><?= e($course['name']) ?></option><?php endforeach; ?></select></div><div class="col-md-6"><label class="form-label">Counselor</label><select name="counselor_id" class="form-select"><option value="">Unassigned</option><?php foreach ($counselors as $counselor): ?><option value="<?= (int)$counselor['id'] ?>" <?= (int)$admission['counselor_id'] === (int)$counselor['id'] ? 'selected' : '' ?>><?= e($counselor['name']) ?></option><?php endforeach; ?></select></div><div class="col-md-4"><label class="form-label">Total Fee</label><input type="number" step="0.01" min="0" name="total_fee" class="form-control" value="<?= e($admission['total_fee']) ?>"></div><div class="col-md-4"><label class="form-label">Discount</label><input type="number" step="0.01" min="0" name="discount" class="form-control" value="<?= e($admission['discount']) ?>"></div><div class="col-md-4"><label class="form-label">Status</label><select name="status" class="form-select"><?php foreach (['pending-docs','confirmed','cancelled'] as $status): ?><option value="<?= $status ?>" <?= $admission['status'] === $status ? 'selected' : '' ?>><?= ucfirst($status) ?></option><?php endforeach; ?></select></div></div><div class="mt-4"><button class="btn btn-primary"><i class="bi bi-check2"></i> Save Changes</button> <a href="view.php?id=<?= $id ?>" class="btn btn-outline-secondary">Cancel</a></div></form></div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
