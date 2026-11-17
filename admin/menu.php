<?php
define('ROOT', dirname(__DIR__));
define('BASE_URL', 'http://localhost/eat_amor');
require_once ROOT . '/config/database.php';
require_once ROOT . '/config/session.php';
requireAdmin();

$pdo     = getDB();
$usuario = usuarioActual();
$msg     = $_GET['msg'] ?? '';

$categorias = $pdo->query("SELECT * FROM menu_categorias ORDER BY orden")->fetchAll();
$catFiltro  = (int)($_GET['categoria'] ?? 0);
$busqueda   = trim($_GET['busqueda'] ?? '');

// POST: crear / editar / toggle item
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'crear' || $accion === 'editar') {
        $nombre  = trim($_POST['nombre']);
        $idCat   = (int)$_POST['id_menu_cat'];
        $precio  = (float)$_POST['precio'];
        $desc    = trim($_POST['descripcion'] ?? '');

        if (!$nombre || !$idCat || !$precio) {
            header('Location: menu.php?msg=error_campos'); exit;
        }

        if ($accion === 'crear') {
            $pdo->prepare("INSERT INTO menu_items (id_menu_cat, nombre, descripcion, precio) VALUES (?,?,?,?)")
                ->execute([$idCat, $nombre, $desc, $precio]);
            header('Location: menu.php?msg=creado'); exit;
        } else {
            $idItem = (int)$_POST['id_item'];
            $pdo->prepare("UPDATE menu_items SET id_menu_cat=?, nombre=?, descripcion=?, precio=? WHERE id_item=?")
                ->execute([$idCat, $nombre, $desc, $precio, $idItem]);
            header('Location: menu.php?msg=editado'); exit;
        }
    }

    if ($accion === 'toggle') {
        $idItem = (int)$_POST['id_item'];
        $disp   = (int)$_POST['disponible'];
        $pdo->prepare("UPDATE menu_items SET disponible=? WHERE id_item=?")
            ->execute([$disp ? 0 : 1, $idItem]);
        header('Location: menu.php?msg=actualizado'); exit;
    }

    if ($accion === 'eliminar') {
        $idItem = (int)$_POST['id_item'];
        $pdo->prepare("DELETE FROM menu_items WHERE id_item=?")->execute([$idItem]);
        header('Location: menu.php?msg=eliminado'); exit;
    }
}

// Query items
$wheres = ["1=1"];
$params = [];
if ($catFiltro) { $wheres[] = "mi.id_menu_cat=?"; $params[] = $catFiltro; }
if ($busqueda)  { $wheres[] = "mi.nombre LIKE ?";  $params[] = "%$busqueda%"; }
$whereSQL = implode(" AND ", $wheres);

