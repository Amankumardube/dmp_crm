<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$where = [];
$params = [];

if (is_counselor()) {
    $where[] = 'l.assigned_to = ?';
    $params[] = current_user_id();
}
if (!empty($_GET['status'])) {
    $where[] = 'l.status = ?';
    $params[] = $_GET['status'];
}
if (!empty($_GET['source'])) {
    $where[] = 'l.source = ?';
    $params[] = $_GET['source'];
}
$selectedMonth = $_GET['month'] ?? '';
if (preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) {
  $where[] = "DATE_FORMAT(l.created_at, '%Y-%m') = ?";
  $params[] = $selectedMonth;
}
$monthOptions = [];
$monthDate = new DateTime('first day of this month');
for ($month = 0; $month < 12; $month++) {
  $monthOptions[$monthDate->format('Y-m')] = $monthDate->format('F Y');
  $monthDate->modify('-1 month');
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare("
    SELECT l.*, c.name AS course_name, u.name AS counselor_name
    FROM leads l
    LEFT JOIN courses c ON c.id = l.course_id
    LEFT JOIN users u ON u.id = l.assigned_to
    $whereSql
    ORDER BY l.created_at DESC
");
$stmt->execute($params);
$leads = $stmt->fetchAll();

$pageTitle = 'Leads';
require __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4>Leads</h4>
  <div>
    <a href="add.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Add Lead</a>
    <a href="import.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-upload"></i> Bulk Import</a>
  </div>
</div>

<form class="row g-2 mb-3">
  <div class="col-auto">
    <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
      <option value="">All Status</option>
      <?php foreach (['new','contacted','follow-up','interested','not-interested','converted','junk'] as $s): ?>
        <option value="<?= $s ?>" <?= (($_GET['status'] ?? '') === $s) ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-auto">
    <select name="source" class="form-select form-select-sm" onchange="this.form.submit()">
      <option value="">All Sources</option>
      <?php foreach (['facebook','google','instagram','whatsapp','referral','walk-in','website','other'] as $s): ?>
        <option value="<?= $s ?>" <?= (($_GET['source'] ?? '') === $s) ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-auto">
    <select name="month" class="form-select form-select-sm" onchange="this.form.submit()">
      <option value="">All Months</option>
      <?php foreach ($monthOptions as $monthValue => $monthLabel): ?>
        <option value="<?= $monthValue ?>" <?= $selectedMonth === $monthValue ? 'selected' : '' ?>><?= $monthLabel ?></option>
      <?php endforeach; ?>
    </select>
  </div>
</form>

<?php if (is_admin()): ?>
<form id="bulkDeleteForm" method="POST" action="bulk-delete.php" class="mb-3" onsubmit="return confirm('Delete all selected leads? Their notes and follow-ups will also be deleted.');">
  <?= csrf_field() ?>
  <button type="submit" class="btn btn-danger btn-sm" id="bulkDeleteButton" disabled>
    <i class="bi bi-trash"></i> Delete Selected
  </button>
</form>
<?php endif; ?>

<div class="card card-stat p-3">
<div class="table-responsive"><table id="leadsTable" class="table table-hover align-middle w-100">
  <thead>
    <tr>
      <?php if (is_admin()): ?><th><input type="checkbox" id="selectAllLeads" aria-label="Select all leads"></th><?php endif; ?>
      <th>Name</th><th>Phone</th><th>Course</th><th>Source</th><th>Status</th><th>Counselor</th><th>Created</th><th>Action</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($leads as $l): ?>
    <tr>
      <?php if (is_admin()): ?><td><input type="checkbox" class="lead-checkbox" name="lead_ids[]" value="<?= (int)$l['id'] ?>" form="bulkDeleteForm" aria-label="Select <?= e($l['name']) ?>"></td><?php endif; ?>
      <td><?= e($l['name']) ?></td>
      <td><?= e($l['phone']) ?></td>
      <td><?= e($l['course_name'] ?? '-') ?></td>
      <td><span class="badge bg-secondary badge-status"><?= e($l['source']) ?></span></td>
      <td>
        <?php
          $colors = ['new'=>'primary','contacted'=>'info','follow-up'=>'warning','interested'=>'success',
                     'not-interested'=>'danger','converted'=>'dark','junk'=>'secondary'];
        ?>
        <span class="badge bg-<?= $colors[$l['status']] ?? 'secondary' ?> badge-status"><?= e($l['status']) ?></span>
      </td>
      <td><?= e($l['counselor_name'] ?? 'Unassigned') ?></td>
      <td><?= e(date('d M Y', strtotime($l['created_at']))) ?></td>
      <td class="text-nowrap">
        <div class="dropdown">
          <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Actions"><i class="bi bi-three-dots"></i></button>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="view.php?id=<?= (int)$l['id'] ?>"><i class="bi bi-eye me-2"></i>View</a></li>
            <?php if (is_admin()): ?>
            <li>
              <form method="POST" action="delete.php" onsubmit="return confirm('Delete this lead? Its notes and follow-ups will also be deleted.');">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
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

<script>
$(function(){
  var table = $('#leadsTable').DataTable({
    order: [[<?= is_admin() ? '7' : '6' ?>,'desc']],
    language: { search: '', searchPlaceholder: 'Search lead details...' }
  });
  <?php if (is_admin()): ?>
  var $button = $('#bulkDeleteButton');
  var updateBulkState = function() {
    $button.prop('disabled', $('.lead-checkbox:checked').length === 0);
  };
  $(document).on('change', '.lead-checkbox', updateBulkState);
  $('#selectAllLeads').on('change', function() {
    table.rows({ search: 'applied' }).nodes().to$().find('.lead-checkbox').prop('checked', this.checked);
    updateBulkState();
  });
  $('#bulkDeleteForm').on('submit', function(event) {
    if ($('.lead-checkbox:checked').length === 0) {
      event.preventDefault();
      updateBulkState();
    }
  });
  <?php endif; ?>
});
</script>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
