<?php
require_once __DIR__ . '/../../config/app.php';
requireLogin();

$pageTitle   = 'Online Orders';
$currentPage = 'online_orders';

$orderModel    = new OnlineOrder();
$productModel  = new Product();
$customerModel = new Customer();

// ── AJAX: customer search
if (isset($_GET['customer_search'])) {
    header('Content-Type: application/json');
    $q = trim($_GET['customer_search'] ?? '');
    echo json_encode($q ? $customerModel->search($q) : []);
    exit;
}

// ── AJAX: order items
if (isset($_GET['order_items'])) {
    header('Content-Type: application/json');
    echo json_encode($orderModel->getItems((int)$_GET['order_items']));
    exit;
}

// ── POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'register_customer') {
        header('Content-Type: application/json');
        $CFN = trim($_POST['CFN'] ?? ''); $CLN = trim($_POST['CLN'] ?? ''); $contact = trim($_POST['contact'] ?? '');
        if (!$CFN || !$CLN) { echo json_encode(['success'=>false,'message'=>'Name required.']); exit; }
        $id = $customerModel->create($CFN, $CLN, $contact);
        echo json_encode($id ? ['success'=>true,'customer_id'=>$id,'full_name'=>"$CFN $CLN",'contact_number'=>$contact]
                              : ['success'=>false,'message'=>'Failed.']);
        exit;
    }

    if ($action === 'create_order') {
        $customerId = (int)($_POST['customer_id'] ?? 0);
        $address    = trim($_POST['delivery_address'] ?? '');
        $payMethod  = in_array($_POST['payment_method'] ?? '', ['cod','paid_online']) ? $_POST['payment_method'] : 'cod';
        $notes      = trim($_POST['notes'] ?? '');
        $items      = json_decode($_POST['items'] ?? '[]', true);
        if (!$customerId || !$address || empty($items)) {
            flash('error', 'Customer, delivery address and at least one item are required.');
        } else {
            $mapped = array_map(fn($i) => ['product_id'=>(int)$i['product_id'],'qty'=>(int)$i['qty'],'price'=>(float)$i['price']], $items);
            $id = $orderModel->create($customerId, (int)$_SESSION['user_id'], $address, $payMethod, $notes, $mapped);
            flash($id ? 'success' : 'error', $id ? 'Order #'.str_pad($id,4,'0',STR_PAD_LEFT).' created.' : 'Failed to create order.');
        }
        redirect(APP_URL . '/modules/online_orders/index.php');
    }

    if ($action === 'update_status') {
        $id = (int)($_POST['order_id'] ?? 0); $status = $_POST['order_status'] ?? '';
        $valid = ['pending','confirmed','shipped','delivered','cancelled'];
        if ($id && in_array($status, $valid)) { $orderModel->updateStatus($id, $status); flash('success','Order status updated.'); }
        redirect(APP_URL . '/modules/online_orders/index.php');
    }

    if ($action === 'mark_paid') {
        $id = (int)($_POST['order_id'] ?? 0);
        if ($id) { $orderModel->updatePaymentStatus($id,'paid'); flash('success','Marked as paid.'); }
        redirect(APP_URL . '/modules/online_orders/index.php');
    }
}

$filters = [
    'status'         => $_GET['status']         ?? '',
    'payment_status' => $_GET['payment_status'] ?? '',
    'from'           => $_GET['from']           ?? '',
    'to'             => $_GET['to']             ?? '',
    'search'         => $_GET['search']         ?? '',
];

$orders   = $orderModel->getAll($filters);
$counts   = $orderModel->countByStatus();
$products = $productModel->getAll();
$baseUrl  = APP_URL . '/modules/online_orders/index.php';

require_once __DIR__ . '/../../includes/head.php';
require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/../../includes/topbar.php';

$statusColors = ['pending'=>'badge-yellow','confirmed'=>'badge-blue','shipped'=>'badge-purple','delivered'=>'badge-green','cancelled'=>'badge-red'];
?>

