<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin','manager']);

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'counselor' LIMIT 1");
$stmt->execute([$id]);
$counselor = $stmt->fetch();
if (!$counselor) {
    http_response_code(404);
    die('Counselor not found.');
}

$stats = $pdo->prepare("SELECT
    (SELECT COUNT(*) FROM leads WHERE assigned_to = ?) AS total_leads,
    (SELECT COUNT(*) FROM leads WHERE assigned_to = ? AND status = 'converted') AS converted_leads,
    (SELECT COUNT(*) FROM admissions WHERE counselor_id = ?) AS admissions,
    (SELECT COALESCE(SUM(p.amount), 0) FROM payments p JOIN admissions a ON a.id = p.admission_id WHERE a.counselor_id = ?) AS revenue");
$stats->execute([$id, $id, $id, $id]);
$stats = $stats->fetch();

$recent = $pdo->prepare("SELECT name, phone, status, created_at FROM leads WHERE assigned_to = ? ORDER BY created_at DESC LIMIT 5");
$recent->execute([$id]);
$recentLeads = $recent->fetchAll();

$pageTitle = 'Counselor Profile';
require __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div><span class="eyebrow">Team member</span><h4 class="mb-0">Counselor Profile</h4></div>
  <div>
    <?php if (is_admin()): ?>
      <a href="edit.php?id=<?= $id ?>" class="btn btn-primary btn-sm"><i class="bi bi-pencil"></i> Edit Details</a>
      <form method="POST" action="delete.php" class="d-inline" onsubmit="return confirm('Delete this counselor? Leads will become unassigned.');">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $id ?>">
        <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i> Delete</button>
      </form>
    <?php endif; ?>
    <a href="list.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back</a>
  </div>
</div>
<div class="row g-3">
  <div class="col-lg-4">
    <div class="card card-stat p-4 h-100">
      <div class="d-flex align-items-center gap-3 mb-4"><?php if (!empty($counselor['photo_path'])): ?><img class="account-avatar account-avatar-image mb-0" style="height:58px;width:58px;font-size:1.3rem;" src="<?= BASE_URL . '/' . e($counselor['photo_path']) ?>" alt="<?= e($counselor['name']) ?> profile picture"><?php else: ?><div class="account-avatar mb-0" style="height:58px;width:58px;font-size:1.3rem;"><?= e(strtoupper(substr($counselor['name'], 0, 1))) ?></div><?php endif; ?><div><h5 class="mb-1"><?= e($counselor['name']) ?></h5><span class="badge bg-<?= $counselor['status'] === 'active' ? 'success' : 'secondary' ?>"><?= e($counselor['status']) ?></span></div></div>
      <dl class="row mb-0 small"><dt class="col-5 text-muted">Email</dt><dd class="col-7 text-break"><?= e($counselor['email']) ?></dd><dt class="col-5 text-muted">Phone</dt><dd class="col-7"><?= e($counselor['phone'] ?: '-') ?></dd><dt class="col-5 text-muted">Target</dt><dd class="col-7"><?= (int)$counselor['monthly_target'] ?> admissions</dd><dt class="col-5 text-muted">Joined</dt><dd class="col-7"><?= e(date('d M Y', strtotime($counselor['created_at']))) ?></dd></dl>
    </div>
  </div>
  <div class="col-lg-8"><div class="row g-3"><div class="col-sm-6"><div class="card card-stat p-3"><span class="text-muted small">Assigned Leads</span><h3><?= (int)$stats['total_leads'] ?></h3></div></div><div class="col-sm-6"><div class="card card-stat p-3"><span class="text-muted small">Converted Leads</span><h3 class="text-success"><?= (int)$stats['converted_leads'] ?></h3></div></div><div class="col-sm-6"><div class="card card-stat p-3"><span class="text-muted small">Admissions</span><h3><?= (int)$stats['admissions'] ?></h3></div></div><div class="col-sm-6"><div class="card card-stat p-3"><span class="text-muted small">Revenue Collected</span><h3 class="text-primary">₹<?= money($stats['revenue']) ?></h3></div></div></div></div>
</div>
<div class="card card-stat p-3 mt-3"><h6>Recent Assigned Leads</h6><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Name</th><th>Phone</th><th>Status</th><th>Added</th></tr></thead><tbody><?php foreach ($recentLeads as $lead): ?><tr><td><?= e($lead['name']) ?></td><td><?= e($lead['phone']) ?></td><td><span class="badge bg-light text-dark"><?= e($lead['status']) ?></span></td><td><?= e(date('d M Y', strtotime($lead['created_at']))) ?></td></tr><?php endforeach; ?><?php if (!$recentLeads): ?><tr><td colspan="4" class="text-muted">No leads assigned yet.</td></tr><?php endif; ?></tbody></table></div></div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
