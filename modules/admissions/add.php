<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$leadId = (int)($_GET['lead_id'] ?? 0);
$lead = null;
if ($leadId) {
    $stmt = $pdo->prepare("SELECT * FROM leads WHERE id = ?");
    $stmt->execute([$leadId]);
    $lead = $stmt->fetch();
    if ($lead && is_counselor() && (int)$lead['assigned_to'] !== current_user_id()) {
        http_response_code(403);
        die('You do not have access to this lead.');
    }
}

$courses = $pdo->query("SELECT id, name, fee FROM courses WHERE status='active' ORDER BY name")->fetchAll();
$counselors = $pdo->query("SELECT id, name FROM users WHERE role='counselor' AND status='active' ORDER BY name")->fetchAll();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $student_name = trim($_POST['student_name'] ?? '');
    $phone = preg_replace('/\D+/', '', $_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $course_id = (int)($_POST['course_id'] ?? 0);
    $counselor_id = is_counselor() ? current_user_id() : ($_POST['counselor_id'] ?: null);
    $admission_date = $_POST['admission_date'] ?? date('Y-m-d');
    $total_fee = (float)($_POST['total_fee'] ?? 0);
    $discount = (float)($_POST['discount'] ?? 0);
    $postedLeadId = (int)($_POST['lead_id'] ?? 0) ?: null;

    if ($postedLeadId && is_counselor()) {
      $leadAccess = $pdo->prepare('SELECT assigned_to FROM leads WHERE id = ? LIMIT 1');
      $leadAccess->execute([$postedLeadId]);
      $leadOwner = $leadAccess->fetchColumn();
      if ((int)$leadOwner !== current_user_id()) {
        $postedLeadId = null;
        $error = 'You can only create an admission from one of your assigned leads.';
      }
    }

    if (!$error && ($student_name === '' || $phone === '' || !$course_id)) {
        $error = 'Student name, phone and course are required.';
    } else {
        $pdo->beginTransaction();
        try {
            // Handle file uploads
            $idProofPath = null; $photoPath = null;
            if (!empty($_FILES['id_proof']['tmp_name'])) {
                $idProofPath = 'uploads/' . uniqid('id_') . '_' . basename($_FILES['id_proof']['name']);
                move_uploaded_file($_FILES['id_proof']['tmp_name'], __DIR__ . '/../../' . $idProofPath);
            }
            if (!empty($_FILES['photo']['tmp_name'])) {
                $photoPath = 'uploads/' . uniqid('photo_') . '_' . basename($_FILES['photo']['name']);
                move_uploaded_file($_FILES['photo']['tmp_name'], __DIR__ . '/../../' . $photoPath);
            }

            $admissionCode = 'DMP' . date('Y') . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);

            $stmt = $pdo->prepare("INSERT INTO admissions
                (admission_code, lead_id, student_name, phone, email, course_id, counselor_id, admission_date, total_fee, discount, status, id_proof_path, photo_path)
                VALUES (?,?,?,?,?,?,?,?,?,?, 'pending-docs', ?, ?)");
            $stmt->execute([$admissionCode, $postedLeadId, $student_name, $phone, $email, $course_id, $counselor_id, $admission_date, $total_fee, $discount, $idProofPath, $photoPath]);
            $admissionId = db_last_insert_id('admissions');

            // Mark lead as converted
            if ($postedLeadId) {
                $stmt = $pdo->prepare("UPDATE leads SET status = 'converted' WHERE id = ?");
                $stmt->execute([$postedLeadId]);
                $stmt = $pdo->prepare("INSERT INTO lead_notes (lead_id, note, created_by) VALUES (?,?,?)");
                $stmt->execute([$postedLeadId, "Converted to Admission ($admissionCode)", current_user_id()]);
            }

            $pdo->commit();
            log_activity($pdo, "Created admission $admissionCode for $student_name");
            $_SESSION['flash_success'] = "Admission created successfully. Admission Code: $admissionCode";
            header('Location: view.php?id=' . $admissionId);
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Error creating admission: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'New Admission';
require __DIR__ . '/../../includes/header.php';
?>

<h4 class="mb-3">New Admission <?= $lead ? '(from Lead #' . $lead['id'] . ')' : '' ?></h4>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

<div class="card card-stat form-card p-4">
<form method="POST" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <input type="hidden" name="lead_id" value="<?= $lead['id'] ?? '' ?>">
  <div class="row g-3">
    <div class="col-md-6">
      <label class="form-label">Student Name *</label>
      <input type="text" name="student_name" class="form-control" value="<?= e($lead['name'] ?? '') ?>" required>
    </div>
    <div class="col-md-6">
      <label class="form-label">Phone *</label>
      <input type="text" name="phone" class="form-control" inputmode="numeric" pattern="[0-9]*" oninput="this.value=this.value.replace(/\D/g,'')" value="<?= e($lead['phone'] ?? '') ?>" required>
    </div>
    <div class="col-md-6">
      <label class="form-label">Email</label>
      <input type="email" name="email" class="form-control" value="<?= e($lead['email'] ?? '') ?>">
    </div>
    <div class="col-md-6">
      <label class="form-label">Admission Date</label>
      <input type="date" name="admission_date" class="form-control" value="<?= date('Y-m-d') ?>">
    </div>
    <div class="col-md-6">
      <label class="form-label">Course *</label>
      <select name="course_id" id="course_id" class="form-select" required onchange="document.getElementById('total_fee').value = this.options[this.selectedIndex].dataset.fee || 0">
        <option value="">-- Select Course --</option>
        <?php foreach ($courses as $c): ?>
          <option value="<?= $c['id'] ?>" data-fee="<?= $c['fee'] ?>" <?= (($lead['course_id'] ?? null) == $c['id']) ? 'selected' : '' ?>><?= e($c['name']) ?> (₹<?= number_format($c['fee'],0) ?>)</option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php if (!is_counselor()): ?>
    <div class="col-md-6">
      <label class="form-label">Counselor</label>
      <select name="counselor_id" class="form-select">
        <option value="">-- Select --</option>
        <?php foreach ($counselors as $c): ?>
          <option value="<?= $c['id'] ?>" <?= (($lead['assigned_to'] ?? null) == $c['id']) ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
    <div class="col-md-6">
      <label class="form-label">Total Fee (₹) *</label>
      <input type="number" step="0.01" id="total_fee" name="total_fee" class="form-control" required>
    </div>
    <div class="col-md-6">
      <label class="form-label">Discount (₹)</label>
      <input type="number" step="0.01" name="discount" class="form-control" value="0">
    </div>
    <div class="col-md-6">
      <label class="form-label">ID Proof (upload)</label>
      <input type="file" name="id_proof" class="form-control">
    </div>
    <div class="col-md-6">
      <label class="form-label">Photo (upload)</label>
      <input type="file" name="photo" class="form-control">
    </div>
  </div>
  <button type="submit" class="btn btn-success mt-4">Confirm Admission</button>
  <a href="list.php" class="btn btn-outline-secondary mt-4">Cancel</a>
</form>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