<?php if ($msg = flash('success')): ?><div class="alert alert-success">✓ <?= e($msg) ?></div><?php endif; ?>
<?php if ($msg = flash('error')):   ?><div class="alert alert-danger">⚠ <?= e($msg) ?></div><?php endif; ?>

<style>
.badge-yellow { background:rgba(234,179,8,.15); color:#a16207; }
.badge-purple { background:rgba(168,85,247,.15); color:#7e22ce; }
.status-btn { background:none; border:1px solid var(--clr-border); border-radius:var(--radius); padding:3px 10px; font-size:.78rem; cursor:pointer; font-weight:600; }
.status-btn:hover { background:var(--clr-bg); }
.order-stat-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(130px,1fr)); gap:12px; margin-bottom:20px; }
.order-stat { background:var(--clr-surface); border:1px solid var(--clr-border); border-radius:var(--radius); padding:14px 16px; text-align:center; }
.order-stat .os-count { font-size:1.6rem; font-weight:800; line-height:1; }
.order-stat .os-label { font-size:.72rem; color:var(--clr-text-muted); margin-top:4px; font-weight:600; text-transform:uppercase; }
.filter-bar { display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end; }

/* Floating dropdown */
#noFloatDD {
  position:fixed; background:var(--clr-surface); border:1px solid var(--clr-border);
  border-radius:var(--radius); box-shadow:0 8px 28px rgba(0,0,0,.18);
  z-index:99999; max-height:240px; overflow-y:auto; display:none; min-width:280px;
}
#noFloatDD.open { display:block; }
.no-dd-item { padding:10px 14px; cursor:pointer; display:flex; justify-content:space-between; align-items:center; font-size:.85rem; border-bottom:1px solid var(--clr-border); user-select:none; }
.no-dd-item:last-child { border-bottom:none; }
.no-dd-item:hover { background:var(--clr-bg); }
.no-dd-item .di-name { font-weight:600; }
.no-dd-item .di-contact { font-size:.75rem; color:var(--clr-text-muted); }
.no-dd-reg { color:var(--clr-primary); font-weight:600; justify-content:flex-start; gap:6px; }
.no-dd-reg:hover { background:rgba(99,102,241,.08); }
</style>

<div class="page-header">
  <div><h1>Online Orders</h1><p>Manage customer delivery orders</p></div>
  <button class="btn btn-primary" id="btnNewOrder">+ New Order</button>
</div>

<div class="order-stat-grid">
  <?php foreach (['pending'=>'','confirmed'=>'','shipped'=>'','delivered'=>'','cancelled'=>''] as $s => $icon): ?>
    <div class="order-stat">
      <div class="os-count"><?= $counts[$s] ?></div>
      <div class="os-label"><?= $icon ?> <?= ucfirst($s) ?></div>
    </div>
  <?php endforeach; ?>
</div>

