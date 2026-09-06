<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$admissionId = (int)($_GET['admission_id'] ?? $_POST['admission_id'] ?? 0);
$stmt = $pdo->prepare("SELECT a.*, c.name AS course_name FROM admissions a LEFT JOIN courses c ON c.id=a.course_id WHERE a.id = ?");
$stmt->execute([$admissionId]);
$adm = $stmt->fetch();
if (!$adm) { die('Admission not found.'); }
if (is_counselor() && (int)$adm['counselor_id'] !== current_user_id()) {
    http_response_code(403); die('Access denied.');
}

$paidStmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE admission_id = ?");
$paidStmt->execute([$admissionId]);
$paid = $paidStmt->fetchColumn();
$netFee = $adm['total_fee'] - $adm['discount'];
$due = $netFee - $paid;

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $amount = (float)($_POST['amount'] ?? 0);
    $mode = $_POST['mode'] ?? 'cash';
    $payment_date = $_POST['payment_date'] ?? date('Y-m-d');
    $remarks = trim($_POST['remarks'] ?? '');

    if ($amount <= 0) {
        $error = 'Enter a valid payment amount.';
    } else {
        $receiptNo = 'RCPT' . date('ymd') . strtoupper(substr(uniqid(), -5));
        $stmt = $pdo->prepare("INSERT INTO payments (admission_id, receipt_no, amount, mode, payment_date, remarks, created_by) VALUES (?,?,?,?,?,?,?)");
        $stmt->execute([$admissionId, $receiptNo, $amount, $mode, $payment_date, $remarks, current_user_id()]);
        $paymentId = db_last_insert_id('payments');
        log_activity($pdo, "Recorded payment $receiptNo (₹$amount) for admission #$admissionId");
        $_SESSION['flash_success'] = "Payment of ₹" . money($amount) . " recorded. Receipt: $receiptNo";
        header('Location: ../admissions/view.php?id=' . $admissionId);
        exit;
    }
}

$pageTitle = 'Add Payment';
require __DIR__ . '/../../includes/header.php';
?>
<h4 class="mb-3">Record Payment - <?= e($adm['student_name']) ?> (<?= e($adm['admission_code']) ?>)</h4>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

<div class="card card-stat form-card p-4">
  <p class="mb-3">Course: <strong><?= e($adm['course_name']) ?></strong> &nbsp;|&nbsp;
    Net Fee: ₹<?= money($netFee) ?> &nbsp;|&nbsp;
    Paid: ₹<?= money($paid) ?> &nbsp;|&nbsp;
    <span class="<?= $due>0?'text-danger':'text-success' ?>">Due: ₹<?= money($due) ?></span>
  </p>
  <form method="POST">
    <?= csrf_field() ?>
    <input type="hidden" name="admission_id" value="<?= $admissionId ?>">
    <div class="mb-3">
      <label class="form-label">Amount (₹) *</label>
      <input type="number" step="0.01" name="amount" class="form-control" required max="<?= max($due,0) ?: '' ?>" value="<?= $due > 0 ? number_format($due,2,'.','') : '' ?>">
    </div>
    <div class="mb-3">
      <label class="form-label">Payment Mode</label>
      <select name="mode" class="form-select">
        <?php foreach (['cash','upi','bank-transfer','card','cheque'] as $m): ?>
          <option value="<?= $m ?>"><?= strtoupper($m) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="mb-3">
      <label class="form-label">Payment Date</label>
      <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>">
    </div>
    <div class="mb-3">
      <label class="form-label">Remarks</label>
      <input type="text" name="remarks" class="form-control" placeholder="e.g. 1st installment">
    </div>
    <button class="btn btn-success">Record Payment</button>
    <a href="../admissions/view.php?id=<?= $admissionId ?>" class="btn btn-outline-secondary">Cancel</a>
  </form>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
