// ============================================================
//  EAT AMOR — JavaScript Principal
//  Archivo: assets/js/main.js
// ============================================================

document.addEventListener('DOMContentLoaded', () => {

  // ── Toggle Sidebar (móvil) ─────────────────────────────────
  const btnToggle = document.getElementById('btnMenuToggle');
  const sidebar   = document.getElementById('sidebar');
  const overlay   = document.getElementById('sidebarOverlay');

  if (btnToggle && sidebar) {
    btnToggle.addEventListener('click', () => {
      sidebar.classList.toggle('abierto');
      if (overlay) overlay.classList.toggle('activo');
    });
  }

  if (overlay) {
    overlay.addEventListener('click', () => {
      sidebar.classList.remove('abierto');
      overlay.classList.remove('activo');
    });
  }

  // ── Cerrar modales al hacer clic fuera ────────────────────
  document.querySelectorAll('.modal-overlay').forEach(mo => {
    mo.addEventListener('click', (e) => {
      if (e.target === mo) cerrarModal(mo.id);
    });
  });

  // ── Marcar nav-item activo según URL actual ───────────────
  const path = window.location.pathname;
  document.querySelectorAll('.nav-item a').forEach(link => {
    if (link.getAttribute('href') && path.includes(link.getAttribute('href').split('/').pop().replace('.php',''))) {
      link.classList.add('activo');
    }
  });

  // ── Alertas con auto-dismiss ──────────────────────────────
  document.querySelectorAll('.alert[data-dismiss]').forEach(alert => {
    setTimeout(() => {
      alert.style.opacity = '0';
      alert.style.transition = 'opacity .4s';
      setTimeout(() => alert.remove(), 400);
    }, parseInt(alert.dataset.dismiss) || 4000);
  });

});

// ── Abrir modal ────────────────────────────────────────────
function abrirModal(id) {
  const modal = document.getElementById(id);
  if (modal) modal.classList.add('abierto');
}

// ── Cerrar modal ───────────────────────────────────────────
function cerrarModal(id) {
  const modal = document.getElementById(id);
  if (modal) modal.classList.remove('abierto');
}

// ── Toast de notificación ──────────────────────────────────
function showToast(msg, tipo = 'success', duracion = 3500) {
  const container = document.getElementById('toastContainer')
    || (() => {
      const c = document.createElement('div');
      c.id = 'toastContainer';
      Object.assign(c.style, {
        position: 'fixed', bottom: '24px', right: '24px',
        zIndex: 9999, display: 'flex', flexDirection: 'column', gap: '10px'
      });
      document.body.appendChild(c);
      return c;
    })();

  const colors = {
    success: '#27ae60', error: '#C0392B',
    warning: '#D4AF37', info: '#2980b9'
  };

  const toast = document.createElement('div');
  toast.style.cssText = `
    background:${colors[tipo]||colors.info}; color:#fff;
    padding:12px 20px; border-radius:8px;
    font-family:'Nunito',sans-serif; font-size:14px; font-weight:600;
    box-shadow:0 4px 20px rgba(0,0,0,.2);
    animation:fadeIn .3s ease;
    max-width:320px;
  `;
  toast.textContent = msg;
  container.appendChild(toast);

  setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transition = 'opacity .3s';
    setTimeout(() => toast.remove(), 300);
  }, duracion);
}

// ── Confirmar acción ───────────────────────────────────────
function confirmar(msg, callback) {
  if (confirm(msg)) callback();
}

// ── Formatear moneda ───────────────────────────────────────
function formatMXN(n) {
  return new Intl.NumberFormat('es-MX', {
    style: 'currency', currency: 'MXN'
  }).format(n);
}

// ── Formatear número con decimales ────────────────────────
function formatNum(n, dec = 2) {
  return parseFloat(n).toFixed(dec);
}