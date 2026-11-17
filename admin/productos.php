<?php
define('ROOT', dirname(__DIR__));
define('BASE_URL', 'http://localhost/eat_amor');
require_once ROOT . '/config/database.php';
require_once ROOT . '/config/session.php';
requireAdmin();

$pdo     = getDB();
$usuario = usuarioActual();
$msg     = $_GET['msg'] ?? '';
$error   = $_GET['error'] ?? '';

// ── Sucursal filtrada ─────────────────────────────────────
$sucursalFiltro = isset($_GET['sucursal']) ? (int)$_GET['sucursal'] : 0;
$sucursales     = $pdo->query("SELECT * FROM sucursales WHERE activa=1 ORDER BY id_sucursal")->fetchAll();

// ── Búsqueda y filtro de categoría ────────────────────────
$busqueda  = trim($_GET['busqueda'] ?? '');
$catFiltro = (int)($_GET['categoria'] ?? 0);
$categorias = $pdo->query("SELECT * FROM categorias WHERE activa=1 ORDER BY nombre")->fetchAll();

// ── Paginación ────────────────────────────────────────────
$porPagina  = 10;
$pagina     = max(1, (int)($_GET['pagina'] ?? 1));
$offset     = ($pagina - 1) * $porPagina;

// ── Query de productos ────────────────────────────────────
$params  = [];
$wheres  = ["p.activo = 1"];

if ($busqueda) {
    $wheres[]  = "p.nombre LIKE ?";
    $params[]  = "%$busqueda%";
}
if ($catFiltro) {
    $wheres[]  = "p.id_categoria = ?";
    $params[]  = $catFiltro;
}

$whereSQL = "WHERE " . implode(" AND ", $wheres);

// Total para paginación
$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM productos p $whereSQL");
$totalStmt->execute($params);
$total      = $totalStmt->fetchColumn();
$totalPags  = ceil($total / $porPagina);

// Productos con stock por sucursal
$sJoin  = $sucursalFiltro
    ? "LEFT JOIN inventario i ON i.id_producto=p.id_producto AND i.id_sucursal=$sucursalFiltro"
    : "LEFT JOIN (SELECT id_producto, SUM(stock_actual) stock_actual, SUM(stock_minimo) stock_minimo FROM inventario GROUP BY id_producto) i ON i.id_producto=p.id_producto";

$stmt = $pdo->prepare("
    SELECT p.id_producto, p.nombre, p.descripcion, p.precio_unitario,
           c.nombre AS categoria, um.nombre AS unidad, um.abreviatura,
           COALESCE(i.stock_actual,0) AS stock_actual,
           COALESCE(i.stock_minimo,0) AS stock_minimo
    FROM productos p
    JOIN categorias c ON c.id_categoria = p.id_categoria
    JOIN unidades_medida um ON um.id_unidad = p.id_unidad
    $sJoin
    $whereSQL
    ORDER BY p.nombre ASC
    LIMIT $porPagina OFFSET $offset
");
$stmt->execute($params);
$productos = $stmt->fetchAll();

// ── CRUD: guardar producto (POST) ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'crear' || $accion === 'editar') {
        $nombre    = trim($_POST['nombre'] ?? '');
        $desc      = trim($_POST['descripcion'] ?? '');
        $idCat     = (int)$_POST['id_categoria'];
        $idUnd     = (int)$_POST['id_unidad'];
        $precio    = (float)$_POST['precio_unitario'];
        $stockMin  = (float)$_POST['stock_minimo'];
        $stockIni  = (float)($_POST['stock_inicial'] ?? 0);

        if (!$nombre || !$idCat || !$idUnd) {
            header('Location: ?msg=&error=campos_vacios'); exit;
        }

        if ($accion === 'crear') {
            $s = $pdo->prepare("INSERT INTO productos (nombre,descripcion,id_categoria,id_unidad,precio_unitario) VALUES (?,?,?,?,?)");
            $s->execute([$nombre,$desc,$idCat,$idUnd,$precio]);
            $idProd = $pdo->lastInsertId();

            // Insertar inventario inicial en todas las sucursales
            foreach ($sucursales as $suc) {
                $pdo->prepare("INSERT INTO inventario (id_sucursal,id_producto,stock_actual,stock_minimo) VALUES (?,?,?,?)
                    ON DUPLICATE KEY UPDATE stock_minimo=VALUES(stock_minimo)")
                    ->execute([$suc['id_sucursal'], $idProd, $stockIni, $stockMin]);
            }
            header('Location: productos.php?msg=producto_creado'); exit;

        } else {
            $idProd = (int)$_POST['id_producto'];
            $pdo->prepare("UPDATE productos SET nombre=?,descripcion=?,id_categoria=?,id_unidad=?,precio_unitario=? WHERE id_producto=?")
                ->execute([$nombre,$desc,$idCat,$idUnd,$precio,$idProd]);
            // Actualizar stock mínimo en todas las sucursales
            $pdo->prepare("UPDATE inventario SET stock_minimo=? WHERE id_producto=?")
                ->execute([$stockMin,$idProd]);
            header('Location: productos.php?msg=producto_editado'); exit;
        }
    }

    if ($accion === 'eliminar') {
        $idProd = (int)$_POST['id_producto'];
        $pdo->prepare("UPDATE productos SET activo=0 WHERE id_producto=?")->execute([$idProd]);
        header('Location: productos.php?msg=producto_eliminado'); exit;
    }
}

