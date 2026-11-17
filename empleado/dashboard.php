<?php
define('ROOT', dirname(__DIR__));
define('BASE_URL', 'http://localhost/eat_amor');
require_once ROOT . '/config/database.php';
require_once ROOT . '/config/session.php';
requireEmpleado();

$pdo       = getDB();
$usuario   = usuarioActual();
$idSucursal = sucursalActual();

// ── Métricas de la sucursal del empleado ──────────────────
$totalProductos = $pdo->query("SELECT COUNT(DISTINCT id_producto) FROM inventario WHERE id_sucursal=$idSucursal")->fetchColumn();

$entradas = $pdo->query("SELECT COALESCE(SUM(cantidad),0) FROM entradas
    WHERE id_sucursal=$idSucursal AND MONTH(fecha)=MONTH(NOW()) AND YEAR(fecha)=YEAR(NOW())")->fetchColumn();

$salidas = $pdo->query("SELECT COALESCE(SUM(cantidad_real),0) FROM salidas
    WHERE id_sucursal=$idSucursal AND MONTH(fecha)=MONTH(NOW()) AND YEAR(fecha)=YEAR(NOW())")->fetchColumn();

$stockBajo = $pdo->query("SELECT COUNT(*) FROM inventario
    WHERE id_sucursal=$idSucursal AND stock_actual < stock_minimo")->fetchColumn();

// Nombre de la sucursal
$sucursalNombre = $pdo->query("SELECT nombre FROM sucursales WHERE id_sucursal=$idSucursal")->fetchColumn();

// Productos con stock bajo
$productosStockBajo = $pdo->query("
    SELECT p.nombre AS producto, c.nombre AS categoria,
           i.stock_actual, i.stock_minimo, um.abreviatura
    FROM inventario i
    JOIN productos p  ON p.id_producto  = i.id_producto
    JOIN categorias c ON c.id_categoria = p.id_categoria
    JOIN unidades_medida um ON um.id_unidad = p.id_unidad
    WHERE i.id_sucursal=$idSucursal AND i.stock_actual < i.stock_minimo
    ORDER BY (i.stock_actual - i.stock_minimo) ASC LIMIT 6
")->fetchAll();

// Movimientos recientes de la sucursal
$movimientos = $pdo->query("
    (SELECT 'Entrada' tipo, fecha, p.nombre producto, cantidad, um.abreviatura
     FROM entradas e
     JOIN productos p ON p.id_producto=e.id_producto
     JOIN unidades_medida um ON um.id_unidad=p.id_unidad
     WHERE e.id_sucursal=$idSucursal ORDER BY fecha DESC LIMIT 5)
    UNION ALL
    (SELECT 'Salida' tipo, fecha, p.nombre producto, cantidad_real cantidad, um.abreviatura
     FROM salidas sa
     JOIN productos p ON p.id_producto=sa.id_producto
     JOIN unidades_medida um ON um.id_unidad=p.id_unidad
     WHERE sa.id_sucursal=$idSucursal ORDER BY fecha DESC LIMIT 5)
    ORDER BY fecha DESC LIMIT 8
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard Empleado — Eat Amor</title>
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
        <span class="topbar-sucursal">📍 <?= htmlspecialchars($sucursalNombre) ?></span>
      </div>
    </header>

    <main class="content">

      <!-- Bienvenida -->
      <div style="margin-bottom:24px;">
        <h2 style="font-family:'Playfair Display',serif;font-size:22px;color:#1a1a1a;">
          👋 Hola, <?= htmlspecialchars(explode(' ', $usuario['nombre'])[0]) ?>
        </h2>
        <p style="color:#666;font-size:14px;">Aquí está el resumen de tu sucursal de hoy.</p>
      </div>

      <!-- MÉTRICAS -->
      <div class="cards-grid">
        <div class="metric-card fade-in">
          <span class="metric-icon">📦</span>
          <span class="metric-label">Productos</span>
          <span class="metric-value"><?= $totalProductos ?></span>
          <span class="metric-sub">en tu sucursal</span>
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
          <span class="metric-sub">por reponer</span>
        </div>
      </div>

      <!-- Accesos rápidos -->
      <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:24px;">
        <a href="<?= BASE_URL ?>/empleado/entradas.php">
          <button class="btn btn-primary">⬇️ Registrar Entrada</button>
        </a>
        <a href="<?= BASE_URL ?>/empleado/salidas.php">
          <button class="btn btn-rosa">⬆️ Registrar Salida</button>
        </a>
        <a href="<?= BASE_URL ?>/empleado/pos.php">
          <button class="btn btn-dorado">🛒 Punto de Venta</button>
        </a>
      </div>

      <!-- STOCK BAJO + MOVIMIENTOS -->
      <div class="two-col">

        <div class="panel">
          <div class="panel-header">
            <h2>⚠️ Stock Bajo</h2>
            <a href="<?= BASE_URL ?>/empleado/productos.php" class="btn btn-sm btn-outline">Ver inventario</a>
          </div>
          <div class="table-wrapper">
            <?php if (empty($productosStockBajo)): ?>
              <p style="padding:20px;color:#666;text-align:center;">✅ Todo el stock está bien</p>
            <?php else: ?>
              <table>
                <thead><tr><th>Producto</th><th>Categoría</th><th>Actual</th><th>Mínimo</th></tr></thead>
                <tbody>
                  <?php foreach ($productosStockBajo as $p): ?>
                  <tr>
                    <td><strong><?= htmlspecialchars($p['producto']) ?></strong></td>
                    <td><small><?= htmlspecialchars($p['categoria']) ?></small></td>
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
          <div class="panel-header">
            <h2>🔄 Movimientos Recientes</h2>
          </div>
          <div class="table-wrapper">
            <?php if (empty($movimientos)): ?>
              <p style="padding:20px;color:#666;text-align:center;">Sin movimientos aún</p>
            <?php else: ?>
              <table>
                <thead><tr><th>Tipo</th><th>Producto</th><th>Cantidad</th><th>Fecha</th></tr></thead>
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
                    <td><small><?= date('d/m/y', strtotime($m['fecha'])) ?></small></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            <?php endif; ?>
          </div>
        </div>

      </div>

    </main>
  </div>
</div>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>