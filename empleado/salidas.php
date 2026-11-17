<?php
define('ROOT', dirname(__DIR__));
define('BASE_URL', 'http://localhost/eat_amor');
require_once ROOT . '/config/database.php';
require_once ROOT . '/config/session.php';
requireEmpleado();

$pdo        = getDB();
$usuario    = usuarioActual();
$idSucursal = sucursalActual();
$msg        = $_GET['msg'] ?? '';

$productos = $pdo->query("SELECT id_producto,nombre FROM productos WHERE activo=1 ORDER BY nombre")->fetchAll();
$motivos   = $pdo->query("SELECT * FROM motivos_salida ORDER BY id_motivo")->fetchAll();
$sucursalNombre = $pdo->query("SELECT nombre FROM sucursales WHERE id_sucursal=$idSucursal")->fetchColumn();

$busqueda  = trim($_GET['busqueda'] ?? '');
$porPagina = 12;
$pagina    = max(1,(int)($_GET['pagina'] ?? 1));
$offset    = ($pagina-1)*$porPagina;

$params = [$idSucursal];
$extraWhere = "";
if ($busqueda) { $extraWhere = "AND p.nombre LIKE ?"; $params[] = "%$busqueda%"; }

$totalQ = $pdo->prepare("SELECT COUNT(*) FROM salidas sa JOIN productos p ON p.id_producto=sa.id_producto WHERE sa.id_sucursal=? $extraWhere");
$totalQ->execute($params);
$total     = $totalQ->fetchColumn();
$totalPags = ceil($total/$porPagina);