$unidades = $pdo->query("SELECT * FROM unidades_medida ORDER BY nombre")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Productos — Eat Amor</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Nunito:wght@300;400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
  <style>
    .search-bar { display:flex; gap:10px; flex-wrap:wrap; align-items:center; }
    .search-bar .form-control { max-width:260px; }
    .search-bar select.form-control { max-width:180px; }
    .precio { color:#1a1a1a; font-weight:700; }
  </style>
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
        <button class="btn btn-primary" onclick="abrirModal('modalProducto')">+ Agregar Producto</button>
      </div>
    </header>

    <main class="content">

      <?php if ($msg): ?>
        <?php $msgs = ['producto_creado'=>'Producto creado correctamente','producto_editado'=>'Producto actualizado','producto_eliminado'=>'Producto eliminado']; ?>
        <div class="alert alert-success" data-dismiss="3000">✅ <?= $msgs[$msg] ?? $msg ?></div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="alert alert-error" data-dismiss="3000">⚠️ Completa todos los campos obligatorios.</div>
      <?php endif; ?>

      <!-- CHIPS SUCURSAL -->
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

      <!-- BUSCADOR -->
      <div class="panel">
        <div class="panel-header">
          <form method="GET" class="search-bar">
            <input type="hidden" name="sucursal" value="<?= $sucursalFiltro ?>">
            <div class="input-group">
              <span class="input-icon">🔍</span>
              <input type="text" name="busqueda" class="form-control" placeholder="Buscar producto..." value="<?= htmlspecialchars($busqueda) ?>">
            </div>
            <select name="categoria" class="form-control">
              <option value="0">Todas las categorías</option>
              <?php foreach ($categorias as $cat): ?>
                <option value="<?= $cat['id_categoria'] ?>" <?= $catFiltro==$cat['id_categoria']?'selected':'' ?>>
                  <?= htmlspecialchars($cat['nombre']) ?>
                </option>
              <?php endforeach; ?>
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
                <th>Producto</th>
                <th>Categoría</th>
                <th>Unidad</th>
                <th>Stock Actual</th>
                <th>Stock Mínimo</th>
                <th>Precio</th>
                <th>Estado</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($productos)): ?>
                <tr><td colspan="9" style="text-align:center;padding:30px;color:#666;">No se encontraron productos</td></tr>
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
                    <?php if ($p['stock_actual'] < $p['stock_minimo']): ?>
                      <span class="badge badge-rojo" style="margin-left:4px;">⚠️ Bajo</span>
                    <?php endif; ?>
                  </td>
                  <td><?= number_format($p['stock_minimo'],1) ?> <?= $p['abreviatura'] ?></td>
                  <td class="precio">$<?= number_format($p['precio_unitario'],2) ?></td>
                  <td><span class="badge badge-verde">Activo</span></td>
                  <td>
                    <button class="btn-icon" title="Editar"
                      onclick="abrirEditar(<?= htmlspecialchars(json_encode($p)) ?>)">✏️</button>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar este producto?')">
                      <input type="hidden" name="accion" value="eliminar">
                      <input type="hidden" name="id_producto" value="<?= $p['id_producto'] ?>">
                      <button type="submit" class="btn-icon" title="Eliminar">🗑️</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <!-- PAGINACIÓN -->
        <?php if ($totalPags > 1): ?>
        <div class="pagination">
          <?php if ($pagina > 1): ?>
            <a href="?pagina=<?= $pagina-1 ?>&sucursal=<?= $sucursalFiltro ?>&busqueda=<?= urlencode($busqueda) ?>&categoria=<?= $catFiltro ?>">
              <button class="page-btn">← Anterior</button>
            </a>
          <?php endif; ?>
          <?php for ($i=1; $i<=$totalPags; $i++): ?>
            <a href="?pagina=<?= $i ?>&sucursal=<?= $sucursalFiltro ?>&busqueda=<?= urlencode($busqueda) ?>&categoria=<?= $catFiltro ?>">
              <button class="page-btn <?= $i===$pagina?'activo':'' ?>"><?= $i ?></button>
            </a>
          <?php endfor; ?>
          <?php if ($pagina < $totalPags): ?>
            <a href="?pagina=<?= $pagina+1 ?>&sucursal=<?= $sucursalFiltro ?>&busqueda=<?= urlencode($busqueda) ?>&categoria=<?= $catFiltro ?>">
              <button class="page-btn">Siguiente →</button>
            </a>
          <?php endif; ?>
        </div>
        <?php endif; ?>

      </div>
    </main>
  </div>
