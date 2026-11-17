<?php
define('ROOT', dirname(__DIR__));
define('BASE_URL', 'http://localhost/eat_amor');
require_once ROOT . '/config/database.php';
require_once ROOT . '/config/session.php';
requireAdmin();

$pdo     = getDB();
$usuario = usuarioActual();
$msg     = $_GET['msg'] ?? '';

// POST: agregar categoría, unidad o proveedor
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'nueva_categoria') {
        $nombre = trim($_POST['nombre_cat']);
        $desc   = trim($_POST['desc_cat'] ?? '');
        if ($nombre) {
            $pdo->prepare("INSERT INTO categorias (nombre, descripcion) VALUES (?,?)")->execute([$nombre,$desc]);
        }
        header('Location: config.php?msg=cat_ok'); exit;
    }

    if ($accion === 'nueva_unidad') {
        $nombre = trim($_POST['nombre_und']);
        $abrev  = trim($_POST['abrev_und']);
        if ($nombre && $abrev) {
            $pdo->prepare("INSERT INTO unidades_medida (nombre, abreviatura) VALUES (?,?)")->execute([$nombre,$abrev]);
        }
        header('Location: config.php?msg=und_ok'); exit;
    }

    if ($accion === 'nuevo_proveedor') {
        $nombre   = trim($_POST['nombre_prov']);
        $contacto = trim($_POST['contacto_prov'] ?? '');
        $telefono = trim($_POST['tel_prov'] ?? '');
        $email    = trim($_POST['email_prov'] ?? '');
        if ($nombre) {
            $pdo->prepare("INSERT INTO proveedores (nombre,contacto,telefono,email) VALUES (?,?,?,?)")
                ->execute([$nombre,$contacto,$telefono,$email]);
        }
        header('Location: config.php?msg=prov_ok'); exit;
    }

    if ($accion === 'nuevo_motivo') {
        $nombre = trim($_POST['nombre_motivo']);
        if ($nombre) {
            $pdo->prepare("INSERT INTO motivos_salida (nombre) VALUES (?)")->execute([$nombre]);
        }
        header('Location: config.php?msg=mot_ok'); exit;
    }

    if ($accion === 'toggle_categoria') {
        $id     = (int)$_POST['id_categoria'];
        $activa = (int)$_POST['activa'];
        $pdo->prepare("UPDATE categorias SET activa=? WHERE id_categoria=?")->execute([$activa?0:1,$id]);
        header('Location: config.php?msg=cat_ok'); exit;
    }

    if ($accion === 'toggle_proveedor') {
        $id     = (int)$_POST['id_proveedor'];
        $activo = (int)$_POST['activo'];
        $pdo->prepare("UPDATE proveedores SET activo=? WHERE id_proveedor=?")->execute([$activo?0:1,$id]);
        header('Location: config.php?msg=prov_ok'); exit;
    }
}

