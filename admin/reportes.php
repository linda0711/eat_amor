<?php
define('ROOT', dirname(__DIR__));
define('BASE_URL', 'http://localhost/eat_amor');
require_once ROOT . '/config/database.php';
require_once ROOT . '/config/session.php';
requireAdmin();

$pdo     = getDB();
$usuario = usuarioActual();

$sucursales     = $pdo->query("SELECT * FROM sucursales WHERE activa=1 ORDER BY id_sucursal")->fetchAll();
$sucursalFiltro = (int)($_GET['sucursal'] ?? 0);
$fechaDesde     = $_GET['desde'] ?? date('Y-m-01');
$fechaHasta     = $_GET['hasta'] ?? date('Y-m-d');

$sWhere  = $sucursalFiltro ? "AND id_sucursal=$sucursalFiltro" : "";
$sWhereV = $sucursalFiltro ? "AND v.id_sucursal=$sucursalFiltro" : "";
$sWhereE = $sucursalFiltro ? "AND e.id_sucursal=$sucursalFiltro" : "";
$sWhereSa= $sucursalFiltro ? "AND sa.id_sucursal=$sucursalFiltro" : "";

// Métricas del período
$totalEntradas = $pdo->query("SELECT COALESCE(SUM(cantidad),0) FROM entradas WHERE fecha BETWEEN '$fechaDesde' AND '$fechaHasta' $sWhere")->fetchColumn();
$totalSalidas  = $pdo->query("SELECT COALESCE(SUM(cantidad_real),0) FROM salidas WHERE fecha BETWEEN '$fechaDesde' AND '$fechaHasta' $sWhere")->fetchColumn();
$totalVentas   = $pdo->query("SELECT COALESCE(SUM(total),0) FROM ventas v WHERE DATE(fecha) BETWEEN '$fechaDesde' AND '$fechaHasta' $sWhereV")->fetchColumn();
$numVentas     = $pdo->query("SELECT COUNT(*) FROM ventas v WHERE DATE(fecha) BETWEEN '$fechaDesde' AND '$fechaHasta' $sWhereV")->fetchColumn();
$stockBajo     = $pdo->query("SELECT COUNT(*) FROM inventario WHERE stock_actual < stock_minimo " . ($sucursalFiltro ? "AND id_sucursal=$sucursalFiltro" : ""))->fetchColumn();

