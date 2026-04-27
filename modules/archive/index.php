<?php
require_once __DIR__ . '/../../config/app.php';
requireAdmin();

$pageTitle   = 'Archive';
$currentPage = 'archive';

$product  = new Product();
$supplier = new Supplier();
$userModel = new User();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action     = $_POST['action']      ?? '';
    $entity     = $_POST['entity_type'] ?? '';
    $id         = (int)($_POST['id']    ?? 0);

    if ($id && in_array($entity, ['product','supplier','staff'])) {

        if ($action === 'restore') {
            match($entity) {
                'product'  => $product->restore($id),
                'supplier' => $supplier->restore($id),
                'staff'    => $userModel->restore($id),
            };
            $label = ucfirst($entity);
            flash('success', "$label restored successfully.");
            redirect(APP_URL . '/modules/archive/index.php');
        }

        if ($action === 'delete_permanent') {
            match($entity) {
                'product'  => $product->delete($id),
                'supplier' => $supplier->delete($id),
                'staff'    => $userModel->delete($id),
            };
            $label = ucfirst($entity);
            flash('success', "$label permanently deleted.");
            redirect(APP_URL . '/modules/archive/index.php');
        }
    }
}

$archivedProducts  = $product->getArchived();
$archivedSuppliers = $supplier->getArchived();
$archivedStaff     = $userModel->getArchived();

$totalArchived = count($archivedProducts) + count($archivedSuppliers) + count($archivedStaff);

require_once __DIR__ . '/../../includes/head.php';
require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/../../includes/topbar.php';
?>

<?php if ($msg = flash('success')): ?><div class="alert alert-success">✓ <?= e($msg) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger">⚠ <?= e($error) ?></div><?php endif; ?>

<div class="page-header">
  <div>
    <h1>Archive</h1>
    <p>Restore items or permanently delete them</p>
  </div>
  <div class="badge badge-blue" style="font-size:0.85rem;padding:6px 14px;">
    <?= $totalArchived ?> archived item<?= $totalArchived !== 1 ? 's' : '' ?>
  </div>
</div>

<!-- Tab Nav -->
<div style="display:flex;gap:4px;margin-bottom:20px;border-bottom:2px solid var(--clr-border);">
  <button class="arch-tab active" onclick="switchTab('products')">
     Products <span class="tab-count"><?= count($archivedProducts) ?></span>
  </button>
  <button class="arch-tab" onclick="switchTab('suppliers')">
     Suppliers <span class="tab-count"><?= count($archivedSuppliers) ?></span>
  </button>
  <button class="arch-tab" onclick="switchTab('staff')">
     Staff <span class="tab-count"><?= count($archivedStaff) ?></span>
  </button>
</div>

