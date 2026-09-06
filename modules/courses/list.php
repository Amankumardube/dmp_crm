<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin','manager']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $name = trim($_POST['name'] ?? '');
    $duration = trim($_POST['duration'] ?? '');
    $fee = (float)($_POST['fee'] ?? 0);
    $desc = trim($_POST['description'] ?? '');
    if ($name !== '') {
        $stmt = $pdo->prepare("INSERT INTO courses (name, duration, fee, description) VALUES (?,?,?,?)");
        $stmt->execute([$name, $duration, $fee, $desc]);
        $_SESSION['flash_success'] = 'Course added.';
    }
    header('Location: list.php');
    exit;
}

$courses = $pdo->query("SELECT * FROM courses ORDER BY created_at DESC")->fetchAll();
$pageTitle = 'Courses';
require __DIR__ . '/../../includes/header.php';
?>
<div class="row g-3">
  <div class="col-md-4">
    <div class="card card-stat p-3">
      <h6>Add Course</h6>
      <form method="POST">
        <?= csrf_field() ?>
        <div class="mb-2"><input type="text" name="name" class="form-control" placeholder="Course Name" required></div>
        <div class="mb-2"><input type="text" name="duration" class="form-control" placeholder="Duration (e.g. 3 Months)"></div>
        <div class="mb-2"><input type="number" step="0.01" name="fee" class="form-control" placeholder="Fee (₹)" required></div>
        <div class="mb-2"><textarea name="description" class="form-control" placeholder="Description" rows="2"></textarea></div>
        <button class="btn btn-primary w-100">Add Course</button>
      </form>
    </div>
  </div>
  <div class="col-md-8">
    <div class="card card-stat p-3">
      <h6>All Courses</h6>
      <div class="table-responsive"><table id="coursesTable" class="table table-sm w-100">
        <thead><tr><th>Name</th><th>Duration</th><th>Fee</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($courses as $c): ?>
          <tr>
            <td><?= e($c['name']) ?></td>
            <td><?= e($c['duration']) ?></td>
            <td>₹<?= money($c['fee']) ?></td>
            <td><span class="badge bg-<?= $c['status']==='active'?'success':'secondary' ?>"><?= e($c['status']) ?></span></td>
            <td class="text-nowrap">
              <?php if (is_admin()): ?>
              <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Actions"><i class="bi bi-three-dots"></i></button>
                <ul class="dropdown-menu dropdown-menu-end">
                  <li><a class="dropdown-item" href="edit.php?id=<?= (int)$c['id'] ?>"><i class="bi bi-pencil me-2"></i>Edit</a></li>
                  <li>
                    <form method="POST" action="delete.php" onsubmit="return confirm('Delete this course?');">
                      <?= csrf_field() ?>
                      <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                      <button type="submit" class="dropdown-item text-danger"><i class="bi bi-trash me-2"></i>Delete</button>
                    </form>
                  </li>
                </ul>
              </div>
              <?php else: ?><span class="text-muted">View only</span><?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
  </div>
</div>
<script>$(function(){ $('#coursesTable').DataTable({ language: { search: '', searchPlaceholder: 'Search course details...' } }); });</script>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
