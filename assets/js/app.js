/* ============================================================
   Company X Merch POS — Main JavaScript
   ============================================================ */

// ── Sidebar Toggle ──────────────────────────────────────────
(function () {
  const sidebar  = document.getElementById('sidebar');
  const wrapper  = document.getElementById('mainWrapper');
  const toggleBtn = document.getElementById('sidebarToggle');
  const COLLAPSED = 'collapsed';
  const LS_KEY = 'sidebarCollapsed';

  if (!sidebar) return;

  // Restore state
  if (localStorage.getItem(LS_KEY) === '1') {
    sidebar.classList.add(COLLAPSED);
    wrapper?.classList.add('sidebar-' + COLLAPSED);
  }

  toggleBtn?.addEventListener('click', () => {
    sidebar.classList.toggle(COLLAPSED);
    wrapper?.classList.toggle('sidebar-' + COLLAPSED);
    localStorage.setItem(LS_KEY, sidebar.classList.contains(COLLAPSED) ? '1' : '0');
  });
})();

// ── Dropdown Helper ─────────────────────────────────────────
function initDropdown(triggerSel, dropdownSel) {
  const trigger  = document.querySelector(triggerSel);
  const dropdown = document.querySelector(dropdownSel);
  if (!trigger || !dropdown) return;

  trigger.addEventListener('click', (e) => {
    e.stopPropagation();
    dropdown.classList.toggle('show');
  });

  document.addEventListener('click', () => dropdown.classList.remove('show'));
}

initDropdown('#profileBtn',  '#profileDropdown');
initDropdown('#notifBtn',    '#notifDropdown');

// ── Modal Helper ────────────────────────────────────────────
function openModal(id)  { document.getElementById(id)?.classList.add('show'); }
function closeModal(id) { document.getElementById(id)?.classList.remove('show'); }

document.addEventListener('click', (e) => {
  // Close modal only when clicking directly on the dark overlay background
  // (not on any child element inside .modal)
  if (e.target.classList.contains('modal-overlay') && !e.target.closest('.modal')) {
    e.target.classList.remove('show');
  }
  if (e.target.classList.contains('modal-close')) {
    e.target.closest('.modal-overlay')?.classList.remove('show');
  }
});

// ── Alert auto-dismiss ──────────────────────────────────────
document.querySelectorAll('.alert').forEach(el => {
  setTimeout(() => {
    el.style.transition = 'opacity .4s';
    el.style.opacity = '0';
    setTimeout(() => el.remove(), 400);
  }, 4000);
});

// ── Confirm Delete ──────────────────────────────────────────
function confirmDelete(form) {
  if (confirm('Move this item to the Archive?\n\nYou can restore or permanently delete it from the Archive section.')) {
    form.submit();
  }
}

