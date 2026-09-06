<?php
// Expects $pageTitle to be set by the including page
require_once __DIR__ . '/auth.php';
require_login();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle ?? APP_NAME) ?> | <?= APP_NAME ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net@1.13.11/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-bs5@1.13.11/js/dataTables.bootstrap5.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-bs5@1.13.11/css/dataTables.bootstrap5.min.css">
<link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>
<?php $currentPage = basename($_SERVER['PHP_SELF']); ?>
<?php
$headerUserStmt = $pdo->prepare('SELECT name, role, phone, photo_path FROM users WHERE id = ? LIMIT 1');
$headerUserStmt->execute([current_user_id()]);
$headerUser = $headerUserStmt->fetch() ?: ['name' => $_SESSION['user_name'] ?? '', 'role' => $_SESSION['user_role'] ?? '', 'phone' => '', 'photo_path' => null];
?>
<aside class="app-sidebar offcanvas" id="appSidebar" tabindex="-1">
  <div class="sidebar-mobile-header">
    <span>Menu</span>
    <button class="sidebar-close" type="button" data-bs-dismiss="offcanvas" aria-label="Close navigation"><i class="bi bi-x-lg"></i></button>
  </div>
  <div class="sidebar-brand">
    <a href="<?= BASE_URL ?>/dashboard.php"><img class="brand-logo" src="<?= BASE_URL ?>/assets/dmp-brand-logo.png" alt="DMP AI Digital Institute"></a>
  </div>
  <div class="sidebar-label">Workspace</div>
  <nav class="sidebar-nav" aria-label="Main navigation">
      <a class="sidebar-link <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>/dashboard.php"><i class="bi bi-grid-1x2-fill"></i><span>Dashboard</span></a>
      <a class="sidebar-link <?= in_array($currentPage, ['list.php', 'add.php', 'view.php', 'import.php', 'webhook.php'], true) && str_contains($_SERVER['PHP_SELF'], '/leads/') ? 'active' : '' ?>" href="<?= BASE_URL ?>/modules/leads/list.php"><i class="bi bi-person-lines-fill"></i><span>Leads</span></a>
      <?php if (is_admin() || is_manager()): ?>
      <a class="sidebar-link <?= str_contains($_SERVER['PHP_SELF'], '/counselors/') ? 'active' : '' ?>" href="<?= BASE_URL ?>/modules/counselors/list.php"><i class="bi bi-people-fill"></i><span>Counselors</span></a>
      <a class="sidebar-link <?= str_contains($_SERVER['PHP_SELF'], '/courses/') ? 'active' : '' ?>" href="<?= BASE_URL ?>/modules/courses/list.php"><i class="bi bi-journal-bookmark"></i><span>Courses</span></a>
      <?php endif; ?>
      <a class="sidebar-link <?= str_contains($_SERVER['PHP_SELF'], '/admissions/') ? 'active' : '' ?>" href="<?= BASE_URL ?>/modules/admissions/list.php"><i class="bi bi-mortarboard-fill"></i><span>Admissions</span></a>
      <a class="sidebar-link <?= str_contains($_SERVER['PHP_SELF'], '/payments/') ? 'active' : '' ?>" href="<?= BASE_URL ?>/modules/payments/list.php"><i class="bi bi-cash-coin"></i><span>Payments</span></a>
      <a class="sidebar-link <?= $currentPage === 'settings.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>/settings.php"><i class="bi bi-gear-fill"></i><span>Settings</span></a>
  </nav>
  <div class="sidebar-footer">
    <button class="theme-toggle" type="button" aria-label="Switch to dark theme" title="Switch to dark theme">
      <i class="bi bi-moon"></i><span>Dark mode</span>
    </button>
    <div class="dropdown profile-menu-wrapper">
      <button class="user-summary profile-menu-trigger dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
        <?php if (!empty($headerUser['photo_path'])): ?><img class="user-avatar-image" src="<?= BASE_URL . '/' . e($headerUser['photo_path']) ?>" alt=""><?php else: ?><span class="user-avatar-fallback"><i class="bi bi-person-circle"></i></span><?php endif; ?>
        <div class="user-summary-copy"><strong><?= e($headerUser['name']) ?></strong><small><?= e($headerUser['phone'] ?: ucfirst($headerUser['role'])) ?></small></div><i class="bi bi-chevron-down user-summary-chevron"></i>
      </button>
      <div class="dropdown-menu dropdown-menu-dark profile-menu">
        <a class="dropdown-item profile-menu-item profile-menu-signout" href="<?= BASE_URL ?>/logout.php"><i class="bi bi-box-arrow-right"></i><span>Sign Out</span></a>
      </div>
    </div>
  </div>
</aside>
<button class="mobile-menu-toggle" type="button" data-bs-toggle="offcanvas" data-bs-target="#appSidebar" aria-controls="appSidebar" aria-label="Open navigation"><i class="bi bi-list"></i></button>
<main class="app-main">
<div class="container-fluid page-content">
<?php if (!empty($_SESSION['flash_success'])): ?>
  <div class="alert alert-success alert-dismissible fade show"><?= e($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?><button class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if (!empty($_SESSION['flash_error'])): ?>
  <div class="alert alert-danger alert-dismissible fade show"><?= e($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?><button class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
