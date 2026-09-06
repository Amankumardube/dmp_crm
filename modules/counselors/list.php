<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin','manager']);

$users = $pdo->query("
    SELECT u.*,
      (SELECT COUNT(*) FROM leads WHERE assigned_to = u.id) AS total_leads,
      (SELECT COUNT(*) FROM leads WHERE assigned_to = u.id AND status='converted') AS converted
    FROM users u WHERE u.role='counselor' ORDER BY u.name
")->fetchAll();

$pageTitle = 'Counselors';
require __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4>Counselors</h4>
  <a href="add.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Add Counselor</a>
</div>

<div class="card card-stat p-3">
<div class="table-responsive"><table id="tbl" class="table table-hover w-100">
  <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Status</th><th>Total Leads</th><th>Converted</th><th>Target</th><th>Actions</th></tr></thead>
  <tbody>
  <?php foreach ($users as $u): ?>
    <tr>
      <td><?= e($u['name']) ?></td>
      <td><?= e($u['email']) ?></td>
      <td><?= e($u['phone'] ?: '-') ?></td>
      <td><span class="badge bg-<?= $u['status']==='active'?'success':'secondary' ?>"><?= e($u['status']) ?></span></td>
      <td><?= (int)$u['total_leads'] ?></td>
      <td><?= (int)$u['converted'] ?></td>
      <td><?= (int)$u['monthly_target'] ?></td>
      <td class="text-nowrap">
        <div class="dropdown">
          <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Actions"><i class="bi bi-three-dots"></i></button>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="view.php?id=<?= (int)$u['id'] ?>"><i class="bi bi-eye me-2"></i>View</a></li>
            <?php if (is_admin()): ?>
            <li><a class="dropdown-item" href="edit.php?id=<?= (int)$u['id'] ?>"><i class="bi bi-pencil me-2"></i>Edit</a></li>
            <?php endif; ?>
            <?php if (is_admin() || is_manager()): ?>
            <li>
              <form method="POST" action="delete.php" onsubmit="return confirm('Delete this counselor? Leads will become unassigned.');">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
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
<script>$(function(){ $('#tbl').DataTable({ language: { search: '', searchPlaceholder: 'Search counselor details...' } }); });</script>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
