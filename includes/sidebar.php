<?php
// ============================================================
//  EAT AMOR — Sidebar reutilizable
//  Archivo: includes/sidebar.php
//  Uso: require_once ROOT . '/includes/sidebar.php';
// ============================================================

$usuario = usuarioActual();
$esAdmin = esAdmin();
$iniciales = strtoupper(substr($usuario['nombre'] ?? 'U', 0, 1));

// Detectar página actual para marcar activo
$paginaActual = basename($_SERVER['PHP_SELF']);
$carpetaActual = basename(dirname($_SERVER['PHP_SELF']));

function navLink(string $href, string $icon, string $label, string $pagActual, string $carpActual): string {
  $file    = basename($href);
  $carpeta = strpos($href, 'admin') !== false ? 'admin' : (strpos($href, 'empleado') !== false ? 'empleado' : '');
  $activo  = ($pagActual === $file && $carpActual === $carpeta) ? 'activo' : '';
  return "<li class='nav-item'>
    <a href='" . BASE_URL . $href . "' class='$activo'>
      <span class='nav-icon'>$icon</span> $label
    </a>
  </li>";
}
?>

<!-- Overlay para móvil -->
<div id="sidebarOverlay" style="
  display:none; position:fixed; inset:0; background:rgba(0,0,0,.5);
  z-index:99; transition:opacity .3s;
" onclick="document.getElementById('sidebar').classList.remove('abierto'); this.classList.remove('activo');"></div>

<style>
  #sidebarOverlay.activo { display:block; }
</style>

<aside class="sidebar" id="sidebar">

  <!-- Logo -->
  <div class="sidebar-logo">
    <div class="logo-icon">🥞</div>
    <div>
      <div class="logo-text">Eat Amor</div>
      <div class="logo-sub">Sistema de Inventario</div>
    </div>
  </div>

  <!-- Navegación -->
  <nav class="sidebar-nav">

    <?php if ($esAdmin): ?>
      <!-- ADMIN NAV -->
      <span class="nav-section-title">Principal</span>
      <ul>
        <?= navLink('/admin/dashboard.php',  '📊', 'Dashboard',   $paginaActual, $carpetaActual) ?>
        <?= navLink('/admin/sucursal.php',   '🏪', 'Sucursales',  $paginaActual, $carpetaActual) ?>
      </ul>

      <span class="nav-section-title">Inventario</span>
      <ul>
        <?= navLink('/admin/productos.php',  '📦', 'Productos',   $paginaActual, $carpetaActual) ?>
        <?= navLink('/admin/entradas.php',   '⬇️', 'Entradas',    $paginaActual, $carpetaActual) ?>
        <?= navLink('/admin/salidas.php',    '⬆️', 'Salidas',     $paginaActual, $carpetaActual) ?>
      </ul>

      <span class="nav-section-title">Negocio</span>
      <ul>
        <?= navLink('/admin/menu.php',       '🥞', 'Menú',        $paginaActual, $carpetaActual) ?>
        <?= navLink('/admin/reportes.php',   '📋', 'Reportes',    $paginaActual, $carpetaActual) ?>
        <?= navLink('/admin/usuarios.php',   '👥', 'Usuarios',    $paginaActual, $carpetaActual) ?>
        <?= navLink('/admin/config.php',     '⚙️', 'Configuración',$paginaActual, $carpetaActual) ?>
      </ul>

    <?php else: ?>
      <!-- EMPLEADO NAV -->
      <span class="nav-section-title">Principal</span>
      <ul>
        <?= navLink('/empleado/dashboard.php', '📊', 'Dashboard',  $paginaActual, $carpetaActual) ?>
        <?= navLink('/empleado/productos.php', '📦', 'Productos',  $paginaActual, $carpetaActual) ?>
      </ul>

      <span class="nav-section-title">Inventario</span>
      <ul>
        <?= navLink('/empleado/entradas.php',  '⬇️', 'Entradas',   $paginaActual, $carpetaActual) ?>
        <?= navLink('/empleado/salidas.php',   '⬆️', 'Salidas',    $paginaActual, $carpetaActual) ?>
      </ul>

      <span class="nav-section-title">Ventas</span>
      <ul>
        <?= navLink('/empleado/pos.php',       '🛒', 'Punto de Venta', $paginaActual, $carpetaActual) ?>
      </ul>
    <?php endif; ?>

  </nav>

  <!-- Footer con usuario y logout -->
  <div class="sidebar-footer">
    <div class="sidebar-user">
      <div class="avatar"><?= htmlspecialchars($iniciales) ?></div>
      <div class="user-info">
        <div class="user-name"><?= htmlspecialchars($usuario['nombre'] ?? '') ?></div>
        <div class="user-rol"><?= htmlspecialchars($usuario['rol'] ?? '') ?></div>
      </div>
    </div>
    <a href="<?= BASE_URL ?>/auth/logout.php">
      <button class="btn-logout">🚪 Cerrar sesión</button>
    </a>
  </div>

</aside>