<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$receiptWhere = 'WHERE p.id = ?';
$receiptParams = [$id];
if (is_counselor()) {
  $receiptWhere .= ' AND a.counselor_id = ?';
  $receiptParams[] = current_user_id();
}
$stmt = $pdo->prepare("
    SELECT p.*, a.student_name, a.admission_code, a.phone, a.total_fee, a.discount, c.name AS course_name
    FROM payments p
    JOIN admissions a ON a.id = p.admission_id
    LEFT JOIN courses c ON c.id = a.course_id
  $receiptWhere
");
$stmt->execute($receiptParams);
$p = $stmt->fetch();
if (!$p) { die('Receipt not found.'); }

$paidTotalStmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE admission_id = (SELECT admission_id FROM payments WHERE id = ?)");
$paidTotalStmt->execute([$id]);
$paidTotal = $paidTotalStmt->fetchColumn();
$netFee = $p['total_fee'] - $p['discount'];
$balance = $netFee - $paidTotal;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Receipt <?= e($p['receipt_no']) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { background: #f5f7fa; padding: clamp(12px, 4vw, 40px); }
.receipt-box { background: #fff; max-width: 700px; margin: auto; border: 1px solid #ddd; padding: clamp(16px, 4vw, 30px); border-radius: 10px; }
.receipt-box table { width: 100%; table-layout: fixed; }
.receipt-box .table-responsive > .table { min-width: 0; }
.receipt-box th, .receipt-box td { overflow-wrap: anywhere; }
@media (max-width: 480px) {
  .receipt-box .row > div { width: 100%; text-align: left !important; }
  .receipt-box .row > div + div { margin-top: 8px; }
}
@media print { .no-print { display: none; } }
</style>
</head>
<body>
<div class="receipt-box">
  <div class="text-center mb-4">
    <h4 class="fw-bold">DMP AI Digital Institute</h4>
    <p class="text-muted mb-0">Fee Payment Receipt</p>
  </div>
  <div class="row mb-3">
    <div class="col-6"><strong>Receipt No:</strong> <?= e($p['receipt_no']) ?></div>
    <div class="col-6 text-end"><strong>Date:</strong> <?= e(date('d M Y', strtotime($p['payment_date']))) ?></div>
  </div>
  <div class="table-responsive"><table class="table table-bordered">
    <tr><th>Student Name</th><td><?= e($p['student_name']) ?></td></tr>
    <tr><th>Admission Code</th><td><?= e($p['admission_code']) ?></td></tr>
    <tr><th>Phone</th><td><?= e($p['phone']) ?></td></tr>
    <tr><th>Course</th><td><?= e($p['course_name']) ?></td></tr>
    <tr><th>Payment Mode</th><td><?= e(strtoupper($p['mode'])) ?></td></tr>
    <tr><th>Remarks</th><td><?= e($p['remarks'] ?: '-') ?></td></tr>
  </table></div>
  <div class="table-responsive"><table class="table table-bordered">
    <tr><th>Amount Paid</th><td>₹<?= money($p['amount']) ?></td></tr>
    <tr><th>Total Paid Till Date</th><td>₹<?= money($paidTotal) ?></td></tr>
    <tr><th>Net Course Fee</th><td>₹<?= money($netFee) ?></td></tr>
    <tr><th>Balance Due</th><td>₹<?= money($balance) ?></td></tr>
  </table></div>
  <p class="text-center text-muted mt-4" style="font-size:0.85rem;">This is a computer-generated receipt.</p>
  <div class="text-center no-print mt-3">
    <button class="btn btn-primary" onclick="window.print()">Print / Save as PDF</button>
  </div>
</div>
</body>
</html>
