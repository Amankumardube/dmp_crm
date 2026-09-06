<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT l.*, c.name AS course_name, u.name AS counselor_name
    FROM leads l
    LEFT JOIN courses c ON c.id = l.course_id
    LEFT JOIN users u ON u.id = l.assigned_to
    WHERE l.id = ?
");
$stmt->execute([$id]);
$lead = $stmt->fetch();

if (!$lead) { die('Lead not found.'); }

// Counselors can only view their own leads
if (is_counselor() && (int)$lead['assigned_to'] !== current_user_id()) {
    http_response_code(403);
    die('You do not have access to this lead.');
}

$courses = $pdo->query("SELECT id, name FROM courses WHERE status='active' ORDER BY name")->fetchAll();
$counselors = $pdo->query("SELECT id, name FROM users WHERE role='counselor' AND status='active' ORDER BY name")->fetchAll();

// ---- Handle POST actions ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_note') {
        $note = trim($_POST['note'] ?? '');
        if ($note !== '') {
            $stmt = $pdo->prepare("INSERT INTO lead_notes (lead_id, note, created_by) VALUES (?,?,?)");
            $stmt->execute([$id, $note, current_user_id()]);
        }
    } elseif ($action === 'change_status') {
        $newStatus = $_POST['status'] ?? $lead['status'];
        $stmt = $pdo->prepare("UPDATE leads SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $id]);
        $stmt = $pdo->prepare("INSERT INTO lead_notes (lead_id, note, created_by) VALUES (?,?,?)");
        $stmt->execute([$id, "Status changed to: $newStatus", current_user_id()]);
    } elseif ($action === 'assign' && !is_counselor()) {
        $newAssign = $_POST['assigned_to'] ?: null;
        $stmt = $pdo->prepare("UPDATE leads SET assigned_to = ? WHERE id = ?");
        $stmt->execute([$newAssign, $id]);
    } elseif ($action === 'add_followup') {
        $fuDate = $_POST['follow_up_date'] ?? '';
        $remarks = trim($_POST['remarks'] ?? '');
        if ($fuDate !== '') {
            $stmt = $pdo->prepare("INSERT INTO follow_ups (lead_id, follow_up_date, remarks, created_by) VALUES (?,?,?,?)");
            $stmt->execute([$id, $fuDate, $remarks, current_user_id()]);
        }
    }
    log_activity($pdo, "Updated lead #$id ($action)");
    header('Location: view.php?id=' . $id);
    exit;
}

// Notes timeline
$notes = $pdo->prepare("
    SELECT n.*, u.name AS author FROM lead_notes n
    LEFT JOIN users u ON u.id = n.created_by
    WHERE n.lead_id = ? ORDER BY n.created_at DESC
");
$notes->execute([$id]);
$notes = $notes->fetchAll();

// Follow-ups
$followups = $pdo->prepare("SELECT * FROM follow_ups WHERE lead_id = ? ORDER BY follow_up_date DESC");
$followups->execute([$id]);
$followups = $followups->fetchAll();

$pageTitle = 'Lead: ' . $lead['name'];
require __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4><?= e($lead['name']) ?> <small class="text-muted">#<?= $lead['id'] ?></small></h4>
  <?php if ($lead['status'] !== 'converted'): ?>
    <a href="../admissions/add.php?lead_id=<?= $lead['id'] ?>" class="btn btn-success btn-sm">
      <i class="bi bi-mortarboard-fill"></i> Convert to Admission
    </a>
  <?php else: ?>
    <span class="badge bg-dark p-2">Already Converted</span>
  <?php endif; ?>
</div>

<div class="row g-3">
  <div class="col-md-4">
    <div class="card card-stat p-3 mb-3">
      <h6>Lead Info</h6>
      <p class="mb-1"><strong>Phone:</strong> <?= e($lead['phone']) ?></p>
      <p class="mb-1"><strong>Email:</strong> <?= e($lead['email'] ?: '-') ?></p>
      <p class="mb-1"><strong>City:</strong> <?= e($lead['city'] ?: '-') ?></p>
      <p class="mb-1"><strong>Source:</strong> <?= e(ucfirst($lead['source'])) ?></p>
      <p class="mb-1"><strong>Course:</strong> <?= e($lead['course_name'] ?? '-') ?></p>
      <p class="mb-1"><strong>Counselor:</strong> <?= e($lead['counselor_name'] ?? 'Unassigned') ?></p>
      <p class="mb-0"><strong>Created:</strong> <?= e(date('d M Y, h:i A', strtotime($lead['created_at']))) ?></p>
    </div>

    <div class="card card-stat p-3 mb-3">
      <h6>Change Status</h6>
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="change_status">
        <select name="status" class="form-select mb-2">
          <?php foreach (['new','contacted','follow-up','interested','not-interested','converted','junk'] as $s): ?>
            <option value="<?= $s ?>" <?= $lead['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
          <?php endforeach; ?>
        </select>
        <button class="btn btn-sm btn-primary w-100">Update Status</button>
      </form>
    </div>

    <?php if (!is_counselor()): ?>
    <div class="card card-stat p-3 mb-3">
      <h6>Assign Counselor</h6>
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="assign">
        <select name="assigned_to" class="form-select mb-2">
          <option value="">-- Unassigned --</option>
          <?php foreach ($counselors as $c): ?>
            <option value="<?= $c['id'] ?>" <?= (int)$lead['assigned_to'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <button class="btn btn-sm btn-outline-primary w-100">Reassign</button>
      </form>
    </div>
    <?php endif; ?>
  </div>

  <div class="col-md-8">
    <div class="card card-stat p-3 mb-3">
      <h6>Schedule Follow-up</h6>
      <form method="POST" class="row g-2">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_followup">
        <div class="col-md-10">
          <input type="datetime-local" name="follow_up_date" class="form-control" value="<?= date('Y-m-d\TH:i') ?>" required>
        </div>
        <div class="col-md-2">
          <button class="btn btn-warning w-100">Add</button>
        </div>
      </form>
    </div>

    <div class="card card-stat p-3">
      <h6>Notes / Timeline</h6>
      <form method="POST" class="mb-3">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_note">
        <textarea name="note" class="form-control mb-2" rows="2" placeholder="Add a note or call remark..." required></textarea>
        <button class="btn btn-sm btn-primary">Add Note</button>
      </form>
      <ul class="list-group">
        <?php foreach ($notes as $n): ?>
          <li class="list-group-item">
            <div class="d-flex justify-content-between">
              <strong><?= e($n['author'] ?? 'System') ?></strong>
              <small class="text-muted"><?= e(date('d M Y, h:i A', strtotime($n['created_at']))) ?></small>
            </div>
            <div><?= nl2br(e($n['note'])) ?></div>
          </li>
        <?php endforeach; ?>
        <?php if (!$notes): ?><li class="list-group-item text-muted">No notes yet.</li><?php endif; ?>
      </ul>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
