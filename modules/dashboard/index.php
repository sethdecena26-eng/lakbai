<?php
require_once __DIR__ . '/../../config/app.php';
requireLogin();

$pageTitle   = 'Dashboard';
$currentPage = 'dashboard';

$product  = new Product();
$sale     = new Sale();
$supplier = new Supplier();
$orderModel = new OnlineOrder();

$totalSalesToday   = $sale->getTodayTotal();
$totalTransactions = $sale->getTodayCount();
$totalProducts     = $product->countAll();
$lowStockCount     = $product->countLowStock();
$totalSuppliers    = $supplier->countAll();
$recentSales       = $sale->getRecent(8);
$lowStockItems     = $product->getLowStock();
$orderCounts       = $orderModel->countByStatus();
$pendingOrders     = $orderCounts['pending'];
$confirmedOrders   = $orderCounts['confirmed'];

require_once __DIR__ . '/../../includes/head.php';
require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/../../includes/topbar.php';
?>

<div class="stat-grid">
  <div class="stat-card">
    <div class="stat-info">
      <div class="label">Sales Today</div>
      <div class="value text-mono">₱<?= number_format($totalSalesToday, 2) ?></div>
      <div class="sub"><?= $totalTransactions ?> transaction(s)</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-info">
      <div class="label">Total Products</div>
      <div class="value"><?= $totalProducts ?></div>
      <div class="sub"></div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-info">
      <div class="label">Low Stock Items</div>
      <div class="value"><?= $lowStockCount ?></div>
      <div class="sub"><?= $lowStockCount > 0 ? 'Needs restocking' : 'All levels OK' ?></div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-info">
      <div class="label">Suppliers</div>
      <div class="value"><?= $totalSuppliers ?></div>
      <div class="sub"></div>
    </div>
  </div>
  <div class="stat-card" style="<?= $pendingOrders > 0 ? 'border-color:var(--clr-warning)' : '' ?>">
    <div class="stat-info">
      <div class="label">Pending Online Orders</div>
      <div class="value" style="<?= $pendingOrders > 0 ? 'color:var(--clr-warning)' : '' ?>"><?= $pendingOrders ?></div>
      <div class="sub"><?= $confirmedOrders ?> confirmed / to ship</div>
    </div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 340px;gap:20px;">

  <!-- Recent Transactions -->
  <div class="card">
    <div class="card-header">
      <div>
        <div class="card-title">Recent Transactions</div>
        <div class="card-subtitle">Latest sales activity</div>
      </div>
      <a href="<?= APP_URL ?>/modules/reports/index.php" class="btn btn-ghost btn-sm">View All</a>
    </div>
    <div class="table-wrapper">
      <?php if (empty($recentSales)): ?>
      <?php else: ?>
        <table>
          <thead>
            <tr><th>#ID</th><th>Cashier</th><th>Amount</th><th>Method</th><th>Time</th></tr>
          </thead>
          <tbody>
            <?php foreach ($recentSales as $s): ?>
              <tr>
                <td><span class="badge badge-blue">#<?= str_pad($s['purchase_id'], 4, '0', STR_PAD_LEFT) ?></span></td>
                <td><?= e($s['cashier']) ?></td>
                <td class="text-mono" style="font-weight:700">₱<?= number_format($s['total_amount'], 2) ?></td>
                <td><span class="badge <?= $s['payment_method']==='cash' ? 'badge-green' : 'badge-blue' ?>"><?= ucfirst($s['payment_method'] ?? 'cash') ?></span></td>
                <td class="text-muted"><?= date('h:i A', strtotime($s['purchase_date'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>

  <!-- Low Stock -->
  <div class="card">
    <div class="card-header">
      <div>
        <div class="card-title"> Low Stock</div>
        <div class="card-subtitle">Items needing restock</div>
      </div>
    </div>
    <?php if (empty($lowStockItems)): ?>
      <div class="empty-state"><div class="icon"></div>All products are well stocked!</div>
    <?php else: ?>
      <div style="display:flex;flex-direction:column;gap:10px;">
        <?php foreach (array_slice($lowStockItems, 0, 8) as $item): ?>
          <div style="display:flex;align-items:center;justify-content:space-between;padding:10px;background:var(--clr-bg);border-radius:8px;">
            <div>
              <div style="font-size:.82rem;font-weight:600;"><?= e($item['product_name']) ?></div>
              <div style="font-size:.72rem;color:var(--clr-text-muted);"><?= e($item['category'] ?? 'Uncategorized') ?> · <?= e($item['size']) ?></div>
            </div>
            <div style="text-align:right;">
              <div style="font-size:.9rem;font-weight:700;color:<?= $item['quantity'] == 0 ? 'var(--clr-danger)' : 'var(--clr-warning)' ?>">
                <?= $item['quantity'] ?> left
              </div>
              <div style="font-size:.7rem;color:var(--clr-text-muted);">min: <?= $item['reorder_lvl'] ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
