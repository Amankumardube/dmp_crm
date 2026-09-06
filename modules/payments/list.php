<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$where = '';
$params = [];
if (is_counselor()) {
    $where = 'WHERE a.counselor_id = ?';
    $params[] = current_user_id();
}

$stmt = $pdo->prepare("
    SELECT p.*, a.student_name, a.admission_code, c.name AS course_name
    FROM payments p
    JOIN admissions a ON a.id = p.admission_id
    LEFT JOIN courses c ON c.id = a.course_id
    $where
    ORDER BY p.payment_date DESC
");
$stmt->execute($params);
$payments = $stmt->fetchAll();

// Due report
$dueStmt = $pdo->prepare("
    SELECT a.id, a.admission_code, a.student_name, a.phone, c.name AS course_name,
      (a.total_fee - a.discount) AS net_fee,
      COALESCE((SELECT SUM(amount) FROM payments WHERE admission_id = a.id),0) AS paid
    FROM admissions a
    LEFT JOIN courses c ON c.id = a.course_id
    $where
    HAVING (net_fee - paid) > 0
    ORDER BY (net_fee - paid) DESC
");
$dueStmt->execute($params);
$dues = $dueStmt->fetchAll();

$pageTitle = 'Payments';
require __DIR__ . '/../../includes/header.php';
?>

<ul class="nav nav-tabs mb-3">
  <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#all">All Payments</a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#due">Due Payments Report</a></li>
</ul>

<div class="tab-content">
  <div class="tab-pane fade show active" id="all">
    <div class="card card-stat p-3">
      <div class="table-responsive"><table id="tbl1" class="table table-hover w-100">
        <thead><tr><th>Receipt#</th><th>Student</th><th>Course</th><th>Amount</th><th>Mode</th><th>Date</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($payments as $p): ?>
          <tr>
            <td><?= e($p['receipt_no']) ?></td>
            <td><?= e($p['student_name']) ?> <small class="text-muted">(<?= e($p['admission_code']) ?>)</small></td>
            <td><?= e($p['course_name']) ?></td>
            <td>₹<?= money($p['amount']) ?></td>
            <td><?= e(strtoupper($p['mode'])) ?></td>
            <td><?= e(date('d M Y', strtotime($p['payment_date']))) ?></td>
            <td class="text-nowrap">
              <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Actions"><i class="bi bi-three-dots"></i></button>
                <ul class="dropdown-menu dropdown-menu-end">
                  <li><a class="dropdown-item" href="receipt.php?id=<?= $p['id'] ?>" target="_blank"><i class="bi bi-receipt me-2"></i>View Receipt</a></li>
                  <?php if (is_admin()): ?>
                  <li>
                    <form method="POST" action="delete.php" onsubmit="return confirm('Delete this receipt? This cannot be undone.');">
                      <?= csrf_field() ?>
                      <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                      <button type="submit" class="dropdown-item text-danger"><i class="bi bi-trash me-2"></i>Delete Receipt</button>
                    </form>
                  </li>
                  <?php endif; ?>
                </ul>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
  </div>
  <div class="tab-pane fade" id="due">
    <div class="card card-stat p-3">
      <div class="table-responsive"><table id="tbl2" class="table table-hover w-100">
        <thead><tr><th>Admission Code</th><th>Student</th><th>Phone</th><th>Course</th><th>Net Fee</th><th>Paid</th><th>Due</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($dues as $d): $due = $d['net_fee'] - $d['paid']; ?>
          <tr>
            <td><?= e($d['admission_code']) ?></td>
            <td><?= e($d['student_name']) ?></td>
            <td><?= e($d['phone']) ?></td>
            <td><?= e($d['course_name']) ?></td>
            <td>₹<?= money($d['net_fee']) ?></td>
            <td>₹<?= money($d['paid']) ?></td>
            <td class="text-danger fw-bold">₹<?= money($due) ?></td>
            <td><a href="add.php?admission_id=<?= $d['id'] ?>" class="btn btn-sm btn-success">Collect</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
  </div>
</div>

<script>$(function(){ var options = { language: { search: '', searchPlaceholder: 'Search payment details...' } }; $('#tbl1').DataTable(options); $('#tbl2').DataTable(options); });</script>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
