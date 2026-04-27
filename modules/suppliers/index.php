<?php
require_once __DIR__ . '/../../config/app.php';
requireAdmin();

$pageTitle   = 'Suppliers';
$currentPage = 'suppliers';

$supplier = new Supplier();
$error    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action']  ?? '';
    $name    = trim($_POST['name']    ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $email   = trim($_POST['email']   ?? '');
    $phone   = trim($_POST['phone']   ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($action === 'create' && $name) {
        $supplier->create($name, $contact, $email, $phone, $address);
        flash('success', "Supplier \"{$name}\" added.");
        redirect(APP_URL . '/modules/suppliers/index.php');
    }
    if ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id && $name) {
            $supplier->update($id, $name, $contact, $email, $phone, $address);
            flash('success', 'Supplier updated.');
            redirect(APP_URL . '/modules/suppliers/index.php');
        }
    }
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $supplier->archive($id);
            flash('success', 'Supplier moved to archive.');
            redirect(APP_URL . '/modules/suppliers/index.php');
        }
    }
}

$suppliers = $supplier->getAll();

require_once __DIR__ . '/../../includes/head.php';
require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/../../includes/topbar.php';
?>

<?php if ($msg = flash('success')): ?>
  <div class="alert alert-success">✓ <?= e($msg) ?></div>
<?php endif; ?>
<?php if ($error): ?>
  <div class="alert alert-danger">⚠ <?= e($error) ?></div>
<?php endif; ?>

<div class="page-header">
  <div><h1>Suppliers</h1><p>Manage your vendors and suppliers</p></div>
  <button class="btn btn-primary" onclick="openModal('addSupplierModal')">+ Add Supplier</button>
</div>

<div class="card">
  <div class="card-header">
    <div class="card-title">All Suppliers (<?= count($suppliers) ?>)</div>
    <div class="search-wrap" style="width:220px">
      <input type="text" id="supSearch" class="form-control" placeholder="Search…">
    </div>
  </div>
  <div class="table-wrapper">
    <table>
      <thead>
        <tr><th>#</th><th>Company Name</th><th>Contact Person</th><th>Email</th><th>Phone</th><th>Address</th><th>Actions</th></tr>
      </thead>
      <tbody id="supBody">
        <?php if (empty($suppliers)): ?>
          <tr><td colspan="7"><div class="empty-state">No suppliers yet.</div></td></tr>
        <?php endif; ?>
        <?php foreach ($suppliers as $i => $s): ?>
          <tr>
            <td class="text-muted"><?= $i + 1 ?></td>
            <td style="font-weight:600"><?= e($s['name']) ?></td>
            <td><?= e($s['contact']) ?></td>
            <td class="text-muted"><?= e($s['email']) ?></td>
            <td><?= e($s['phone']) ?></td>
            <td class="text-muted"><?= e($s['address']) ?></td>
            <td>
              <div class="d-flex gap-8">
                <button class="btn btn-ghost btn-sm"
                  onclick="openEditSupplier(<?= htmlspecialchars(json_encode($s), ENT_QUOTES) ?>)">
                   Edit
                </button>
                <form method="POST" onsubmit="event.preventDefault();confirmDelete(this)">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= $s['supplier_id'] ?>">
                  <button class="btn btn-danger btn-sm" type="submit">Delete</button>
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
<div class="modal-overlay" id="addSupplierModal">
  <div class="modal">
    <div class="modal-header"><div class="modal-title">+ Add Supplier</div><button class="modal-close">✕</button></div>
    <form method="POST">
      <input type="hidden" name="action" value="create">
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Company Name *</label>
            <input type="text" name="name" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">Contact Person</label>
            <input type="text" name="contact" class="form-control">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control">
          </div>
          <div class="form-group">
            <label class="form-label">Phone</label>
            <input type="text" name="phone" class="form-control">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Address</label>
          <textarea name="address" class="form-control" rows="2"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('addSupplierModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Supplier</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="editSupplierModal">
  <div class="modal">
    <div class="modal-header"><div class="modal-title">Edit Supplier</div><button class="modal-close">✕</button></div>
    <form method="POST">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" id="esId">
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Company Name *</label>
            <input type="text" name="name" id="esName" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">Contact Person</label>
            <input type="text" name="contact" id="esContact" class="form-control">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Email</label>
            <input type="email" name="email" id="esEmail" class="form-control">
          </div>
          <div class="form-group">
            <label class="form-label">Phone</label>
            <input type="text" name="phone" id="esPhone" class="form-control">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Address</label>
          <textarea name="address" id="esAddress" class="form-control" rows="2"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('editSupplierModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Update Supplier</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEditSupplier(s) {
  document.getElementById('esId').value      = s.supplier_id;
  document.getElementById('esName').value    = s.name;
  document.getElementById('esContact').value = s.contact  || '';
  document.getElementById('esEmail').value   = s.email    || '';
  document.getElementById('esPhone').value   = s.phone    || '';
  document.getElementById('esAddress').value = s.address  || '';
  openModal('editSupplierModal');
}
document.getElementById('supSearch').addEventListener('input', function() {
  filterTable('supSearch', 'supBody');
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