$stmt = $pdo->prepare("
    SELECT sa.*, p.nombre AS producto, um.abreviatura, ms.nombre AS motivo
    FROM salidas sa
    JOIN productos p ON p.id_producto=sa.id_producto
    JOIN unidades_medida um ON um.id_unidad=p.id_unidad
    JOIN motivos_salida ms ON ms.id_motivo=sa.id_motivo
    WHERE sa.id_sucursal=? $extraWhere
    ORDER BY sa.created_at DESC
    LIMIT $porPagina OFFSET $offset
");
$stmt->execute($params);
$salidas = $stmt->fetchAll();

// POST
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $idProd  = (int)$_POST['id_producto'];
    $idMot   = (int)$_POST['id_motivo'];
    $cant    = (float)$_POST['cantidad'];
    $cantReal= (float)$_POST['cantidad_real'];
    $fecha   = $_POST['fecha'];
    $obs     = trim($_POST['observaciones'] ?? '');

    if (!$idProd || !$idMot || !$cant || !$fecha) {
        header('Location: salidas.php?msg=error_campos'); exit;
    }

    // Verificar margen de error (máximo 2 unidades de diferencia)
    $diferencia = abs($cantReal - $cant);
    if ($diferencia > 2) {
        header('Location: salidas.php?msg=error_margen'); exit;
    }

    $pdo->prepare("INSERT INTO salidas (id_sucursal,id_producto,id_usuario,id_motivo,cantidad,cantidad_real,fecha,observaciones)
        VALUES (?,?,?,?,?,?,?,?)")
        ->execute([$idSucursal,$idProd,$usuario['id'],$idMot,$cant,$cantReal,$fecha,$obs]);

    header('Location: salidas.php?msg=salida_ok'); exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Salidas — Eat Amor</title>
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
        <h1 class="topbar-title">Registrar Salidas</h1>
      </div>
      <div class="topbar-right">
        <span class="topbar-sucursal">📍 <?= htmlspecialchars($sucursalNombre) ?></span>
        <button class="btn btn-rosa" onclick="abrirModal('modalSalida')">+ Nueva Salida</button>
      </div>
    </header>
    <main class="content">
      <?php if ($msg==='salida_ok'): ?>
        <div class="alert alert-success" data-dismiss="3000">✅ Salida registrada correctamente.</div>
      <?php elseif ($msg==='error_campos'): ?>
        <div class="alert alert-error" data-dismiss="3000">⚠️ Completa todos los campos obligatorios.</div>
      <?php elseif ($msg==='error_margen'): ?>
        <div class="alert alert-error" data-dismiss="5000">🚨 La diferencia entre cantidad esperada y real supera el margen permitido (2 unidades). Contacta al administrador.</div>
      <?php endif; ?>

      <div class="panel">
        <div class="panel-header">
          <form method="GET" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
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
              <tr><th>Fecha</th><th>Producto</th><th>Esperado</th><th>Real</th><th>Diferencia</th><th>Motivo</th></tr>
            </thead>
            <tbody>
              <?php if (empty($salidas)): ?>
                <tr><td colspan="6" style="text-align:center;padding:30px;color:#666;">Sin salidas registradas aún</td></tr>
              <?php endif; ?>
              <?php foreach ($salidas as $sa): ?>
              <tr>
                <td><small><?= date('d/m/Y', strtotime($sa['fecha'])) ?></small></td>
                <td><strong><?= htmlspecialchars($sa['producto']) ?></strong></td>
                <td><?= number_format($sa['cantidad'],1) ?> <?= $sa['abreviatura'] ?></td>
                <td><span class="badge badge-rojo">-<?= number_format($sa['cantidad_real'],1) ?> <?= $sa['abreviatura'] ?></span></td>
                <td>
                  <?php $dif = $sa['cantidad_real'] - $sa['cantidad']; ?>
                  <?php if (abs($dif) > 0): ?>
                    <span class="badge <?= abs($dif) > 2 ? 'badge-rojo' : 'badge-dorado' ?>">
                      <?= $dif > 0 ? '+' : '' ?><?= number_format($dif,1) ?>
                    </span>
                  <?php else: ?>
                    <span class="badge badge-verde">✅ OK</span>
                  <?php endif; ?>
                </td>
                <td><small><?= htmlspecialchars($sa['motivo']) ?></small></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php if ($totalPags>1): ?>
        <div class="pagination">
          <?php if ($pagina>1): ?><a href="?pagina=<?=$pagina-1?>&busqueda=<?=urlencode($busqueda)?>"><button class="page-btn">← Anterior</button></a><?php endif; ?>
          <?php for($i=1;$i<=$totalPags;$i++): ?><a href="?pagina=<?=$i?>&busqueda=<?=urlencode($busqueda)?>"><button class="page-btn <?=$i===$pagina?'activo':''?>"><?=$i?></button></a><?php endfor; ?>
          <?php if ($pagina<$totalPags): ?><a href="?pagina=<?=$pagina+1?>&busqueda=<?=urlencode($busqueda)?>"><button class="page-btn">Siguiente →</button></a><?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
    </main>
  </div>
</div>

<div class="modal-overlay" id="modalSalida">
  <div class="modal">
    <div class="modal-header">
      <h3>⬆️ Nueva Salida</h3>
      <button class="btn-icon" onclick="cerrarModal('modalSalida')">✕</button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <div class="alert alert-warning" style="margin-bottom:16px;">
          ⚠️ Máximo <strong>2 unidades de diferencia</strong> entre cantidad esperada y real permitidas.
        </div>
        <div class="form-grid">
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
            <input type="number" name="cantidad" id="cantEsp" class="form-control" step="0.001" min="0.001" placeholder="0.00" required oninput="autoReal(this.value)">
          </div>
          <div class="form-group">
            <label>Cantidad real *</label>
            <input type="number" name="cantidad_real" id="cantRealEmp" class="form-control" step="0.001" min="0.001" placeholder="0.00" required oninput="checkDif()">
          </div>
        </div>
        <div id="alertaDif" style="display:none;" class="alert alert-warning">
          ⚠️ La diferencia supera 2 unidades. No podrás guardar.
        </div>
        <div class="form-group">
          <label>Observaciones</label>
          <textarea name="observaciones" class="form-control" rows="2" placeholder="Notas opcionales..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="cerrarModal('modalSalida')">Cancelar</button>
        <button type="submit" id="btnGuardar" class="btn btn-rosa">💾 Guardar Salida</button>
      </div>
    </form>
  </div>
</div>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script>
function autoReal(val) {
  const r = document.getElementById('cantRealEmp');
  if (!r.value) r.value = val;
  checkDif();
}
function checkDif() {
  const esp  = parseFloat(document.getElementById('cantEsp').value) || 0;
  const real = parseFloat(document.getElementById('cantRealEmp').value) || 0;
  const dif  = Math.abs(real - esp);
  const alerta = document.getElementById('alertaDif');
  const btn    = document.getElementById('btnGuardar');
  if (dif > 2) {
    alerta.style.display = 'flex';
    btn.disabled = true;
    btn.style.opacity = '0.5';
  } else {
    alerta.style.display = 'none';
    btn.disabled = false;
    btn.style.opacity = '1';
  }
}
</script>
</body>
</html>