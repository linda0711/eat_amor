<?php
define('ROOT', dirname(__DIR__));
define('BASE_URL', 'http://localhost/eat_amor');
require_once ROOT . '/config/database.php';
require_once ROOT . '/config/session.php';
requireEmpleado();

$pdo        = getDB();
$usuario    = usuarioActual();
$idSucursal = sucursalActual();
$sucursalNombre = $pdo->query("SELECT nombre FROM sucursales WHERE id_sucursal=$idSucursal")->fetchColumn();

$categorias = $pdo->query("SELECT * FROM menu_categorias ORDER BY orden")->fetchAll();
$items = $pdo->query("
    SELECT mi.*, mc.nombre AS categoria
    FROM menu_items mi
    JOIN menu_categorias mc ON mc.id_menu_cat = mi.id_menu_cat
    WHERE mi.disponible = 1
    ORDER BY mc.orden, mi.nombre
")->fetchAll();

$ventasHoy = $pdo->query("
    SELECT v.id_venta, v.total, v.fecha,
           COUNT(vd.id_detalle) AS num_items
    FROM ventas v
    JOIN venta_detalle vd ON vd.id_venta = v.id_venta
    WHERE v.id_sucursal = $idSucursal AND DATE(v.fecha) = CURDATE()
    GROUP BY v.id_venta
    ORDER BY v.fecha DESC LIMIT 10
")->fetchAll();

$totalHoy = $pdo->query("SELECT COALESCE(SUM(total),0) FROM ventas WHERE id_sucursal=$idSucursal AND DATE(fecha)=CURDATE()")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Punto de Venta — Eat Amor</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Nunito:wght@300;400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
  <style>
    /* ── Layout POS ──────────────────────────────────────── */
    .pos-layout { display:grid; grid-template-columns:1fr 380px; gap:20px; height:calc(100vh - 80px); }
    .pos-left   { display:flex; flex-direction:column; overflow:hidden; }
    .pos-right  { display:flex; flex-direction:column; gap:12px; overflow:hidden; }

    /* ── Menú ────────────────────────────────────────────── */
    .menu-search { margin-bottom:12px; }
    .cat-tabs    { display:flex; gap:7px; flex-wrap:wrap; margin-bottom:14px; }
    .cat-tab     { padding:6px 14px; border-radius:99px; border:1.5px solid #eee; background:#fff;
                   font-size:12px; font-weight:700; cursor:pointer; transition:all .2s;
                   font-family:'Nunito',sans-serif; white-space:nowrap; }
    .cat-tab:hover  { border-color:var(--rosa); color:var(--rosa); }
    .cat-tab.activo { background:var(--negro); border-color:var(--negro); color:var(--dorado); }
    .menu-grid   { display:grid; grid-template-columns:repeat(auto-fill,minmax(150px,1fr)); gap:10px;
                   overflow-y:auto; flex:1; padding-bottom:10px; }
    .menu-card   { background:#fff; border-radius:10px; padding:12px; box-shadow:0 2px 8px rgba(0,0,0,.07);
                   cursor:pointer; transition:all .2s; border:2px solid transparent; user-select:none; }
    .menu-card:hover  { border-color:var(--rosa); transform:translateY(-2px); box-shadow:0 4px 16px rgba(233,30,140,.15); }
    .menu-card:active { transform:scale(.97); }
    .card-cat    { font-size:10px; color:#aaa; margin-bottom:4px; text-transform:uppercase; letter-spacing:.4px; }
    .card-name   { font-size:13px; font-weight:700; color:var(--negro); margin-bottom:4px; line-height:1.3; }
    .card-price  { font-size:15px; font-weight:700; color:var(--rojo); }

    /* ── Tickets (pestañas) ──────────────────────────────── */
    .tickets-tabs { display:flex; gap:6px; align-items:center; flex-wrap:wrap; }
    .ticket-tab   { padding:6px 14px; border-radius:8px 8px 0 0; font-size:13px; font-weight:700;
                    cursor:pointer; border:1.5px solid #eee; border-bottom:none;
                    background:#f5f5f5; color:#666; font-family:'Nunito',sans-serif;
                    transition:all .2s; white-space:nowrap; }
    .ticket-tab.activo { background:#fff; color:var(--negro); border-color:#ddd; }
    .ticket-tab .close-tab { margin-left:6px; color:#aaa; font-size:11px; }
    .ticket-tab .close-tab:hover { color:var(--rojo); }
    .btn-new-ticket { padding:5px 10px; border-radius:8px; border:1.5px dashed #ccc;
                      background:none; cursor:pointer; font-size:18px; color:#aaa;
                      transition:all .2s; line-height:1; }
    .btn-new-ticket:hover { border-color:var(--rosa); color:var(--rosa); }

    /* ── Ticket panel ────────────────────────────────────── */
    .ticket-panel { background:#fff; border-radius:0 12px 12px 12px; box-shadow:0 4px 20px rgba(0,0,0,.1);
                    display:flex; flex-direction:column; flex:1; overflow:hidden; border:1.5px solid #eee; }
    .ticket-header { background:var(--negro); color:var(--beige); padding:14px 18px; }
    .ticket-header h3 { font-family:'Playfair Display',serif; font-size:16px; }
    .ticket-header p  { font-size:11px; color:var(--gris-400); margin-top:2px; }
    .ticket-items { flex:1; overflow-y:auto; padding:10px; }
    .ticket-item  { display:flex; align-items:center; gap:8px; padding:9px 8px;
                    border-bottom:1px solid #f0f0f0; }
    .item-name    { flex:1; font-size:13px; font-weight:600; }
    .item-note    { font-size:11px; color:var(--rosa); font-weight:400; }
    .qty-controls { display:flex; align-items:center; gap:5px; }
    .qty-btn      { width:24px; height:24px; border-radius:50%; border:none; cursor:pointer;
                    font-size:14px; font-weight:700; display:flex; align-items:center; justify-content:center; }
    .qty-btn.minus { background:#fee; color:var(--rojo); }
    .qty-btn.plus  { background:#efe; color:var(--exito); }
    .qty-num       { font-weight:700; font-size:13px; min-width:18px; text-align:center; }
    .item-price    { font-size:13px; color:var(--rojo); font-weight:700; white-space:nowrap; min-width:60px; text-align:right; }

    /* ── Totales y acciones ──────────────────────────────── */
    .ticket-totals { padding:14px 18px; border-top:2px solid #eee; }
    .total-row     { display:flex; justify-content:space-between; font-size:14px; margin-bottom:6px; }
    .desc-row      { display:flex; align-items:center; gap:8px; margin-bottom:8px; }
    .desc-input    { flex:1; padding:6px 10px; border:1.5px solid #eee; border-radius:6px;
                     font-family:'Nunito',sans-serif; font-size:13px; }
    .desc-input:focus { outline:none; border-color:var(--rosa); }
    .total-final   { font-family:'Playfair Display',serif; font-size:24px; font-weight:700; color:var(--negro); }
    .ticket-actions { padding:12px 18px; border-top:1px solid #eee; display:flex; gap:8px; }

    /* ── Opciones leche ──────────────────────────────────── */
    .leche-opts { display:flex; gap:6px; flex-wrap:wrap; margin-top:8px; }
    .leche-btn  { padding:4px 10px; border-radius:99px; font-size:11px; font-weight:700;
                  border:1.5px solid #eee; background:#fff; cursor:pointer;
                  font-family:'Nunito',sans-serif; transition:all .2s; }
    .leche-btn.sel { background:var(--negro); color:var(--dorado); border-color:var(--negro); }

    /* ── Empty ticket ────────────────────────────────────── */
    .empty-ticket { text-align:center; padding:40px 20px; color:#aaa; }
    .empty-ticket .ei { font-size:44px; margin-bottom:10px; }

    /* ── Ventas hoy ──────────────────────────────────────── */
    .ventas-hoy { background:#fff; border-radius:10px; padding:14px; box-shadow:0 2px 8px rgba(0,0,0,.07); }

    @media(max-width:900px){
      .pos-layout { grid-template-columns:1fr; height:auto; }
    }
  </style>
</head>
<body>
<div class="layout">
  <?php require_once ROOT . '/includes/sidebar.php'; ?>
  <div class="main-wrapper">
    <header class="topbar">
      <div class="topbar-left">
        <button class="btn-menu-toggle" id="btnMenuToggle">☰</button>
        <h1 class="topbar-title">🛒 Punto de Venta</h1>
      </div>
      <div class="topbar-right">
        <span class="topbar-sucursal">📍 <?= htmlspecialchars($sucursalNombre) ?></span>
        <span style="font-size:13px;color:#666;">Hoy: <strong style="color:var(--rojo)">$<?= number_format($totalHoy,2) ?></strong></span>
      </div>
    </header>

    <main class="content" style="padding-bottom:0;display:flex;flex-direction:column;gap:16px;">
      <div class="pos-layout">

        <!-- ── IZQUIERDA: Menú ─────────────────────────── -->
        <div class="pos-left">
          <div class="menu-search input-group">
            <span class="input-icon">🔍</span>
            <input type="text" id="buscador" class="form-control" placeholder="Buscar en el menú..." oninput="filtrarMenu(this.value)">
          </div>
          <div class="cat-tabs">
            <button class="cat-tab activo" onclick="filtrarCat(0,this)">Todos</button>
            <?php foreach ($categorias as $cat): ?>
              <button class="cat-tab" onclick="filtrarCat(<?= $cat['id_menu_cat'] ?>,this)">
                <?= htmlspecialchars($cat['nombre']) ?>
              </button>
            <?php endforeach; ?>
          </div>
          <div class="menu-grid" id="menuGrid">
            <?php foreach ($items as $item): ?>
              <div class="menu-card"
                   data-cat="<?= $item['id_menu_cat'] ?>"
                   data-nombre="<?= strtolower(htmlspecialchars($item['nombre'])) ?>"
                   data-cat-nombre="<?= strtolower(htmlspecialchars($item['categoria'])) ?>"
                   onclick="agregarItem(<?= $item['id_item'] ?>,'<?= addslashes($item['nombre']) ?>',<?= $item['precio'] ?>,'<?= addslashes($item['categoria']) ?>')">
                <div class="card-cat"><?= htmlspecialchars($item['categoria']) ?></div>
                <div class="card-name"><?= htmlspecialchars($item['nombre']) ?></div>
                <div class="card-price">$<?= number_format($item['precio'],2) ?></div>
              </div>
            <?php endforeach; ?>
          </div>

          <!-- Ventas del día -->
          <div class="ventas-hoy" style="margin-top:14px;">
            <p style="font-size:14px;font-weight:700;margin-bottom:10px;">📋 Ventas de hoy</p>
            <?php if (empty($ventasHoy)): ?>
              <p style="color:#aaa;font-size:13px;">Sin ventas registradas hoy</p>
            <?php else: ?>
              <table style="width:100%;font-size:13px;">
                <thead><tr style="color:#aaa;"><th style="text-align:left;">Hora</th><th>Items</th><th style="text-align:right;">Total</th></tr></thead>
                <tbody>
                  <?php foreach ($ventasHoy as $v): ?>
                  <tr>
                    <td><?= date('H:i', strtotime($v['fecha'])) ?></td>
                    <td style="text-align:center;"><?= $v['num_items'] ?></td>
                    <td style="text-align:right;font-weight:700;color:var(--rojo);">$<?= number_format($v['total'],2) ?></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            <?php endif; ?>
          </div>
        </div>

        <!-- ── DERECHA: Tickets ────────────────────────── -->
        <div class="pos-right">
          <!-- Pestañas de tickets -->
          <div class="tickets-tabs" id="ticketsTabs">
            <div class="ticket-tab activo" data-ticket="1" onclick="cambiarTicket(1)">
              🧾 Ticket 1
            </div>
            <button class="btn-new-ticket" onclick="nuevoTicket()" title="Nuevo ticket">+</button>
          </div>

          <!-- Panel del ticket activo -->
          <div class="ticket-panel">
            <div class="ticket-header">
              <h3 id="ticketTitle">🧾 Ticket 1</h3>
              <p><?= htmlspecialchars($sucursalNombre) ?> · <span id="ticketHora"><?= date('d/m/Y H:i') ?></span></p>
            </div>

            <div class="ticket-items" id="ticketItems">
              <div class="empty-ticket">
                <div class="ei">🛒</div>
                <p>Agrega productos del menú</p>
              </div>
            </div>

            <div class="ticket-totals">
              <!-- Descuento -->
              <div class="desc-row">
                <span style="font-size:13px;font-weight:700;white-space:nowrap;">🏷️ Descuento:</span>
                <input type="number" id="descuentoInput" class="desc-input"
                       placeholder="$0.00 o %" min="0" step="0.01" oninput="renderTicket()">
                <select id="descuentoTipo" class="desc-input" style="max-width:60px;" onchange="renderTicket()">
                  <option value="peso">$</option>
                  <option value="pct">%</option>
                </select>
              </div>
              <div class="total-row"><span>Subtotal</span><span id="subtotalTxt">$0.00</span></div>
              <div class="total-row" id="descRow" style="display:none;color:var(--rosa);">
                <span>Descuento</span><span id="descTxt">-$0.00</span>
              </div>
              <div class="total-row total-final"><span>TOTAL</span><span id="totalTxt">$0.00</span></div>
            </div>

            <div class="ticket-actions">
              <button class="btn btn-outline" style="flex:1;" onclick="limpiarTicket()">🗑️ Limpiar</button>
              <button class="btn btn-primary" style="flex:2;" onclick="cobrar()">✅ Cobrar</button>
            </div>
          </div>
        </div>

      </div>
    </main>
  </div>
</div>

<!-- MODAL: Tipo de leche -->
<div class="modal-overlay" id="modalLeche">
  <div class="modal" style="max-width:360px;">
    <div class="modal-header">
      <h3>🥛 Tipo de leche</h3>
      <button class="btn-icon" onclick="cerrarModal('modalLeche')">✕</button>
    </div>
    <div class="modal-body">
      <p style="font-size:14px;margin-bottom:14px;">Selecciona el tipo de leche para <strong id="modalLecheNombre"></strong>:</p>
      <div class="leche-opts">
        <button class="leche-btn sel" onclick="selLeche(this,'Leche entera')">🥛 Entera</button>
        <button class="leche-btn" onclick="selLeche(this,'Leche deslactosada')">🥛 Deslactosada</button>
        <button class="leche-btn" onclick="selLeche(this,'Leche de almendras')">🌰 Almendras</button>
        <button class="leche-btn" onclick="selLeche(this,'Agua de garrafón')">💧 Agua</button>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="cerrarModal('modalLeche')">Cancelar</button>
      <button class="btn btn-primary" onclick="confirmarLeche()">✅ Confirmar</button>
    </div>
  </div>
</div>

<!-- MODAL: Confirmar cobro -->
<div class="modal-overlay" id="modalCobro">
  <div class="modal" style="max-width:380px;">
    <div class="modal-header">
      <h3>✅ Confirmar Venta</h3>
      <button class="btn-icon" onclick="cerrarModal('modalCobro')">✕</button>
    </div>
    <div class="modal-body" style="text-align:center;">
      <p style="font-size:14px;margin-bottom:6px;">Total a cobrar:</p>
      <p style="font-family:'Playfair Display',serif;font-size:38px;font-weight:700;color:var(--rojo);" id="totalModal">$0.00</p>
      <p style="color:#aaa;font-size:12px;margin-top:8px;">El inventario se desconta automáticamente.</p>
    </div>
    <div class="modal-footer" style="justify-content:center;gap:12px;">
      <button class="btn btn-outline" onclick="cerrarModal('modalCobro')">Cancelar</button>
      <button class="btn btn-primary" id="btnConfirmar" onclick="confirmarVenta()">💰 Confirmar Cobro</button>
    </div>
  </div>
</div>

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script>
const BASE_URL   = '<?= BASE_URL ?>';
const ID_SUC     = <?= $idSucursal ?>;
const ID_USR     = <?= $usuario['id'] ?>;

// ── Categorías que usan leche ──────────────────────────────
const CAT_LECHE = ['bebidas especiales','malteadas','frappés','bebidas calientes','bebidas frías','sorbetos','chamoyadas'];

// ── Estado global ──────────────────────────────────────────
let tickets       = { 1: { items: {}, descuento: 0, descTipo: 'peso' } };
let ticketActivo  = 1;
let nextTicket    = 2;

// Estado temporal para modal de leche
let pendingItem   = null;
let lecheSelec    = 'Leche entera';

// ── Agregar item al ticket ─────────────────────────────────
function agregarItem(id, nombre, precio, categoria) {
  const catLower = categoria.toLowerCase();
  const necesitaLeche = CAT_LECHE.some(c => catLower.includes(c));

  if (necesitaLeche) {
    pendingItem = { id, nombre, precio, categoria };
    document.getElementById('modalLecheNombre').textContent = nombre;
    // Reset selección
    document.querySelectorAll('.leche-btn').forEach(b => b.classList.remove('sel'));
    document.querySelector('.leche-btn').classList.add('sel');
    lecheSelec = 'Leche entera';
    abrirModal('modalLeche');
  } else {
    agregarAlTicket(id, nombre, precio, null);
  }
}

function selLeche(btn, leche) {
  document.querySelectorAll('.leche-btn').forEach(b => b.classList.remove('sel'));
  btn.classList.add('sel');
  lecheSelec = leche;
}

function confirmarLeche() {
  if (!pendingItem) return;
  agregarAlTicket(pendingItem.id, pendingItem.nombre, pendingItem.precio, lecheSelec);
  cerrarModal('modalLeche');
  pendingItem = null;
}

function agregarAlTicket(id, nombre, precio, leche) {
  const t   = tickets[ticketActivo];
  const key = leche ? `${id}_${leche}` : `${id}`;

  if (t.items[key]) {
    t.items[key].qty++;
  } else {
    t.items[key] = { id, nombre, precio, qty: 1, leche };
  }
  renderTicket();
}

// ── Cambiar cantidad ───────────────────────────────────────
function cambiarQty(key, delta) {
  const t = tickets[ticketActivo];
  if (!t.items[key]) return;
  t.items[key].qty += delta;
  if (t.items[key].qty <= 0) delete t.items[key];
  renderTicket();
}

// ── Render ticket activo ───────────────────────────────────
function renderTicket() {
  const t       = tickets[ticketActivo];
  const keys    = Object.keys(t.items);
  const cont    = document.getElementById('ticketItems');

  if (keys.length === 0) {
    cont.innerHTML = '<div class="empty-ticket"><div class="ei">🛒</div><p>Agrega productos del menú</p></div>';
    document.getElementById('subtotalTxt').textContent = '$0.00';
    document.getElementById('totalTxt').textContent    = '$0.00';
    document.getElementById('descRow').style.display   = 'none';
    return;
  }

  let subtotal = 0;
  let html = '';
  keys.forEach(key => {
    const item = t.items[key];
    const sub  = item.precio * item.qty;
    subtotal  += sub;
    const nota = item.leche ? `<span class="item-note">(${item.leche})</span>` : '';
    html += `<div class="ticket-item">
      <div class="item-name">${item.nombre} ${nota}</div>
      <div class="qty-controls">
        <button class="qty-btn minus" onclick="cambiarQty('${key}',-1)">−</button>
        <span class="qty-num">${item.qty}</span>
        <button class="qty-btn plus"  onclick="cambiarQty('${key}', 1)">+</button>
      </div>
      <div class="item-price">$${sub.toFixed(2)}</div>
    </div>`;
  });
  cont.innerHTML = html;

  // Descuento
  const descVal  = parseFloat(document.getElementById('descuentoInput').value) || 0;
  const descTipo = document.getElementById('descuentoTipo').value;
  const descMonto = descTipo === 'pct' ? (subtotal * descVal / 100) : descVal;
  const total     = Math.max(0, subtotal - descMonto);

  document.getElementById('subtotalTxt').textContent = '$' + subtotal.toFixed(2);
  document.getElementById('totalTxt').textContent    = '$' + total.toFixed(2);

  if (descMonto > 0) {
    document.getElementById('descRow').style.display = 'flex';
    document.getElementById('descTxt').textContent   = '-$' + descMonto.toFixed(2);
  } else {
    document.getElementById('descRow').style.display = 'none';
  }
}

// ── Tickets múltiples ──────────────────────────────────────
function nuevoTicket() {
  tickets[nextTicket] = { items: {}, descuento: 0, descTipo: 'peso' };
  const tabs = document.getElementById('ticketsTabs');
  const btn  = document.querySelector('.btn-new-ticket');
  const tab  = document.createElement('div');
  tab.className    = 'ticket-tab';
  tab.dataset.ticket = nextTicket;
  const n = nextTicket;
  tab.innerHTML = `🧾 Ticket ${n} <span class="close-tab" onclick="cerrarTicket(event,${n})">✕</span>`;
  tab.onclick = () => cambiarTicket(n);
  tabs.insertBefore(tab, btn);
  cambiarTicket(nextTicket);
  nextTicket++;
}

function cambiarTicket(num) {
  ticketActivo = num;
  document.querySelectorAll('.ticket-tab').forEach(t => {
    t.classList.toggle('activo', parseInt(t.dataset.ticket) === num);
  });
  document.getElementById('ticketTitle').textContent = `🧾 Ticket ${num}`;
  // Restaurar descuento del ticket
  document.getElementById('descuentoInput').value = '';
  renderTicket();
}

function cerrarTicket(e, num) {
  e.stopPropagation();
  if (Object.keys(tickets).length <= 1) {
    showToast('Debe quedar al menos un ticket', 'warning'); return;
  }
  delete tickets[num];
  document.querySelector(`.ticket-tab[data-ticket="${num}"]`).remove();
  const remaining = Object.keys(tickets);
  cambiarTicket(parseInt(remaining[remaining.length - 1]));
}

// ── Cobrar ─────────────────────────────────────────────────
function cobrar() {
  const t = tickets[ticketActivo];
  if (Object.keys(t.items).length === 0) {
    showToast('Agrega al menos un producto', 'error'); return;
  }
  const subtotal  = Object.values(t.items).reduce((s,i) => s + i.precio*i.qty, 0);
  const descVal   = parseFloat(document.getElementById('descuentoInput').value) || 0;
  const descTipo  = document.getElementById('descuentoTipo').value;
  const descMonto = descTipo === 'pct' ? (subtotal * descVal / 100) : descVal;
  const total     = Math.max(0, subtotal - descMonto);

  document.getElementById('totalModal').textContent = '$' + total.toFixed(2);
  abrirModal('modalCobro');
}

async function confirmarVenta() {
  const t = tickets[ticketActivo];
  const btn = document.getElementById('btnConfirmar');
  btn.disabled = true;
  btn.textContent = '⏳ Procesando...';

  const subtotal  = Object.values(t.items).reduce((s,i) => s + i.precio*i.qty, 0);
  const descVal   = parseFloat(document.getElementById('descuentoInput').value) || 0;
  const descTipo  = document.getElementById('descuentoTipo').value;
  const descMonto = descTipo === 'pct' ? (subtotal * descVal / 100) : descVal;

  const detalle = Object.values(t.items).map(i => ({
    id_item:    i.id,
    cantidad:   i.qty,
    precio_unit:i.precio,
    leche:      i.leche || null
  }));

  try {
    const res  = await fetch(`${BASE_URL}/api/ventas.php`, {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({
        id_sucursal: ID_SUC,
        id_usuario:  ID_USR,
        detalle,
        descuento:   descMonto
      })
    });
    const data = await res.json();

    if (data.success) {
      cerrarModal('modalCobro');
      showToast('✅ Venta registrada — $' + data.total.toFixed(2), 'success');
      // Cerrar el ticket actual y crear uno nuevo limpio
      const num = ticketActivo;
      delete tickets[num];
      if (Object.keys(tickets).length === 0) {
        tickets[1] = { items:{}, descuento:0, descTipo:'peso' };
        ticketActivo = 1;
        document.getElementById('ticketsTabs').innerHTML =
          `<div class="ticket-tab activo" data-ticket="1" onclick="cambiarTicket(1)">🧾 Ticket 1</div>
           <button class="btn-new-ticket" onclick="nuevoTicket()" title="Nuevo ticket">+</button>`;
      } else {
        document.querySelector(`.ticket-tab[data-ticket="${num}"]`).remove();
        const remaining = Object.keys(tickets);
        cambiarTicket(parseInt(remaining[0]));
      }
      document.getElementById('descuentoInput').value = '';
      renderTicket();
      setTimeout(() => location.reload(), 2000);
    } else {
      showToast('Error: ' + data.message, 'error');
    }
  } catch(e) {
    showToast('Error de conexión', 'error');
  }

  btn.disabled = false;
  btn.textContent = '💰 Confirmar Cobro';
}

function limpiarTicket() {
  if (Object.keys(tickets[ticketActivo].items).length === 0) return;
  if (confirm('¿Limpiar este ticket?')) {
    tickets[ticketActivo].items = {};
    document.getElementById('descuentoInput').value = '';
    renderTicket();
  }
}

// ── Filtros de menú ────────────────────────────────────────
function filtrarCat(catId, btn) {
  document.querySelectorAll('.cat-tab').forEach(t => t.classList.remove('activo'));
  btn.classList.add('activo');
  document.querySelectorAll('.menu-card').forEach(card => {
    card.style.display = (!catId || card.dataset.cat == catId) ? '' : 'none';
  });
}

function filtrarMenu(q) {
  q = q.toLowerCase();
  document.querySelectorAll('.menu-card').forEach(card => {
    const match = card.dataset.nombre.includes(q) || card.dataset.catNombre?.includes(q);
    card.style.display = match ? '' : 'none';
  });
}
</script>
</body>
</html>