<?php
require_once __DIR__ . '/../../config/app.php';
requireAdmin();

$pageTitle   = 'Users';
$currentPage = 'users';

$userModel = new User();
$error     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action   = $_POST['action'] ?? '';
    $SFN      = trim($_POST['SFN']      ?? '');
    $SLN      = trim($_POST['SLN']      ?? '');
    $email    = trim($_POST['email']    ?? '');
    $role     = in_array($_POST['role'] ?? '', ['admin','employee']) ? $_POST['role'] : 'employee';
    $password = $_POST['password'] ?? '';

    if ($action === 'create') {
        if (!$SFN || !$SLN || !$email || !$password) {
            $error = 'All fields are required.';
        } else {
            try {
                $userModel->create($SFN, $SLN, $email, $password, $role);
                flash('success', "Staff \"{$SFN} {$SLN}\" created.");
                redirect(APP_URL . '/modules/users/index.php');
            } catch (PDOException $e) {
                $error = 'Email already exists.';
            }
        }
    }

    if ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id && $SFN && $SLN && $email) {
            if ($id === (int)$_SESSION['user_id'] && $role !== 'admin') {
                $error = 'You cannot demote yourself.';
            } else {
                $userModel->update($id, $SFN, $SLN, $email, $role, $password);
                flash('success', 'Staff updated.');
                redirect(APP_URL . '/modules/users/index.php');
            }
        } else {
            $error = 'SFN, SLN and email are required.';
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id === (int)$_SESSION['user_id']) {
            $error = 'You cannot archive your own account.';
        } elseif ($id) {
            $userModel->archive($id);
            flash('success', 'Staff moved to archive.');
            redirect(APP_URL . '/modules/users/index.php');
        }
    }
}

$users = $userModel->getAll();

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
  <div><h1>Staff / Users</h1><p>Manage system staff accounts and roles</p></div>
  <button class="btn btn-primary" onclick="openModal('addUserModal')">+ Add Staff</button>
</div>

<div class="card">
  <div class="table-wrapper">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>SFN (First Name)</th>
          <th>SLN (Last Name)</th>
          <th>Email</th>
          <th>Role</th>
          <th>Created</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $i => $u): ?>
          <tr>
            <td class="text-muted"><?= $i + 1 ?></td>
            <td>
              <div class="d-flex align-center gap-8">
                <div style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,#3b82f6,#8b5cf6);display:grid;place-items:center;font-size:.75rem;font-weight:700;color:#fff;flex-shrink:0;">
                  <?= strtoupper(substr($u['SFN'], 0, 1)) ?>
                </div>
                <span style="font-weight:600"><?= e($u['SFN']) ?></span>
                <?php if ($u['staff_id'] === (int)$_SESSION['user_id']): ?>
                  <span class="badge badge-blue" style="font-size:.65rem">You</span>
                <?php endif; ?>
              </div>
            </td>
            <td style="font-weight:600"><?= e($u['SLN']) ?></td>
            <td class="text-muted"><?= e($u['email']) ?></td>
            <td>
              <span class="badge <?= $u['role'] === 'admin' ? 'badge-purple' : 'badge-gray' ?>">
                <?= ucfirst($u['role']) ?>
              </span>
            </td>
            <td class="text-muted"><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
            <td>
              <div class="d-flex gap-8">
                <button class="btn btn-ghost btn-sm"
                  onclick="openEditUser(<?= htmlspecialchars(json_encode([
                    'id'    => $u['staff_id'],
                    'SFN'   => $u['SFN'],
                    'SLN'   => $u['SLN'],
                    'email' => $u['email'],
                    'role'  => $u['role'],
                  ]), ENT_QUOTES) ?>)">
                  Edit
                </button>
                <?php if ($u['staff_id'] !== (int)$_SESSION['user_id']): ?>
                  <form method="POST" onsubmit="event.preventDefault();confirmDelete(this)">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $u['staff_id'] ?>">
                    <button class="btn btn-danger btn-sm" type="submit">Delete</button>
                  </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add Staff Modal -->
<div class="modal-overlay" id="addUserModal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">+ Add Staff</div>
      <button class="modal-close">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="create">
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">SFN — Staff First Name *</label>
            <input type="text" name="SFN" class="form-control" placeholder="e.g. Seth" required>
          </div>
          <div class="form-group">
            <label class="form-label">SLN — Staff Last Name *</label>
            <input type="text" name="SLN" class="form-control" placeholder="e.g. Decena" required>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Email *</label>
          <input type="email" name="email" class="form-control" required>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Password *</label>
            <input type="password" name="password" class="form-control" required minlength="6">
          </div>
          <div class="form-group">
            <label class="form-label">Role *</label>
            <select name="role" class="form-control">
              <option value="employee">Employee</option>
              <option value="admin">Admin</option>
            </select>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('addUserModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Create Staff</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Staff Modal -->
<div class="modal-overlay" id="editUserModal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">✏️ Edit Staff</div>
      <button class="modal-close">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" id="euId">
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">SFN — Staff First Name *</label>
            <input type="text" name="SFN" id="euSFN" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">SLN — Staff Last Name *</label>
            <input type="text" name="SLN" id="euSLN" class="form-control" required>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Email *</label>
          <input type="email" name="email" id="euEmail" class="form-control" required>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">New Password <small class="text-muted">(leave blank to keep)</small></label>
            <input type="password" name="password" class="form-control" minlength="6">
          </div>
          <div class="form-group">
            <label class="form-label">Role</label>
            <select name="role" id="euRole" class="form-control">
              <option value="employee">Employee</option>
              <option value="admin">Admin</option>
            </select>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('editUserModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Update Staff</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEditUser(u) {
  document.getElementById('euId').value    = u.id;
  document.getElementById('euSFN').value   = u.SFN;
  document.getElementById('euSLN').value   = u.SLN;
  document.getElementById('euEmail').value = u.email;
  document.getElementById('euRole').value  = u.role;
  openModal('editUserModal');
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
