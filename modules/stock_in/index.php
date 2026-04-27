<?php
require_once __DIR__ . '/../../config/app.php';
requireLogin();

$pageTitle   = 'Stock In';
$currentPage = 'stock_in';

$stockInModel  = new StockIn();
$productModel  = new Product();
$supplierModel = new Supplier();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $supplierId = (int)($_POST['supplier_id'] ?? 0) ?: null;
    $notes      = trim($_POST['notes'] ?? '');
    $pids       = $_POST['product_id']  ?? [];
    $qtys       = $_POST['qty']         ?? [];
    $costs      = $_POST['cost_price']  ?? [];

    $items = [];
    foreach ($pids as $idx => $pid) {
        $pid  = (int)$pid;
        $qty  = (int)($qtys[$idx]  ?? 0);
        $cost = (float)($costs[$idx] ?? 0);
        if ($pid && $qty > 0) $items[] = ['product_id'=>$pid,'qty'=>$qty,'cost_price'=>$cost];
    }

    if (empty($items)) {
        $error = 'Please add at least one product item.';
    } else {
        $id = $stockInModel->create((int)$_SESSION['user_id'], $supplierId, $notes, $items);
        if ($id) {
            flash('success', 'Stock In #' . str_pad($id, 4, '0', STR_PAD_LEFT) . ' saved.');
            redirect(APP_URL . '/modules/stock_in/index.php');
        } else {
            $error = 'Failed to save. Please try again.';
        }
    }
}

$stockIns  = $stockInModel->getAll();
$products  = $productModel->getAll();
$suppliers = $supplierModel->getAll();

require_once __DIR__ . '/../../includes/head.php';
require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/../../includes/topbar.php';
?>

<?php if ($msg = flash('success')): ?><div class="alert alert-success">✓ <?= e($msg) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger">⚠ <?= e($error) ?></div><?php endif; ?>

<div class="page-header">
  <div><h1>Stock In</h1><p>Record incoming stock from suppliers</p></div>
  <button class="btn btn-primary" onclick="openModal('stockInModal')">+ New Stock In</button>
</div>

<div class="card">
  <div class="card-header"><div class="card-title">Stock In History</div></div>
  <div class="table-wrapper">
    <table>
      <thead>
        <tr><th>#</th><th>Date & Time</th><th>Supplier</th><th>Received By</th><th>Notes</th><th>Items</th></tr>
      </thead>
      <tbody>
        <?php if (empty($stockIns)): ?>
          <tr><td colspan="6"><div class="empty-state">No stock-in records yet.</div></td></tr>
        <?php endif; ?>
        <?php foreach ($stockIns as $si): ?>
          <tr>
            <td><span class="badge badge-blue">#<?= str_pad($si['stockin_id'], 4, '0', STR_PAD_LEFT) ?></span></td>
            <td><?= date('M d, Y h:i A', strtotime($si['date_received'])) ?></td>
            <td><?= e($si['supplier_name'] ?? '—') ?></td>
            <td><?= e($si['received_by']) ?></td>
            <td class="text-muted"><?= e($si['notes'] ?: '—') ?></td>
            <td><button class="btn btn-ghost btn-sm" onclick="viewItems(<?= $si['stockin_id'] ?>)">View Items</button></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- New Stock In Modal -->
<div class="modal-overlay" id="stockInModal">
  <div class="modal modal-lg">
    <div class="modal-header"><div class="modal-title">📥 New Stock In</div><button class="modal-close">✕</button></div>
    <form method="POST">
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Supplier</label>
            <select name="supplier_id" class="form-control">
              <option value="">— Select Supplier (optional) —</option>
              <?php foreach ($suppliers as $s): ?><option value="<?= $s['supplier_id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Notes</label>
            <input type="text" name="notes" class="form-control" placeholder="Optional…">
          </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 100px 120px 40px;gap:10px;font-size:.72rem;font-weight:700;text-transform:uppercase;color:var(--clr-text-muted);letter-spacing:.05em;margin-bottom:6px;">
          <div>Product</div><div>Quantity</div><div>Cost Price (₱)</div><div></div>
        </div>

        <div id="stockRows">
          <div class="stock-row mb-16" style="display:grid;grid-template-columns:1fr 100px 120px 40px;gap:10px;align-items:center;">
            <select name="product_id[]" class="form-control" required>
              <option value="">Select product</option>
              <?php foreach ($products as $p): ?>
                <option value="<?= $p['product_id'] ?>"><?= e($p['product_name']) ?> (<?= e($p['size']) ?>)</option>
              <?php endforeach; ?>
            </select>
            <input type="number" name="qty[]" class="form-control" placeholder="Qty" min="1" required>
            <input type="number" name="cost_price[]" class="form-control" placeholder="0.00" step="0.01" min="0" value="0">
            <span style="color:var(--clr-text-muted)">—</span>
          </div>
        </div>
        <button type="button" class="btn btn-ghost btn-sm" onclick="addStockRow()">+ Add Row</button>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('stockInModal')">Cancel</button>
        <button type="submit" class="btn btn-success">Save Stock In</button>
      </div>
    </form>
  </div>
</div>

<!-- View Items Modal -->
<div class="modal-overlay" id="viewItemsModal">
  <div class="modal">
    <div class="modal-header"><div class="modal-title">📋 Stock In Items</div><button class="modal-close">✕</button></div>
    <div class="modal-body" id="viewItemsBody"><div class="empty-state">Loading…</div></div>
    <div class="modal-footer"><button class="btn btn-ghost" onclick="closeModal('viewItemsModal')">Close</button></div>
  </div>
</div>

<script>
window.PRODUCTS = <?= json_encode(array_map(fn($p) => ['id'=>$p['product_id'],'name'=>$p['product_name'].' ('.$p['size'].')'], $products)) ?>;

function addStockRow() {
  const opts = window.PRODUCTS.map(p => `<option value="${p.id}">${p.name}</option>`).join('');
  const row  = document.createElement('div');
  row.className = 'stock-row mb-16';
  row.style.cssText = 'display:grid;grid-template-columns:1fr 100px 120px 40px;gap:10px;align-items:center;';
  row.innerHTML = `
    <select name="product_id[]" class="form-control" required><option value="">Select product</option>${opts}</select>
    <input type="number" name="qty[]" class="form-control" placeholder="Qty" min="1" required>
    <input type="number" name="cost_price[]" class="form-control" placeholder="0.00" step="0.01" min="0" value="0">
    <button type="button" class="btn btn-ghost btn-icon" onclick="this.closest('.stock-row').remove()">✕</button>
  `;
  document.getElementById('stockRows').appendChild(row);
}

function viewItems(id) {
  openModal('viewItemsModal');
  document.getElementById('viewItemsBody').innerHTML = '<div class="empty-state">Loading…</div>';
  fetch('<?= APP_URL ?>/modules/stock_in/get_items.php?id=' + id)
    .then(r => r.json())
    .then(items => {
      if (!items.length) { document.getElementById('viewItemsBody').innerHTML = '<div class="empty-state">No items.</div>'; return; }
      let html = '<table><thead><tr><th>Product</th><th>Size</th><th>Qty</th><th>Cost Price</th></tr></thead><tbody>';
      items.forEach(i => {
        html += `<tr><td style="font-weight:600">${i.product_name}</td><td>${i.size}</td><td>${i.quantity}</td><td class="text-mono">₱${parseFloat(i.cost_price).toFixed(2)}</td></tr>`;
      });
      html += '</tbody></table>';
      document.getElementById('viewItemsBody').innerHTML = html;
    });
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