</div>

<!-- MODAL: Crear Producto -->
<div class="modal-overlay" id="modalProducto">
  <div class="modal">
    <div class="modal-header">
      <h3>📦 Agregar Producto</h3>
      <button class="btn-icon" onclick="cerrarModal('modalProducto')">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="accion" value="crear">
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group">
            <label>Nombre del producto *</label>
            <input type="text" name="nombre" class="form-control" placeholder="Ej. Harina 500g" required>
          </div>
          <div class="form-group">
            <label>Categoría *</label>
            <select name="id_categoria" class="form-control" required>
              <option value="">Seleccione...</option>
              <?php foreach ($categorias as $cat): ?>
                <option value="<?= $cat['id_categoria'] ?>"><?= htmlspecialchars($cat['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Unidad de medida *</label>
            <select name="id_unidad" class="form-control" required>
              <option value="">Seleccione...</option>
              <?php foreach ($unidades as $u): ?>
                <option value="<?= $u['id_unidad'] ?>"><?= htmlspecialchars($u['nombre']) ?> (<?= $u['abreviatura'] ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Precio unitario</label>
            <input type="number" name="precio_unitario" class="form-control" step="0.01" min="0" placeholder="0.00">
          </div>
          <div class="form-group">
            <label>Stock mínimo</label>
            <input type="number" name="stock_minimo" class="form-control" step="0.001" min="0" placeholder="0">
          </div>
          <div class="form-group">
            <label>Stock inicial</label>
            <input type="number" name="stock_inicial" class="form-control" step="0.001" min="0" placeholder="0">
          </div>
        </div>
        <div class="form-group">
          <label>Descripción</label>
          <textarea name="descripcion" class="form-control" rows="2" placeholder="Descripción opcional..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="cerrarModal('modalProducto')">Cancelar</button>
        <button type="submit" class="btn btn-primary">💾 Guardar Producto</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL: Editar Producto -->
<div class="modal-overlay" id="modalEditar">
  <div class="modal">
    <div class="modal-header">
      <h3>✏️ Editar Producto</h3>
      <button class="btn-icon" onclick="cerrarModal('modalEditar')">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="accion" value="editar">
      <input type="hidden" name="id_producto" id="edit_id">
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group">
            <label>Nombre del producto *</label>
            <input type="text" name="nombre" id="edit_nombre" class="form-control" required>
          </div>
          <div class="form-group">
            <label>Categoría *</label>
            <select name="id_categoria" id="edit_categoria" class="form-control" required>
              <?php foreach ($categorias as $cat): ?>
                <option value="<?= $cat['id_categoria'] ?>"><?= htmlspecialchars($cat['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Unidad de medida *</label>
            <select name="id_unidad" id="edit_unidad" class="form-control" required>
              <?php foreach ($unidades as $u): ?>
                <option value="<?= $u['id_unidad'] ?>"><?= htmlspecialchars($u['nombre']) ?> (<?= $u['abreviatura'] ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Precio unitario</label>
            <input type="number" name="precio_unitario" id="edit_precio" class="form-control" step="0.01" min="0">
          </div>
          <div class="form-group">
            <label>Stock mínimo</label>
            <input type="number" name="stock_minimo" id="edit_stock_min" class="form-control" step="0.001" min="0">
          </div>
        </div>
        <div class="form-group">
          <label>Descripción</label>
          <textarea name="descripcion" id="edit_desc" class="form-control" rows="2"></textarea>
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
function abrirEditar(p) {
  document.getElementById('edit_id').value         = p.id_producto;
  document.getElementById('edit_nombre').value     = p.nombre;
  document.getElementById('edit_desc').value       = p.descripcion || '';
  document.getElementById('edit_categoria').value  = p.id_categoria;
  document.getElementById('edit_unidad').value     = p.id_unidad;
  document.getElementById('edit_precio').value     = p.precio_unitario;
  document.getElementById('edit_stock_min').value  = p.stock_minimo;
  abrirModal('modalEditar');
}
</script>
</body>
</html>