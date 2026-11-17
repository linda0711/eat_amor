<?php
define('ROOT', dirname(__DIR__));
define('BASE_URL', 'http://localhost/eat_amor');
require_once ROOT . '/config/database.php';
require_once ROOT . '/config/session.php';
requireAdmin();

$pdo     = getDB();
$usuario = usuarioActual();

$sucursalFiltro = isset($_GET['sucursal']) ? (int)$_GET['sucursal'] : 0;
$sucursales     = $pdo->query("SELECT * FROM sucursales WHERE activa=1 ORDER BY id_sucursal")->fetchAll();

// ── Aquí especifico siempre el alias de tabla para evitar ambigüedad ──
$sWhereI  = $sucursalFiltro ? "AND i.id_sucursal=$sucursalFiltro"   : "";
$sWhereE  = $sucursalFiltro ? "AND e.id_sucursal=$sucursalFiltro"   : "";
$sWhereSa = $sucursalFiltro ? "AND sa.id_sucursal=$sucursalFiltro"  : "";
$sWhereV  = $sucursalFiltro ? "AND v.id_sucursal=$sucursalFiltro"   : "";
$sWhereIn = $sucursalFiltro ? "AND id_sucursal=$sucursalFiltro"     : "";

// Métricas
$totalProductos = $pdo->query("
    SELECT COUNT(DISTINCT id_producto) FROM inventario WHERE 1=1 $sWhereIn
")->fetchColumn();

$entradas = $pdo->query("
    SELECT COALESCE(SUM(cantidad),0) FROM entradas e
    WHERE MONTH(e.fecha)=MONTH(NOW()) AND YEAR(e.fecha)=YEAR(NOW()) $sWhereE
")->fetchColumn();

$salidas = $pdo->query("
    SELECT COALESCE(SUM(sa.cantidad_real),0) FROM salidas sa
    WHERE MONTH(sa.fecha)=MONTH(NOW()) AND YEAR(sa.fecha)=YEAR(NOW()) $sWhereSa
")->fetchColumn();

$stockBajo = $pdo->query("
    SELECT COUNT(*) FROM inventario i
    WHERE i.stock_actual < i.stock_minimo $sWhereI
")->fetchColumn();

$ventasHoy = $pdo->query("
    SELECT COALESCE(SUM(v.total),0) FROM ventas v
    WHERE DATE(v.fecha)=CURDATE() $sWhereV
")->fetchColumn();

// Productos con stock bajo
$productosStockBajo = $pdo->query("
    SELECT p.nombre AS producto, c.nombre AS categoria,
           i.stock_actual, i.stock_minimo, um.abreviatura,
           s.nombre AS sucursal
    FROM inventario i
    JOIN productos p       ON p.id_producto  = i.id_producto
    JOIN categorias c      ON c.id_categoria = p.id_categoria
    JOIN unidades_medida um ON um.id_unidad  = p.id_unidad
    JOIN sucursales s      ON s.id_sucursal  = i.id_sucursal
    WHERE i.stock_actual < i.stock_minimo $sWhereI
    ORDER BY (i.stock_actual - i.stock_minimo) ASC LIMIT 8
")->fetchAll();

// Movimientos recientes
$movimientos = $pdo->query("
    (SELECT 'Entrada' tipo, e.fecha, p.nombre producto,
            e.cantidad cantidad, um.abreviatura, s.nombre sucursal
     FROM entradas e
     JOIN productos p       ON p.id_producto = e.id_producto
     JOIN unidades_medida um ON um.id_unidad  = p.id_unidad
     JOIN sucursales s      ON s.id_sucursal  = e.id_sucursal
     WHERE 1=1 $sWhereE ORDER BY e.fecha DESC LIMIT 5)
    UNION ALL
    (SELECT 'Salida' tipo, sa.fecha, p.nombre producto,
            sa.cantidad_real cantidad, um.abreviatura, s.nombre sucursal
     FROM salidas sa
     JOIN productos p       ON p.id_producto = sa.id_producto
     JOIN unidades_medida um ON um.id_unidad  = p.id_unidad
     JOIN sucursales s      ON s.id_sucursal  = sa.id_sucursal
     WHERE 1=1 $sWhereSa ORDER BY sa.fecha DESC LIMIT 5)
    ORDER BY fecha DESC LIMIT 8
")->fetchAll();

// Resumen por sucursal
$resumenSucursal = $pdo->query("
    SELECT s.id_sucursal, s.nombre, s.municipio,
           COUNT(DISTINCT i.id_producto) total_productos,
           SUM(CASE WHEN i.stock_actual < i.stock_minimo THEN 1 ELSE 0 END) alertas
    FROM sucursales s
    LEFT JOIN inventario i ON i.id_sucursal = s.id_sucursal
    WHERE s.activa = 1
    GROUP BY s.id_sucursal, s.nombre, s.municipio
    ORDER BY s.id_sucursal
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard — Eat Amor</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Nunito:wght@300;400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
  <style>
    .two-col { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:24px; }
    @media(max-width:900px){ .two-col{ grid-template-columns:1fr; } }
  </style>
</head>
<body>
<div class="layout">
  <?php require_once ROOT . '/includes/sidebar.php'; ?>
  <div class="main-wrapper">
    <header class="topbar">
      <div class="topbar-left">
        <button class="btn-menu-toggle" id="btnMenuToggle">☰</button>
        <h1 class="topbar-title">Dashboard</h1>
      </div>
      <div class="topbar-right">
        <?php if ($sucursalFiltro):
          $sActiva = array_values(array_filter($sucursales, fn($s)=>$s['id_sucursal']==$sucursalFiltro))[0] ?? null;
        ?>
          <span class="topbar-sucursal">📍 <?= htmlspecialchars($sActiva['nombre'] ?? '') ?></span>
        <?php else: ?>
          <span class="topbar-sucursal">🌐 Todas las sucursales</span>
        <?php endif; ?>
      </div>
    </header>

    <main class="content">

      <!-- CHIPS SUCURSAL -->
      <div class="sucursal-chips">
        <a href="?sucursal=0"><button class="chip-sucursal <?= !$sucursalFiltro?'activo':'' ?>">🌐 Todas</button></a>
        <?php foreach ($sucursales as $s): ?>
          <a href="?sucursal=<?= $s['id_sucursal'] ?>">
            <button class="chip-sucursal <?= $sucursalFiltro==(int)$s['id_sucursal']?'activo':'' ?>">
              🏪 <?= htmlspecialchars($s['municipio']) ?>
            </button>
          </a>
        <?php endforeach; ?>
      </div>

      <!-- MÉTRICAS -->
      <div class="cards-grid">
        <div class="metric-card fade-in">
          <span class="metric-icon">📦</span>
          <span class="metric-label">Total Productos</span>
          <span class="metric-value"><?= number_format($totalProductos) ?></span>
          <span class="metric-sub">en inventario</span>
        </div>
        <div class="metric-card verde fade-in">
          <span class="metric-icon">⬇️</span>
          <span class="metric-label">Entradas (mes)</span>
          <span class="metric-value"><?= number_format($entradas,0) ?></span>
          <span class="metric-sub">unidades ingresadas</span>
        </div>
        <div class="metric-card rosa fade-in">
          <span class="metric-icon">⬆️</span>
          <span class="metric-label">Salidas (mes)</span>
          <span class="metric-value"><?= number_format($salidas,0) ?></span>
          <span class="metric-sub">unidades despachadas</span>
        </div>
        <div class="metric-card rojo fade-in">
          <span class="metric-icon">⚠️</span>
          <span class="metric-label">Stock Bajo</span>
          <span class="metric-value"><?= $stockBajo ?></span>
          <span class="metric-sub">productos por reponer</span>
        </div>
        <div class="metric-card fade-in">
          <span class="metric-icon">💰</span>
          <span class="metric-label">Ventas Hoy</span>
          <span class="metric-value" style="font-size:22px;">$<?= number_format($ventasHoy,2) ?></span>
          <span class="metric-sub">ingresos del día</span>
        </div>
      </div>

      <!-- STOCK BAJO + MOVIMIENTOS -->
      <div class="two-col">
        <div class="panel">
          <div class="panel-header">
            <h2>⚠️ Stock Bajo</h2>
            <a href="<?= BASE_URL ?>/admin/productos.php<?= $sucursalFiltro?"?sucursal=$sucursalFiltro":'' ?>" class="btn btn-sm btn-outline">Ver todos</a>
          </div>
          <div class="table-wrapper">
            <?php if (empty($productosStockBajo)): ?>
              <p style="padding:20px;color:#666;text-align:center;">✅ Todo el stock está bien</p>
            <?php else: ?>
              <table>
                <thead><tr><th>Producto</th><th>Sucursal</th><th>Actual</th><th>Mínimo</th></tr></thead>
                <tbody>
                  <?php foreach ($productosStockBajo as $p): ?>
                  <tr>
                    <td>
                      <strong><?= htmlspecialchars($p['producto']) ?></strong><br>
                      <small style="color:#aaa;"><?= htmlspecialchars($p['categoria']) ?></small>
                    </td>
                    <td><small><?= htmlspecialchars($p['sucursal']) ?></small></td>
                    <td class="stock-bajo"><?= number_format($p['stock_actual'],1) ?> <?= $p['abreviatura'] ?></td>
                    <td style="color:#666;"><?= number_format($p['stock_minimo'],1) ?> <?= $p['abreviatura'] ?></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            <?php endif; ?>
          </div>
        </div>

        <div class="panel">
          <div class="panel-header"><h2>🔄 Movimientos Recientes</h2></div>
          <div class="table-wrapper">
            <?php if (empty($movimientos)): ?>
              <p style="padding:20px;color:#666;text-align:center;">Sin movimientos aún</p>
            <?php else: ?>
              <table>
                <thead><tr><th>Tipo</th><th>Producto</th><th>Cantidad</th><th>Sucursal</th><th>Fecha</th></tr></thead>
                <tbody>
                  <?php foreach ($movimientos as $m): ?>
                  <tr>
                    <td>
                      <span class="badge <?= $m['tipo']==='Entrada'?'badge-verde':'badge-rojo' ?>">
                        <?= $m['tipo']==='Entrada'?'⬇️':'⬆️' ?> <?= $m['tipo'] ?>
                      </span>
                    </td>
                    <td><?= htmlspecialchars($m['producto']) ?></td>
                    <td><?= number_format($m['cantidad'],1) ?> <?= $m['abreviatura'] ?></td>
                    <td><small><?= htmlspecialchars($m['sucursal']) ?></small></td>
                    <td><small><?= date('d/m/y', strtotime($m['fecha'])) ?></small></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- RESUMEN POR SUCURSAL solo en vista global -->
      <?php if (!$sucursalFiltro): ?>
      <div class="panel">
        <div class="panel-header"><h2>🏪 Resumen por Sucursal</h2></div>
        <div class="table-wrapper">
          <table>
            <thead><tr><th>Sucursal</th><th>Municipio</th><th>Productos</th><th>Alertas</th><th>Estado</th><th>Acciones</th></tr></thead>
            <tbody>
              <?php foreach ($resumenSucursal as $rs): ?>
              <tr>
                <td><strong><?= htmlspecialchars($rs['nombre']) ?></strong></td>
                <td><?= htmlspecialchars($rs['municipio']) ?></td>
                <td><?= $rs['total_productos'] ?></td>
                <td>
                  <?php if ($rs['alertas'] > 0): ?>
                    <span class="badge badge-rojo"> <?= $rs['alertas'] ?></span>
                  <?php else: ?>
                    <span class="badge badge-verde"> OK</span>
                  <?php endif; ?>
                </td>
                <td><span class="badge badge-verde">Activa</span></td>
                <td>
                  <a href="?sucursal=<?= $rs['id_sucursal'] ?>">
                    <button class="btn btn-sm btn-outline">Ver detalle →</button>
                  </a>
                </td>
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