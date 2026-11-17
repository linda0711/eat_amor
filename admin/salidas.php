<?php
define('ROOT', dirname(__DIR__));
define('BASE_URL', 'http://localhost/eat_amor');
require_once ROOT . '/config/database.php';
require_once ROOT . '/config/session.php';
requireAdmin();

$pdo     = getDB();
$usuario = usuarioActual();
$msg     = $_GET['msg'] ?? '';

$sucursales  = $pdo->query("SELECT * FROM sucursales WHERE activa=1 ORDER BY id_sucursal")->fetchAll();
$productos   = $pdo->query("SELECT id_producto, nombre FROM productos WHERE activo=1 ORDER BY nombre")->fetchAll();
$motivos     = $pdo->query("SELECT * FROM motivos_salida ORDER BY id_motivo")->fetchAll();

$sucursalFiltro = (int)($_GET['sucursal'] ?? 0);
$busqueda       = trim($_GET['busqueda'] ?? '');
$porPagina      = 12;
$pagina         = max(1,(int)($_GET['pagina'] ?? 1));
$offset         = ($pagina-1)*$porPagina;

$wheres = ["1=1"];
$params = [];
if ($sucursalFiltro) { $wheres[] = "sa.id_sucursal=?"; $params[] = $sucursalFiltro; }
if ($busqueda)       { $wheres[] = "p.nombre LIKE ?";  $params[] = "%$busqueda%"; }
$whereSQL = implode(" AND ", $wheres);

$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM salidas sa JOIN productos p ON p.id_producto=sa.id_producto WHERE $whereSQL");
$totalStmt->execute($params);
$total     = $totalStmt->fetchColumn();
$totalPags = ceil($total/$porPagina);

