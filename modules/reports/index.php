<?php
require_once __DIR__ . '/../../config/app.php';
requireLogin();

$pageTitle   = 'Reports';
$currentPage = 'reports';

$saleModel  = new Sale();
$orderModel = new OnlineOrder();

$today = date('Y-m-d');
$from  = $_GET['from'] ?? $today;
$to    = $_GET['to']   ?? $today;
if ($from > $to) $from = $to;

$activeTab = $_GET['tab'] ?? 'walkin';

// Walk-in data
$dailySummary = $saleModel->getDaily($from, $to);
$transactions = ($from === $to) ? $saleModel->getAllForReport($from) : [];
$todayTotal   = $saleModel->getTodayTotal();
$todayCount   = $saleModel->getTodayCount();

// Online orders data
$onlineOrders      = $orderModel->getForReport($from, $to);
$onlineTotal       = array_sum(array_column(array_filter($onlineOrders, fn($o) => $o['order_status'] !== 'cancelled'), 'total_amount'));
$onlineTodayOrders = $orderModel->getForReport($today, $today);
$onlineTodayTotal  = array_sum(array_column(array_filter($onlineTodayOrders, fn($o) => $o['order_status'] !== 'cancelled'), 'total_amount'));
$onlineTodayCount  = count($onlineTodayOrders);

// Combined
$combinedTotal = $todayTotal + $onlineTodayTotal;
$combinedCount = $todayCount + $onlineTodayCount;

require_once __DIR__ . '/../../includes/head.php';
require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/../../includes/topbar.php';

$statusColors = [
    'pending'   => 'badge-yellow',
    'confirmed' => 'badge-blue',
    'shipped'   => 'badge-purple',
    'delivered' => 'badge-green',
    'cancelled' => 'badge-red',
];
?>

