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

$categorias     = $pdo->query("SELECT * FROM categorias WHERE activa=1 ORDER BY nombre")->fetchAll();
$unidades       = $pdo->query("SELECT * FROM unidades_medida ORDER BY nombre")->fetchAll();
$sucursalNombre = $pdo->query("SELECT nombre FROM sucursales WHERE id_sucursal=$idSucursal")->fetchColumn();

$busqueda  = trim($_GET['busqueda'] ?? '');
$catFiltro = (int)($_GET['categoria'] ?? 0);
$orden     = $_GET['orden'] ?? 'nombre_asc';
$porPagina = 15;
$pagina    = max(1,(int)($_GET['pagina'] ?? 1));
$offset    = ($pagina-1)*$porPagina;

// POST: registrar entrada rápida desde productos
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'entrada_rapida') {
        $idProd = (int)$_POST['id_producto'];
        $cant   = (float)$_POST['cantidad'];
        $fecha  = date('Y-m-d');
        $obs    = 'Entrada rápida desde módulo productos';

        if ($idProd && $cant > 0) {
            $pdo->prepare("INSERT INTO entradas (id_sucursal,id_producto,id_usuario,cantidad,fecha,observaciones)
                VALUES (?,?,?,?,?,?)")
                ->execute([$idSucursal,$idProd,$usuario['id'],$cant,$fecha,$obs]);
        }
        header('Location: productos.php?msg=entrada_ok'); exit;
    }

    if ($accion === 'salida_rapida') {
        $idProd   = (int)$_POST['id_producto'];
        $cant     = (float)$_POST['cantidad'];
        $cantReal = (float)$_POST['cantidad_real'];
        $idMot    = (int)$_POST['id_motivo'];
        $fecha    = date('Y-m-d');

        // Validar margen ±2
        if (abs($cantReal - $cant) > 2) {
            header('Location: productos.php?msg=error_margen'); exit;
        }

        if ($idProd && $cant > 0) {
            $pdo->prepare("INSERT INTO salidas (id_sucursal,id_producto,id_usuario,id_motivo,cantidad,cantidad_real,fecha,observaciones)
                VALUES (?,?,?,?,?,?,?,'Salida rápida desde módulo productos')")
                ->execute([$idSucursal,$idProd,$usuario['id'],$idMot,$cant,$cantReal,$fecha]);
        }
        header('Location: productos.php?msg=salida_ok'); exit;
    }
}

$motivos = $pdo->query("SELECT * FROM motivos_salida ORDER BY id_motivo")->fetchAll();

// Ordenamiento
$orderMap = [
    'nombre_asc'  => 'p.nombre ASC',
    'nombre_desc' => 'p.nombre DESC',
    'stock_asc'   => 'i.stock_actual ASC',
    'stock_desc'  => 'i.stock_actual DESC',
    'cat_asc'     => 'c.nombre ASC',
];
$orderSQL = $orderMap[$orden] ?? 'p.nombre ASC';

// Filtros
$wheres = ["p.activo = 1"];
$params = [];
if ($busqueda)  { $wheres[] = "p.nombre LIKE ?";      $params[] = "%$busqueda%"; }
if ($catFiltro) { $wheres[] = "p.id_categoria = ?";   $params[] = $catFiltro; }
$whereSQL = "WHERE " . implode(" AND ", $wheres);

// Total
$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM productos p
    JOIN categorias c ON c.id_categoria=p.id_categoria
    LEFT JOIN inventario i ON i.id_producto=p.id_producto AND i.id_sucursal=$idSucursal
    $whereSQL");
$totalStmt->execute($params);
$total     = $totalStmt->fetchColumn();
$totalPags = ceil($total/$porPagina);

// Productos
$stmt = $pdo->prepare("
    SELECT p.id_producto, p.nombre, p.descripcion,
           c.nombre AS categoria, c.id_categoria,
           um.nombre AS unidad_nombre, um.abreviatura, p.id_unidad,
           COALESCE(i.stock_actual,0) AS stock_actual,
           COALESCE(i.stock_minimo,0) AS stock_minimo
    FROM productos p
    JOIN categorias c ON c.id_categoria=p.id_categoria
    JOIN unidades_medida um ON um.id_unidad=p.id_unidad
    LEFT JOIN inventario i ON i.id_producto=p.id_producto AND i.id_sucursal=$idSucursal
    $whereSQL
    ORDER BY $orderSQL
    LIMIT $porPagina OFFSET $offset
");
$stmt->execute($params);
$productos = $stmt->fetchAll();

// Métricas rápidas
$totalProds  = $pdo->query("SELECT COUNT(DISTINCT id_producto) FROM inventario WHERE id_sucursal=$idSucursal")->fetchColumn();
$stockBajoN  = $pdo->query("SELECT COUNT(*) FROM inventario WHERE id_sucursal=$idSucursal AND stock_actual < stock_minimo")->fetchColumn();
$sinStock    = $pdo->query("SELECT COUNT(*) FROM inventario WHERE id_sucursal=$idSucursal AND stock_actual <= 0")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Productos — Eat Amor</title>
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
        <h1 class="topbar-title">Productos</h1>
      </div>
      <div class="topbar-right">
        <span class="topbar-sucursal">📍 <?= htmlspecialchars($sucursalNombre) ?></span>
      </div>
    </header>

    <main class="content">

      <?php
      $msgs = [
        'entrada_ok'   => ['success','✅ Entrada registrada. Stock actualizado.'],
        'salida_ok'    => ['success','✅ Salida registrada correctamente.'],
        'error_margen' => ['error',  '🚨 La diferencia supera el margen permitido (±2 unidades).'],
      ];
      if ($msg && isset($msgs[$msg])):
        [$tipo,$texto] = $msgs[$msg];
      ?><div class="alert alert-<?= $tipo ?>" data-dismiss="3000"><?= $texto ?></div><?php endif; ?>

      <!-- MÉTRICAS -->
      <div class="cards-grid" style="margin-bottom:20px;">
        <div class="metric-card fade-in">
          <span class="metric-icon">📦</span>
          <span class="metric-label">Total Productos</span>
          <span class="metric-value"><?= $totalProds ?></span>
          <span class="metric-sub">en tu sucursal</span>
        </div>
        <div class="metric-card rojo fade-in">
          <span class="metric-icon">⚠️</span>
          <span class="metric-label">Stock Bajo</span>
          <span class="metric-value"><?= $stockBajoN ?></span>
          <span class="metric-sub">por reponer</span>
        </div>
        <div class="metric-card fade-in" style="border-left-color:#888;">
          <span class="metric-icon">⛔</span>
          <span class="metric-label">Sin Stock</span>
          <span class="metric-value"><?= $sinStock ?></span>
          <span class="metric-sub">agotados</span>
        </div>
      </div>

      <div class="panel">
        <!-- FILTROS -->
        <div class="panel-header" style="flex-wrap:wrap;gap:10px;">
          <form method="GET" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;flex:1;">
            <div class="input-group">
              <span class="input-icon">🔍</span>
              <input type="text" name="busqueda" class="form-control"
                placeholder="Buscar producto..." value="<?= htmlspecialchars($busqueda) ?>"
                style="max-width:200px;">
            </div>
            <select name="categoria" class="form-control" style="max-width:170px;">
              <option value="0">Todas las categorías</option>
              <?php foreach ($categorias as $cat): ?>
                <option value="<?= $cat['id_categoria'] ?>" <?= $catFiltro==$cat['id_categoria']?'selected':'' ?>>
                  <?= htmlspecialchars($cat['nombre']) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <select name="orden" class="form-control" style="max-width:160px;">
              <option value="nombre_asc"  <?= $orden==='nombre_asc' ?'selected':'' ?>>Nombre A → Z</option>
              <option value="nombre_desc" <?= $orden==='nombre_desc'?'selected':'' ?>>Nombre Z → A</option>
              <option value="stock_asc"   <?= $orden==='stock_asc'  ?'selected':'' ?>>Stock ↑ menor</option>
              <option value="stock_desc"  <?= $orden==='stock_desc' ?'selected':'' ?>>Stock ↓ mayor</option>
              <option value="cat_asc"     <?= $orden==='cat_asc'    ?'selected':'' ?>>Categoría A → Z</option>
            </select>
            <button type="submit" class="btn btn-outline">Filtrar</button>
            <a href="productos.php"><button type="button" class="btn btn-outline">Limpiar</button></a>
          </form>
          <span style="color:#666;font-size:13px;"><?= $total ?> producto(s)</span>
        </div>

        <!-- TABLA -->
        <div class="table-wrapper">
          <table>
            <thead>
              <tr>
                <th>#</th>
                <th>
                  <a href="?busqueda=<?= urlencode($busqueda) ?>&categoria=<?= $catFiltro ?>&orden=<?= $orden==='nombre_asc'?'nombre_desc':'nombre_asc' ?>"
                     style="color:var(--beige);display:flex;align-items:center;gap:4px;">
                    Producto <?= $orden==='nombre_asc'?'↑':($orden==='nombre_desc'?'↓':'') ?>
                  </a>
                </th>
                <th>
                  <a href="?busqueda=<?= urlencode($busqueda) ?>&categoria=<?= $catFiltro ?>&orden=<?= $orden==='cat_asc'?'nombre_asc':'cat_asc' ?>"
                     style="color:var(--beige);display:flex;align-items:center;gap:4px;">
                    Categoría <?= $orden==='cat_asc'?'↑':'' ?>
                  </a>
                </th>
                <th>Unidad</th>
                <th>
                  <a href="?busqueda=<?= urlencode($busqueda) ?>&categoria=<?= $catFiltro ?>&orden=<?= $orden==='stock_asc'?'stock_desc':'stock_asc' ?>"
                     style="color:var(--beige);display:flex;align-items:center;gap:4px;">
                    Stock Actual <?= $orden==='stock_asc'?'↑':($orden==='stock_desc'?'↓':'') ?>
                  </a>
                </th>
                <th>Stock Mínimo</th>
                <th>Estado</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($productos)): ?>
                <tr><td colspan="8" style="text-align:center;padding:30px;color:#666;">No se encontraron productos</td></tr>
              <?php endif; ?>
              <?php foreach ($productos as $p): ?>
              <tr>
                <td style="color:#aaa;font-size:12px;"><?= $p['id_producto'] ?></td>
                <td>
                  <strong><?= htmlspecialchars($p['nombre']) ?></strong>
                  <?php if ($p['descripcion']): ?>
                    <br><small style="color:#aaa;"><?= htmlspecialchars(substr($p['descripcion'],0,40)) ?>...</small>
                  <?php endif; ?>
                </td>
                <td><span class="badge badge-gris"><?= htmlspecialchars($p['categoria']) ?></span></td>
                <td><?= htmlspecialchars($p['abreviatura']) ?></td>
                <td class="<?= $p['stock_actual'] < $p['stock_minimo'] ? 'stock-bajo' : '' ?>">
                  <?= number_format($p['stock_actual'],1) ?> <?= $p['abreviatura'] ?>
                  <?php if ($p['stock_actual'] <= 0): ?>
                    <span class="badge badge-rojo" style="margin-left:4px;">⛔ Agotado</span>
                  <?php elseif ($p['stock_actual'] < $p['stock_minimo']): ?>
                    <span class="badge badge-dorado" style="margin-left:4px;">⚠️ Bajo</span>
                  <?php endif; ?>
                </td>
                <td><?= number_format($p['stock_minimo'],1) ?> <?= $p['abreviatura'] ?></td>
                <td>
                  <?php if ($p['stock_actual'] <= 0): ?>
                    <span class="badge badge-rojo">Sin stock</span>
                  <?php elseif ($p['stock_actual'] < $p['stock_minimo']): ?>
                    <span class="badge badge-dorado">Stock bajo</span>
                  <?php else: ?>
                    <span class="badge badge-verde">OK</span>
                  <?php endif; ?>
                </td>
                <td style="white-space:nowrap;">
                  <button class="btn btn-sm btn-outline" style="margin-right:4px;"
                    onclick="abrirEntrada(<?= $p['id_producto'] ?>,'<?= addslashes($p['nombre']) ?>','<?= $p['abreviatura'] ?>')">
                    ⬇️ Entrada
                  </button>
                  <button class="btn btn-sm btn-outline"
                    onclick="abrirSalida(<?= $p['id_producto'] ?>,'<?= addslashes($p['nombre']) ?>','<?= $p['abreviatura'] ?>')">
                    ⬆️ Salida
                  </button>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <!-- PAGINACIÓN -->
        <?php if ($totalPags > 1): ?>
        <div class="pagination">
          <?php if ($pagina>1): ?>
            <a href="?pagina=<?=$pagina-1?>&busqueda=<?=urlencode($busqueda)?>&categoria=<?=$catFiltro?>&orden=<?=$orden?>">
              <button class="page-btn">← Anterior</button>
            </a>
          <?php endif; ?>
          <?php for($i=1;$i<=$totalPags;$i++): ?>
            <a href="?pagina=<?=$i?>&busqueda=<?=urlencode($busqueda)?>&categoria=<?=$catFiltro?>&orden=<?=$orden?>">
              <button class="page-btn <?=$i===$pagina?'activo':''?>"><?=$i?></button>
            </a>
          <?php endfor; ?>
          <?php if ($pagina<$totalPags): ?>
            <a href="?pagina=<?=$pagina+1?>&busqueda=<?=urlencode($busqueda)?>&categoria=<?=$catFiltro?>&orden=<?=$orden?>">
              <button class="page-btn">Siguiente →</button>
            </a>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
    </main>
  </div>
</div>

<!-- MODAL ENTRADA RÁPIDA -->
<div class="modal-overlay" id="modalEntrada">
  <div class="modal" style="max-width:420px;">
    <div class="modal-header">
      <h3>⬇️ Registrar Entrada</h3>
      <button class="btn-icon" onclick="cerrarModal('modalEntrada')">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="accion" value="entrada_rapida">
      <input type="hidden" name="id_producto" id="ent_id_prod">
      <div class="modal-body">
        <p style="font-size:14px;margin-bottom:16px;">
          Producto: <strong id="ent_nombre"></strong>
        </p>
        <div class="form-group">
          <label>Cantidad a ingresar *</label>
          <div class="input-group">
            <span class="input-icon">📦</span>
            <input type="number" name="cantidad" class="form-control"
              step="0.001" min="0.001" placeholder="0.00" required>
          </div>
          <small style="color:#aaa;margin-top:4px;display:block;">
            Unidad: <span id="ent_unidad"></span>
          </small>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="cerrarModal('modalEntrada')">Cancelar</button>
        <button type="submit" class="btn btn-primary">💾 Registrar Entrada</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL SALIDA RÁPIDA -->
<div class="modal-overlay" id="modalSalida">
  <div class="modal" style="max-width:420px;">
    <div class="modal-header">
      <h3>⬆️ Registrar Salida</h3>
      <button class="btn-icon" onclick="cerrarModal('modalSalida')">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="accion" value="salida_rapida">
      <input type="hidden" name="id_producto" id="sal_id_prod">
      <div class="modal-body">
        <p style="font-size:14px;margin-bottom:16px;">
          Producto: <strong id="sal_nombre"></strong>
        </p>
        <div class="alert alert-warning" style="margin-bottom:16px;">
          ⚠️ Máximo <strong>±2 unidades</strong> de diferencia permitidas.
        </div>
        <div class="form-grid">
          <div class="form-group">
            <label>Cantidad esperada *</label>
            <input type="number" name="cantidad" id="sal_cant" class="form-control"
              step="0.001" min="0.001" placeholder="0.00" required
              oninput="autoRealProd(this.value)">
            <small style="color:#aaa;margin-top:4px;display:block;">
              Unidad: <span id="sal_unidad"></span>
            </small>
          </div>
          <div class="form-group">
            <label>Cantidad real *</label>
            <input type="number" name="cantidad_real" id="sal_cant_real" class="form-control"
              step="0.001" min="0.001" placeholder="0.00" required
              oninput="checkDifProd()">
          </div>
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
        <div id="alertaDifProd" style="display:none;" class="alert alert-error">
          🚨 La diferencia supera ±2 unidades. No puedes guardar.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="cerrarModal('modalSalida')">Cancelar</button>
        <button type="submit" id="btnGuardarSal" class="btn btn-rosa">💾 Registrar Salida</button>
      </div>
    </form>
  </div>
</div>

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script>
function abrirEntrada(id, nombre, unidad) {
  document.getElementById('ent_id_prod').value = id;
  document.getElementById('ent_nombre').textContent  = nombre;
  document.getElementById('ent_unidad').textContent  = unidad;
  abrirModal('modalEntrada');
}

function abrirSalida(id, nombre, unidad) {
  document.getElementById('sal_id_prod').value = id;
  document.getElementById('sal_nombre').textContent  = nombre;
  document.getElementById('sal_unidad').textContent  = unidad;
  document.getElementById('sal_cant').value      = '';
  document.getElementById('sal_cant_real').value = '';
  document.getElementById('alertaDifProd').style.display = 'none';
  document.getElementById('btnGuardarSal').disabled = false;
  document.getElementById('btnGuardarSal').style.opacity = '1';
  abrirModal('modalSalida');
}

function autoRealProd(val) {
  const r = document.getElementById('sal_cant_real');
  if (!r.value) r.value = val;
  checkDifProd();
}

function checkDifProd() {
  const esp  = parseFloat(document.getElementById('sal_cant').value) || 0;
  const real = parseFloat(document.getElementById('sal_cant_real').value) || 0;
  const dif  = Math.abs(real - esp);
  const alerta = document.getElementById('alertaDifProd');
  const btn    = document.getElementById('btnGuardarSal');
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