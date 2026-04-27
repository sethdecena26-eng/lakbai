<?php
// includes/topbar.php
$pageTitle   = $pageTitle ?? 'Dashboard';
$SFN         = $_SESSION['SFN'] ?? '';
$SLN         = $_SESSION['SLN'] ?? '';
$fullName    = trim($SFN . ' ' . $SLN) ?: 'User';
$initials    = strtoupper(substr($SFN, 0, 1) . substr($SLN, 0, 1));
$displayRole = ucfirst($_SESSION['role'] ?? 'Employee');

// Fetch low-stock count for notification
$_topbarProduct  = new Product();
$lowStockCount   = $_topbarProduct->countLowStock();
?>
<div class="main-wrapper" id="mainWrapper">
  <header class="topbar">
    <div class="topbar-left">
      <button class="topbar-toggle" id="sidebarToggle" title="Toggle Sidebar">☰</button>
      <div class="page-title"><?= e($pageTitle) ?></div>
    </div>

    <div class="topbar-right">
      <!-- Notification -->
      <div class="dropdown-wrapper">
        <button class="notif-btn" id="notifBtn" title="Notifications">
          🔔
          <?php if ($lowStockCount > 0): ?>
            <span class="notif-badge"></span>
          <?php endif; ?>
        </button>
        <div class="notif-dropdown" id="notifDropdown">
          <div class="notif-header">🔔 Notifications</div>
          <?php if ($lowStockCount > 0): ?>
            <div class="notif-item" onclick="window.location='<?= APP_URL ?>/modules/inventory/index.php'">
              <div class="notif-title">⚠️ Low Stock Alert</div>
              <div class="notif-desc"><?= $lowStockCount ?> product(s) are below reorder level</div>
            </div>
          <?php else: ?>
            <div class="notif-item">
              <div class="notif-title">All Good!</div>
              <div class="notif-desc">No low stock alerts at this time.</div>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Profile -->
      <div class="dropdown-wrapper">
        <button class="profile-btn" id="profileBtn">
          <div class="avatar"><?= e($initials) ?></div>
          <span class="profile-name"><?= e($fullName) ?></span>
          ▾
        </button>
        <div class="profile-dropdown" id="profileDropdown">
          <div class="profile-dropdown-header">
            <div class="p-name"><?= e($fullName) ?></div>
            <div class="p-role"><?= e($displayRole) ?></div>
          </div>
          <a href="<?= APP_URL ?>/logout.php" class="profile-dropdown-item danger">
             Logout
          </a>
        </div>
      </div>
    </div>
  </header>

  <main class="page-content">
