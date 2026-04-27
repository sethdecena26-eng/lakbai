<?php
require_once __DIR__ . '/../../config/app.php';
requireAdmin();

$pageTitle   = 'Products';
$currentPage = 'products';

$product = new Product();
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action   = $_POST['action'] ?? '';
    $name     = trim($_POST['product_name'] ?? '');
    $catId    = (int)($_POST['category_id'] ?? 0) ?: null;
    $size     = trim($_POST['size'] ?? 'One Size');
    $price    = (float)($_POST['price'] ?? 0);
    $cost     = (float)($_POST['cost_price'] ?? 0);
    $reorder  = (int)($_POST['reorder_lvl'] ?? 5);

    if ($action === 'create') {
        if ($name && $price >= 0) {
            $product->create($name, $catId, $size, $price, $cost, $reorder);
            flash('success', "Product \"$name\" added.");
            redirect(APP_URL . '/modules/products/index.php');
        } else { $error = 'Name and price are required.'; }
    }
    if ($action === 'edit') {
        $id  = (int)($_POST['id'] ?? 0);
        $qty = (int)($_POST['quantity'] ?? 0);
        if ($id && $name) {
            $product->update($id, $name, $catId, $size, $price, $cost, $qty, $reorder);
            flash('success', 'Product updated.');
            redirect(APP_URL . '/modules/products/index.php');
        }
    }
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) { $product->archive($id); flash('success', 'Product moved to archive.'); redirect(APP_URL . '/modules/products/index.php'); }
    }
    if ($action === 'add_category') {
        $catName = trim($_POST['cat_name'] ?? '');
        if ($catName) { $product->createCategory($catName); flash('success', "Category \"$catName\" created."); redirect(APP_URL . '/modules/products/index.php'); }
    }
}

$products   = $product->getAll();
$categories = $product->getCategories();

require_once __DIR__ . '/../../includes/head.php';
require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/../../includes/topbar.php';
?>

<?php if ($msg = flash('success')): ?><div class="alert alert-success">✓ <?= e($msg) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger">⚠ <?= e($error) ?></div><?php endif; ?>

<div class="page-header">
  <div><h1>Products</h1><p>Manage your product catalog</p></div>
  <div class="d-flex gap-8">
    <button class="btn btn-ghost btn-sm" onclick="openModal('addCatModal')">+ Category</button>
    <button class="btn btn-primary" onclick="openModal('addProductModal')">+ Add Product</button>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <div class="card-title">All Products (<?= count($products) ?>)</div>
    <div class="search-wrap" style="width:220px">
      <span class="icon"></span>
      <input type="text" id="prodSearch" class="form-control" placeholder="Search…">
    </div>
  </div>
  <div class="table-wrapper">
    <table>
      <thead>
        <tr><th>#</th><th>Product Name</th><th>Category</th><th>Size</th><th>Cost Price</th><th>Selling Price</th><th>Stock</th><th>Reorder</th><th>Actions</th></tr>
      </thead>
      <tbody id="prodBody">
        <?php foreach ($products as $i => $p): ?>
          <tr>
            <td class="text-muted"><?= $i+1 ?></td>
            <td style="font-weight:600"><?= e($p['product_name']) ?></td>
            <td><span class="badge badge-blue"><?= e($p['category'] ?? '—') ?></span></td>
            <td><?= e($p['size']) ?></td>
            <td class="text-mono">₱<?= number_format($p['cost_price'],2) ?></td>
            <td class="text-mono" style="font-weight:600">₱<?= number_format($p['price'],2) ?></td>
            <td style="font-weight:700;color:<?= $p['quantity']<=$p['reorder_lvl'] ? 'var(--clr-warning)' : 'inherit' ?>"><?= $p['quantity'] ?></td>
            <td class="text-muted"><?= $p['reorder_lvl'] ?></td>
            <td>
              <div class="d-flex gap-8">
                <button class="btn btn-ghost btn-sm" onclick="openEditModal(<?= htmlspecialchars(json_encode($p),ENT_QUOTES) ?>)">Edit</button>
                <form method="POST" onsubmit="event.preventDefault();confirmDelete(this)">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= $p['product_id'] ?>">
                  <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add Modal -->
<div class="modal-overlay" id="addProductModal">
  <div class="modal">
    <div class="modal-header"><div class="modal-title">+ Add Product</div><button class="modal-close">✕</button></div>
    <form method="POST">
      <input type="hidden" name="action" value="create">
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Product Name *</label>
            <input type="text" name="product_name" class="form-control" placeholder="e.g. Classic T-Shirt" required>
          </div>
          <div class="form-group">
            <label class="form-label">Category</label>
            <select name="category_id" class="form-control">
              <option value="">— None —</option>
              <?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Size</label>
            <input type="text" name="size" class="form-control" placeholder="e.g. M, L, One Size" value="One Size">
          </div>
          <div class="form-group">
            <label class="form-label">Cost Price (₱)</label>
            <input type="number" name="cost_price" class="form-control" placeholder="0.00" step="0.01" min="0" value="0">
          </div>
          <div class="form-group">
            <label class="form-label">Selling Price (₱) *</label>
            <input type="number" name="price" class="form-control" placeholder="0.00" step="0.01" min="0" required>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Reorder Level</label>
          <input type="number" name="reorder_lvl" class="form-control" value="5" min="0">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('addProductModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Add Product</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="editProductModal">
  <div class="modal">
    <div class="modal-header"><div class="modal-title">✏️ Edit Product</div><button class="modal-close">✕</button></div>
    <form method="POST">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" id="editId">
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Product Name *</label>
            <input type="text" name="product_name" id="editName" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">Category</label>
            <select name="category_id" id="editCat" class="form-control">
              <option value="">— None —</option>
              <?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Size</label>
            <input type="text" name="size" id="editSize" class="form-control">
          </div>
          <div class="form-group">
            <label class="form-label">Cost Price (₱)</label>
            <input type="number" name="cost_price" id="editCost" class="form-control" step="0.01" min="0">
          </div>
          <div class="form-group">
            <label class="form-label">Selling Price (₱)</label>
            <input type="number" name="price" id="editPrice" class="form-control" step="0.01" min="0">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Stock Quantity</label>
            <input type="number" name="quantity" id="editQty" class="form-control" min="0">
          </div>
          <div class="form-group">
            <label class="form-label">Reorder Level</label>
            <input type="number" name="reorder_lvl" id="editReorder" class="form-control" min="0">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('editProductModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Update Product</button>
      </div>
    </form>
  </div>
</div>

<!-- Add Category Modal -->
<div class="modal-overlay" id="addCatModal">
  <div class="modal" style="max-width:380px">
    <div class="modal-header"><div class="modal-title">+ Add Category</div><button class="modal-close">✕</button></div>
    <form method="POST">
      <input type="hidden" name="action" value="add_category">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Category Name *</label>
          <input type="text" name="cat_name" class="form-control" placeholder="e.g. Hats" required>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('addCatModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Add Category</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEditModal(p) {
  document.getElementById('editId').value      = p.product_id;
  document.getElementById('editName').value    = p.product_name;
  document.getElementById('editCat').value     = p.category_id || '';
  document.getElementById('editSize').value    = p.size;
  document.getElementById('editCost').value    = p.cost_price;
  document.getElementById('editPrice').value   = p.price;
  document.getElementById('editQty').value     = p.quantity;
  document.getElementById('editReorder').value = p.reorder_lvl;
  openModal('editProductModal');
}
document.getElementById('prodSearch').addEventListener('input', function() { filterTable('prodSearch','prodBody'); });
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
