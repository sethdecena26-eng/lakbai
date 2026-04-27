<?php
require_once __DIR__ . '/../../config/app.php';
requireLogin();

$customerModel = new Customer();

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['customer_search'])) {
    header('Content-Type: application/json');
    $q = trim($_GET['customer_search'] ?? '');
    echo json_encode($q ? $customerModel->search($q) : []);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'register_customer') {
    header('Content-Type: application/json');
    $CFN = trim($_POST['CFN'] ?? ''); $CLN = trim($_POST['CLN'] ?? ''); $contact = trim($_POST['contact'] ?? '');
    if (!$CFN || !$CLN) { echo json_encode(['success'=>false,'message'=>'First and last name are required.']); exit; }
    $id = $customerModel->create($CFN, $CLN, $contact);
    echo $id ? json_encode(['success'=>true,'customer_id'=>$id,'full_name'=>trim("$CFN $CLN"),'contact_number'=>$contact])
             : json_encode(['success'=>false,'message'=>'Failed to register customer.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'checkout') {
    header('Content-Type: application/json');
    $items = json_decode($_POST['cart'] ?? '[]', true);
    $payment = (float)($_POST['payment'] ?? 0);
    $method = in_array($_POST['method'] ?? '', ['cash','digital']) ? $_POST['method'] : 'cash';
    $customerId = ($_POST['customer_id'] ?? '') !== '' ? (int)$_POST['customer_id'] : null;
    if (empty($items)) { echo json_encode(['success'=>false,'message'=>'Cart is empty.']); exit; }
    $total = array_sum(array_map(fn($i) => $i['price'] * $i['qty'], $items));
    if ($payment < $total) { echo json_encode(['success'=>false,'message'=>'Insufficient payment.']); exit; }
    $customerName = 'Walk-in Customer';
    if ($customerId) { $c = $customerModel->getById($customerId); if ($c) $customerName = trim($c['CFN'].' '.$c['CLN']); }
    $saleModel = new Sale();
    $saleItems = array_map(fn($i) => ['product_id'=>(int)$i['id'],'qty'=>(int)$i['qty'],'price'=>(float)$i['price']], $items);
    $purchaseId = $saleModel->create((int)$_SESSION['user_id'], $total, $payment, $method, $saleItems, $customerId);
    echo $purchaseId ? json_encode(['success'=>true,'sale_id'=>$purchaseId,'total'=>$total,'payment'=>$payment,'change'=>$payment-$total,'customer_name'=>$customerName])
                     : json_encode(['success'=>false,'message'=>'Failed to save transaction.']);
    exit;
}

$pageTitle = 'POS — Point of Sale';
$currentPage = 'pos';
$product = new Product();
$products = $product->getForPOS();
$posUrl = APP_URL . '/modules/pos/index.php';

require_once __DIR__ . '/../../includes/head.php';
require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/../../includes/topbar.php';
?>

<style>
/* Customer bar — fixed, no dropdown inside card */
.customer-bar {
  display:flex; align-items:center; gap:8px; padding:10px 14px;
  background:var(--clr-surface); border:1px solid var(--clr-border);
  border-radius:var(--radius); margin-bottom:10px; flex-shrink:0;
}
.customer-bar .cust-label { font-size:.75rem; font-weight:600; color:var(--clr-text-muted); white-space:nowrap; }
.customer-bar input { flex:1; border:none; background:transparent; font-size:.85rem; color:var(--clr-text); outline:none; min-width:0; }
.customer-bar input::placeholder { color:var(--clr-text-muted); }
.customer-bar .cust-clear { background:none; border:none; cursor:pointer; color:var(--clr-text-muted); font-size:.9rem; padding:2px 4px; display:none; }
.customer-bar.has-customer { border-color:var(--clr-primary); background:rgba(99,102,241,.06); }
.customer-bar.has-customer .cust-label { color:var(--clr-primary); }
.customer-bar.has-customer .cust-clear { display:inline; }

/* Floating dropdown — appended to body, positioned via JS */
#custFloatDropdown {
  position:fixed;
  background:var(--clr-surface); border:1px solid var(--clr-border);
  border-radius:var(--radius); box-shadow:0 8px 28px rgba(0,0,0,.18);
  z-index:9999; max-height:240px; overflow-y:auto; display:none;
  min-width:260px;
}
#custFloatDropdown.open { display:block; }
.cust-option { padding:10px 14px; cursor:pointer; display:flex; justify-content:space-between; align-items:center; font-size:.85rem; border-bottom:1px solid var(--clr-border); user-select:none; }
.cust-option:last-child { border-bottom:none; }
.cust-option:hover, .cust-option:active { background:var(--clr-bg); }
.cust-option .co-name { font-weight:600; }
.cust-option .co-contact { font-size:.75rem; color:var(--clr-text-muted); }
.cust-option.new-cust { color:var(--clr-primary); font-weight:600; justify-content:flex-start; gap:6px; }
.cust-option.new-cust:hover { background:rgba(99,102,241,.08); }
.cust-spinner { text-align:center; padding:12px; color:var(--clr-text-muted); font-size:.82rem; }
</style>

<div class="pos-layout">
  <div class="pos-products">
    <div class="card" style="flex:1;display:flex;flex-direction:column;">
      <div class="card-header" style="flex-shrink:0">
        <div>
          <div class="card-title">Products</div>
          <div class="card-subtitle">Click a product to add to cart</div>
        </div>
        <div class="search-wrap" style="width:220px;">
          <span class="icon"></span>
          <input type="text" id="posSearch" class="form-control" placeholder="Search products…">
        </div>
      </div>
      <div class="product-grid" id="productGrid" style="flex:1;overflow-y:auto;max-height:calc(100vh - 220px);">
        <?php if (empty($products)): ?>
          <div class="empty-state" style="grid-column:1/-1">No products available.</div>
        <?php else: ?>
          <?php foreach ($products as $p): ?>
            <div class="product-tile <?= $p['quantity'] <= 0 ? 'out-of-stock' : '' ?>"
                 data-id="<?= (int)$p['product_id'] ?>"
                 data-name="<?= e($p['product_name']) ?>"
                 data-price="<?= (float)$p['price'] ?>"
                 data-stock="<?= (int)$p['quantity'] ?>"
                 data-search="<?= strtolower(e($p['product_name']).' '.e($p['category']??'').' '.e($p['size'])) ?>">
              <div class="cat-badge"><?= e($p['category'] ?? 'General') ?></div>
              <div class="p-name"><?= e($p['product_name']) ?></div>
              <div style="font-size:.72rem;color:var(--clr-text-muted);margin-bottom:2px;">Size: <?= e($p['size']) ?></div>
              <div class="p-price">₱<?= number_format($p['price'],2) ?></div>
              <div class="p-qty"><?= $p['quantity'] > 0 ? $p['quantity'].' in stock' : 'Out of stock' ?></div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="cart-panel">
    <div class="card" style="height:100%;display:flex;flex-direction:column;">
      <div class="card-header" style="flex-shrink:0">
        <div><div class="card-subtitle" id="cartCount">0 items</div></div>
        <button class="btn btn-ghost btn-sm" onclick="posCartClear()">Clear All</button>
      </div>

      <div class="customer-bar" id="customerBar">
        <span class="cust-label" id="custLabel">👤 Customer</span>
        <input type="text" id="custSearch" placeholder="Search or add customer…" autocomplete="off">
        <button class="cust-clear" onclick="posClearCustomer()" title="Remove">✕</button>
      </div>

      <div class="cart-items" style="flex:1;overflow-y:auto;">
        <div id="cartEmpty" class="empty-state">Cart is empty.<br>Click a product to add.</div>
        <div id="cartList"></div>
      </div>

      <div style="flex-shrink:0;margin-top:12px;">
        <div class="cart-totals">
          <div class="row total">
            <span>Total</span>
            <span id="cartTotal" style="font-family:var(--font-mono)">₱0.00</span>
          </div>
        </div>
        <div style="margin-top:14px;display:flex;flex-direction:column;gap:10px;">
          <div class="form-group mb-0">
            <label class="form-label">Payment Method</label>
            <select id="paymentMethod" class="form-control">
              <option value="cash">Cash</option>
              <option value="digital">Other</option>
            </select>
          </div>
          <div class="form-group mb-0">
            <label class="form-label">Amount Paid (₱)</label>
            <input type="number" id="paymentInput" class="form-control" placeholder="0.00" min="0" step="0.01" oninput="posComputeChange()">
          </div>
          <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 12px;background:var(--clr-bg);border-radius:8px;">
            <span style="font-size:.82rem;font-weight:600;">Change</span>
            <span id="changeAmt" style="font-family:var(--font-mono);font-weight:700;font-size:1rem;">—</span>
          </div>
          <button class="btn btn-success w-100" style="justify-content:center;padding:12px;font-size:.9rem;" onclick="posCheckout()">
            ✓ Complete Sale
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Floating customer dropdown — lives on body, NOT inside card -->
<div id="custFloatDropdown"></div>

<!-- Receipt Modal -->
<div class="modal-overlay" id="receiptModal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">🧾 Sale Receipt</div>
      <button class="modal-close">✕</button>
    </div>
    <div class="modal-body"><div class="receipt-box" id="receiptContent"></div></div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="closeModal('receiptModal');posCartClear();">New Sale</button>
      <button class="btn btn-primary" onclick="window.print()">🖨 Print</button>
    </div>
  </div>
</div>

<!-- Register Customer Modal -->
<div class="modal-overlay" id="registerCustModal">
  <div class="modal" style="max-width:420px;">
    <div class="modal-header">
      <div class="modal-title">👤 Register New Customer</div>
      <button class="modal-close">✕</button>
    </div>
    <div class="modal-body">
      <div id="regCustError" class="alert alert-danger" style="display:none;"></div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">First Name *</label>
          <input type="text" id="regCFN" class="form-control" placeholder="Juan">
        </div>
        <div class="form-group">
          <label class="form-label">Last Name *</label>
          <input type="text" id="regCLN" class="form-control" placeholder="Dela Cruz">
        </div>
      </div>
      <div class="form-group mb-0">
        <label class="form-label">Contact Number</label>
        <input type="text" id="regContact" class="form-control" placeholder="09XXXXXXXXX">
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" id="btnCancelRegister">Cancel</button>
      <button class="btn btn-primary" id="btnDoRegister">Register &amp; Select</button>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

<script>
(function () {
  'use strict';
  const POS_URL = '<?= $posUrl ?>';
  const posCart = {};
  let posCustomer = null;
  let custTimer = null;
  let dropdownVisible = false;

  /* ── Format ── */
  function peso(n) { return '₱' + parseFloat(n).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g,','); }

  /* ── Dropdown (body-level, positioned via getBoundingClientRect) ── */
  const floatDD = document.getElementById('custFloatDropdown');
  const custInp = document.getElementById('custSearch');
  const custBar = document.getElementById('customerBar');
  const custLbl = document.getElementById('custLabel');

  function showDropdown(html) {
    floatDD.innerHTML = html;
    const rect = custInp.getBoundingClientRect();
    floatDD.style.left  = rect.left + 'px';
    floatDD.style.top   = (rect.bottom + window.scrollY + 4) + 'px';
    floatDD.style.width = custBar.offsetWidth + 'px';
    floatDD.classList.add('open');
    dropdownVisible = true;
  }

  function hideDropdown() {
    floatDD.classList.remove('open');
    dropdownVisible = false;
  }

  custInp.addEventListener('input', function () {
    clearTimeout(custTimer);
    const q = this.value.trim();
    if (!q) { hideDropdown(); return; }
    showDropdown('<div class="cust-spinner">Searching…</div>');
    custTimer = setTimeout(function () {
      fetch(POS_URL + '?customer_search=' + encodeURIComponent(q))
        .then(function(r){ return r.json(); })
        .then(function(res){ buildDropdown(res, q); })
        .catch(function(){ hideDropdown(); });
    }, 300);
  });

  custInp.addEventListener('keydown', function(e){ if(e.key==='Escape') hideDropdown(); });

  function buildDropdown(results, query) {
    var html = '';
    results.forEach(function(c) {
      html += '<div class="cust-option" data-id="'+c.customer_id+'" data-name=\''+encodeURI(c.full_name)+'\' data-contact=\''+encodeURI(c.contact_number||'')+'\'>'+
              '<span class="co-name">'+esc(c.full_name)+'</span>'+
              '<span class="co-contact">'+esc(c.contact_number||'—')+'</span>'+
              '</div>';
    });
    html += '<div class="cust-option new-cust" id="ddRegister">＋ Register "'+esc(query)+'" as new customer</div>';
    showDropdown(html);

    // Attach events AFTER inserting into DOM
    floatDD.querySelectorAll('.cust-option[data-id]').forEach(function(el) {
      el.addEventListener('click', function() {
        posSelectCustomer(
          parseInt(this.dataset.id),
          decodeURI(this.dataset.name),
          decodeURI(this.dataset.contact)
        );
      });
    });
    var regBtn = document.getElementById('ddRegister');
    if (regBtn) {
      regBtn.addEventListener('click', function() {
        hideDropdown();
        posOpenRegister(query);
      });
    }
  }

  // Hide dropdown on outside click
  document.addEventListener('click', function(e) {
    if (!custBar.contains(e.target) && !floatDD.contains(e.target)) {
      hideDropdown();
    }
  });

  /* ── Select customer ── */
  window.posSelectCustomer = function(id, name, contact) {
    posCustomer = { customer_id: id, full_name: name, contact_number: contact };
    custInp.value = name;
    custLbl.textContent = '✓ Customer';
    custBar.classList.add('has-customer');
    custInp.readOnly = true;
    hideDropdown();
  };

  window.posClearCustomer = function() {
    posCustomer = null;
    custInp.value = '';
    custInp.readOnly = false;
    custLbl.textContent = '👤 Customer';
    custBar.classList.remove('has-customer');
    hideDropdown();
  };

  /* ── Register modal ── */
  window.posOpenRegister = function(prefill) {
    document.getElementById('regCustError').style.display = 'none';
    var parts = (prefill||'').trim().split(/\s+/);
    document.getElementById('regCFN').value = parts[0] || '';
    document.getElementById('regCLN').value = parts.slice(1).join(' ') || '';
    document.getElementById('regContact').value = '';
    openModal('registerCustModal');
  };

  document.getElementById('btnCancelRegister').addEventListener('click', function() {
    closeModal('registerCustModal');
  });

  document.getElementById('btnDoRegister').addEventListener('click', function() {
    var CFN     = document.getElementById('regCFN').value.trim();
    var CLN     = document.getElementById('regCLN').value.trim();
    var contact = document.getElementById('regContact').value.trim();
    var errEl   = document.getElementById('regCustError');
    if (!CFN || !CLN) {
      errEl.textContent = 'First and last name are required.';
      errEl.style.display = '';
      return;
    }
    errEl.style.display = 'none';

    // Disable button to prevent double-submit
    this.disabled = true;
    this.textContent = 'Saving…';
    var self = this;

    fetch(POS_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'action=register_customer&CFN=' + encodeURIComponent(CFN) +
            '&CLN=' + encodeURIComponent(CLN) +
            '&contact=' + encodeURIComponent(contact)
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      self.disabled = false;
      self.textContent = 'Register & Select';
      if (data.success) {
        closeModal('registerCustModal');
        posSelectCustomer(data.customer_id, data.full_name, data.contact_number || '');
        toast('Customer registered!', 'success');
      } else {
        errEl.textContent = data.message || 'Registration failed.';
        errEl.style.display = '';
      }
    })
    .catch(function() {
      self.disabled = false;
      self.textContent = 'Register & Select';
      errEl.textContent = 'Network error. Please try again.';
      errEl.style.display = '';
    });
  });

  // Remove old window.posRegisterCustomer — no longer needed

  /* ── Cart ── */
  function cartRender() {
    var list  = document.getElementById('cartList');
    var empty = document.getElementById('cartEmpty');
    var items = Object.values(posCart);
    if (!items.length) {
      list.innerHTML = ''; empty.style.display = '';
      document.getElementById('cartTotal').textContent = '₱0.00';
      document.getElementById('cartCount').textContent = '0 items';
      posComputeChange(); updateBadges(); return;
    }
    empty.style.display = 'none';
    list.innerHTML = items.map(function(item) {
      return '<div class="cart-item">'+
        '<div class="cart-item-info"><div class="ci-name">'+esc(item.name)+'</div><div class="ci-price">'+peso(item.price)+' each</div></div>'+
        '<div class="qty-control">'+
          '<button type="button" onclick="posDecrease('+item.id+')">−</button>'+
          '<span>'+item.qty+'</span>'+
          '<button type="button" onclick="posIncrease('+item.id+')">+</button>'+
        '</div>'+
        '<div class="ci-subtotal">'+peso(item.price*item.qty)+'</div>'+
        '<button type="button" class="btn-remove" onclick="posRemove('+item.id+')">✕</button>'+
      '</div>';
    }).join('');
    var total = items.reduce(function(s,i){return s+i.price*i.qty;},0);
    var count = items.reduce(function(s,i){return s+i.qty;},0);
    document.getElementById('cartTotal').textContent = peso(total);
    document.getElementById('cartCount').textContent = count+' item'+(count!==1?'s':'');
    posComputeChange(); updateBadges();
  }

  function updateBadges() {
    document.querySelectorAll('.product-tile').forEach(function(tile) {
      var id = parseInt(tile.dataset.id);
      var badge = tile.querySelector('.qty-badge');
      if (posCart[id]) {
        if (!badge) { badge=document.createElement('div'); badge.className='qty-badge'; tile.appendChild(badge); }
        badge.textContent = posCart[id].qty;
      } else { if(badge) badge.remove(); }
    });
  }

  window.posIncrease = function(id) {
    if (!posCart[id]) return;
    if (posCart[id].qty >= posCart[id].stock) { toast('Max stock reached!','warning'); return; }
    posCart[id].qty++; cartRender();
  };
  window.posDecrease = function(id) {
    if (!posCart[id]) return;
    posCart[id].qty--; if (posCart[id].qty<=0) delete posCart[id]; cartRender();
  };
  window.posRemove = function(id) { delete posCart[id]; cartRender(); };
  window.posCartClear = function() {
    Object.keys(posCart).forEach(function(k){delete posCart[k];});
    document.getElementById('paymentInput').value=''; cartRender();
  };
  window.posComputeChange = function() {
    var total   = Object.values(posCart).reduce(function(s,i){return s+i.price*i.qty;},0);
    var payment = parseFloat(document.getElementById('paymentInput').value)||0;
    var el      = document.getElementById('changeAmt');
    if (payment<=0){el.textContent='—';el.style.color='';return;}
    var change = payment-total;
    el.textContent = peso(change);
    el.style.color = change>=0?'var(--clr-success)':'var(--clr-danger)';
  };

  document.getElementById('productGrid').addEventListener('click', function(e) {
    var tile = e.target.closest('.product-tile');
    if (!tile||tile.classList.contains('out-of-stock')) return;
    var id=parseInt(tile.dataset.id), name=tile.dataset.name, price=parseFloat(tile.dataset.price), stock=parseInt(tile.dataset.stock);
    if (posCart[id]) {
      if (posCart[id].qty>=stock){toast('Not enough stock!','warning');return;}
      posCart[id].qty++;
    } else { posCart[id]={id,name,price,qty:1,stock}; }
    cartRender();
  });

  /* ── Checkout ── */
  window.posCheckout = function() {
    var items   = Object.values(posCart);
    var total   = items.reduce(function(s,i){return s+i.price*i.qty;},0);
    var payment = parseFloat(document.getElementById('paymentInput').value)||0;
    var method  = document.getElementById('paymentMethod').value;
    if (!items.length)   {toast('Cart is empty!','warning');return;}
    if (payment<=0)      {toast('Enter payment amount!','warning');return;}
    if (payment<total)   {toast('Insufficient payment!','danger');return;}
    var payload = items.map(function(i){return{id:i.id,name:i.name,price:i.price,qty:i.qty};});
    var custId  = posCustomer ? posCustomer.customer_id : '';
    fetch(POS_URL,{
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body:'action=checkout&cart='+encodeURIComponent(JSON.stringify(payload))+'&payment='+payment+'&method='+method+'&customer_id='+custId
    })
    .then(function(r){return r.json();})
    .then(function(data){
      if (data.success){
        posShowReceipt(data,payload,method);
        toast('Sale #'+String(data.sale_id).padStart(4,'0')+' saved!','success');
        posClearCustomer();
      } else { toast(data.message||'Error.','danger'); }
    })
    .catch(function(){toast('Network error.','danger');});
  };

  function posShowReceipt(data, items, method) {
    var date = new Date().toLocaleString('en-PH');
    var rows = items.map(function(i){return'<div class="r-row"><span>'+esc(i.name)+' ×'+i.qty+'</span><span>₱'+(i.price*i.qty).toFixed(2)+'</span></div>';}).join('');
    var custLine = (data.customer_name && data.customer_name!=='Walk-in Customer')
      ? '<div style="font-size:.78rem;margin-top:2px;">Customer: <strong>'+esc(data.customer_name)+'</strong></div>'
      : '<div style="font-size:.78rem;margin-top:2px;color:#999;">Walk-in Customer</div>';
    document.getElementById('receiptContent').innerHTML =
      '<div class="r-header"><strong>LakBai Salakay Merch</strong><br><small>'+date+'</small><br><small>Sale #'+String(data.sale_id).padStart(4,'0')+'</small></div>'+
      custLine+'<hr>'+rows+'<hr>'+
      '<div class="r-row r-total"><span>TOTAL</span><span>₱'+data.total.toFixed(2)+'</span></div>'+
      '<div class="r-row"><span>Payment ('+method+')</span><span>₱'+data.payment.toFixed(2)+'</span></div>'+
      '<div class="r-row"><span>Change</span><span>₱'+data.change.toFixed(2)+'</span></div>'+
      '<hr><div style="text-align:center;font-size:.72rem;margin-top:8px;">Thank you!</div>';
    openModal('receiptModal');
  }

  function esc(s){ var d=document.createElement('div');d.textContent=String(s);return d.innerHTML; }

})();
</script>