$stmt = $pdo->prepare("
    SELECT sa.*, p.nombre AS producto, um.abreviatura,
           s.nombre AS sucursal, u.nombre AS empleado,
           ms.nombre AS motivo
    FROM salidas sa
    JOIN productos p        ON p.id_producto  = sa.id_producto
    JOIN unidades_medida um ON um.id_unidad   = p.id_unidad
    JOIN sucursales s       ON s.id_sucursal  = sa.id_sucursal
    JOIN usuarios u         ON u.id_usuario   = sa.id_usuario
    JOIN motivos_salida ms  ON ms.id_motivo   = sa.id_motivo
    WHERE $whereSQL
    ORDER BY sa.created_at DESC
    LIMIT $porPagina OFFSET $offset
");
$stmt->execute($params);
$salidas = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $idSuc    = (int)$_POST['id_sucursal'];
    $idProd   = (int)$_POST['id_producto'];
    $idMot    = (int)$_POST['id_motivo'];
    $cant     = (float)$_POST['cantidad'];
    $cantReal = (float)$_POST['cantidad_real'];
    $fecha    = $_POST['fecha'];
    $obs      = trim($_POST['observaciones'] ?? '');

    if (!$idSuc || !$idProd || !$idMot || !$cant || !$fecha) {
        header('Location: salidas.php?msg=error_campos'); exit;
    }

    $pdo->prepare("INSERT INTO salidas (id_sucursal,id_producto,id_usuario,id_motivo,cantidad,cantidad_real,fecha,observaciones) VALUES (?,?,?,?,?,?,?,?)")
        ->execute([$idSuc,$idProd,$usuario['id'],$idMot,$cant,$cantReal,$fecha,$obs]);

    header('Location: salidas.php?msg=salida_ok'); exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Salidas Admin — Eat Amor</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Nunito:wght@300;400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="layout">
  <?php require_once ROOT . '/includes/sidebar.php'; ?>
  <div class="main-wrapper">
    <header class="topbar">
      <div class="topbar-left">
        <button class="btn-menu-toggle" id="btnMenuToggle">☰</button>
        <h1 class="topbar-title">Salidas de Inventario</h1>
      </div>
      <div class="topbar-right">
        <button class="btn btn-rosa" onclick="abrirModal('modalSalida')">+ Registrar Salida</button>
      </div>
    </header>
    <main class="content">

      <?php if ($msg==='salida_ok'): ?>
        <div class="alert alert-success" data-dismiss="3000">✅ Salida registrada. Stock actualizado.</div>
      <?php elseif ($msg==='error_campos'): ?>
        <div class="alert alert-error" data-dismiss="3000">⚠️ Completa todos los campos obligatorios.</div>
      <?php endif; ?>

      <div class="sucursal-chips">
        <a href="?sucursal=0"><button class="chip-sucursal <?= !$sucursalFiltro?'activo':'' ?>">🌐 Todas</button></a>
        <?php foreach ($sucursales as $s): ?>
          <a href="?sucursal=<?= $s['id_sucursal'] ?>">
            <button class="chip-sucursal <?= $sucursalFiltro==$s['id_sucursal']?'activo':'' ?>">
              🏪 <?= htmlspecialchars($s['municipio']) ?>
            </button>
          </a>
        <?php endforeach; ?>
      </div>

      <div class="panel">
        <div class="panel-header">
          <form method="GET" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
            <input type="hidden" name="sucursal" value="<?= $sucursalFiltro ?>">
            <div class="input-group">
              <span class="input-icon">🔍</span>
              <input type="text" name="busqueda" class="form-control" placeholder="Buscar producto..." value="<?= htmlspecialchars($busqueda) ?>" style="max-width:240px;">
            </div>
            <button type="submit" class="btn btn-outline">Filtrar</button>
            <a href="salidas.php"><button type="button" class="btn btn-outline">Limpiar</button></a>
          </form>
          <span style="color:#666;font-size:13px;"><?= $total ?> registro(s)</span>
        </div>

        <div class="table-wrapper">
          <table>
            <thead>
              <tr><th>Fecha</th><th>Producto</th><th>Sucursal</th><th>Esperado</th><th>Real</th><th>Diferencia</th><th>Motivo</th><th>Empleado</th></tr>
            </thead>
            <tbody>
              <?php if (empty($salidas)): ?>
                <tr><td colspan="8" style="text-align:center;padding:30px;color:#666;">Sin salidas registradas</td></tr>
              <?php endif; ?>
              <?php foreach ($salidas as $sa): ?>
              <tr>
                <td><small><?= date('d/m/Y', strtotime($sa['fecha'])) ?></small></td>
                <td><strong><?= htmlspecialchars($sa['producto']) ?></strong></td>
                <td><span class="badge badge-gris"><?= htmlspecialchars($sa['sucursal']) ?></span></td>
                <td><?= number_format($sa['cantidad'],1) ?> <?= $sa['abreviatura'] ?></td>
                <td><span class="badge badge-rojo">-<?= number_format($sa['cantidad_real'],1) ?> <?= $sa['abreviatura'] ?></span></td>
                <td>
                  <?php $dif = $sa['cantidad_real'] - $sa['cantidad']; ?>
                  <?php if (abs($dif) > 0): ?>
                    <span class="badge <?= $dif > 0 ? 'badge-rojo' : 'badge-verde' ?>">
                      <?= $dif > 0 ? '+' : '' ?><?= number_format($dif,1) ?>
                    </span>
                  <?php else: ?>
                    <span class="badge badge-verde">✅ OK</span>
                  <?php endif; ?>
                </td>
                <td><small><?= htmlspecialchars($sa['motivo']) ?></small></td>
                <td><small><?= htmlspecialchars($sa['empleado']) ?></small></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <?php if ($totalPags>1): ?>
        <div class="pagination">
          <?php if ($pagina>1): ?><a href="?pagina=<?=$pagina-1?>&sucursal=<?=$sucursalFiltro?>"><button class="page-btn">← Anterior</button></a><?php endif; ?>
          <?php for($i=1;$i<=$totalPags;$i++): ?><a href="?pagina=<?=$i?>&sucursal=<?=$sucursalFiltro?>"><button class="page-btn <?=$i===$pagina?'activo':''?>"><?=$i?></button></a><?php endfor; ?>
          <?php if ($pagina<$totalPags): ?><a href="?pagina=<?=$pagina+1?>&sucursal=<?=$sucursalFiltro?>"><button class="page-btn">Siguiente →</button></a><?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
    </main>
  </div>
</div>

<div class="modal-overlay" id="modalSalida">
  <div class="modal">
    <div class="modal-header">
      <h3>⬆️ Registrar Salida</h3>
      <button class="btn-icon" onclick="cerrarModal('modalSalida')">✕</button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <div class="alert alert-warning" style="margin-bottom:16px;">
          ⚠️ El campo <strong>Cantidad Real</strong> es lo que físicamente salió.
        </div>
        <div class="form-grid">
          <div class="form-group">
            <label>Sucursal *</label>
            <select name="id_sucursal" class="form-control" required>
              <option value="">Seleccione...</option>
              <?php foreach ($sucursales as $s): ?>
                <option value="<?= $s['id_sucursal'] ?>"><?= htmlspecialchars($s['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Fecha *</label>
            <input type="date" name="fecha" class="form-control" value="<?= date('Y-m-d') ?>" required>
          </div>
          <div class="form-group">
            <label>Producto *</label>
            <select name="id_producto" class="form-control" required>
              <option value="">Seleccione...</option>
              <?php foreach ($productos as $p): ?>
                <option value="<?= $p['id_producto'] ?>"><?= htmlspecialchars($p['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Motivo *</label>
            <select name="id_motivo" class="form-control" required>
              <option value="">Seleccione...</option>
              <?php foreach ($motivos as $m): ?>
                <option value="<?= $m['id_motivo'] ?>"><?= htmlspecialchars($m['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Cantidad esperada *</label>
            <input type="number" name="cantidad" class="form-control" step="0.001" min="0.001" placeholder="0.00" required oninput="document.querySelector('[name=cantidad_real]').value=this.value">
          </div>
          <div class="form-group">
            <label>Cantidad real *</label>
            <input type="number" name="cantidad_real" class="form-control" step="0.001" min="0.001" placeholder="0.00" required>
          </div>
        </div>
        <div class="form-group">
          <label>Observaciones</label>
          <textarea name="observaciones" class="form-control" rows="2" placeholder="Notas opcionales..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="cerrarModal('modalSalida')">Cancelar</button>
        <button type="submit" class="btn btn-rosa">💾 Guardar Salida</button>
      </div>
    </form>
  </div>
</div>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>