<style>
.badge-yellow  { background:rgba(234,179,8,.15);  color:#a16207; }
.badge-purple  { background:rgba(168,85,247,.15); color:#7e22ce; }
.rep-tab { background:none; border:none; padding:10px 20px; font-size:.9rem; color:var(--clr-text-muted); cursor:pointer; border-bottom:3px solid transparent; margin-bottom:-2px; font-weight:500; transition:all .15s; }
.rep-tab:hover { color:var(--clr-text); }
.rep-tab.active { color:var(--clr-primary); border-bottom-color:var(--clr-primary); }
.rep-section { display:none; }
.rep-section.active { display:block; }
</style>

<div class="page-header">
  <div><h1>Reports</h1><p>Sales summary across walk-in and online orders</p></div>
</div>

<!-- Today summary cards -->
<div class="stat-grid" style="margin-bottom:20px;">
  <div class="stat-card">
    <div class="stat-info">
      <div class="label">Today — Walk-in Revenue</div>
      <div class="value text-mono">₱<?= number_format($todayTotal,2) ?></div>
      <div style="font-size:.72rem;color:var(--clr-text-muted);margin-top:2px;"><?= $todayCount ?> transaction<?= $todayCount!=1?'s':'' ?></div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-info">
      <div class="label">Today — Online Revenue</div>
      <div class="value text-mono">₱<?= number_format($onlineTodayTotal,2) ?></div>
      <div style="font-size:.72rem;color:var(--clr-text-muted);margin-top:2px;"><?= $onlineTodayCount ?> order<?= $onlineTodayCount!=1?'s':'' ?></div>
    </div>
  </div>
  <div class="stat-card" style="border-color:var(--clr-primary);">
    <div class="stat-info">
      <div class="label">Today — Combined Total</div>
      <div class="value text-mono" style="color:var(--clr-primary)">₱<?= number_format($combinedTotal,2) ?></div>
      <div style="font-size:.72rem;color:var(--clr-text-muted);margin-top:2px;"><?= $combinedCount ?> total transaction<?= $combinedCount!=1?'s':'' ?></div>
    </div>
  </div>
</div>

<!-- Date filter -->
<div class="card" style="margin-bottom:16px;">
  <form method="GET" class="d-flex gap-12 align-center" style="flex-wrap:wrap;padding:14px 16px;">
    <input type="hidden" name="tab" value="<?= e($activeTab) ?>">
    <div class="form-group mb-0">
      <label class="form-label">From</label>
      <input type="date" name="from" class="form-control" value="<?= e($from) ?>">
    </div>
    <div class="form-group mb-0">
      <label class="form-label">To</label>
      <input type="date" name="to" class="form-control" value="<?= e($to) ?>">
    </div>
    <div style="align-self:flex-end;padding-bottom:1px;">
      <button type="submit" class="btn btn-primary">Filter</button>
      <a href="?tab=<?= e($activeTab) ?>" class="btn btn-ghost">Reset</a>
    </div>
    <div style="align-self:flex-end;padding-bottom:1px;margin-left:auto;">
      <span style="font-size:.78rem;color:var(--clr-text-muted);">
        Showing: <strong><?= $from === $to ? date('M d, Y', strtotime($from)) : e($from).' → '.e($to) ?></strong>
      </span>
    </div>
  </form>
</div>

<!-- Tabs -->
<div style="display:flex;gap:4px;margin-bottom:20px;border-bottom:2px solid var(--clr-border);">
  <button class="rep-tab <?= $activeTab==='walkin' ? 'active':'' ?>" onclick="switchTab('walkin',this)">
     Walk-in Sales
    <span style="background:var(--clr-surface-2);border-radius:999px;padding:1px 8px;font-size:.72rem;margin-left:4px;"><?= count($dailySummary) ?></span>
  </button>
  <button class="rep-tab <?= $activeTab==='online' ? 'active':'' ?>" onclick="switchTab('online',this)">
     Online Orders
    <span style="background:var(--clr-surface-2);border-radius:999px;padding:1px 8px;font-size:.72rem;margin-left:4px;"><?= count($onlineOrders) ?></span>
  </button>
  <button class="rep-tab <?= $activeTab==='combined' ? 'active':'' ?>" onclick="switchTab('combined',this)">
     Combined
  </button>
</div>

<!-- ── Walk-in Tab ── -->
<div id="tab-walkin" class="rep-section <?= $activeTab==='walkin'?'active':'' ?>">
  <div class="card" style="margin-bottom:20px;">
    <div class="card-header">
      <div class="card-title">Daily Walk-in Summary</div>
      <div class="card-subtitle"><?= e($from) ?> to <?= e($to) ?></div>
    </div>
    <div class="table-wrapper">
      <?php if (empty($dailySummary)): ?>
        <div class="empty-state"><div class="icon"></div>No walk-in sales for selected period.</div>
      <?php else: ?>
        <table>
          <thead><tr><th>Date</th><th>Transactions</th><th>Items Sold</th><th>Revenue</th><th>Cashier</th></tr></thead>
          <tbody>
            <?php $grandTotal=0; foreach ($dailySummary as $row): $grandTotal+=$row['total_revenue']; ?>
              <tr>
                <td style="font-weight:600"><?= date('M d, Y', strtotime($row['sale_date'])) ?></td>
                <td><span class="badge badge-blue"><?= $row['total_transactions'] ?></span></td>
                <td><?= $row['total_items_sold'] ?></td>
                <td class="text-mono" style="font-weight:700;color:var(--clr-success)">₱<?= number_format($row['total_revenue'],2) ?></td>
                <td><?= e($row['cashier']) ?></td>
              </tr>
            <?php endforeach; ?>
            <tr style="background:var(--clr-bg);">
              <td colspan="3" style="font-weight:700;text-align:right">Grand Total</td>
              <td class="text-mono" style="font-weight:800;color:var(--clr-accent)">₱<?= number_format($grandTotal,2) ?></td>
              <td></td>
            </tr>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($from === $to): ?>
  <div class="card">
    <div class="card-header">
      <div class="card-title">Transactions — <?= date('F d, Y', strtotime($from)) ?></div>
    </div>
    <div class="table-wrapper">
      <?php if (empty($transactions)): ?>
        <div class="empty-state"><div class="icon">🧾</div>No transactions on this date.</div>
      <?php else: ?>
        <table>
          <thead><tr><th>Sale #</th><th>Cashier</th><th>Total</th><th>Payment</th><th>Paid</th><th>Change</th><th>Time</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($transactions as $t): ?>
              <tr>
                <td><span class="badge badge-purple">#<?= str_pad($t['purchase_id'],4,'0',STR_PAD_LEFT) ?></span></td>
                <td style="font-weight:600"><?= e($t['cashier']) ?></td>
                <td class="text-mono" style="font-weight:700">₱<?= number_format($t['total_amount'],2) ?></td>
                <td><span class="badge <?= $t['payment_method']==='cash'?'badge-green':'badge-blue' ?>"><?= ucfirst($t['payment_method']??'cash') ?></span></td>
                <td class="text-mono">₱<?= number_format($t['amount_paid'],2) ?></td>
                <td class="text-mono">₱<?= number_format($t['change_amount'],2) ?></td>
                <td class="text-muted"><?= date('h:i A', strtotime($t['purchase_date'])) ?></td>
                <td><button class="btn btn-ghost btn-sm" onclick="viewSaleItems(<?= $t['purchase_id'] ?>)">👁 View</button></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- ── Online Orders Tab ── -->
<div id="tab-online" class="rep-section <?= $activeTab==='online'?'active':'' ?>">
  <div class="card">
    <div class="card-header">
      <div class="card-title">Online Orders — <?= e($from) ?> to <?= e($to) ?></div>
      <div class="card-subtitle">
        <?= count($onlineOrders) ?> order<?= count($onlineOrders)!=1?'s':'' ?>
        &nbsp;·&nbsp;
        Revenue (excl. cancelled): <strong class="text-mono">₱<?= number_format($onlineTotal,2) ?></strong>
      </div>
    </div>
    <div class="table-wrapper">
      <?php if (empty($onlineOrders)): ?>
        <div class="empty-state"><div class="icon"></div>No online orders for selected period.</div>
      <?php else: ?>
        <table>
          <thead>
            <tr><th>Order #</th><th>Customer</th><th>Total</th><th>Payment</th><th>Status</th><th>Entered By</th><th>Date</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($onlineOrders as $o): ?>
              <tr>
                <td><span class="badge badge-blue">#<?= str_pad($o['order_id'],4,'0',STR_PAD_LEFT) ?></span></td>
                <td>
                  <div style="font-weight:600"><?= e($o['customer_name']) ?></div>
                  <div style="font-size:.72rem;color:var(--clr-text-muted)"><?= e($o['contact_number']??'') ?></div>
                </td>
                <td class="text-mono" style="font-weight:700">₱<?= number_format($o['total_amount'],2) ?></td>
                <td>
                  <div><span class="badge <?= $o['payment_method']==='paid_online'?'badge-blue':'badge-yellow' ?>">
                    <?= $o['payment_method']==='paid_online'?'Paid Online':'COD' ?>
                  </span></div>
                  <div style="margin-top:3px"><span class="badge <?= $o['payment_status']==='paid'?'badge-green':'badge-red' ?>">
                    <?= $o['payment_status']==='paid'?'✓ Paid':'Unpaid' ?>
                  </span></div>
                </td>
                <td><span class="badge <?= $statusColors[$o['order_status']] ?? 'badge-blue' ?>"><?= ucfirst($o['order_status']) ?></span></td>
                <td class="text-muted"><?= e($o['staff_name']) ?></td>
                <td class="text-muted" style="font-size:.78rem"><?= date('M d, Y', strtotime($o['ordered_at'])) ?></td>
                <td><button class="btn btn-ghost btn-sm" onclick="viewOrderItems(<?= $o['order_id'] ?>)">👁 View</button></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ── Combined Tab ── -->
<div id="tab-combined" class="rep-section <?= $activeTab==='combined'?'active':'' ?>">
  <?php
  // Merge walk-in + online into a flat list sorted by date
  $combined = [];
  foreach ($transactions as $t) {
    $combined[] = [
      'type'    => 'walkin',
      'ref'     => '#'.str_pad($t['purchase_id'],4,'0',STR_PAD_LEFT),
      'party'   => $t['cashier'],
      'total'   => $t['total_amount'],
      'method'  => ucfirst($t['payment_method']??'cash'),
      'status'  => '✓ Completed',
      'status_class' => 'badge-green',
      'date'    => $t['purchase_date'],
    ];
  }
  foreach ($onlineOrders as $o) {
    $combined[] = [
      'type'    => 'online',
      'ref'     => '#'.str_pad($o['order_id'],4,'0',STR_PAD_LEFT),
      'party'   => $o['customer_name'],
      'total'   => $o['total_amount'],
      'method'  => $o['payment_method']==='paid_online'?'Paid Online':'COD',
      'status'  => ucfirst($o['order_status']),
      'status_class' => $statusColors[$o['order_status']] ?? 'badge-blue',
      'date'    => $o['ordered_at'],
    ];
  }
  usort($combined, fn($a,$b) => strcmp($b['date'], $a['date']));
  $combinedGrand = array_sum(array_column(
    array_filter($combined, fn($r) => !($r['type']==='online' && strtolower($r['status'])==='cancelled')),
    'total'
  ));
  ?>
  <div class="card">
    <div class="card-header">
      <div class="card-title">All Transactions — <?= e($from) ?> to <?= e($to) ?></div>
      <div class="card-subtitle">
        <?= count($combined) ?> total &nbsp;·&nbsp;
        Combined revenue: <strong class="text-mono">₱<?= number_format($combinedGrand,2) ?></strong>
      </div>
    </div>
    <div class="table-wrapper">
      <?php if (empty($combined)): ?>
        <div class="empty-state"><div class="icon">📊</div>No transactions for selected period.</div>
      <?php else: ?>
        <table>
          <thead>
            <tr><th>Ref</th><th>Type</th><th>Customer / Cashier</th><th>Total</th><th>Method</th><th>Status</th><th>Date & Time</th></tr>
          </thead>
          <tbody>
            <?php foreach ($combined as $r): ?>
              <tr>
                <td><span class="badge <?= $r['type']==='online'?'badge-blue':'badge-purple' ?>"><?= e($r['ref']) ?></span></td>
                <td><span class="badge <?= $r['type']==='online'?'badge-yellow':'badge-green' ?>"><?= $r['type']==='online'?'🌐 Online':'🏪 Walk-in' ?></span></td>
                <td style="font-weight:600"><?= e($r['party']) ?></td>
                <td class="text-mono" style="font-weight:700">₱<?= number_format($r['total'],2) ?></td>
                <td><?= e($r['method']) ?></td>
                <td><span class="badge <?= $r['status_class'] ?>"><?= e($r['status']) ?></span></td>
                <td class="text-muted" style="font-size:.78rem"><?= date('M d, Y h:i A', strtotime($r['date'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr style="background:var(--clr-bg);">
              <td colspan="3" style="font-weight:700;text-align:right;">Grand Total</td>
              <td class="text-mono" style="font-weight:800;color:var(--clr-accent);">₱<?= number_format($combinedGrand,2) ?></td>
              <td colspan="3"></td>
            </tr>
          </tfoot>
        </table>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Sale Items Modal (walk-in) -->
<div class="modal-overlay" id="saleItemsModal">
  <div class="modal modal-lg">
    <div class="modal-header"><div class="modal-title">🧾 Transaction Details</div><button class="modal-close">✕</button></div>
    <div class="modal-body" id="saleItemsBody"><div class="empty-state">Loading…</div></div>
    <div class="modal-footer"><button class="btn btn-ghost" onclick="closeModal('saleItemsModal')">Close</button></div>
  </div>
</div>

<!-- Order Items Modal (online) -->
<div class="modal-overlay" id="orderItemsModal">
  <div class="modal modal-lg">
    <div class="modal-header"><div class="modal-title" id="oiTitle">📦 Order Details</div><button class="modal-close">✕</button></div>
    <div class="modal-body" id="orderItemsBody"><div class="empty-state">Loading…</div></div>
    <div class="modal-footer"><button class="btn btn-ghost" onclick="closeModal('orderItemsModal')">Close</button></div>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

<script>
(function() {
function switchTab(tab, btn) {
  document.querySelectorAll('.rep-section').forEach(s=>s.classList.remove('active'));
  document.querySelectorAll('.rep-tab').forEach(t=>t.classList.remove('active'));
  document.getElementById('tab-'+tab).classList.add('active');
  btn.classList.add('active');
  // Keep tab in URL on next filter submit
  document.querySelector('input[name="tab"]').value = tab;
}

function viewSaleItems(id) {
  openModal('saleItemsModal');
  document.getElementById('saleItemsBody').innerHTML='<div class="empty-state">Loading…</div>';
  fetch('<?= APP_URL ?>/modules/reports/get_sale_items.php?id='+id)
    .then(r=>r.json()).then(items=>{
      if (!items.length){document.getElementById('saleItemsBody').innerHTML='<div class="empty-state">No items.</div>';return;}
      let total=0, html='<table><thead><tr><th>Product</th><th>Category</th><th>Size</th><th>Unit Price</th><th>Qty</th><th>Subtotal</th></tr></thead><tbody>';
      items.forEach(i=>{const sub=i.quantity*i.price;total+=sub;html+=`<tr><td style="font-weight:600">${i.product_name}</td><td>${i.category||'—'}</td><td>${i.size}</td><td class="text-mono">₱${parseFloat(i.price).toFixed(2)}</td><td>${i.quantity}</td><td class="text-mono" style="font-weight:700">₱${sub.toFixed(2)}</td></tr>`;});
      html+=`</tbody><tfoot><tr style="background:var(--clr-bg)"><td colspan="5" style="text-align:right;font-weight:700">Total</td><td class="text-mono" style="font-weight:800;color:var(--clr-accent)">₱${total.toFixed(2)}</td></tr></tfoot></table>`;
      document.getElementById('saleItemsBody').innerHTML=html;
    });
}

function viewOrderItems(id) {
  openModal('orderItemsModal');
  document.getElementById('oiTitle').textContent = 'Order #' + String(id).padStart(4,'0');
  document.getElementById('orderItemsBody').innerHTML='<div class="empty-state">Loading…</div>';
  fetch('<?= APP_URL ?>/modules/online_orders/index.php?order_items='+id)
    .then(r=>r.json()).then(items=>{
      if (!items.length){document.getElementById('orderItemsBody').innerHTML='<div class="empty-state">No items.</div>';return;}
      let total=0, html='<table><thead><tr><th>Product</th><th>Category</th><th>Size</th><th>Unit Price</th><th>Qty</th><th>Subtotal</th></tr></thead><tbody>';
      items.forEach(i=>{const sub=i.quantity*i.price;total+=sub;html+=`<tr><td style="font-weight:600">${i.product_name}</td><td>${i.category||'—'}</td><td>${i.size}</td><td class="text-mono">₱${parseFloat(i.price).toFixed(2)}</td><td>${i.quantity}</td><td class="text-mono" style="font-weight:700">₱${sub.toFixed(2)}</td></tr>`;});
      html+=`</tbody><tfoot><tr style="background:var(--clr-bg)"><td colspan="5" style="text-align:right;font-weight:700">Total</td><td class="text-mono" style="font-weight:800;color:var(--clr-accent)">₱${total.toFixed(2)}</td></tr></tfoot></table>`;
      document.getElementById('orderItemsBody').innerHTML=html;
    });
}

window.switchTab     = switchTab;
window.viewSaleItems = viewSaleItems;
window.viewOrderItems = viewOrderItems;

})(); // end IIFE
</script>
