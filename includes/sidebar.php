<?php
// includes/sidebar.php
$currentPage = $currentPage ?? '';
$role = $_SESSION['role'] ?? 'employee';

$navItems = [
  ['href' => 'dashboard',      'icon' => 'D',  'label' => 'Dashboard',      'roles' => ['admin','employee']],
  ['href' => 'pos',            'icon' => 'P',  'label' => 'POS / Sales',    'roles' => ['admin','employee']],
  ['href' => 'online_orders',  'icon' => 'O',  'label' => 'Online Orders',  'roles' => ['admin','employee']],
  ['href' => 'inventory',      'icon' => 'I',  'label' => 'Inventory',      'roles' => ['admin','employee']],
  ['href' => 'stock_in',       'icon' => 'SI', 'label' => 'Stock In',       'roles' => ['admin','employee']],
  ['href' => 'products',       'icon' => 'P',  'label' => 'Products',       'roles' => ['admin']],
  ['href' => 'suppliers',      'icon' => 'S',  'label' => 'Suppliers',      'roles' => ['admin']],
  ['href' => 'reports',        'icon' => 'R',  'label' => 'Reports',        'roles' => ['admin','employee']],
  ['href' => 'users',          'icon' => 'U',  'label' => 'Users',          'roles' => ['admin']],
  ['href' => 'archive',        'icon' => 'A',  'label' => 'Archive',        'roles' => ['admin']],
];

$SFN        = $_SESSION['SFN'] ?? '';
$SLN        = $_SESSION['SLN'] ?? '';
$fullName   = trim($SFN . ' ' . $SLN) ?: 'User';
$initials   = strtoupper(substr($SFN, 0, 1) . substr($SLN, 0, 1));
$displayRole = ucfirst($_SESSION['role'] ?? 'Employee');
?>
<aside class="sidebar" id="sidebar">
  <!-- Brand -->
  <div class="sidebar-brand">
    <div class="brand-icon">LS</div>
    <div class="brand-text">
      Lakbai Sakalay
      <span>Merch</span>
    </div>
  </div>

  <!-- Nav -->
  <nav class="sidebar-nav">
    <div class="nav-label">Main Menu</div>
    <?php foreach ($navItems as $item): ?>
      <?php if (in_array($role, $item['roles'])): ?>
        <a href="<?= APP_URL ?>/modules/<?= $item['href'] ?>/index.php"
           class="nav-item <?= $currentPage === $item['href'] ? 'active' : '' ?>">
          <div class="icon"><?= $item['icon'] ?></div>
          <span><?= e($item['label']) ?></span>
        </a>
      <?php endif; ?>
    <?php endforeach; ?>
  </nav>

  <!-- Footer -->
  <div class="sidebar-footer">
    <div class="sidebar-user">
      <div class="sidebar-avatar"><?= e($initials) ?></div>
      <div class="sidebar-user-info">
        <div class="name"><?= e($fullName) ?></div>
        <div class="role"><?= e($displayRole) ?></div>
      </div>
    </div>
  </div>
</aside>
