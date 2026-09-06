<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin']);

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'counselor' LIMIT 1");
$stmt->execute([$id]);
$counselor = $stmt->fetch();
if (!$counselor) {
    http_response_code(404);
    die('Counselor not found.');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = preg_replace('/\D+/', '', $_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $target = max(0, (int)($_POST['monthly_target'] ?? 0));
    $status = ($_POST['status'] ?? '') === 'inactive' ? 'inactive' : 'active';

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid name and email address.';
    } elseif ($password !== '' && strlen($password) < 8) {
        $error = 'The new password must be at least 8 characters.';
    } else {
        $dup = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
        $dup->execute([$email, $id]);
        if ($dup->fetch()) {
            $error = 'A user with this email already exists.';
        } else {
            if ($password !== '') {
                $update = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ?, password = ?, status = ?, monthly_target = ? WHERE id = ? AND role = 'counselor'");
                $update->execute([$name, $email, $phone, password_hash($password, PASSWORD_BCRYPT), $status, $target, $id]);
            } else {
                $update = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ?, status = ?, monthly_target = ? WHERE id = ? AND role = 'counselor'");
                $update->execute([$name, $email, $phone, $status, $target, $id]);
            }
            log_activity($pdo, "Updated counselor: $name");
            $_SESSION['flash_success'] = 'Counselor details updated successfully.';
            header('Location: view.php?id=' . $id);
            exit;
        }
    }
}

$pageTitle = 'Edit Counselor';
require __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4>Edit Counselor</h4>
  <a href="view.php?id=<?= $id ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back to profile</a>
</div>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<div class="card card-stat form-card p-4">
<form method="POST">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label">Full Name *</label><input type="text" name="name" class="form-control" value="<?= e($counselor['name']) ?>" required></div>
    <div class="col-md-6"><label class="form-label">Email *</label><input type="email" name="email" class="form-control" value="<?= e($counselor['email']) ?>" required></div>
    <div class="col-md-6"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control" inputmode="numeric" pattern="[0-9]*" oninput="this.value=this.value.replace(/\D/g,'')" value="<?= e($counselor['phone']) ?>"></div>
    <div class="col-md-6"><label class="form-label">Status</label><select name="status" class="form-select"><option value="active" <?= $counselor['status'] === 'active' ? 'selected' : '' ?>>Active</option><option value="inactive" <?= $counselor['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option></select></div>
    <div class="col-md-6"><label class="form-label">Monthly Target</label><input type="number" name="monthly_target" class="form-control" min="0" value="<?= (int)$counselor['monthly_target'] ?>"></div>
    <div class="col-12"><label class="form-label">New Password <span class="text-muted fw-normal">(leave blank to keep current)</span></label><div class="password-field"><input type="password" id="counselor_new_password" name="password" class="form-control" minlength="8"><button class="password-toggle" type="button" data-password-toggle="counselor_new_password" aria-label="Show password" title="Show password"><i class="bi bi-eye"></i></button></div></div>
  </div>
  <div class="mt-4"><button class="btn btn-primary"><i class="bi bi-check2"></i> Save Changes</button> <a href="view.php?id=<?= $id ?>" class="btn btn-outline-secondary">Cancel</a></div>
</form>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
