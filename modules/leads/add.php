<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$courses = $pdo->query("SELECT id, name FROM courses WHERE status='active' ORDER BY name")->fetchAll();
$counselors = $pdo->query("SELECT id, name FROM users WHERE role='counselor' AND status='active' ORDER BY name")->fetchAll();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $name   = trim($_POST['name'] ?? '');
    $phone  = preg_replace('/\D+/', '', $_POST['phone'] ?? '');
    $email  = trim($_POST['email'] ?? '');
    $city   = trim($_POST['city'] ?? '');
    $source = $_POST['source'] ?? 'other';
    $course_id = $_POST['course_id'] ?: null;
    $assigned_to = is_counselor() ? current_user_id() : ($_POST['assigned_to'] ?: null);

    if ($name === '' || $phone === '') {
        $error = 'Name and Phone are required.';
    } else {
        // Duplicate check by phone
        $dup = $pdo->prepare("SELECT id FROM leads WHERE phone = ? LIMIT 1");
        $dup->execute([$phone]);
        if ($dup->fetch()) {
            $error = 'A lead with this phone number already exists.';
        } else {
            $stmt = $pdo->prepare("INSERT INTO leads (name, phone, email, city, source, course_id, assigned_to, created_by, status)
                                    VALUES (?,?,?,?,?,?,?,?,'new')");
            $stmt->execute([$name, $phone, $email, $city, $source, $course_id, $assigned_to, current_user_id()]);
            $leadId = db_last_insert_id('leads');
            log_activity($pdo, "Added lead #$leadId ($name)");
            $_SESSION['flash_success'] = 'Lead added successfully.';
            header('Location: view.php?id=' . $leadId);
            exit;
        }
    }
}

$pageTitle = 'Add Lead';
require __DIR__ . '/../../includes/header.php';
?>

<h4 class="mb-3">Add New Lead</h4>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

<div class="card card-stat form-card p-4">
<form method="POST">
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-md-6">
      <label class="form-label">Full Name *</label>
      <input type="text" name="name" class="form-control" required>
    </div>
    <div class="col-md-6">
      <label class="form-label">Phone *</label>
      <input type="text" name="phone" class="form-control" inputmode="numeric" pattern="[0-9]*" oninput="this.value=this.value.replace(/\D/g,'')" required>
    </div>
    <div class="col-md-6">
      <label class="form-label">Email</label>
      <input type="email" name="email" class="form-control">
    </div>
    <div class="col-md-6">
      <label class="form-label">City</label>
      <input type="text" name="city" class="form-control">
    </div>
    <div class="col-md-6">
      <label class="form-label">Lead Source</label>
      <select name="source" class="form-select">
        <?php foreach (['facebook','google','instagram','whatsapp','referral','walk-in','website','other'] as $s): ?>
          <option value="<?= $s ?>"><?= ucfirst($s) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-6">
      <label class="form-label">Course Interested</label>
      <select name="course_id" class="form-select">
        <option value="">-- Select --</option>
        <?php foreach ($courses as $c): ?>
          <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php if (!is_counselor()): ?>
    <div class="col-md-6">
      <label class="form-label">Assign to Counselor</label>
      <select name="assigned_to" class="form-select">
        <option value="">-- Unassigned --</option>
        <?php foreach ($counselors as $c): ?>
          <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
  </div>
  <button type="submit" class="btn btn-primary mt-4">Save Lead</button>
  <a href="list.php" class="btn btn-outline-secondary mt-4">Cancel</a>
</form>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
