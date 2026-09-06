<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

$role = current_user_role();
$uid  = current_user_id();

// Base WHERE clause: counselors only see their own leads
$leadWhere = '';
$params = [];
if (is_counselor()) {
    $leadWhere = 'WHERE assigned_to = ?';
    $params[] = $uid;
}

// KPI: total leads
$stmt = $pdo->prepare("SELECT COUNT(*) FROM leads $leadWhere");
$stmt->execute($params);
$totalLeads = $stmt->fetchColumn();

// KPI: converted leads
$stmt = $pdo->prepare("SELECT COUNT(*) FROM leads " . ($leadWhere ? $leadWhere . " AND status='converted'" : "WHERE status='converted'"));
$stmt->execute($params);
$converted = $stmt->fetchColumn();

// KPI: today's follow-ups
$fuWhere = "WHERE DATE(f.follow_up_date) = CURDATE() AND f.status='pending'";
$fuParams = [];
if (is_counselor()) {
    $fuWhere .= " AND l.assigned_to = ?";
    $fuParams[] = $uid;
}
$stmt = $pdo->prepare("SELECT COUNT(*) FROM follow_ups f JOIN leads l ON l.id=f.lead_id $fuWhere");
$stmt->execute($fuParams);
$todayFollowUps = $stmt->fetchColumn();
$todayFollowupSql = "SELECT f.follow_up_date, f.remarks, l.name AS lead_name, l.phone, u.name AS counselor_name
  FROM follow_ups f
  JOIN leads l ON l.id = f.lead_id
  LEFT JOIN users u ON u.id = l.assigned_to
  WHERE DATE(f.follow_up_date) = CURDATE() AND f.status = 'pending'";
$todayFollowupParams = [];
if (is_counselor()) {
    $todayFollowupSql .= ' AND l.assigned_to = ?';
    $todayFollowupParams[] = $uid;
}
$todayFollowupSql .= ' ORDER BY f.follow_up_date LIMIT 4';
$todayFollowupStmt = $pdo->prepare($todayFollowupSql);
$todayFollowupStmt->execute($todayFollowupParams);
$todayFollowupRows = $todayFollowupStmt->fetchAll();

// KPI: total revenue collected
$revWhere = '';
$revParams = [];
if (is_counselor()) {
    $revWhere = "JOIN admissions a ON a.id = p.admission_id WHERE a.counselor_id = ?";
    $revParams[] = $uid;
}
$stmt = $pdo->prepare("SELECT COALESCE(SUM(p.amount),0) FROM payments p $revWhere");
$stmt->execute($revParams);
$totalRevenue = $stmt->fetchColumn();