$categorias  = $pdo->query("SELECT * FROM categorias ORDER BY nombre")->fetchAll();
$unidades    = $pdo->query("SELECT * FROM unidades_medida ORDER BY nombre")->fetchAll();
$proveedores = $pdo->query("SELECT * FROM proveedores ORDER BY nombre")->fetchAll();
$motivos     = $pdo->query("SELECT * FROM motivos_salida ORDER BY id_motivo")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Configuración — Eat Amor</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Nunito:wght@300;400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
  <style>
    .config-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; }
    @media(max-width:900px){ .config-grid{ grid-template-columns:1fr; } }
    .add-form { display:flex; gap:8px; margin-top:14px; flex-wrap:wrap; }
    .add-form .form-control { flex:1; min-width:120px; }
    .config-list { list-style:none; margin-top:12px; }
    .config-list li {
      display:flex; align-items:center; justify-content:space-between;
      padding:8px 12px; border-radius:6px; font-size:13px;
      border-bottom:1px solid #eee;
    }
    .config-list li:hover { background:#f9f9f9; }
  </style>
</head>
<body>
<div class="layout">
  <?php require_once ROOT . '/includes/sidebar.php'; ?>
  <div class="main-wrapper">
    <header class="topbar">
      <div class="topbar-left">
        <button class="btn-menu-toggle" id="btnMenuToggle">☰</button>
        <h1 class="topbar-title">Configuración</h1>
      </div>
    </header>
    <main class="content">

      <?php
      $msgs = [
        'cat_ok'  => '✅ Categoría actualizada.',
        'und_ok'  => '✅ Unidad de medida actualizada.',
        'prov_ok' => '✅ Proveedor actualizado.',
        'mot_ok'  => '✅ Motivo de salida actualizado.',
      ];
      if ($msg && isset($msgs[$msg])):
      ?><div class="alert alert-success" data-dismiss="3000"><?= $msgs[$msg] ?></div><?php endif; ?>

      <div class="config-grid">

        <!-- CATEGORÍAS -->
        <div class="panel">
          <div class="panel-header"><h2>🗂️ Categorías de Inventario</h2></div>
          <div class="panel-body">
            <ul class="config-list">
              <?php foreach ($categorias as $cat): ?>
              <li>
                <span>
                  <strong><?= htmlspecialchars($cat['nombre']) ?></strong>
                  <?php if ($cat['descripcion']): ?>
                    <br><small style="color:#aaa;"><?= htmlspecialchars($cat['descripcion']) ?></small>
                  <?php endif; ?>
                </span>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="accion" value="toggle_categoria">
                  <input type="hidden" name="id_categoria" value="<?= $cat['id_categoria'] ?>">
                  <input type="hidden" name="activa" value="<?= $cat['activa'] ?>">
                  <button type="submit" class="btn-icon">
                    <?= $cat['activa'] ? '✅' : '⛔' ?>
                  </button>
                </form>
              </li>
              <?php endforeach; ?>
            </ul>
            <form method="POST" class="add-form">
              <input type="hidden" name="accion" value="nueva_categoria">
              <input type="text" name="nombre_cat" class="form-control" placeholder="Nueva categoría..." required>
              <input type="text" name="desc_cat" class="form-control" placeholder="Descripción (opcional)">
              <button type="submit" class="btn btn-primary btn-sm">+ Agregar</button>
            </form>
          </div>
        </div>

        <!-- UNIDADES -->
        <div class="panel">
          <div class="panel-header"><h2>📏 Unidades de Medida</h2></div>
          <div class="panel-body">
            <ul class="config-list">
              <?php foreach ($unidades as $u): ?>
              <li>
                <span><strong><?= htmlspecialchars($u['nombre']) ?></strong></span>
                <span class="badge badge-gris"><?= htmlspecialchars($u['abreviatura']) ?></span>
              </li>
              <?php endforeach; ?>
            </ul>
            <form method="POST" class="add-form">
              <input type="hidden" name="accion" value="nueva_unidad">
              <input type="text" name="nombre_und" class="form-control" placeholder="Nombre (ej. Gramo)" required>
              <input type="text" name="abrev_und" class="form-control" placeholder="Abrev. (ej. g)" required style="max-width:80px;">
              <button type="submit" class="btn btn-primary btn-sm">+ Agregar</button>
            </form>
          </div>
        </div>

        <!-- PROVEEDORES -->
        <div class="panel">
          <div class="panel-header"><h2>🚚 Proveedores</h2></div>
          <div class="panel-body">
            <ul class="config-list">
              <?php foreach ($proveedores as $pv): ?>
              <li>
                <span>
                  <strong><?= htmlspecialchars($pv['nombre']) ?></strong>
                  <?php if ($pv['telefono']): ?>
                    <br><small style="color:#aaa;">📞 <?= htmlspecialchars($pv['telefono']) ?></small>
                  <?php endif; ?>
                </span>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="accion" value="toggle_proveedor">
                  <input type="hidden" name="id_proveedor" value="<?= $pv['id_proveedor'] ?>">
                  <input type="hidden" name="activo" value="<?= $pv['activo'] ?>">
                  <button type="submit" class="btn-icon"><?= $pv['activo'] ? '✅' : '⛔' ?></button>
                </form>
              </li>
              <?php endforeach; ?>
            </ul>
            <form method="POST" class="add-form">
              <input type="hidden" name="accion" value="nuevo_proveedor">
              <input type="text" name="nombre_prov" class="form-control" placeholder="Nombre proveedor" required>
              <input type="text" name="tel_prov" class="form-control" placeholder="Teléfono" style="max-width:130px;">
              <button type="submit" class="btn btn-primary btn-sm">+ Agregar</button>
            </form>
          </div>
        </div>

        <!-- MOTIVOS DE SALIDA -->
        <div class="panel">
          <div class="panel-header"><h2>📤 Motivos de Salida</h2></div>
          <div class="panel-body">
            <ul class="config-list">
              <?php foreach ($motivos as $m): ?>
              <li>
                <span><strong><?= htmlspecialchars($m['nombre']) ?></strong></span>
                <span class="badge badge-gris">#<?= $m['id_motivo'] ?></span>
              </li>
              <?php endforeach; ?>
            </ul>
            <form method="POST" class="add-form">
              <input type="hidden" name="accion" value="nuevo_motivo">
              <input type="text" name="nombre_motivo" class="form-control" placeholder="Nuevo motivo..." required>
              <button type="submit" class="btn btn-primary btn-sm">+ Agregar</button>
            </form>
          </div>
        </div>

      </div>
    </main>
  </div>
</div>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>