<style>
.arch-tab {
  background: none;
  border: none;
  padding: 10px 20px;
  font-size: 0.9rem;
  color: var(--clr-text-muted);
  cursor: pointer;
  border-bottom: 3px solid transparent;
  margin-bottom: -2px;
  font-weight: 500;
  transition: all .15s;
  display: flex;
  align-items: center;
  gap: 6px;
}
.arch-tab:hover { color: var(--clr-text); }
.arch-tab.active {
  color: var(--clr-primary);
  border-bottom-color: var(--clr-primary);
}
.tab-count {
  background: var(--clr-surface-2);
  color: var(--clr-text-muted);
  border-radius: 999px;
  padding: 1px 8px;
  font-size: 0.75rem;
}
.arch-tab.active .tab-count {
  background: var(--clr-primary);
  color: #fff;
}
.arch-section { display: none; }
.arch-section.active { display: block; }
.empty-arch {
  text-align: center;
  padding: 60px 20px;
  color: var(--clr-text-muted);
  font-size: 0.95rem;
}
.empty-arch .empty-icon { font-size: 2.5rem; margin-bottom: 10px; }
.btn-restore {
  background: var(--clr-success, #22c55e);
  color: #fff;
  border: none;
  border-radius: var(--radius);
  padding: 4px 12px;
  font-size: 0.8rem;
  cursor: pointer;
  font-weight: 600;
}
.btn-restore:hover { opacity: .85; }
.btn-perm-delete {
  background: none;
  border: 1px solid var(--clr-danger, #ef4444);
  color: var(--clr-danger, #ef4444);
  border-radius: var(--radius);
  padding: 4px 12px;
  font-size: 0.8rem;
  cursor: pointer;
  font-weight: 600;
}
.btn-perm-delete:hover { background: var(--clr-danger, #ef4444); color: #fff; }
.archived-date { font-size: 0.75rem; color: var(--clr-text-muted); }
</style>

<!-- Products Tab -->
<div id="tab-products" class="arch-section active">
  <div class="card">
    <div class="card-header">
      <div class="card-title">Archived Products (<?= count($archivedProducts) ?>)</div>
      <div class="search-wrap" style="width:220px">
        <span class="icon"></span>
        <input type="text" id="searchProducts" class="form-control" placeholder="Search…">
      </div>
    </div>
    <?php if (empty($archivedProducts)): ?>
      <div class="empty-arch">
        <div class="empty-icon"></div>
        No archived products
      </div>
    <?php else: ?>
    <div class="table-wrapper">
      <table>
        <thead>
          <tr><th>#</th><th>Product Name</th><th>Category</th><th>Size</th><th>Price</th><th>Stock</th><th>Archived</th><th>Actions</th></tr>
        </thead>
        <tbody id="bodyProducts">
          <?php foreach ($archivedProducts as $i => $p): ?>
            <tr>
              <td class="text-muted"><?= $i+1 ?></td>
              <td style="font-weight:600"><?= e($p['product_name']) ?></td>
              <td><span class="badge badge-blue"><?= e($p['category'] ?? '—') ?></span></td>
              <td><?= e($p['size']) ?></td>
              <td class="text-mono">₱<?= number_format($p['price'],2) ?></td>
              <td><?= $p['quantity'] ?></td>
              <td class="archived-date"><?= date('M d, Y', strtotime($p['archived_at'])) ?></td>
              <td>
                <div class="d-flex gap-8">
                  <form method="POST">
                    <input type="hidden" name="action" value="restore">
                    <input type="hidden" name="entity_type" value="product">
                    <input type="hidden" name="id" value="<?= $p['product_id'] ?>">
                    <button type="submit" class="btn-restore">↩ Restore</button>
                  </form>
                  <form method="POST" onsubmit="event.preventDefault();confirmPermDelete(this,'<?= e($p['product_name']) ?>')">
                    <input type="hidden" name="action" value="delete_permanent">
                    <input type="hidden" name="entity_type" value="product">
                    <input type="hidden" name="id" value="<?= $p['product_id'] ?>">
                    <button type="submit" class="btn-perm-delete">🗑 Delete</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Suppliers Tab -->
<div id="tab-suppliers" class="arch-section">
  <div class="card">
    <div class="card-header">
      <div class="card-title">Archived Suppliers (<?= count($archivedSuppliers) ?>)</div>
      <div class="search-wrap" style="width:220px">
        <span class="icon"></span>
        <input type="text" id="searchSuppliers" class="form-control" placeholder="Search…">
      </div>
    </div>
    <?php if (empty($archivedSuppliers)): ?>
      <div class="empty-arch">
        <div class="empty-icon"></div>
        No archived suppliers
      </div>
    <?php else: ?>
    <div class="table-wrapper">
      <table>
        <thead>
          <tr><th>#</th><th>Company Name</th><th>Contact</th><th>Email</th><th>Phone</th><th>Archived</th><th>Actions</th></tr>
        </thead>
        <tbody id="bodySuppliers">
          <?php foreach ($archivedSuppliers as $i => $s): ?>
            <tr>
              <td class="text-muted"><?= $i+1 ?></td>
              <td style="font-weight:600"><?= e($s['name']) ?></td>
              <td><?= e($s['contact']) ?></td>
              <td class="text-muted"><?= e($s['email']) ?></td>
              <td><?= e($s['phone']) ?></td>
              <td class="archived-date"><?= date('M d, Y', strtotime($s['archived_at'])) ?></td>
              <td>
                <div class="d-flex gap-8">
                  <form method="POST">
                    <input type="hidden" name="action" value="restore">
                    <input type="hidden" name="entity_type" value="supplier">
                    <input type="hidden" name="id" value="<?= $s['supplier_id'] ?>">
                    <button type="submit" class="btn-restore">↩ Restore</button>
                  </form>
                  <form method="POST" onsubmit="event.preventDefault();confirmPermDelete(this,'<?= e($s['name']) ?>')">
                    <input type="hidden" name="action" value="delete_permanent">
                    <input type="hidden" name="entity_type" value="supplier">
                    <input type="hidden" name="id" value="<?= $s['supplier_id'] ?>">
                    <button type="submit" class="btn-perm-delete">🗑 Delete</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Staff Tab -->
<div id="tab-staff" class="arch-section">
  <div class="card">
    <div class="card-header">
      <div class="card-title">Archived Staff (<?= count($archivedStaff) ?>)</div>
      <div class="search-wrap" style="width:220px">
        <span class="icon"></span>
        <input type="text" id="searchStaff" class="form-control" placeholder="Search…">
      </div>
    </div>
    <?php if (empty($archivedStaff)): ?>
      <div class="empty-arch">
        <div class="empty-icon"></div>
        No archived staff
      </div>
    <?php else: ?>
    <div class="table-wrapper">
      <table>
        <thead>
          <tr><th>#</th><th>Name</th><th>Email</th><th>Role</th><th>Archived</th><th>Actions</th></tr>
        </thead>
        <tbody id="bodyStaff">
          <?php foreach ($archivedStaff as $i => $u): ?>
            <tr>
              <td class="text-muted"><?= $i+1 ?></td>
              <td style="font-weight:600"><?= e($u['SFN'].' '.$u['SLN']) ?></td>
              <td class="text-muted"><?= e($u['email']) ?></td>
              <td><span class="badge badge-<?= $u['role']==='admin' ? 'red' : 'blue' ?>"><?= ucfirst($u['role']) ?></span></td>
              <td class="archived-date"><?= date('M d, Y', strtotime($u['archived_at'])) ?></td>
              <td>
                <div class="d-flex gap-8">
                  <form method="POST">
                    <input type="hidden" name="action" value="restore">
                    <input type="hidden" name="entity_type" value="staff">
                    <input type="hidden" name="id" value="<?= $u['staff_id'] ?>">
                    <button type="submit" class="btn-restore">↩ Restore</button>
                  </form>
                  <form method="POST" onsubmit="event.preventDefault();confirmPermDelete(this,'<?= e($u['SFN'].' '.$u['SLN']) ?>')">
                    <input type="hidden" name="action" value="delete_permanent">
                    <input type="hidden" name="entity_type" value="staff">
                    <input type="hidden" name="id" value="<?= $u['staff_id'] ?>">
                    <button type="submit" class="btn-perm-delete">🗑 Delete</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<script>
function switchTab(tab) {
  document.querySelectorAll('.arch-section').forEach(s => s.classList.remove('active'));
  document.querySelectorAll('.arch-tab').forEach(t => t.classList.remove('active'));
  document.getElementById('tab-' + tab).classList.add('active');
  event.currentTarget.classList.add('active');
}

function confirmPermDelete(form, name) {
  if (confirm('⚠️ Permanently delete "' + name + '"?\n\nThis action CANNOT be undone.')) {
    form.submit();
  }
}

// Search filters
document.getElementById('searchProducts').addEventListener('input', function() { filterTable(this, 'bodyProducts'); });
document.getElementById('searchSuppliers').addEventListener('input', function() { filterTable(this, 'bodySuppliers'); });
document.getElementById('searchStaff').addEventListener('input', function() { filterTable(this, 'bodyStaff'); });

function filterTable(input, bodyId) {
  const q = input.value.toLowerCase();
  document.querySelectorAll('#' + bodyId + ' tr').forEach(row => {
    row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
