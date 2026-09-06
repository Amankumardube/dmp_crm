<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin','manager']);

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = preg_replace('/\D+/', '', $_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $target = (int)($_POST['monthly_target'] ?? 0);

    if ($name === '' || $email === '' || strlen($password) < 8) {
        $error = 'Name, email and a password (min 8 chars) are required.';
    } else {
        $dup = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $dup->execute([$email]);
        if ($dup->fetch()) {
            $error = 'A user with this email already exists.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password, role, monthly_target) VALUES (?,?,?,?,'counselor',?)");
            $stmt->execute([$name, $email, $phone, $hash, $target]);
            log_activity($pdo, "Added counselor: $name");
            $_SESSION['flash_success'] = 'Counselor added successfully.';
            header('Location: list.php');
            exit;
        }
    }
}

$pageTitle = 'Add Counselor';
require __DIR__ . '/../../includes/header.php';
?>
<h4 class="mb-3">Add Counselor</h4>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<div class="card card-stat form-card p-4">
<form method="POST">
  <?= csrf_field() ?>
  <div class="mb-3"><label class="form-label">Full Name *</label><input type="text" name="name" class="form-control" required></div>
  <div class="mb-3"><label class="form-label">Email *</label><input type="email" name="email" class="form-control" required></div>
  <div class="mb-3"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control" inputmode="numeric" pattern="[0-9]*" oninput="this.value=this.value.replace(/\D/g,'')"></div>
    <div class="mb-3"><label class="form-label">Password *</label><div class="password-field"><input type="password" id="counselor_password" name="password" class="form-control" required minlength="8"><button class="password-toggle" type="button" data-password-toggle="counselor_password" aria-label="Show password" title="Show password"><i class="bi bi-eye"></i></button></div></div>
  <div class="mb-3"><label class="form-label">Monthly Target (admissions)</label><input type="number" name="monthly_target" class="form-control" value="0"></div>
  <button class="btn btn-primary">Save</button>
  <a href="list.php" class="btn btn-outline-secondary">Cancel</a>
</form>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
