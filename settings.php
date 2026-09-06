<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

$userId = current_user_id();
$error = '';
$success = '';
$passwordError = '';
$passwordSuccess = '';

$stmt = $pdo->prepare('SELECT id, name, email, phone, photo_path, password, role, status, created_at FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    http_response_code(404);
    exit('User account not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'profile') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = preg_replace('/\D+/', '', $_POST['phone'] ?? '');

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Enter a valid name and email address.';
        } else {
            $emailCheck = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
            $emailCheck->execute([$email, $userId]);
            if ($emailCheck->fetch()) {
                $error = 'That email address is already in use.';
            } else {
                $photoPath = $user['photo_path'];
                if (!empty($_POST['remove_photo'])) {
                  if ($user['photo_path'] && is_file(__DIR__ . '/' . $user['photo_path'])) {
                    unlink(__DIR__ . '/' . $user['photo_path']);
                  }
                  $photoPath = null;
                }
                if (!empty($_FILES['photo']['tmp_name'])) {
                  $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                  $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['photo']['tmp_name']);
                  if ($_FILES['photo']['error'] !== UPLOAD_ERR_OK || !isset($allowed[$mime]) || $_FILES['photo']['size'] > 5 * 1024 * 1024) {
                    $error = 'Upload a JPG, PNG, or WEBP image up to 5 MB.';
                  } else {
                    $filename = 'profile_' . $userId . '_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
                    $photoPath = 'uploads/' . $filename;
                    if (move_uploaded_file($_FILES['photo']['tmp_name'], __DIR__ . '/' . $photoPath)) {
                      if ($user['photo_path'] && is_file(__DIR__ . '/' . $user['photo_path'])) {
                        unlink(__DIR__ . '/' . $user['photo_path']);
                      }
                    } else {
                      $error = 'The profile picture could not be saved.';
                    }
                  }
                }
                if (!$error) {
                  $update = $pdo->prepare('UPDATE users SET name = ?, email = ?, phone = ?, photo_path = ? WHERE id = ?');
                  $update->execute([$name, $email, $phone !== '' ? $phone : null, $photoPath, $userId]);
                }
                if (!$error) {
                $_SESSION['user_name'] = $name;
                $_SESSION['user_email'] = $email;
                $_SESSION['last_login_email'] = $email;
                $user['name'] = $name;
                $user['email'] = $email;
                $user['phone'] = $phone;
                $user['photo_path'] = $photoPath;
                $success = 'Profile details updated successfully.';
                log_activity($pdo, 'Updated profile details');
                }
            }
        }
    }

    if ($action === 'password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (!password_verify($currentPassword, $user['password'])) {
            $passwordError = 'Your current password is incorrect.';
        } elseif (strlen($newPassword) < 8) {
            $passwordError = 'New password must be at least 8 characters long.';
        } elseif ($newPassword !== $confirmPassword) {
            $passwordError = 'The new password and confirmation do not match.';
        } else {
            $update = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
            $update->execute([password_hash($newPassword, PASSWORD_BCRYPT), $userId]);
            $_SESSION['last_login_email'] = $user['email'];
            $_SESSION['user_email'] = $user['email'];
            $passwordSuccess = 'Password updated successfully.';
            log_activity($pdo, 'Updated password');
        }
    }

}

$pageTitle = 'Settings';
require __DIR__ . '/includes/header.php';
?>

<div class="settings-heading">
  <div>
    <span class="eyebrow"><i class="bi bi-sliders2"></i> Account center</span>
    <h4>Settings</h4>
    <p>Manage your profile details and profile picture.</p>
  </div>
</div>

<?php if ($error): ?><div class="alert alert-danger settings-alert"><i class="bi bi-exclamation-circle me-2"></i><?= e($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success settings-alert"><i class="bi bi-check-circle me-2"></i><?= e($success) ?></div><?php endif; ?>

<div class="row g-4">
  <div class="col-xl-8">
    <section class="settings-panel">
      <div class="settings-panel-title"><span class="settings-icon"><i class="bi bi-person-vcard"></i></span><div><h5>Profile information</h5><p>Keep your contact details up to date.</p></div></div>
      <form method="POST" enctype="multipart/form-data" class="row g-3">
        <?= csrf_field() ?><input type="hidden" name="action" value="profile">
        <div class="col-md-6"><label class="form-label" for="name">Full name</label><input class="form-control" id="name" name="name" value="<?= e($user['name']) ?>" required></div>
        <div class="col-md-6"><label class="form-label" for="email">Email address</label><input class="form-control" id="email" type="email" name="email" value="<?= e($user['email']) ?>" required></div>
        <div class="col-md-6"><label class="form-label" for="phone">Phone number</label><input class="form-control" id="phone" name="phone" inputmode="numeric" pattern="[0-9]*" oninput="this.value=this.value.replace(/\D/g,'')" value="<?= e($user['phone']) ?>" maxlength="20" placeholder="Add a phone number"></div>
        <div class="col-md-6">
          <label class="form-label" for="photo">Profile picture</label>
          <input class="form-control" id="photo" type="file" name="photo" accept="image/jpeg,image/png,image/webp">
          <small class="text-muted">JPG, PNG, or WEBP up to 5 MB</small>
          <?php if (!empty($user['photo_path'])): ?>
          <div class="form-check mt-2">
            <input class="form-check-input" type="checkbox" name="remove_photo" value="1" id="remove_photo">
            <label class="form-check-label text-danger" for="remove_photo">Remove current profile picture</label>
          </div>
          <?php endif; ?>
        </div>
        <div class="col-12"><button class="btn settings-primary" type="submit"><i class="bi bi-check2 me-2"></i>Save profile</button></div>
      </form>
    </section>


  </div>

  <div class="col-xl-4">
    <aside class="account-card">
      <?php if (!empty($user['photo_path'])): ?><img class="account-avatar account-avatar-image" src="<?= BASE_URL . '/' . e($user['photo_path']) ?>" alt="<?= e($user['name']) ?> profile picture"><?php else: ?><div class="account-avatar"><?= e(strtoupper(substr($user['name'], 0, 1))) ?></div><?php endif; ?>
      <h5><?= e($user['name']) ?></h5><p><?= e($user['email']) ?></p>
      <span class="account-status"><i class="bi bi-circle-fill"></i> <?= e(ucfirst($user['status'])) ?> account</span>
      <dl class="account-details"><div><dt>Role</dt><dd><?= e(ucfirst($user['role'])) ?></dd></div><div><dt>Member since</dt><dd><?= e(date('M Y', strtotime($user['created_at']))) ?></dd></div></dl>
    </aside>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