<div class="card" style="margin-bottom:16px;">
  <form method="GET" class="filter-bar" style="padding:14px 16px;">
    <div class="form-group mb-0"><label class="form-label">Search</label><input type="text" name="search" class="form-control" placeholder="Customer or address…" value="<?= e($filters['search']) ?>" style="width:200px;"></div>
    <div class="form-group mb-0"><label class="form-label">Status</label><select name="status" class="form-control"><option value="">All</option><?php foreach (['pending','confirmed','shipped','delivered','cancelled'] as $s): ?><option value="<?= $s ?>" <?= $filters['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option><?php endforeach; ?></select></div>
    <div class="form-group mb-0"><label class="form-label">Payment</label><select name="payment_status" class="form-control"><option value="">All</option><option value="unpaid" <?= $filters['payment_status']==='unpaid'?'selected':'' ?>>Unpaid (COD)</option><option value="paid" <?= $filters['payment_status']==='paid'?'selected':'' ?>>Paid</option></select></div>
    <div class="form-group mb-0"><label class="form-label">From</label><input type="date" name="from" class="form-control" value="<?= e($filters['from']) ?>"></div>
    <div class="form-group mb-0"><label class="form-label">To</label><input type="date" name="to" class="form-control" value="<?= e($filters['to']) ?>"></div>
    <div><button type="submit" class="btn btn-primary">Filter</button> <a href="?" class="btn btn-ghost">Reset</a></div>
  </form>
</div>

<div class="card">
  <div class="card-header"><div class="card-title">Orders (<?= count($orders) ?>)</div></div>
  <div class="table-wrapper">
    <?php if (empty($orders)): ?>
      <div class="empty-state"><div class="icon"></div>No orders found.</div>
    <?php else: ?>
      <table>
        <thead><tr><th>Order #</th><th>Customer</th><th>Delivery Address</th><th>Total</th><th>Payment</th><th>Status</th><th>Date</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($orders as $o): ?>
          <tr>
            <td><span class="badge badge-blue">#<?= str_pad($o['order_id'],4,'0',STR_PAD_LEFT) ?></span></td>
            <td><div style="font-weight:600"><?= e($o['customer_name']) ?></div><div style="font-size:.72rem;color:var(--clr-text-muted)"><?= e($o['contact_number']??'') ?></div></td>
            <td style="max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= e($o['delivery_address']) ?></td>
            <td class="text-mono" style="font-weight:700">₱<?= number_format($o['total_amount'],2) ?></td>
            <td>
              <div><span class="badge <?= $o['payment_method']==='paid_online'?'badge-blue':'badge-yellow' ?>"><?= $o['payment_method']==='paid_online'?'Paid Online':'COD' ?></span></div>
              <div style="margin-top:3px">
                <?php if ($o['payment_status']==='paid'): ?>
                  <span class="badge badge-green">✓ Paid</span>
                <?php else: ?>
                  <form method="POST" style="display:inline"><input type="hidden" name="action" value="mark_paid"><input type="hidden" name="order_id" value="<?= $o['order_id'] ?>"><button type="submit" class="status-btn" style="color:var(--clr-warning);border-color:var(--clr-warning)">Mark Paid</button></form>
                <?php endif; ?>
              </div>
            </td>
            <td>
              <form method="POST" style="display:flex;gap:4px;align-items:center;">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="order_id" value="<?= $o['order_id'] ?>">
                <select name="order_status" class="form-control" style="padding:4px 8px;font-size:.78rem;width:auto;" onchange="this.form.submit()">
                  <?php foreach (['pending','confirmed','shipped','delivered','cancelled'] as $s): ?>
                    <option value="<?= $s ?>" <?= $o['order_status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                  <?php endforeach; ?>
                </select>
              </form>
            </td>
            <td class="text-muted" style="font-size:.78rem"><?= date('M d, Y', strtotime($o['ordered_at'])) ?><br><?= date('h:i A', strtotime($o['ordered_at'])) ?></td>
            <td><button class="btn btn-ghost btn-sm" data-view-order="<?= $o['order_id'] ?>">👁 View</button></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

<!-- Floating customer dropdown — on body -->
<div id="noFloatDD"></div>

<!-- New Order Modal -->
<div class="modal-overlay" id="newOrderModal">
  <div class="modal" style="max-width:640px;">
    <div class="modal-header"><div class="modal-title">New Online Order</div><button class="modal-close">✕</button></div>
    <div class="modal-body">
      <div id="noErrMsg" class="alert alert-danger" style="display:none;"></div>
      <div class="form-group">
        <label class="form-label">Customer *</label>
        <input type="text" id="noCustSearch" class="form-control" placeholder="Type a name or contact number…" autocomplete="off">
        <input type="hidden" id="noCustomerId">
        <div id="noSelectedCust" style="display:none;margin-top:6px;padding:8px 12px;background:rgba(99,102,241,.07);border:1px solid var(--clr-primary);border-radius:var(--radius);display:flex;justify-content:space-between;align-items:center;">
          <span id="noSelectedCustName" style="font-weight:600;font-size:.85rem;"></span>
          <button type="button" id="noClearCustBtn" style="background:none;border:none;cursor:pointer;color:var(--clr-text-muted);">✕</button>
        </div>
        <button type="button" id="btnRegNewCust" class="btn btn-ghost btn-sm" style="margin-top:6px;">＋ Register new customer</button>
      </div>
      <div class="form-group"><label class="form-label">Delivery Address *</label><textarea id="noAddress" class="form-control" rows="2" placeholder="Full delivery address…"></textarea></div>
      <div class="form-group"><label class="form-label">Payment Method</label><select id="noPayMethod" class="form-control"><option value="cod">Cash on Delivery (COD)</option><option value="paid_online">Paid Online</option></select></div>
      <div class="form-group">
        <label class="form-label">Order Items *</label>
        <div style="display:flex;gap:8px;margin-bottom:8px;">
          <select id="noProductSel" class="form-control">
            <option value="">— Select product —</option>
            <?php foreach ($products as $p): ?>
              <option value="<?= $p['product_id'] ?>" data-name="<?= e($p['product_name'].' ('.$p['size'].')') ?>" data-price="<?= $p['price'] ?>">
                <?= e($p['product_name']) ?> (<?= e($p['size']) ?>) — ₱<?= number_format($p['price'],2) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <input type="number" id="noQty" class="form-control" value="1" min="1" style="width:80px;">
          <button type="button" id="btnAddItem" class="btn btn-primary btn-sm">Add</button>
        </div>
        <div id="noItemsList"></div>
        <div style="text-align:right;font-weight:700;margin-top:8px;">Total: <span id="noTotal" style="font-family:var(--font-mono);color:var(--clr-accent);">₱0.00</span></div>
      </div>
      <div class="form-group mb-0"><label class="form-label">Notes</label><textarea id="noNotes" class="form-control" rows="2" placeholder="Special instructions, landmark, etc."></textarea></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" id="btnCancelOrder">Cancel</button>
      <button class="btn btn-primary" id="btnSubmitOrder">Create Order</button>
    </div>
  </div>
</div>

<!-- Register Customer Modal -->
<div class="modal-overlay" id="noRegCustModal">
  <div class="modal" style="max-width:400px;">
    <div class="modal-header"><div class="modal-title">👤 Register Customer</div><button class="modal-close">✕</button></div>
    <div class="modal-body">
      <div id="noRegErr" class="alert alert-danger" style="display:none;"></div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">First Name *</label><input type="text" id="noRegCFN" class="form-control"></div>
        <div class="form-group"><label class="form-label">Last Name *</label><input type="text" id="noRegCLN" class="form-control"></div>
      </div>
      <div class="form-group mb-0"><label class="form-label">Contact Number</label><input type="text" id="noRegContact" class="form-control" placeholder="09XXXXXXXXX"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" id="btnCancelReg">Cancel</button>
      <button class="btn btn-primary" id="btnDoRegister">Register &amp; Select</button>
    </div>
  </div>
</div>

<!-- View Order Modal -->
<div class="modal-overlay" id="viewOrderModal">
  <div class="modal modal-lg">
    <div class="modal-header"><div class="modal-title" id="voTitle">Order Details</div><button class="modal-close">✕</button></div>
    <div class="modal-body" id="voBody"><div class="empty-state">Loading…</div></div>
    <div class="modal-footer"><button class="btn btn-ghost" id="btnCloseView">Close</button></div>
  </div>
</div>

<!-- Hidden submit form -->
<form id="noForm" method="POST" style="display:none;">
  <input type="hidden" name="action"           value="create_order">
  <input type="hidden" name="customer_id"      id="nfCustomerId">
  <input type="hidden" name="delivery_address" id="nfAddress">
  <input type="hidden" name="payment_method"   id="nfPayMethod">
  <input type="hidden" name="notes"            id="nfNotes">
  <input type="hidden" name="items"            id="nfItems">
</form>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

<script>
(function () {
  'use strict';

  var BASE_URL   = '<?= $baseUrl ?>';
  var noItems    = [];
  var noTimer    = null;
  var noFloatDD  = document.getElementById('noFloatDD');
  var noCustInp  = document.getElementById('noCustSearch');

  /* ── Helpers ── */
  function esc(s) { var d=document.createElement('div'); d.textContent=String(s); return d.innerHTML; }

  /* ── Floating dropdown ── */
  function showDD(html) {
    noFloatDD.innerHTML = html;
    var rect = noCustInp.getBoundingClientRect();
    noFloatDD.style.top   = (rect.bottom + window.scrollY + 4) + 'px';
    noFloatDD.style.left  = rect.left + 'px';
    noFloatDD.style.width = rect.width + 'px';
    noFloatDD.classList.add('open');
  }
  function hideDD() { noFloatDD.classList.remove('open'); }

  /* ── Customer search ── */
  noCustInp.addEventListener('input', function () {
    clearTimeout(noTimer);
    var q = this.value.trim();
    if (!q) { hideDD(); return; }
    showDD('<div style="padding:10px;font-size:.82rem;color:var(--clr-text-muted);">Searching…</div>');
    noTimer = setTimeout(function () {
      fetch(BASE_URL + '?customer_search=' + encodeURIComponent(q))
        .then(function(r){ return r.json(); })
        .then(function(res){ buildDD(res, q); })
        .catch(function(){ hideDD(); });
    }, 300);
  });

  noCustInp.addEventListener('keydown', function(e){ if(e.key==='Escape') hideDD(); });

  function buildDD(results, query) {
    var html = '';
    if (results.length) {
      results.forEach(function(c) {
        html += '<div class="no-dd-item" data-id="'+c.customer_id+'" data-name="'+esc(c.full_name)+'" data-contact="'+esc(c.contact_number||'')+'">'+
                '<span class="di-name">'+esc(c.full_name)+'</span>'+
                '<span class="di-contact">'+esc(c.contact_number||'—')+'</span>'+
                '</div>';
      });
    } else {
      html += '<div style="padding:10px;font-size:.82rem;color:var(--clr-text-muted);">No results found.</div>';
    }
    html += '<div class="no-dd-item no-dd-reg" id="ddRegBtn">＋ Register "'+esc(query)+'" as new customer</div>';
    showDD(html);

    // Attach events after DOM insert
    noFloatDD.querySelectorAll('.no-dd-item[data-id]').forEach(function(el) {
      el.addEventListener('click', function(e) {
        e.stopPropagation();
        selectCust(parseInt(this.dataset.id), this.dataset.name, this.dataset.contact);
      });
    });
    var regBtn = document.getElementById('ddRegBtn');
    if (regBtn) {
      regBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        hideDD();
        openRegModal(query);
      });
    }
  }

  // Close on outside click
  document.addEventListener('click', function(e) {
    if (e.target !== noCustInp && !noFloatDD.contains(e.target)) hideDD();
  });

  /* ── Select / clear customer ── */
  function selectCust(id, name, contact) {
    document.getElementById('noCustomerId').value = id;
    document.getElementById('noSelectedCustName').textContent = name + (contact ? ' · ' + contact : '');
    document.getElementById('noSelectedCust').style.display = 'flex';
    noCustInp.style.display = 'none';
    hideDD();
  }

  document.getElementById('noClearCustBtn').addEventListener('click', function() {
    document.getElementById('noCustomerId').value = '';
    document.getElementById('noSelectedCust').style.display = 'none';
    noCustInp.style.display = '';
    noCustInp.value = '';
    noCustInp.focus();
  });

  /* ── Register modal ── */
  function openRegModal(prefill) {
    document.getElementById('noRegErr').style.display = 'none';
    var parts = (prefill||'').trim().split(/\s+/);
    document.getElementById('noRegCFN').value     = parts[0] || '';
    document.getElementById('noRegCLN').value     = parts.slice(1).join(' ') || '';
    document.getElementById('noRegContact').value = '';
    openModal('noRegCustModal');
  }

  document.getElementById('btnRegNewCust').addEventListener('click', function() {
    openRegModal(noCustInp.value.trim());
  });

  document.getElementById('btnCancelReg').addEventListener('click', function(e) {
    e.stopPropagation();
    closeModal('noRegCustModal');
  });

  document.getElementById('btnDoRegister').addEventListener('click', function(e) {
    e.stopPropagation();
    var CFN     = document.getElementById('noRegCFN').value.trim();
    var CLN     = document.getElementById('noRegCLN').value.trim();
    var contact = document.getElementById('noRegContact').value.trim();
    var errEl   = document.getElementById('noRegErr');
    if (!CFN || !CLN) { errEl.textContent='First and last name are required.'; errEl.style.display=''; return; }
    errEl.style.display = 'none';

    this.disabled = true;
    this.textContent = 'Saving…';
    var self = this;

    fetch(BASE_URL, {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded'},
      body: 'action=register_customer&CFN='+encodeURIComponent(CFN)+'&CLN='+encodeURIComponent(CLN)+'&contact='+encodeURIComponent(contact)
    })
    .then(function(r){ return r.json(); })
    .then(function(data) {
      self.disabled = false;
      self.textContent = 'Register & Select';
      if (data.success) {
        closeModal('noRegCustModal');
        selectCust(data.customer_id, data.full_name, data.contact_number || '');
        toast('Customer registered!', 'success');
      } else {
        errEl.textContent = data.message || 'Error.';
        errEl.style.display = '';
      }
    })
    .catch(function(){
      self.disabled = false;
      self.textContent = 'Register & Select';
      errEl.textContent = 'Network error. Please try again.';
      errEl.style.display = '';
    });
  });

  /* ── Order items ── */
  document.getElementById('btnAddItem').addEventListener('click', function() {
    var sel   = document.getElementById('noProductSel');
    var qty   = parseInt(document.getElementById('noQty').value) || 1;
    var pid   = parseInt(sel.value);
    if (!pid) return;
    var opt   = sel.options[sel.selectedIndex];
    var price = parseFloat(opt.dataset.price);
    var name  = opt.dataset.name;
    var existing = noItems.find(function(i){ return i.product_id === pid; });
    if (existing) { existing.qty += qty; } else { noItems.push({product_id:pid, name:name, price:price, qty:qty}); }
    renderItems();
  });

  function renderItems() {
    var container = document.getElementById('noItemsList');
    if (!noItems.length) {
      container.innerHTML = '<div style="color:var(--clr-text-muted);font-size:.82rem;padding:4px;">No items added yet.</div>';
      document.getElementById('noTotal').textContent = '₱0.00';
      return;
    }
    container.innerHTML = noItems.map(function(i) {
      return '<div style="display:flex;align-items:center;gap:10px;padding:8px 10px;background:var(--clr-bg);border-radius:var(--radius);margin-bottom:4px;">'+
        '<span style="flex:1;font-size:.84rem;font-weight:600;">'+esc(i.name)+'</span>'+
        '<span style="font-size:.8rem;color:var(--clr-text-muted);">×'+i.qty+'</span>'+
        '<span style="font-family:var(--font-mono);font-weight:700;">₱'+(i.price*i.qty).toFixed(2)+'</span>'+
        '<button type="button" data-rm="'+i.product_id+'" style="background:none;border:none;cursor:pointer;color:var(--clr-danger);font-size:1rem;">✕</button>'+
      '</div>';
    }).join('');
    // Remove buttons
    container.querySelectorAll('[data-rm]').forEach(function(btn) {
      btn.addEventListener('click', function() {
        var pid = parseInt(this.dataset.rm);
        noItems = noItems.filter(function(i){ return i.product_id !== pid; });
        renderItems();
      });
    });
    var total = noItems.reduce(function(s,i){ return s+i.price*i.qty; }, 0);
    document.getElementById('noTotal').textContent = '₱' + total.toFixed(2);
  }

  /* ── New order modal ── */
  document.getElementById('btnNewOrder').addEventListener('click', function() {
    // Reset form
    noCustInp.value = ''; noCustInp.style.display = '';
    document.getElementById('noCustomerId').value = '';
    document.getElementById('noSelectedCust').style.display = 'none';
    document.getElementById('noAddress').value = '';
    document.getElementById('noNotes').value = '';
    document.getElementById('noErrMsg').style.display = 'none';
    noItems = []; renderItems();
    openModal('newOrderModal');
  });

  document.getElementById('btnCancelOrder').addEventListener('click', function() {
    closeModal('newOrderModal');
  });

  /* ── Submit order ── */
  document.getElementById('btnSubmitOrder').addEventListener('click', function() {
    var custId  = document.getElementById('noCustomerId').value;
    var address = document.getElementById('noAddress').value.trim();
    var errEl   = document.getElementById('noErrMsg');
    if (!custId)        { errEl.textContent='Please select a customer.';     errEl.style.display=''; return; }
    if (!address)       { errEl.textContent='Delivery address is required.'; errEl.style.display=''; return; }
    if (!noItems.length){ errEl.textContent='Add at least one item.';        errEl.style.display=''; return; }
    errEl.style.display = 'none';
    document.getElementById('nfCustomerId').value = custId;
    document.getElementById('nfAddress').value    = address;
    document.getElementById('nfPayMethod').value  = document.getElementById('noPayMethod').value;
    document.getElementById('nfNotes').value      = document.getElementById('noNotes').value;
    document.getElementById('nfItems').value      = JSON.stringify(noItems.map(function(i){ return {product_id:i.product_id,qty:i.qty,price:i.price}; }));
    document.getElementById('noForm').submit();
  });

  /* ── View order ── */
  document.addEventListener('click', function(e) {
    var btn = e.target.closest('[data-view-order]');
    if (!btn) return;
    var id = parseInt(btn.dataset.viewOrder);
    document.getElementById('voTitle').textContent = 'Order #' + String(id).padStart(4,'0');
    document.getElementById('voBody').innerHTML = '<div class="empty-state">Loading…</div>';
    openModal('viewOrderModal');
    fetch(BASE_URL + '?order_items=' + id)
      .then(function(r){ return r.json(); })
      .then(function(items) {
        if (!items.length) { document.getElementById('voBody').innerHTML='<div class="empty-state">No items found.</div>'; return; }
        var total=0, html='<table><thead><tr><th>Product</th><th>Category</th><th>Size</th><th>Price</th><th>Qty</th><th>Subtotal</th></tr></thead><tbody>';
        items.forEach(function(i){ var sub=i.quantity*i.price; total+=sub;
          html+='<tr><td style="font-weight:600">'+esc(i.product_name)+'</td><td>'+esc(i.category||'—')+'</td><td>'+esc(i.size)+'</td><td class="text-mono">₱'+parseFloat(i.price).toFixed(2)+'</td><td>'+i.quantity+'</td><td class="text-mono" style="font-weight:700">₱'+sub.toFixed(2)+'</td></tr>';
        });
        html+='</tbody><tfoot><tr style="background:var(--clr-bg)"><td colspan="5" style="text-align:right;font-weight:700">Total</td><td class="text-mono" style="font-weight:800;color:var(--clr-accent)">₱'+total.toFixed(2)+'</td></tr></tfoot></table>';
        document.getElementById('voBody').innerHTML = html;
      })
      .catch(function(){ document.getElementById('voBody').innerHTML='<div class="empty-state">Failed to load.</div>'; });
  });

  document.getElementById('btnCloseView').addEventListener('click', function() {
    closeModal('viewOrderModal');
  });

})();
</script>