// Top 10 productos más vendidos
$topVendidos = $pdo->query("
    SELECT mi.nombre, mc.nombre AS categoria,
           SUM(vd.cantidad) AS total_vendido,
           SUM(vd.subtotal) AS total_ingresos
    FROM venta_detalle vd
    JOIN menu_items mi ON mi.id_item=vd.id_item
    JOIN menu_categorias mc ON mc.id_menu_cat=mi.id_menu_cat
    JOIN ventas v ON v.id_venta=vd.id_venta
    WHERE DATE(v.fecha) BETWEEN '$fechaDesde' AND '$fechaHasta'
    $sWhereV
    GROUP BY mi.id_item, mi.nombre, mc.nombre
    ORDER BY total_vendido DESC
    LIMIT 10
")->fetchAll();

// Diferencias en salidas (control robo hormiga)
$diferencias = $pdo->query("
    SELECT p.nombre AS producto, s.nombre AS sucursal,
           sa.fecha, sa.cantidad AS esperado,
           sa.cantidad_real AS cant_real,
           (sa.cantidad_real - sa.cantidad) AS cant_diferencia,
           u.nombre AS empleado, ms.nombre AS motivo
    FROM salidas sa
    JOIN productos p        ON p.id_producto  = sa.id_producto
    JOIN sucursales s       ON s.id_sucursal  = sa.id_sucursal
    JOIN usuarios u         ON u.id_usuario   = sa.id_usuario
    JOIN motivos_salida ms  ON ms.id_motivo   = sa.id_motivo
    WHERE (sa.cantidad_real - sa.cantidad) <> 0
    AND sa.fecha BETWEEN '$fechaDesde' AND '$fechaHasta'
    $sWhereSa
    ORDER BY ABS(sa.cantidad_real - sa.cantidad) DESC
    LIMIT 20
")->fetchAll();

// Entradas por sucursal
$entradasSuc = $pdo->query("
    SELECT s.nombre AS sucursal,
           COUNT(*) AS num_entradas,
           SUM(e.cantidad) AS total_cantidad
    FROM entradas e
    JOIN sucursales s ON s.id_sucursal=e.id_sucursal
    WHERE e.fecha BETWEEN '$fechaDesde' AND '$fechaHasta'
    $sWhereE
    GROUP BY s.id_sucursal, s.nombre
    ORDER BY total_cantidad DESC
")->fetchAll();

// Ventas por día
$ventasDia = $pdo->query("
    SELECT DATE(v.fecha) AS dia,
           COUNT(*) AS num_ventas,
           SUM(v.total) AS total
    FROM ventas v
    WHERE DATE(v.fecha) BETWEEN '$fechaDesde' AND '$fechaHasta'
    $sWhereV
    GROUP BY DATE(v.fecha)
    ORDER BY dia ASC
")->fetchAll();

// Stock actual por sucursal (productos bajo mínimo)
$stockAlerts = $pdo->query("
    SELECT p.nombre AS producto, s.nombre AS sucursal,
           c.nombre AS categoria,
           i.stock_actual, i.stock_minimo, um.abreviatura
    FROM inventario i
    JOIN productos p        ON p.id_producto  = i.id_producto
    JOIN sucursales s       ON s.id_sucursal  = i.id_sucursal
    JOIN categorias c       ON c.id_categoria = p.id_categoria
    JOIN unidades_medida um ON um.id_unidad   = p.id_unidad
    WHERE i.stock_actual < i.stock_minimo
    " . ($sucursalFiltro ? "AND i.id_sucursal=$sucursalFiltro" : "") . "
    ORDER BY (i.stock_actual - i.stock_minimo) ASC
    LIMIT 20
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reportes — Eat Amor</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Nunito:wght@300;400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
  <style>
    .two-col { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:24px; }
    @media(max-width:900px){ .two-col{ grid-template-columns:1fr; } }
    .bar-wrap { display:flex; align-items:center; gap:10px; margin-bottom:8px; }
    .bar-label { font-size:12px; width:140px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; flex-shrink:0; }
    .bar-track { flex:1; background:#eee; border-radius:99px; height:10px; }
    .bar-fill  { background:var(--rojo); border-radius:99px; height:10px; transition:width .4s; }
    .bar-val   { font-size:12px; font-weight:700; color:var(--rojo); width:50px; text-align:right; flex-shrink:0; }
  </style>
</head>
<body>
<div class="layout">
  <?php require_once ROOT . '/includes/sidebar.php'; ?>
  <div class="main-wrapper">
    <header class="topbar">
      <div class="topbar-left">
        <button class="btn-menu-toggle" id="btnMenuToggle">☰</button>
        <h1 class="topbar-title">Reportes</h1>
      </div>
    </header>
    <main class="content">

      <!-- FILTROS -->
      <div class="panel" style="margin-bottom:20px;">
        <div class="panel-body">
          <form method="GET" style="display:flex;gap:14px;align-items:flex-end;flex-wrap:wrap;">
            <div class="form-group" style="margin:0;">
              <label>Sucursal</label>
              <select name="sucursal" class="form-control" style="min-width:180px;">
                <option value="0">Todas</option>
                <?php foreach ($sucursales as $s): ?>
                  <option value="<?= $s['id_sucursal'] ?>" <?= $sucursalFiltro==$s['id_sucursal']?'selected':'' ?>>
                    <?= htmlspecialchars($s['nombre']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group" style="margin:0;">
              <label>Desde</label>
              <input type="date" name="desde" class="form-control" value="<?= $fechaDesde ?>">
            </div>
            <div class="form-group" style="margin:0;">
              <label>Hasta</label>
              <input type="date" name="hasta" class="form-control" value="<?= $fechaHasta ?>">
            </div>
            <button type="submit" class="btn btn-primary">📊 Generar</button>
            <a href="reportes.php"><button type="button" class="btn btn-outline">Limpiar</button></a>
          </form>
        </div>
      </div>

      <!-- CHIPS SUCURSAL -->
      <div class="sucursal-chips">
        <a href="?sucursal=0&desde=<?=$fechaDesde?>&hasta=<?=$fechaHasta?>">
          <button class="chip-sucursal <?= !$sucursalFiltro?'activo':'' ?>">🌐 Todas</button>
        </a>
        <?php foreach ($sucursales as $s): ?>
          <a href="?sucursal=<?=$s['id_sucursal']?>&desde=<?=$fechaDesde?>&hasta=<?=$fechaHasta?>">
            <button class="chip-sucursal <?= $sucursalFiltro==$s['id_sucursal']?'activo':'' ?>">
              🏪 <?= htmlspecialchars($s['municipio']) ?>
            </button>
          </a>
        <?php endforeach; ?>
      </div>

      <!-- MÉTRICAS -->
      <div class="cards-grid">
        <div class="metric-card verde fade-in">
          <span class="metric-icon">⬇️</span>
          <span class="metric-label">Entradas período</span>
          <span class="metric-value"><?= number_format($totalEntradas,0) ?></span>
          <span class="metric-sub">unidades ingresadas</span>
        </div>
        <div class="metric-card rosa fade-in">
          <span class="metric-icon">⬆️</span>
          <span class="metric-label">Salidas período</span>
          <span class="metric-value"><?= number_format($totalSalidas,0) ?></span>
          <span class="metric-sub">unidades despachadas</span>
        </div>
        <div class="metric-card fade-in">
          <span class="metric-icon">💰</span>
          <span class="metric-label">Ventas período</span>
          <span class="metric-value" style="font-size:20px;">$<?= number_format($totalVentas,2) ?></span>
          <span class="metric-sub"><?= $numVentas ?> transacciones</span>
        </div>
        <div class="metric-card rojo fade-in">
          <span class="metric-icon">⚠️</span>
          <span class="metric-label">Stock bajo actual</span>
          <span class="metric-value"><?= $stockBajo ?></span>
          <span class="metric-sub">productos por reponer</span>
        </div>
      </div>

      <div class="two-col">

        <!-- TOP PRODUCTOS VENDIDOS -->
        <div class="panel">
          <div class="panel-header"><h2>🏆 Más Vendidos</h2></div>
          <div class="panel-body">
            <?php if (empty($topVendidos)): ?>
              <p style="color:#aaa;text-align:center;padding:20px;">Sin ventas en este período</p>
            <?php else:
              $maxVendido = max(array_column($topVendidos,'total_vendido'));
              foreach ($topVendidos as $i => $p):
                $pct = $maxVendido > 0 ? ($p['total_vendido']/$maxVendido)*100 : 0;
            ?>
              <div class="bar-wrap">
                <div class="bar-label" title="<?= htmlspecialchars($p['nombre']) ?>">
                  <?= $i+1 ?>. <?= htmlspecialchars($p['nombre']) ?>
                </div>
                <div class="bar-track">
                  <div class="bar-fill" style="width:<?= $pct ?>%"></div>
                </div>
                <div class="bar-val"><?= $p['total_vendido'] ?></div>
              </div>
            <?php endforeach; endif; ?>
          </div>
        </div>

        <!-- DIFERENCIAS / ROBO HORMIGA -->
        <div class="panel">
          <div class="panel-header">
            <h2>🔍 Diferencias en Salidas</h2>
            <span class="badge <?= count($diferencias)>0?'badge-rojo':'badge-verde' ?>">
              <?= count($diferencias) ?> registros
            </span>
          </div>
          <div class="table-wrapper">
            <?php if (empty($diferencias)): ?>
              <p style="padding:20px;color:#27ae60;text-align:center;">✅ Sin diferencias detectadas</p>
            <?php else: ?>
            <table>
              <thead><tr><th>Producto</th><th>Sucursal</th><th>Esp.</th><th>Real</th><th>Dif.</th><th>Empleado</th></tr></thead>
              <tbody>
                <?php foreach ($diferencias as $d): ?>
                <tr>
                  <td><strong><?= htmlspecialchars($d['producto']) ?></strong></td>
                  <td><small><?= htmlspecialchars($d['sucursal']) ?></small></td>
                  <td><?= number_format($d['cant_esperada'],1) ?></td>
                  <td><?= number_format($d['cant_real'],1) ?></td>
                  <td>
                    <span class="badge <?= abs($d['cant_diferencia'])>2?'badge-rojo':'badge-dorado' ?>">
                      <?= $d['cant_diferencia']>0?'+':'' ?><?= number_format($d['cant_diferencia'],1) ?>
                    </span>
                  </td>
                  <td><small><?= htmlspecialchars($d['empleado']) ?></small></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            <?php endif; ?>
          </div>
        </div>

      </div>

      <div class="two-col">

        <!-- VENTAS POR DÍA -->
        <div class="panel">
          <div class="panel-header"><h2>📅 Ventas por Día</h2></div>
          <div class="table-wrapper">
            <?php if (empty($ventasDia)): ?>
              <p style="padding:20px;color:#aaa;text-align:center;">Sin ventas en este período</p>
            <?php else: ?>
            <table>
              <thead><tr><th>Día</th><th>Nº Ventas</th><th>Total</th></tr></thead>
              <tbody>
                <?php foreach ($ventasDia as $vd): ?>
                <tr>
                  <td><?= date('d/m/Y', strtotime($vd['dia'])) ?></td>
                  <td><?= $vd['num_ventas'] ?></td>
                  <td><strong style="color:var(--rojo);">$<?= number_format($vd['total'],2) ?></strong></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            <?php endif; ?>
          </div>
        </div>

        <!-- STOCK BAJO -->
        <div class="panel">
          <div class="panel-header">
            <h2>⚠️ Productos con Stock Bajo</h2>
            <span class="badge badge-rojo"><?= count($stockAlerts) ?></span>
          </div>
          <div class="table-wrapper">
            <?php if (empty($stockAlerts)): ?>
              <p style="padding:20px;color:#27ae60;text-align:center;">✅ Todo el stock está bien</p>
            <?php else: ?>
            <table>
              <thead><tr><th>Producto</th><th>Sucursal</th><th>Actual</th><th>Mínimo</th></tr></thead>
              <tbody>
                <?php foreach ($stockAlerts as $sa): ?>
                <tr>
                  <td>
                    <strong><?= htmlspecialchars($sa['producto']) ?></strong><br>
                    <small style="color:#aaa;"><?= htmlspecialchars($sa['categoria']) ?></small>
                  </td>
                  <td><small><?= htmlspecialchars($sa['sucursal']) ?></small></td>
                  <td class="stock-bajo"><?= number_format($sa['stock_actual'],1) ?> <?= $sa['abreviatura'] ?></td>
                  <td><?= number_format($sa['stock_minimo'],1) ?> <?= $sa['abreviatura'] ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            <?php endif; ?>
          </div>
        </div>

      </div>

      <!-- ENTRADAS POR SUCURSAL -->
      <?php if (!empty($entradasSuc)): ?>
      <div class="panel">
        <div class="panel-header"><h2>📦 Entradas por Sucursal</h2></div>
        <div class="table-wrapper">
          <table>
            <thead><tr><th>Sucursal</th><th>Nº Entradas</th><th>Total Unidades</th></tr></thead>
            <tbody>
              <?php foreach ($entradasSuc as $es): ?>
              <tr>
                <td><strong><?= htmlspecialchars($es['sucursal']) ?></strong></td>
                <td><?= $es['num_entradas'] ?></td>
                <td><?= number_format($es['total_cantidad'],1) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php endif; ?>

    </main>
  </div>
</div>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>