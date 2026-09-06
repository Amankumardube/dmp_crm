<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("
    SELECT a.*, c.name AS course_name, u.name AS counselor_name
    FROM admissions a
    LEFT JOIN courses c ON c.id = a.course_id
    LEFT JOIN users u ON u.id = a.counselor_id
    WHERE a.id = ?
");
$stmt->execute([$id]);
$adm = $stmt->fetch();
if (!$adm) { die('Admission not found.'); }
if (is_counselor() && (int)$adm['counselor_id'] !== current_user_id()) {
    http_response_code(403); die('Access denied.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_status' && !is_counselor()) {
    csrf_verify();
    $stmt = $pdo->prepare("UPDATE admissions SET status = ? WHERE id = ?");
    $stmt->execute([$_POST['status'], $id]);
    header('Location: view.php?id=' . $id);
    exit;
}

$payments = $pdo->prepare("SELECT p.*, u.name AS collected_by FROM payments p LEFT JOIN users u ON u.id = p.created_by WHERE p.admission_id = ? ORDER BY p.payment_date DESC");
$payments->execute([$id]);
$payments = $payments->fetchAll();

$netFee = $adm['total_fee'] - $adm['discount'];
$paid = array_sum(array_column($payments, 'amount'));
$due = $netFee - $paid;

$pageTitle = 'Admission: ' . $adm['student_name'];
require __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4><?= e($adm['student_name']) ?> <small class="text-muted"><?= e($adm['admission_code']) ?></small></h4>
  <div><?php if (is_admin()): ?><a href="edit.php?id=<?= (int)$adm['id'] ?>" class="btn btn-primary btn-sm"><i class="bi bi-pencil"></i> Edit</a><?php endif; ?> <a href="../payments/add.php?admission_id=<?= (int)$adm['id'] ?>" class="btn btn-success btn-sm"><i class="bi bi-cash-coin"></i> Add Payment</a></div>
</div>

<div class="row g-3">
  <div class="col-md-4">
    <div class="card card-stat p-3 mb-3">
      <h6>Admission Info</h6>
      <p class="mb-1"><strong>Phone:</strong> <?= e($adm['phone']) ?></p>
      <p class="mb-1"><strong>Email:</strong> <?= e($adm['email'] ?: '-') ?></p>
      <p class="mb-1"><strong>Course:</strong> <?= e($adm['course_name']) ?></p>
      <p class="mb-1"><strong>Counselor:</strong> <?= e($adm['counselor_name'] ?? '-') ?></p>
      <p class="mb-1"><strong>Admission Date:</strong> <?= e(date('d M Y', strtotime($adm['admission_date']))) ?></p>
      <?php if ($adm['id_proof_path']): ?><p class="mb-1"><a href="../../<?= e($adm['id_proof_path']) ?>" target="_blank">View ID Proof</a></p><?php endif; ?>
      <?php if ($adm['photo_path']): ?><p class="mb-0"><a href="../../<?= e($adm['photo_path']) ?>" target="_blank">View Photo</a></p><?php endif; ?>
    </div>

    <div class="card card-stat p-3 mb-3">
      <h6>Fee Summary</h6>
      <p class="mb-1">Total Fee: ₹<?= money($adm['total_fee']) ?></p>
      <p class="mb-1">Discount: ₹<?= money($adm['discount']) ?></p>
      <p class="mb-1">Net Payable: ₹<?= money($netFee) ?></p>
      <p class="mb-1 text-success">Paid: ₹<?= money($paid) ?></p>
      <p class="mb-0 fw-bold <?= $due > 0 ? 'text-danger' : 'text-success' ?>">Due: ₹<?= money($due) ?></p>
    </div>

    <?php if (!is_counselor()): ?>
    <div class="card card-stat p-3">
      <h6>Status</h6>
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="change_status">
        <select name="status" class="form-select mb-2">
          <?php foreach (['pending-docs','confirmed','cancelled'] as $s): ?>
            <option value="<?= $s ?>" <?= $adm['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
          <?php endforeach; ?>
        </select>
        <button class="btn btn-sm btn-primary w-100">Update</button>
      </form>
    </div>
    <?php endif; ?>
  </div>

  <div class="col-md-8">
    <div class="card card-stat p-3">
      <h6>Payment History</h6>
      <div class="table-responsive"><table class="table table-sm">
        <thead><tr><th>Receipt #</th><th>Date</th><th>Amount</th><th>Mode</th><th>Collected By</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($payments as $p): ?>
          <tr>
            <td><?= e($p['receipt_no']) ?></td>
            <td><?= e(date('d M Y', strtotime($p['payment_date']))) ?></td>
            <td>₹<?= money($p['amount']) ?></td>
            <td><?= e(strtoupper($p['mode'])) ?></td>
            <td><?= e($p['collected_by'] ?? '-') ?></td>
            <td><a href="../payments/receipt.php?id=<?= $p['id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary">Receipt</a></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$payments): ?><tr><td colspan="6" class="text-muted">No payments recorded yet.</td></tr><?php endif; ?>
        </tbody>
      </table></div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
