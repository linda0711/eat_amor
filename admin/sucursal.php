<?php
define('ROOT', dirname(__DIR__));
define('BASE_URL', 'http://localhost/eat_amor');
require_once ROOT . '/config/database.php';
require_once ROOT . '/config/session.php';
requireAdmin();

$pdo     = getDB();
$usuario = usuarioActual();
$msg     = $_GET['msg'] ?? '';

// POST: crear / editar sucursal
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion    = $_POST['accion'] ?? '';
    $nombre    = trim($_POST['nombre'] ?? '');
    $municipio = trim($_POST['municipio'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $telefono  = trim($_POST['telefono'] ?? '');

    if ($accion === 'crear') {
        if (!$nombre || !$municipio) {
            header('Location: sucursal.php?msg=error_campos'); exit;
        }
        $pdo->prepare("INSERT INTO sucursales (nombre, municipio, direccion, telefono) VALUES (?,?,?,?)")
            ->execute([$nombre, $municipio, $direccion, $telefono]);
        header('Location: sucursal.php?msg=creada'); exit;
    }

    if ($accion === 'editar') {
        $idSuc = (int)$_POST['id_sucursal'];
        $pdo->prepare("UPDATE sucursales SET nombre=?, municipio=?, direccion=?, telefono=? WHERE id_sucursal=?")
            ->execute([$nombre, $municipio, $direccion, $telefono, $idSuc]);
        header('Location: sucursal.php?msg=editada'); exit;
    }

    if ($accion === 'toggle') {
        $idSuc  = (int)$_POST['id_sucursal'];
        $activa = (int)$_POST['activa'];
        $pdo->prepare("UPDATE sucursales SET activa=? WHERE id_sucursal=?")
            ->execute([$activa ? 0 : 1, $idSuc]);
        header('Location: sucursal.php?msg=actualizada'); exit;
    }
}

// Obtener sucursales con métricas
$sucursales = $pdo->query("
    SELECT s.*,
           COUNT(DISTINCT u.id_usuario) AS num_empleados,
           COUNT(DISTINCT i.id_producto) AS num_productos,
           SUM(CASE WHEN i.stock_actual < i.stock_minimo THEN 1 ELSE 0 END) AS alertas_stock,
           COALESCE((SELECT SUM(total) FROM ventas v WHERE v.id_sucursal=s.id_sucursal AND DATE(v.fecha)=CURDATE()),0) AS ventas_hoy
    FROM sucursales s
    LEFT JOIN usuarios u ON u.id_sucursal=s.id_sucursal AND u.activo=1
    LEFT JOIN inventario i ON i.id_sucursal=s.id_sucursal
    GROUP BY s.id_sucursal
    ORDER BY s.id_sucursal
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sucursales — Eat Amor</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Nunito:wght@300;400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
  <style>
    .sucursales-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
      gap: 20px;
      margin-bottom: 24px;
    }
    .sucursal-card {
      background: #fff;
      border-radius: 14px;
      box-shadow: 0 2px 12px rgba(0,0,0,.08);
      overflow: hidden;
      transition: transform .2s, box-shadow .2s;
    }
    .sucursal-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 6px 24px rgba(0,0,0,.13);
    }
    .card-header {
      background: var(--negro);
      padding: 18px 20px;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .card-header h3 {
      font-family: 'Playfair Display', serif;
      color: var(--beige);
      font-size: 16px;
    }
    .card-header .municipio {
      color: var(--dorado);
      font-size: 12px;
      margin-top: 2px;
    }
    .card-body { padding: 18px 20px; }
    .card-stats {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px;
      margin-bottom: 16px;
    }
    .stat-item { text-align: center; }
    .stat-num  { font-size: 22px; font-weight: 700; color: var(--negro); font-family: 'Playfair Display', serif; }
    .stat-label{ font-size: 11px; color: #aaa; text-transform: uppercase; letter-spacing: .5px; }
    .card-info  { font-size: 13px; color: #666; margin-bottom: 6px; }
    .card-info span { font-weight: 600; color: var(--negro); }
    .card-actions { display: flex; gap: 8px; margin-top: 14px; padding-top: 14px; border-top: 1px solid #eee; }
  </style>
</head>
<body>
<div class="layout">
  <?php require_once ROOT . '/includes/sidebar.php'; ?>
  <div class="main-wrapper">
    <header class="topbar">
      <div class="topbar-left">
        <button class="btn-menu-toggle" id="btnMenuToggle">☰</button>
        <h1 class="topbar-title">Sucursales</h1>
      </div>
      <div class="topbar-right">
        <button class="btn btn-primary" onclick="abrirModal('modalSucursal')">+ Nueva Sucursal</button>
      </div>
    </header>

    <main class="content">

      <?php
      $msgs = [
        'creada'      => ['success', '✅ Sucursal creada correctamente.'],
        'editada'     => ['success', '✅ Sucursal actualizada.'],
        'actualizada' => ['success', '✅ Estado actualizado.'],
        'error_campos'=> ['error',   '⚠️ Nombre y municipio son obligatorios.'],
      ];
      if ($msg && isset($msgs[$msg])):
        [$tipo, $texto] = $msgs[$msg];
      ?>
        <div class="alert alert-<?= $tipo ?>" data-dismiss="3000"><?= $texto ?></div>
      <?php endif; ?>

      <!-- RESUMEN GLOBAL -->
      <div class="cards-grid" style="margin-bottom:28px;">
        <div class="metric-card fade-in">
          <span class="metric-icon">🏪</span>
          <span class="metric-label">Total Sucursales</span>
          <span class="metric-value"><?= count($sucursales) ?></span>
          <span class="metric-sub"><?= count(array_filter($sucursales, fn($s)=>$s['activa'])) ?> activas</span>
        </div>
        <div class="metric-card verde fade-in">
          <span class="metric-icon">👥</span>
          <span class="metric-label">Total Empleados</span>
          <span class="metric-value"><?= array_sum(array_column($sucursales,'num_empleados')) ?></span>
          <span class="metric-sub">en todas las sucursales</span>
        </div>
        <div class="metric-card rojo fade-in">
          <span class="metric-icon">⚠️</span>
          <span class="metric-label">Alertas Stock</span>
          <span class="metric-value"><?= array_sum(array_column($sucursales,'alertas_stock')) ?></span>
          <span class="metric-sub">productos bajo mínimo</span>
        </div>
        <div class="metric-card fade-in">
          <span class="metric-icon">💰</span>
          <span class="metric-label">Ventas Hoy</span>
          <span class="metric-value" style="font-size:20px;">$<?= number_format(array_sum(array_column($sucursales,'ventas_hoy')),2) ?></span>
          <span class="metric-sub">todas las sucursales</span>
        </div>
      </div>

      <!-- TARJETAS DE SUCURSALES -->
      <div class="sucursales-grid">
        <?php foreach ($sucursales as $s): ?>
        <div class="sucursal-card">
          <div class="card-header">
            <div>
              <h3>🏪 <?= htmlspecialchars($s['nombre']) ?></h3>
              <div class="municipio">📍 <?= htmlspecialchars($s['municipio']) ?></div>
            </div>
            <span class="badge <?= $s['activa'] ? 'badge-verde' : 'badge-gris' ?>">
              <?= $s['activa'] ? 'Activa' : 'Inactiva' ?>
            </span>
          </div>
          <div class="card-body">
            <div class="card-stats">
              <div class="stat-item">
                <div class="stat-num"><?= $s['num_productos'] ?></div>
                <div class="stat-label">Productos</div>
              </div>
              <div class="stat-item">
                <div class="stat-num"><?= $s['num_empleados'] ?></div>
                <div class="stat-label">Empleados</div>
              </div>
              <div class="stat-item">
                <div class="stat-num <?= $s['alertas_stock'] > 0 ? 'stock-bajo' : '' ?>">
                  <?= $s['alertas_stock'] ?>
                </div>
                <div class="stat-label">Alertas</div>
              </div>
              <div class="stat-item">
                <div class="stat-num" style="font-size:16px;color:var(--rojo);">
                  $<?= number_format($s['ventas_hoy'],0) ?>
                </div>
                <div class="stat-label">Ventas hoy</div>
              </div>
            </div>

            <?php if ($s['direccion']): ?>
              <div class="card-info">📌 <span><?= htmlspecialchars($s['direccion']) ?></span></div>
            <?php endif; ?>
            <?php if ($s['telefono']): ?>
              <div class="card-info">📞 <span><?= htmlspecialchars($s['telefono']) ?></span></div>
            <?php endif; ?>

            <div class="card-actions">
              <a href="dashboard.php?sucursal=<?= $s['id_sucursal'] ?>" style="flex:1;">
                <button class="btn btn-outline" style="width:100%;">📊 Ver detalle</button>
              </a>
              <button class="btn btn-outline btn-sm" onclick="abrirEditar(<?= htmlspecialchars(json_encode($s)) ?>)">✏️</button>
              <form method="POST" style="display:inline;">
                <input type="hidden" name="accion" value="toggle">
                <input type="hidden" name="id_sucursal" value="<?= $s['id_sucursal'] ?>">
                <input type="hidden" name="activa" value="<?= $s['activa'] ?>">
                <button type="submit" class="btn btn-outline btn-sm"
                  title="<?= $s['activa'] ? 'Desactivar' : 'Activar' ?>"
                  onclick="return confirm('¿Cambiar estado de esta sucursal?')">
                  <?= $s['activa'] ? '🔒' : '🔓' ?>
                </button>
              </form>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

    </main>
  </div>
</div>

<!-- MODAL CREAR -->
<div class="modal-overlay" id="modalSucursal">
  <div class="modal">
    <div class="modal-header">
      <h3>🏪 Nueva Sucursal</h3>
      <button class="btn-icon" onclick="cerrarModal('modalSucursal')">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="accion" value="crear">
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group">
            <label>Nombre *</label>
            <input type="text" name="nombre" class="form-control" placeholder="Ej. Eat Amor Chalco" required>
          </div>
          <div class="form-group">
            <label>Municipio *</label>
            <input type="text" name="municipio" class="form-control" placeholder="Ej. Chalco de Díaz Covarrubias" required>
          </div>
          <div class="form-group">
            <label>Dirección</label>
            <input type="text" name="direccion" class="form-control" placeholder="Dirección completa">
          </div>
          <div class="form-group">
            <label>Teléfono</label>
            <input type="text" name="telefono" class="form-control" placeholder="55 1234 5678">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="cerrarModal('modalSucursal')">Cancelar</button>
        <button type="submit" class="btn btn-primary">💾 Crear Sucursal</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL EDITAR -->
<div class="modal-overlay" id="modalEditar">
  <div class="modal">
    <div class="modal-header">
      <h3>✏️ Editar Sucursal</h3>
      <button class="btn-icon" onclick="cerrarModal('modalEditar')">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="accion" value="editar">
      <input type="hidden" name="id_sucursal" id="edit_id">
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group">
            <label>Nombre *</label>
            <input type="text" name="nombre" id="edit_nombre" class="form-control" required>
          </div>
          <div class="form-group">
            <label>Municipio *</label>
            <input type="text" name="municipio" id="edit_municipio" class="form-control" required>
          </div>
          <div class="form-group">
            <label>Dirección</label>
            <input type="text" name="direccion" id="edit_direccion" class="form-control">
          </div>
          <div class="form-group">
            <label>Teléfono</label>
            <input type="text" name="telefono" id="edit_telefono" class="form-control">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="cerrarModal('modalEditar')">Cancelar</button>
        <button type="submit" class="btn btn-primary">💾 Actualizar</button>
      </div>
    </form>
  </div>
</div>

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script>
function abrirEditar(s) {
  document.getElementById('edit_id').value        = s.id_sucursal;
  document.getElementById('edit_nombre').value    = s.nombre;
  document.getElementById('edit_municipio').value = s.municipio;
  document.getElementById('edit_direccion').value = s.direccion || '';
  document.getElementById('edit_telefono').value  = s.telefono || '';
  abrirModal('modalEditar');
}
</script>
</body>
</html>