$items = $pdo->prepare("
    SELECT mi.*, mc.nombre AS categoria
    FROM menu_items mi
    JOIN menu_categorias mc ON mc.id_menu_cat = mi.id_menu_cat
    WHERE $whereSQL
    ORDER BY mc.orden, mi.nombre
");
$items->execute($params);
$items = $items->fetchAll();

$total = count($items);
$disponibles = count(array_filter($items, fn($i) => $i['disponible']));
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Menú — Eat Amor</title>
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
        <h1 class="topbar-title">Menú</h1>
      </div>
      <div class="topbar-right">
        <button class="btn btn-primary" onclick="abrirModal('modalItem')">+ Agregar Item</button>
      </div>
    </header>
    <main class="content">

      <?php
      $msgs = [
        'creado'      => ['success','✅ Item agregado al menú.'],
        'editado'     => ['success','✅ Item actualizado.'],
        'actualizado' => ['success','✅ Disponibilidad actualizada.'],
        'eliminado'   => ['success','✅ Item eliminado.'],
        'error_campos'=> ['error',  '⚠️ Completa todos los campos obligatorios.'],
      ];
      if ($msg && isset($msgs[$msg])):
        [$tipo,$texto] = $msgs[$msg];
      ?><div class="alert alert-<?= $tipo ?>" data-dismiss="3000"><?= $texto ?></div><?php endif; ?>

      <!-- MÉTRICAS -->
      <div class="cards-grid" style="margin-bottom:20px;">
        <div class="metric-card fade-in">
          <span class="metric-icon">🥞</span>
          <span class="metric-label">Total Items</span>
          <span class="metric-value"><?= $total ?></span>
          <span class="metric-sub">en el menú</span>
        </div>
        <div class="metric-card verde fade-in">
          <span class="metric-icon">✅</span>
          <span class="metric-label">Disponibles</span>
          <span class="metric-value"><?= $disponibles ?></span>
          <span class="metric-sub">activos en POS</span>
        </div>
        <div class="metric-card rojo fade-in">
          <span class="metric-icon">⛔</span>
          <span class="metric-label">No disponibles</span>
          <span class="metric-value"><?= $total - $disponibles ?></span>
          <span class="metric-sub">ocultos en POS</span>
        </div>
        <div class="metric-card fade-in">
          <span class="metric-icon">🗂️</span>
          <span class="metric-label">Categorías</span>
          <span class="metric-value"><?= count($categorias) ?></span>
          <span class="metric-sub">categorías activas</span>
        </div>
      </div>

      <!-- FILTROS -->
      <div class="panel">
        <div class="panel-header">
          <form method="GET" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
            <div class="input-group">
              <span class="input-icon">🔍</span>
              <input type="text" name="busqueda" class="form-control" placeholder="Buscar item..." value="<?= htmlspecialchars($busqueda) ?>" style="max-width:220px;">
            </div>
            <select name="categoria" class="form-control" style="max-width:200px;">
              <option value="0">Todas las categorías</option>
              <?php foreach ($categorias as $cat): ?>
                <option value="<?= $cat['id_menu_cat'] ?>" <?= $catFiltro==$cat['id_menu_cat']?'selected':'' ?>>
                  <?= htmlspecialchars($cat['nombre']) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-outline">Filtrar</button>
            <a href="menu.php"><button type="button" class="btn btn-outline">Limpiar</button></a>
          </form>
          <span style="color:#666;font-size:13px;"><?= $total ?> item(s)</span>
        </div>

        <div class="table-wrapper">
          <table>
            <thead>
              <tr><th>#</th><th>Nombre</th><th>Categoría</th><th>Precio</th><th>Estado</th><th>Acciones</th></tr>
            </thead>
            <tbody>
              <?php if (empty($items)): ?>
                <tr><td colspan="6" style="text-align:center;padding:30px;color:#666;">No se encontraron items</td></tr>
              <?php endif; ?>
              <?php foreach ($items as $item): ?>
              <tr>
                <td style="color:#aaa;font-size:12px;"><?= $item['id_item'] ?></td>
                <td>
                  <strong><?= htmlspecialchars($item['nombre']) ?></strong>
                  <?php if ($item['descripcion']): ?>
                    <br><small style="color:#aaa;"><?= htmlspecialchars(substr($item['descripcion'],0,50)) ?></small>
                  <?php endif; ?>
                </td>
                <td><span class="badge badge-gris"><?= htmlspecialchars($item['categoria']) ?></span></td>
                <td><strong style="color:var(--rojo);">$<?= number_format($item['precio'],2) ?></strong></td>
                <td>
                  <span class="badge <?= $item['disponible']?'badge-verde':'badge-gris' ?>">
                    <?= $item['disponible'] ? '✅ Disponible' : '⛔ No disponible' ?>
                  </span>
                </td>
                <td>
                  <button class="btn-icon" title="Editar"
                    onclick="abrirEditar(<?= htmlspecialchars(json_encode($item)) ?>)">✏️</button>
                  <form method="POST" style="display:inline;">
                    <input type="hidden" name="accion" value="toggle">
                    <input type="hidden" name="id_item" value="<?= $item['id_item'] ?>">
                    <input type="hidden" name="disponible" value="<?= $item['disponible'] ?>">
                    <button type="submit" class="btn-icon" title="<?= $item['disponible']?'Ocultar en POS':'Mostrar en POS' ?>">
                      <?= $item['disponible'] ? '👁️' : '🚫' ?>
                    </button>
                  </form>
                  <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar este item del menú?')">
                    <input type="hidden" name="accion" value="eliminar">
                    <input type="hidden" name="id_item" value="<?= $item['id_item'] ?>">
                    <button type="submit" class="btn-icon" title="Eliminar">🗑️</button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </main>
  </div>
</div>

<!-- MODAL CREAR -->
<div class="modal-overlay" id="modalItem">
  <div class="modal">
    <div class="modal-header">
      <h3>🥞 Agregar Item al Menú</h3>
      <button class="btn-icon" onclick="cerrarModal('modalItem')">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="accion" value="crear">
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group">
            <label>Nombre *</label>
            <input type="text" name="nombre" class="form-control" placeholder="Ej. Crepa Sencilla" required>
          </div>
          <div class="form-group">
            <label>Categoría *</label>
            <select name="id_menu_cat" class="form-control" required>
              <option value="">Seleccione...</option>
              <?php foreach ($categorias as $cat): ?>
                <option value="<?= $cat['id_menu_cat'] ?>"><?= htmlspecialchars($cat['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Precio *</label>
            <input type="number" name="precio" class="form-control" step="0.01" min="0" placeholder="0.00" required>
          </div>
        </div>
        <div class="form-group">
          <label>Descripción</label>
          <textarea name="descripcion" class="form-control" rows="2" placeholder="Descripción opcional..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="cerrarModal('modalItem')">Cancelar</button>
        <button type="submit" class="btn btn-primary">💾 Guardar</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL EDITAR -->
<div class="modal-overlay" id="modalEditar">
  <div class="modal">
    <div class="modal-header">
      <h3>✏️ Editar Item</h3>
      <button class="btn-icon" onclick="cerrarModal('modalEditar')">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="accion" value="editar">
      <input type="hidden" name="id_item" id="edit_id">
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group">
            <label>Nombre *</label>
            <input type="text" name="nombre" id="edit_nombre" class="form-control" required>
          </div>
          <div class="form-group">
            <label>Categoría *</label>
            <select name="id_menu_cat" id="edit_cat" class="form-control" required>
              <?php foreach ($categorias as $cat): ?>
                <option value="<?= $cat['id_menu_cat'] ?>"><?= htmlspecialchars($cat['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Precio *</label>
            <input type="number" name="precio" id="edit_precio" class="form-control" step="0.01" min="0" required>
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
function abrirEditar(item) {
  document.getElementById('edit_id').value     = item.id_item;
  document.getElementById('edit_nombre').value = item.nombre;
  document.getElementById('edit_cat').value    = item.id_menu_cat;
  document.getElementById('edit_precio').value = item.precio;
  document.getElementById('edit_desc').value   = item.descripcion || '';
  abrirModal('modalEditar');
}
</script>
</body>
</html>