// ── Number Format ───────────────────────────────────────────
function fmt(n) {
  return '₱' + parseFloat(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// ── POS System ──────────────────────────────────────────────
const POS = (function () {
  let cart = {}; // { product_id: { id, name, price, qty, stock } }

  function render() {
    const list  = document.getElementById('cartList');
    const empty = document.getElementById('cartEmpty');
    if (!list) return;

    const items = Object.values(cart);
    list.innerHTML = '';

    if (items.length === 0) {
      empty?.classList.remove('d-none');
    } else {
      empty?.classList.add('d-none');
      items.forEach(item => {
        const el = document.createElement('div');
        el.className = 'cart-item';
        el.innerHTML = `
          <div class="cart-item-info">
            <div class="ci-name">${escHtml(item.name)}</div>
            <div class="ci-price">${fmt(item.price)} each</div>
          </div>
          <div class="qty-control">
            <button onclick="POS.decrease(${item.id})">−</button>
            <span>${item.qty}</span>
            <button onclick="POS.increase(${item.id})">+</button>
          </div>
          <div class="ci-subtotal">${fmt(item.price * item.qty)}</div>
          <button class="btn-remove" onclick="POS.remove(${item.id})" title="Remove">✕</button>
        `;
        list.appendChild(el);
      });
    }

    // Update totals
    const total = items.reduce((s, i) => s + i.price * i.qty, 0);
    const totalEl = document.getElementById('cartTotal');
    if (totalEl) totalEl.textContent = fmt(total);

    // Update tile badges
    document.querySelectorAll('.product-tile').forEach(tile => {
      const pid = parseInt(tile.dataset.id);
      const badge = tile.querySelector('.qty-badge');
      if (cart[pid]) {
        if (!badge) {
          const b = document.createElement('div');
          b.className = 'qty-badge';
          tile.appendChild(b);
        }
        tile.querySelector('.qty-badge').textContent = cart[pid].qty;
      } else {
        badge?.remove();
      }
    });

    // Compute change
    computeChange();

    // Update hidden input
    const cartInput = document.getElementById('cartData');
    if (cartInput) cartInput.value = JSON.stringify(items);
  }

  function computeChange() {
    const payEl    = document.getElementById('paymentInput');
    const changeEl = document.getElementById('changeAmt');
    const totalEl  = document.getElementById('cartTotal');
    if (!payEl || !changeEl || !totalEl) return;

    const total   = Object.values(cart).reduce((s, i) => s + i.price * i.qty, 0);
    const payment = parseFloat(payEl.value) || 0;
    const change  = payment - total;

    changeEl.textContent = change >= 0 ? fmt(change) : '—';
    changeEl.style.color = change >= 0 ? 'var(--clr-success)' : 'var(--clr-danger)';
  }

  function add(id, name, price, stock) {
    if (cart[id]) {
      if (cart[id].qty >= stock) { toast('Not enough stock!', 'warning'); return; }
      cart[id].qty++;
    } else {
      cart[id] = { id, name, price, qty: 1, stock };
    }
    render();
  }

  function increase(id) {
    if (!cart[id]) return;
    if (cart[id].qty >= cart[id].stock) { toast('Max stock reached!', 'warning'); return; }
    cart[id].qty++;
    render();
  }

  function decrease(id) {
    if (!cart[id]) return;
    cart[id].qty--;
    if (cart[id].qty <= 0) delete cart[id];
    render();
  }

  function remove(id) {
    delete cart[id];
    render();
  }

  function clear() {
    cart = {};
    render();
  }

  function getTotal() {
    return Object.values(cart).reduce((s, i) => s + i.price * i.qty, 0);
  }

  return { add, increase, decrease, remove, clear, computeChange, getTotal };
})();

// ── Toast Notification ──────────────────────────────────────
function toast(msg, type = 'success') {
  const container = document.getElementById('toastContainer') || (() => {
    const c = document.createElement('div');
    c.id = 'toastContainer';
    Object.assign(c.style, {
      position: 'fixed', bottom: '24px', right: '24px',
      display: 'flex', flexDirection: 'column', gap: '8px', zIndex: '9999'
    });
    document.body.appendChild(c);
    return c;
  })();

  const colors = { success: '#22c55e', danger: '#ef4444', warning: '#f59e0b', info: '#3b82f6' };
  const icons  = { success: '✓', danger: '✕', warning: '⚠', info: 'ℹ' };

  const t = document.createElement('div');
  Object.assign(t.style, {
    background: '#fff', border: `1px solid ${colors[type]}`,
    borderLeft: `4px solid ${colors[type]}`,
    borderRadius: '10px', padding: '12px 16px',
    boxShadow: '0 4px 16px rgba(0,0,0,.12)',
    display: 'flex', alignItems: 'center', gap: '10px',
    fontSize: '.84rem', fontWeight: '500', color: '#1e293b',
    animation: 'slideUp .2s ease', minWidth: '220px', maxWidth: '320px'
  });
  t.innerHTML = `<span style="color:${colors[type]};font-weight:700">${icons[type]}</span> ${escHtml(msg)}`;
  container.appendChild(t);

  setTimeout(() => {
    t.style.opacity = '0'; t.style.transition = 'opacity .3s';
    setTimeout(() => t.remove(), 300);
  }, 3000);
}

function escHtml(str) {
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Search / Filter Table ───────────────────────────────────
function filterTable(inputId, tableBodyId) {
  const input = document.getElementById(inputId);
  const tbody = document.getElementById(tableBodyId);
  if (!input || !tbody) return;

  input.addEventListener('input', () => {
    const q = input.value.toLowerCase();
    tbody.querySelectorAll('tr').forEach(tr => {
      tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
  });
}

// ── POS Product Search ──────────────────────────────────────
const posSearch = document.getElementById('posSearch');
if (posSearch) {
  posSearch.addEventListener('input', () => {
    const q = posSearch.value.toLowerCase();
    document.querySelectorAll('.product-tile').forEach(tile => {
      tile.style.display = tile.dataset.search?.includes(q) ? '' : 'none';
    });
  });
}

// ── Stock-in Item Rows ──────────────────────────────────────
let stockRowCount = 1;
function addStockRow() {
  stockRowCount++;
  const container = document.getElementById('stockRows');
  if (!container) return;
  const products  = window.PRODUCTS || [];
  const opts = products.map(p => `<option value="${p.id}">${escHtml(p.name)}</option>`).join('');
  const row = document.createElement('div');
  row.className = 'form-row stock-row mb-16';
  row.innerHTML = `
    <div class="form-group mb-0">
      <select name="product_id[]" class="form-control" required>
        <option value="">Select product</option>${opts}
      </select>
    </div>
    <div class="form-group mb-0">
      <input type="number" name="qty[]" class="form-control" placeholder="Qty" min="1" required>
    </div>
    <div class="form-group mb-0">
      <input type="number" name="cost_price[]" class="form-control" placeholder="Cost ₱" step="0.01" min="0" required>
    </div>
    <div class="form-group mb-0">
      <button type="button" class="btn btn-ghost btn-icon" onclick="this.closest('.stock-row').remove()">✕</button>
    </div>
  `;
  container.appendChild(row);
}
