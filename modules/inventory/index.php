<?php
require_once __DIR__ . '/../../config/app.php';
requireLogin();

$pageTitle   = 'Inventory';
$currentPage = 'inventory';

$product = new Product();

if (isAdmin() && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'edit') {
        $id       = (int)($_POST['id'] ?? 0);
        $name     = trim($_POST['product_name'] ?? '');
        $catId    = (int)($_POST['category_id'] ?? 0) ?: null;
        $size     = trim($_POST['size'] ?? 'One Size');
        $price    = (float)($_POST['price'] ?? 0);
        $cost     = (float)($_POST['cost_price'] ?? 0);
        $qty      = (int)($_POST['quantity'] ?? 0);
        $reorder  = (int)($_POST['reorder_lvl'] ?? 5);
        if ($id && $name) {
            $product->update($id, $name, $catId, $size, $price, $cost, $qty, $reorder);
            flash('success', 'Product updated.');
        }
        redirect(APP_URL . '/modules/inventory/index.php');
    }
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) { $product->delete($id); flash('success', 'Product deleted.'); }
        redirect(APP_URL . '/modules/inventory/index.php');
    }
}

$products   = $product->getAll();
$categories = $product->getCategories();

require_once __DIR__ . '/../../includes/head.php';
require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/../../includes/topbar.php';
?>

<?php if ($msg = flash('success')): ?>
  <div class="alert alert-success">✓ <?= e($msg) ?></div>
<?php endif; ?>

<div class="page-header">
  <div><h1>Inventory</h1><p>Current stock levels for all products</p></div>
  <div class="d-flex gap-8">
    <div class="search-wrap">
      <span class="icon"></span>
      <input type="text" id="invSearch" class="form-control" placeholder="Search…">
    </div>
    <?php if (isAdmin()): ?>
      <a href="<?= APP_URL ?>/modules/stock_in/index.php" class="btn btn-ghost"> Stock In</a>
      <a href="<?= APP_URL ?>/modules/products/index.php" class="btn btn-primary">+ Add Product</a>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <div class="table-wrapper">
    <table>
      <thead>
        <tr>
          <th>#</th><th>Product Name</th><th>Category</th><th>Size</th>
          <th>Cost Price</th><th>Selling Price</th><th>Stock Qty</th>
          <th>Reorder</th><th>Status</th>
          <?php if (isAdmin()): ?><th>Actions</th><?php endif; ?>
        </tr>
      </thead>
      <tbody id="invBody">
        <?php if (empty($products)): ?>
          <tr><td colspan="10"><div class="empty-state">No products found.</div></td></tr>
        <?php endif; ?>
        <?php foreach ($products as $i => $p):
          $stockStatus = 'good'; $statusLabel = 'Available'; $badgeClass = 'badge-green';
          if ($p['quantity'] == 0)              { $stockStatus='out'; $statusLabel='Out of Stock'; $badgeClass='badge-red'; }
          elseif ($p['quantity'] <= $p['reorder_lvl']) { $stockStatus='low'; $statusLabel='Low Stock';    $badgeClass='badge-orange'; }
        ?>
          <tr>
            <td class="text-muted"><?= $i + 1 ?></td>
            <td style="font-weight:600"><?= e($p['product_name']) ?></td>
            <td><span class="badge badge-blue"><?= e($p['category'] ?? 'Uncategorized') ?></span></td>
            <td><?= e($p['size']) ?></td>
            <td class="text-mono">₱<?= number_format($p['cost_price'], 2) ?></td>
            <td class="text-mono" style="font-weight:600">₱<?= number_format($p['price'], 2) ?></td>
            <td style="font-weight:700;color:<?= $stockStatus==='out' ? 'var(--clr-danger)' : ($stockStatus==='low' ? 'var(--clr-warning)' : 'var(--clr-success)') ?>">
              <?= number_format($p['quantity']) ?>
            </td>
            <td class="text-muted"><?= $p['reorder_lvl'] ?></td>
            <td><span class="badge <?= $badgeClass ?>"><?= $statusLabel ?></span></td>
            <?php if (isAdmin()): ?>
            <td>
              <div class="d-flex gap-8">
                <button class="btn btn-ghost btn-sm" onclick="openInvEdit(<?= htmlspecialchars(json_encode($p), ENT_QUOTES) ?>)">Edit</button>
                <form method="POST" onsubmit="event.preventDefault();confirmDelete(this)">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= $p['product_id'] ?>">
                  <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                </form>
              </div>
            </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if (isAdmin()): ?>
<div class="modal-overlay" id="invEditModal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">✏️ Edit Product</div>
      <button class="modal-close">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" id="ieId">
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Product Name *</label>
            <input type="text" name="product_name" id="ieName" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">Category</label>
            <select name="category_id" id="ieCat" class="form-control">
              <option value="">— None —</option>
              <?php foreach ($categories as $c): ?>
                <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Size</label>
            <input type="text" name="size" id="ieSize" class="form-control" placeholder="e.g. M, One Size">
          </div>
          <div class="form-group">
            <label class="form-label">Cost Price (₱)</label>
            <input type="number" name="cost_price" id="ieCost" class="form-control" step="0.01" min="0">
          </div>
          <div class="form-group">
            <label class="form-label">Selling Price (₱)</label>
            <input type="number" name="price" id="iePrice" class="form-control" step="0.01" min="0">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Stock Quantity</label>
            <input type="number" name="quantity" id="ieQty" class="form-control" min="0">
          </div>
          <div class="form-group">
            <label class="form-label">Reorder Level</label>
            <input type="number" name="reorder_lvl" id="ieReorder" class="form-control" min="0">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('invEditModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Update Product</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
document.getElementById('invSearch').addEventListener('input', function() { filterTable('invSearch','invBody'); });
function openInvEdit(p) {
  document.getElementById('ieId').value      = p.product_id;
  document.getElementById('ieName').value    = p.product_name;
  document.getElementById('ieCat').value     = p.category_id || '';
  document.getElementById('ieSize').value    = p.size;
  document.getElementById('ieCost').value    = p.cost_price;
  document.getElementById('iePrice').value   = p.price;
  document.getElementById('ieQty').value     = p.quantity;
  document.getElementById('ieReorder').value = p.reorder_lvl;
  openModal('invEditModal');
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
