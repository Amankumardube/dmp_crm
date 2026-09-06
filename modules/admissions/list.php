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
    SELECT a.*, c.name AS course_name, u.name AS counselor_name,
      (SELECT COALESCE(SUM(p.amount),0) FROM payments p WHERE p.admission_id = a.id) AS paid
    FROM admissions a
    LEFT JOIN courses c ON c.id = a.course_id
    LEFT JOIN users u ON u.id = a.counselor_id
    $where
    ORDER BY a.created_at DESC
");
$stmt->execute($params);
$admissions = $stmt->fetchAll();

$pageTitle = 'Admissions';
require __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4>Admissions</h4>
  <a href="add.php" class="btn btn-success btn-sm"><i class="bi bi-plus-lg"></i> New Admission</a>
</div>

<div class="card card-stat p-3">
<div class="table-responsive"><table id="tbl" class="table table-hover w-100">
  <thead><tr><th>Code</th><th>Student</th><th>Course</th><th>Counselor</th><th>Total Fee</th><th>Paid</th><th>Due</th><th>Status</th><th>Actions</th></tr></thead>
  <tbody>
  <?php foreach ($admissions as $a): $net = $a['total_fee'] - $a['discount']; $due = $net - $a['paid']; ?>
    <tr>
      <td><?= e($a['admission_code']) ?></td>
      <td><?= e($a['student_name']) ?></td>
      <td><?= e($a['course_name']) ?></td>
      <td><?= e($a['counselor_name'] ?? '-') ?></td>
      <td>₹<?= money($net) ?></td>
      <td class="text-success">₹<?= money($a['paid']) ?></td>
      <td class="<?= $due > 0 ? 'text-danger' : 'text-success' ?>">₹<?= money($due) ?></td>
      <td><span class="badge bg-<?= $a['status']==='confirmed'?'success':($a['status']==='cancelled'?'danger':'warning') ?>"><?= e($a['status']) ?></span></td>
      <td class="text-nowrap">
        <div class="dropdown">
          <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Actions"><i class="bi bi-three-dots"></i></button>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="view.php?id=<?= (int)$a['id'] ?>"><i class="bi bi-eye me-2"></i>View</a></li>
            <?php if (is_admin()): ?>
            <li><a class="dropdown-item" href="edit.php?id=<?= (int)$a['id'] ?>"><i class="bi bi-pencil me-2"></i>Edit</a></li>
            <li>
              <form method="POST" action="delete.php" onsubmit="return confirm('Delete this admission? Payment history must be empty.');">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                <button type="submit" class="dropdown-item text-danger"><i class="bi bi-trash me-2"></i>Delete</button>
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
<script>$(function(){ $('#tbl').DataTable({ language: { search: '', searchPlaceholder: 'Search admission details...' } }); });</script>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