// Payment summary: counselors see only their assigned admissions.
$paymentScope = '';
$paymentParams = [];
if (is_counselor()) {
  $paymentScope = 'WHERE a.counselor_id = ?';
  $paymentParams[] = $uid;
}
$stmt = $pdo->prepare("SELECT COUNT(p.id), COALESCE(SUM(p.amount), 0)
  FROM payments p JOIN admissions a ON a.id = p.admission_id $paymentScope");
$stmt->execute($paymentParams);
$paymentSummary = $stmt->fetch(PDO::FETCH_NUM);
$paymentCount = (int)$paymentSummary[0];
$totalPayments = (float)$paymentSummary[1];

$stmt = $pdo->prepare("SELECT COUNT(*), COALESCE(SUM(balance), 0) FROM (
  SELECT a.id, (a.total_fee - a.discount - COALESCE(SUM(p.amount), 0)) AS balance
  FROM admissions a LEFT JOIN payments p ON p.admission_id = a.id
  $paymentScope GROUP BY a.id, a.total_fee, a.discount
) due_summary WHERE balance > 0");
$stmt->execute($paymentParams);
$dueSummary = $stmt->fetch(PDO::FETCH_NUM);
$dueAdmissions = (int)$dueSummary[0];
$totalDue = (float)$dueSummary[1];

$monthlyPaymentLabels = [];
$monthlyPaymentValues = [];
$monthStart = new DateTime('first day of -11 months');
$monthStart->setTime(0, 0, 0);
for ($month = 0; $month < 12; $month++) {
  $monthlyPaymentLabels[] = $monthStart->format('M Y');
  $monthlyPaymentValues[$monthStart->format('Y-m')] = 0;
  $monthStart->modify('+1 month');
}
$chartStart = new DateTime('first day of -11 months');
$chartStart->setTime(0, 0, 0);
$monthlyPaymentScope = is_counselor() ? 'AND a.counselor_id = ?' : '';
$monthlyStmt = $pdo->prepare("SELECT DATE_FORMAT(p.payment_date, '%Y-%m') AS payment_month, COALESCE(SUM(p.amount), 0) AS total
  FROM payments p JOIN admissions a ON a.id = p.admission_id
  WHERE p.payment_date >= ? $monthlyPaymentScope
  GROUP BY payment_month ORDER BY payment_month");
$monthlyStmt->execute(array_merge([$chartStart->format('Y-m-d')], $paymentParams));
foreach ($monthlyStmt->fetchAll() as $monthlyPayment) {
  if (array_key_exists($monthlyPayment['payment_month'], $monthlyPaymentValues)) {
    $monthlyPaymentValues[$monthlyPayment['payment_month']] = (float)$monthlyPayment['total'];
  }
}
$monthlyPaymentValues = array_values($monthlyPaymentValues);

// Chart data: leads by source
$stmt = $pdo->prepare("SELECT source, COUNT(*) c FROM leads $leadWhere GROUP BY source");
$stmt->execute($params);
$sourceData = $stmt->fetchAll();

// Chart data: leads by status
$stmt = $pdo->prepare("SELECT status, COUNT(*) c FROM leads $leadWhere GROUP BY status");
$stmt->execute($params);
$statusData = $stmt->fetchAll();

$monthlyLeadLabels = [];
$monthlyLeadValues = [];
$leadMonthStart = new DateTime('first day of -11 months');
$leadMonthStart->setTime(0, 0, 0);
for ($month = 0; $month < 12; $month++) {
  $monthlyLeadLabels[] = $leadMonthStart->format('M Y');
  $monthlyLeadValues[$leadMonthStart->format('Y-m')] = 0;
  $leadMonthStart->modify('+1 month');
}
$leadChartStart = new DateTime('first day of -11 months');
$leadChartStart->setTime(0, 0, 0);
$monthlyLeadScope = is_counselor() ? 'AND assigned_to = ?' : '';
$monthlyLeadStmt = $pdo->prepare("SELECT DATE_FORMAT(created_at, '%Y-%m') AS lead_month, COUNT(*) AS total
  FROM leads WHERE created_at >= ? $monthlyLeadScope
  GROUP BY lead_month ORDER BY lead_month");
$monthlyLeadStmt->execute(array_merge([$leadChartStart->format('Y-m-d')], is_counselor() ? [$uid] : []));
foreach ($monthlyLeadStmt->fetchAll() as $monthlyLead) {
  if (array_key_exists($monthlyLead['lead_month'], $monthlyLeadValues)) {
    $monthlyLeadValues[$monthlyLead['lead_month']] = (int)$monthlyLead['total'];
  }
}
$monthlyLeadValues = array_values($monthlyLeadValues);

$myAdmissions = 0;
$monthlyAdmissions = 0;
$monthlyTarget = 0;
$recentAdmissions = [];
$recentPayments = [];
$duePayments = [];
if (is_counselor()) {
  $stmt = $pdo->prepare("SELECT COUNT(*) FROM admissions WHERE counselor_id = ?");
  $stmt->execute([$uid]);
  $myAdmissions = (int)$stmt->fetchColumn();

  $stmt = $pdo->prepare("SELECT COUNT(*) FROM admissions WHERE counselor_id = ? AND admission_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')");
  $stmt->execute([$uid]);
  $monthlyAdmissions = (int)$stmt->fetchColumn();

  $stmt = $pdo->prepare("SELECT monthly_target FROM users WHERE id = ?");
  $stmt->execute([$uid]);
  $monthlyTarget = (int)$stmt->fetchColumn();

  $stmt = $pdo->prepare("SELECT a.admission_code, a.student_name, a.admission_date, c.name AS course_name
    FROM admissions a
    LEFT JOIN courses c ON c.id = a.course_id
    WHERE a.counselor_id = ? ORDER BY a.created_at DESC LIMIT 5");
  $stmt->execute([$uid]);
  $recentAdmissions = $stmt->fetchAll();

  $stmt = $pdo->prepare("SELECT p.receipt_no, p.amount, p.payment_date, a.student_name, a.admission_code
    FROM payments p JOIN admissions a ON a.id = p.admission_id
    WHERE a.counselor_id = ? ORDER BY p.payment_date DESC, p.id DESC LIMIT 5");
  $stmt->execute([$uid]);
  $recentPayments = $stmt->fetchAll();

  $stmt = $pdo->prepare("SELECT a.id, a.admission_code, a.student_name, a.phone, c.name AS course_name,
    (a.total_fee - a.discount - COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.admission_id = a.id), 0)) AS due
    FROM admissions a LEFT JOIN courses c ON c.id = a.course_id
    WHERE a.counselor_id = ? AND (a.total_fee - a.discount - COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.admission_id = a.id), 0)) > 0
    ORDER BY due DESC LIMIT 5");
  $stmt->execute([$uid]);
  $duePayments = $stmt->fetchAll();
}

// Counselor performance table (admin/manager only)
$counselorPerf = [];
$counselorFinance = [];
if (is_admin() || is_manager()) {
    $counselorPerf = $pdo->query("
        SELECT u.name,
            COUNT(l.id) AS total_leads,
            SUM(CASE WHEN l.status='converted' THEN 1 ELSE 0 END) AS converted
        FROM users u
        LEFT JOIN leads l ON l.assigned_to = u.id
        WHERE u.role='counselor'
        GROUP BY u.id, u.name
        ORDER BY converted DESC
    ")->fetchAll();

    $counselorFinance = $pdo->query("SELECT u.name,
        COALESCE(payment_totals.paid, 0) AS paid,
        COALESCE(due_totals.due, 0) AS due,
        COALESCE(due_totals.due_admissions, 0) AS due_admissions,
        COALESCE(admission_totals.admissions, 0) AS admissions
      FROM users u
      LEFT JOIN (
        SELECT a.counselor_id, SUM(p.amount) AS paid
        FROM payments p JOIN admissions a ON a.id = p.admission_id
        GROUP BY a.counselor_id
      ) payment_totals ON payment_totals.counselor_id = u.id
      LEFT JOIN (
        SELECT a.counselor_id,
          COUNT(CASE WHEN balance > 0 THEN 1 END) AS due_admissions,
          SUM(CASE WHEN balance > 0 THEN balance ELSE 0 END) AS due
        FROM (
          SELECT a.id, a.counselor_id,
            a.total_fee - a.discount - COALESCE(SUM(p.amount), 0) AS balance
          FROM admissions a LEFT JOIN payments p ON p.admission_id = a.id
          GROUP BY a.id, a.counselor_id, a.total_fee, a.discount
        ) a
        GROUP BY a.counselor_id
      ) due_totals ON due_totals.counselor_id = u.id
      LEFT JOIN (
        SELECT counselor_id, COUNT(*) AS admissions
        FROM admissions GROUP BY counselor_id
      ) admission_totals ON admission_totals.counselor_id = u.id
      WHERE u.role = 'counselor'
      ORDER BY u.name")->fetchAll();
}

$hour = (int)date('G');
$greeting = $hour < 12 ? 'Good Morning' : ($hour < 17 ? 'Good Afternoon' : 'Good Evening');
$pageTitle = 'Dashboard';
require __DIR__ . '/includes/header.php';
?>

<div class="dashboard-container">
<div class="dashboard-hero mb-4">
  <div>
    <h4><?= e($greeting) ?>, <span><?= e($headerUser['name'] ?? $_SESSION['user_name'] ?? '') ?></span> <span aria-hidden="true">&#128075;</span></h4>
    <p><i class="bi bi-geo-alt-fill"></i> DMP AI Digital Institute <span class="dashboard-date">&bull; <?= e(date('l, d M Y')) ?></span></p>
  </div>
  <a href="<?= BASE_URL ?>/modules/leads/list.php" class="dashboard-hero-action"><i class="bi bi-arrow-up-right"></i> View leads</a>
</div>

<div class="dashboard-section-heading">
  <div><span class="dashboard-section-kicker"><i class="bi bi-clock"></i> Today</span><h5>Today's follow-ups</h5></div>
  <a href="<?= BASE_URL ?>/modules/leads/list.php" class="dashboard-section-link">View all <i class="bi bi-chevron-right"></i></a>
</div>
<div class="dashboard-schedule-panel mb-4">
  <?php if ($todayFollowupRows): ?>
    <?php foreach ($todayFollowupRows as $followup): ?>
    <a class="dashboard-schedule-item" href="<?= BASE_URL ?>/modules/leads/list.php">
      <span class="schedule-icon"><i class="bi bi-person-lines-fill"></i></span>
      <span class="schedule-copy"><strong><?= e($followup['lead_name']) ?></strong><small><?= e($followup['remarks'] ?: 'Scheduled lead follow-up') ?><?php if (!is_counselor() && $followup['counselor_name']): ?> &bull; <?= e($followup['counselor_name']) ?><?php endif; ?></small></span>
      <span class="schedule-time"><?= e(date('h:i A', strtotime($followup['follow_up_date']))) ?><small><?= e($followup['phone']) ?></small></span>
    </a>
    <?php endforeach; ?>
  <?php else: ?>
    <div class="dashboard-empty-state"><i class="bi bi-calendar2-check"></i><strong>No follow-ups scheduled for today</strong><span>Your next lead conversations will appear here.</span></div>
  <?php endif; ?>
</div>

<?php if (is_counselor()): ?>
<div class="counselor-progress card-stat mb-4">
  <div class="progress-heading">
    <div><span class="chart-kicker"><i class="bi bi-person-check-fill"></i> My performance</span><h5>Monthly progress</h5></div>
    <a href="<?= BASE_URL ?>/modules/leads/list.php" class="progress-action">View my leads <i class="bi bi-arrow-up-right"></i></a>
  </div>
  <div class="progress-stats">
    <div><span>Assigned leads</span><strong><?= (int)$totalLeads ?></strong></div>
    <div><span>Converted leads</span><strong><?= (int)$converted ?></strong></div>
    <div><span>This month's admissions</span><strong><?= $monthlyAdmissions ?></strong></div>
    <div><span>Admission target</span><strong><?= $monthlyTarget ?: '—' ?></strong></div>
  </div>
  <?php if ($monthlyTarget > 0): ?>
  <div class="target-track"><span style="width: <?= min(100, round(($monthlyAdmissions / $monthlyTarget) * 100)) ?>%"></span></div>
  <small class="target-caption"><?= min(100, round(($monthlyAdmissions / $monthlyTarget) * 100)) ?>% of monthly target completed</small>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="row g-3 mb-4">
  <div class="col-12 col-sm-6 col-xl-3">
    <div class="card card-stat stat-card stat-card-leads p-3">
      <span class="stat-icon"><i class="bi bi-person-lines-fill"></i></span>
      <span class="text-muted small">Total Leads</span>
      <h3><?= (int)$totalLeads ?></h3>
    </div>
  </div>
  <div class="col-12 col-sm-6 col-xl-3">
    <div class="card card-stat stat-card stat-card-success p-3">
      <span class="stat-icon"><i class="bi bi-check2-circle"></i></span>
      <span class="text-muted small">Converted / Admissions</span>
      <h3 class="text-success"><?= (int)$converted ?></h3>
    </div>
  </div>
  <div class="col-12 col-sm-6 col-xl-3">
    <div class="card card-stat stat-card stat-card-warning p-3">
      <span class="stat-icon"><i class="bi bi-clock-history"></i></span>
      <span class="text-muted small">Today's Follow-ups</span>
      <h3 class="text-warning"><?= (int)$todayFollowUps ?></h3>
    </div>
  </div>
  <div class="col-12 col-sm-6 col-xl-3">
    <div class="card card-stat stat-card stat-card-primary p-3">
      <span class="stat-icon"><i class="bi bi-wallet2"></i></span>
      <span class="text-muted small"><?= is_counselor() ? 'All Payments' : 'Revenue Collected' ?></span>
      <h3 class="text-primary">₹<?= money($totalRevenue) ?></h3>
      <small class="text-muted"><?= $paymentCount ?> payment<?= $paymentCount === 1 ? '' : 's' ?> recorded</small>
    </div>
  </div>
  <div class="col-12 col-sm-6 col-xl-3">
    <div class="card card-stat stat-card stat-card-danger p-3">
      <span class="stat-icon"><i class="bi bi-exclamation-circle"></i></span>
      <span class="text-muted small">Total Due</span>
      <h3 class="text-danger">₹<?= money($totalDue) ?></h3>
      <small class="text-muted"><?= $dueAdmissions ?> admission<?= $dueAdmissions === 1 ? '' : 's' ?> pending</small>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-12 col-xl-6">
    <div class="card card-stat chart-card">
      <div class="chart-card-header">
        <div>
          <span class="chart-kicker"><i class="bi bi-bullseye"></i> Acquisition</span>
          <h6>Leads by Source</h6>
        </div>
        <span class="chart-total"><?= (int)array_sum(array_map('intval', array_column($sourceData, 'c'))) ?><small> leads</small></span>
      </div>
      <div class="chart-shell"><canvas id="sourceChart"></canvas></div>
    </div>
  </div>
  <div class="col-12 col-xl-6">
    <div class="card card-stat chart-card">
      <div class="chart-card-header">
        <div>
          <span class="chart-kicker"><i class="bi bi-bar-chart-line-fill"></i> Pipeline health</span>
          <h6>Leads by Status</h6>
        </div>
        <span class="chart-total"><?= (int)array_sum(array_map('intval', array_column($statusData, 'c'))) ?><small> leads</small></span>
      </div>
      <div class="chart-shell"><canvas id="statusChart"></canvas></div>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-12 col-xl-6">
    <div class="card card-stat chart-card chart-card-sm">
      <div class="chart-card-header">
        <div><span class="chart-kicker"><i class="bi bi-calendar3"></i> Collection trend</span><h6>Month-wise Payments</h6></div>
        <span class="chart-total">₹<?= money($totalRevenue) ?><small> total</small></span>
      </div>
      <div class="chart-shell"><canvas id="monthlyPaymentsChart"></canvas></div>
    </div>
  </div>
  <div class="col-12 col-xl-6">
    <div class="card card-stat chart-card chart-card-sm">
      <div class="chart-card-header">
        <div><span class="chart-kicker"><i class="bi bi-people-fill"></i> Lead activity</span><h6>Month-wise Leads</h6></div>
        <span class="chart-total"><?= (int)$totalLeads ?><small> total leads</small></span>
      </div>
      <div class="chart-shell"><canvas id="monthlyLeadsChart"></canvas></div>
    </div>
  </div>
</div>

<?php if (is_admin() || is_manager()): ?>
<div class="card card-stat p-3 mb-4">
  <h6>Counselor Performance</h6>
  <div class="table-responsive"><table class="table table-sm table-hover mt-2">
    <thead><tr><th>Counselor</th><th>Total Leads</th><th>Converted</th><th>Conversion %</th></tr></thead>
    <tbody>
    <?php foreach ($counselorPerf as $c): $rate = $c['total_leads'] > 0 ? round($c['converted']/$c['total_leads']*100,1) : 0; ?>
      <tr>
        <td><?= e($c['name']) ?></td>
        <td><?= (int)$c['total_leads'] ?></td>
        <td><?= (int)$c['converted'] ?></td>
        <td><?= $rate ?>%</td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<div class="card card-stat p-3 mb-4">
  <div class="d-flex justify-content-between align-items-center"><h6 class="mb-0">Counselor Payment &amp; Due Summary</h6><a href="<?= BASE_URL ?>/modules/payments/list.php" class="progress-action">View all payments <i class="bi bi-arrow-up-right"></i></a></div>
  <div class="table-responsive"><table class="table table-sm table-hover mt-2 mb-0"><thead><tr><th>Counselor</th><th>Admissions</th><th>Payments Collected</th><th>Due Admissions</th><th>Due Amount</th></tr></thead><tbody><?php foreach ($counselorFinance as $finance): ?><tr><td><?= e($finance['name']) ?></td><td><?= (int)$finance['admissions'] ?></td><td class="text-success">₹<?= money($finance['paid']) ?></td><td><?= (int)$finance['due_admissions'] ?></td><td class="text-danger fw-bold">₹<?= money($finance['due']) ?></td></tr><?php endforeach; ?><?php if (!$counselorFinance): ?><tr><td colspan="5" class="text-muted">No counselor records yet.</td></tr><?php endif; ?></tbody></table></div>
</div>
<?php endif; ?>

<?php if (is_counselor()): ?>
<div class="card card-stat p-3 mb-4">
  <div class="d-flex justify-content-between align-items-center mb-2"><h6 class="mb-0">Recent admissions</h6><span class="text-muted small"><?= $myAdmissions ?> total</span></div>
  <?php if ($recentAdmissions): ?>
  <div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0"><thead><tr><th>Admission</th><th>Student</th><th>Course</th><th>Date</th></tr></thead><tbody>
    <?php foreach ($recentAdmissions as $admission): ?><tr><td><?= e($admission['admission_code']) ?></td><td><?= e($admission['student_name']) ?></td><td><?= e($admission['course_name']) ?></td><td><?= e(date('d M Y', strtotime($admission['admission_date']))) ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
  <?php else: ?><p class="text-muted small mb-0">No admissions have been recorded for your account yet.</p><?php endif; ?>
</div>
<div class="row g-3 mb-4">
  <div class="col-lg-6"><div class="card card-stat p-3"><div class="d-flex justify-content-between align-items-center mb-2"><h6 class="mb-0">Recent payments</h6><a href="<?= BASE_URL ?>/modules/payments/list.php" class="progress-action">View all <i class="bi bi-arrow-up-right"></i></a></div><?php if ($recentPayments): ?><div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0"><thead><tr><th>Receipt</th><th>Student</th><th>Amount</th><th>Date</th></tr></thead><tbody><?php foreach ($recentPayments as $payment): ?><tr><td><?= e($payment['receipt_no']) ?></td><td><?= e($payment['student_name']) ?><small class="d-block text-muted"><?= e($payment['admission_code']) ?></small></td><td class="text-success">₹<?= money($payment['amount']) ?></td><td><?= e(date('d M Y', strtotime($payment['payment_date']))) ?></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><p class="text-muted small mb-0">No payments recorded yet.</p><?php endif; ?></div></div>
  <div class="col-lg-6"><div class="card card-stat p-3"><div class="d-flex justify-content-between align-items-center mb-2"><h6 class="mb-0">Due payments</h6><a href="<?= BASE_URL ?>/modules/payments/list.php#due" class="progress-action">View report <i class="bi bi-arrow-up-right"></i></a></div><?php if ($duePayments): ?><div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0"><thead><tr><th>Student</th><th>Course</th><th>Due</th><th></th></tr></thead><tbody><?php foreach ($duePayments as $duePayment): ?><tr><td><?= e($duePayment['student_name']) ?><small class="d-block text-muted"><?= e($duePayment['admission_code']) ?></small></td><td><?= e($duePayment['course_name']) ?></td><td class="text-danger fw-bold">₹<?= money($duePayment['due']) ?></td><td><a href="<?= BASE_URL ?>/modules/payments/add.php?admission_id=<?= (int)$duePayment['id'] ?>" class="btn btn-sm btn-success">Collect</a></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><p class="text-muted small mb-0">No pending dues.</p><?php endif; ?></div></div>
</div>
<?php endif; ?>

<script>
const sourceCtx = document.getElementById('sourceChart');
new Chart(sourceCtx, {
  type: 'doughnut',
  data: {
    labels: <?= json_encode(array_column($sourceData, 'source')) ?>,
    datasets: [{ data: <?= json_encode(array_map('intval', array_column($sourceData, 'c'))) ?>,
      backgroundColor: ['#202768', '#f47a3c', '#ffca28', '#3d8b9b', '#6f63a8', '#5e9b67', '#d95d8a'],
      borderColor: '#ffffff', borderWidth: 4, hoverOffset: 8 }]
  },
  options: {
    cutout: '68%',
    maintainAspectRatio: false,
    plugins: {
      legend: { position: 'bottom', labels: { usePointStyle: true, pointStyle: 'circle', padding: 18, color: '#52606d', font: { family: 'DM Sans' } } },
      tooltip: { padding: 12, backgroundColor: '#17212b', displayColors: true, callbacks: { label: context => ` ${context.label}: ${context.raw} leads` } }
    }
  }
});
const statusCtx = document.getElementById('statusChart');
new Chart(statusCtx, {
  type: 'bar',
  data: {
    labels: <?= json_encode(array_column($statusData, 'status')) ?>,
    datasets: [{ label: 'Leads', data: <?= json_encode(array_map('intval', array_column($statusData, 'c'))) ?>,
      backgroundColor: ['#202768', '#ffca28', '#f47a3c', '#3d8b9b', '#6f63a8', '#d95d8a'],
      borderRadius: 7, borderSkipped: false, barThickness: 18 }]
  },
  options: {
    indexAxis: 'y', maintainAspectRatio: false,
    scales: {
      x: { beginAtZero: true, ticks: { precision: 0, color: '#8796a3' }, grid: { color: '#edf1f4', drawBorder: false } },
      y: { ticks: { color: '#52606d', font: { family: 'DM Sans', weight: '600' } }, grid: { display: false } }
    },
    plugins: {
      legend: { display: false },
      tooltip: { padding: 12, backgroundColor: '#17212b', callbacks: { label: context => ` ${context.raw} leads` } }
    }
  }
});
const monthlyPaymentsCtx = document.getElementById('monthlyPaymentsChart');
new Chart(monthlyPaymentsCtx, {
  type: 'bar',
  data: {
    labels: <?= json_encode($monthlyPaymentLabels) ?>,
    datasets: [{ label: 'Payments collected', data: <?= json_encode($monthlyPaymentValues) ?>,
      backgroundColor: '#f47a3c', borderRadius: 7, borderSkipped: false, barThickness: 22 }]
  },
  options: {
    maintainAspectRatio: false,
    scales: {
      x: { ticks: { color: '#52606d' }, grid: { display: false } },
      y: { beginAtZero: true, ticks: { color: '#8796a3', callback: value => '₹' + Number(value).toLocaleString('en-IN') }, grid: { color: '#edf1f4', drawBorder: false } }
    },
    plugins: {
      legend: { display: false },
      tooltip: { padding: 12, backgroundColor: '#17212b', callbacks: { label: context => ` ₹${Number(context.raw).toLocaleString('en-IN')}` } }
    }
  }
});
const monthlyLeadsCtx = document.getElementById('monthlyLeadsChart');
new Chart(monthlyLeadsCtx, {
  type: 'line',
  data: {
    labels: <?= json_encode($monthlyLeadLabels) ?>,
    datasets: [{ label: 'Leads', data: <?= json_encode($monthlyLeadValues) ?>,
      borderColor: '#202768', backgroundColor: 'rgba(32,39,104,.12)', fill: true, tension: .35, pointBackgroundColor: '#f47a3c', pointBorderColor: '#fff', pointBorderWidth: 2, pointRadius: 4 }]
  },
  options: {
    maintainAspectRatio: false,
    scales: {
      x: { ticks: { color: '#52606d' }, grid: { display: false } },
      y: { beginAtZero: true, ticks: { precision: 0, color: '#8796a3' }, grid: { color: '#edf1f4', drawBorder: false } }
    },
    plugins: { legend: { display: false }, tooltip: { padding: 12, backgroundColor: '#17212b', callbacks: { label: context => ` ${context.raw} leads` } } }
  }
});
</script>

</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
