<?php
define('ROOT', dirname(__DIR__));
define('BASE_URL', 'http://localhost/eat_amor');
require_once ROOT . '/config/database.php';
require_once ROOT . '/config/session.php';
requireAdmin();

$pdo     = getDB();
$usuario = usuarioActual();
$msg     = $_GET['msg'] ?? '';

$sucursales = $pdo->query("SELECT * FROM sucursales WHERE activa=1 ORDER BY id_sucursal")->fetchAll();
$roles      = $pdo->query("SELECT * FROM roles ORDER BY id_rol")->fetchAll();

// POST: crear / editar / eliminar usuario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'crear') {
        $nombre  = trim($_POST['nombre']);
        $email   = trim($_POST['email']);
        $pass    = $_POST['password'];
        $idRol   = (int)$_POST['id_rol'];
        $idSuc   = $_POST['id_sucursal'] ? (int)$_POST['id_sucursal'] : null;

        if (!$nombre || !$email || !$pass || !$idRol) {
            header('Location: usuarios.php?msg=error_campos'); exit;
        }

        // Verificar email único
        $existe = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE email=?");
        $existe->execute([$email]);
        if ($existe->fetchColumn() > 0) {
            header('Location: usuarios.php?msg=email_duplicado'); exit;
        }

        $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost'=>12]);
        $pdo->prepare("INSERT INTO usuarios (id_sucursal,id_rol,nombre,email,password_hash) VALUES (?,?,?,?,?)")
            ->execute([$idSuc,$idRol,$nombre,$email,$hash]);

        header('Location: usuarios.php?msg=usuario_creado'); exit;
    }

    if ($accion === 'editar') {
        $idUsr  = (int)$_POST['id_usuario'];
        $nombre = trim($_POST['nombre']);
        $email  = trim($_POST['email']);
        $idRol  = (int)$_POST['id_rol'];
        $idSuc  = $_POST['id_sucursal'] ? (int)$_POST['id_sucursal'] : null;
        $pass   = trim($_POST['password'] ?? '');

        $pdo->prepare("UPDATE usuarios SET nombre=?,email=?,id_rol=?,id_sucursal=? WHERE id_usuario=?")
            ->execute([$nombre,$email,$idRol,$idSuc,$idUsr]);

        if ($pass) {
            $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost'=>12]);
            $pdo->prepare("UPDATE usuarios SET password_hash=? WHERE id_usuario=?")->execute([$hash,$idUsr]);
        }

        header('Location: usuarios.php?msg=usuario_editado'); exit;
    }

    if ($accion === 'toggle') {
        $idUsr  = (int)$_POST['id_usuario'];
        $activo = (int)$_POST['activo'];
        $pdo->prepare("UPDATE usuarios SET activo=? WHERE id_usuario=?")->execute([$activo ? 0 : 1, $idUsr]);
        header('Location: usuarios.php?msg=usuario_actualizado'); exit;
    }
}

// Obtener usuarios
$usuarios = $pdo->query("
    SELECT u.*, r.nombre AS rol, s.nombre AS sucursal
    FROM usuarios u
    JOIN roles r ON r.id_rol=u.id_rol
    LEFT JOIN sucursales s ON s.id_sucursal=u.id_sucursal
    ORDER BY u.id_rol ASC, u.nombre ASC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Usuarios — Eat Amor</title>
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
        <h1 class="topbar-title">Usuarios</h1>
      </div>
      <div class="topbar-right">
        <button class="btn btn-primary" onclick="abrirModal('modalUsuario')">+ Nuevo Usuario</button>
      </div>
    </header>
    <main class="content">

      <?php
      $msgs = [
        'usuario_creado'     => ['success','✅ Usuario creado correctamente.'],
        'usuario_editado'    => ['success','✅ Usuario actualizado.'],
        'usuario_actualizado'=> ['success','✅ Estado del usuario actualizado.'],
        'error_campos'       => ['error',  '⚠️ Completa todos los campos obligatorios.'],
        'email_duplicado'    => ['error',  '⚠️ Ese correo ya está registrado.'],
      ];
      if ($msg && isset($msgs[$msg])):
        [$tipo,$texto] = $msgs[$msg];
      ?>
        <div class="alert alert-<?= $tipo ?>" data-dismiss="3000"><?= $texto ?></div>
      <?php endif; ?>

      <div class="panel">
        <div class="panel-header">
          <h2>👥 Lista de Usuarios</h2>
          <span style="color:#666;font-size:13px;"><?= count($usuarios) ?> usuarios</span>
        </div>
        <div class="table-wrapper">
          <table>
            <thead>
              <tr><th>Nombre</th><th>Correo</th><th>Rol</th><th>Sucursal</th><th>Estado</th><th>Acciones</th></tr>
            </thead>
            <tbody>
              <?php foreach ($usuarios as $u): ?>
              <tr>
                <td>
                  <div style="display:flex;align-items:center;gap:10px;">
                    <div style="width:34px;height:34px;border-radius:50%;background:<?= $u['rol']==='administrador'?'#C0392B':'#E91E8C' ?>;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:13px;flex-shrink:0;">
                      <?= strtoupper(substr($u['nombre'],0,1)) ?>
                    </div>
                    <strong><?= htmlspecialchars($u['nombre']) ?></strong>
                  </div>
                </td>
                <td><small><?= htmlspecialchars($u['email']) ?></small></td>
                <td>
                  <span class="badge <?= $u['rol']==='administrador'?'badge-rojo':'badge-rosa' ?>">
                    <?= $u['rol']==='administrador' ? '👑 Admin' : '👤 Empleado' ?>
                  </span>
                </td>
                <td><?= $u['sucursal'] ? htmlspecialchars($u['sucursal']) : '<span style="color:#aaa;">Global</span>' ?></td>
                <td>
                  <span class="badge <?= $u['activo']?'badge-verde':'badge-gris' ?>">
                    <?= $u['activo'] ? '✅ Activo' : '⛔ Inactivo' ?>
                  </span>
                </td>
                <td>
                  <button class="btn-icon" title="Editar"
                    onclick="abrirEditar(<?= htmlspecialchars(json_encode($u)) ?>)">✏️</button>
                  <form method="POST" style="display:inline;">
                    <input type="hidden" name="accion" value="toggle">
                    <input type="hidden" name="id_usuario" value="<?= $u['id_usuario'] ?>">
                    <input type="hidden" name="activo" value="<?= $u['activo'] ?>">
                    <button type="submit" class="btn-icon" title="<?= $u['activo']?'Desactivar':'Activar' ?>">
                      <?= $u['activo'] ? '🔒' : '🔓' ?>
                    </button>
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
<div class="modal-overlay" id="modalUsuario">
  <div class="modal">
    <div class="modal-header">
      <h3>👤 Nuevo Usuario</h3>
      <button class="btn-icon" onclick="cerrarModal('modalUsuario')">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="accion" value="crear">
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group">
            <label>Nombre completo *</label>
            <input type="text" name="nombre" class="form-control" placeholder="Ej. María López" required>
          </div>
          <div class="form-group">
            <label>Correo electrónico *</label>
            <input type="email" name="email" class="form-control" placeholder="correo@eatamor.com" required>
          </div>
          <div class="form-group">
            <label>Contraseña *</label>
            <input type="password" name="password" class="form-control" placeholder="Mínimo 6 caracteres" required>
          </div>
          <div class="form-group">
            <label>Rol *</label>
            <select name="id_rol" class="form-control" required onchange="toggleSucursal(this.value)">
              <option value="">Seleccione...</option>
              <?php foreach ($roles as $r): ?>
                <option value="<?= $r['id_rol'] ?>"><?= ucfirst($r['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group" id="campoSucursal">
            <label>Sucursal</label>
            <select name="id_sucursal" class="form-control">
              <option value="">Sin sucursal (Admin global)</option>
              <?php foreach ($sucursales as $s): ?>
                <option value="<?= $s['id_sucursal'] ?>"><?= htmlspecialchars($s['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="cerrarModal('modalUsuario')">Cancelar</button>
        <button type="submit" class="btn btn-primary">💾 Crear Usuario</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL EDITAR -->
<div class="modal-overlay" id="modalEditar">
  <div class="modal">
    <div class="modal-header">
      <h3>✏️ Editar Usuario</h3>
      <button class="btn-icon" onclick="cerrarModal('modalEditar')">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="accion" value="editar">
      <input type="hidden" name="id_usuario" id="edit_id">
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group">
            <label>Nombre completo *</label>
            <input type="text" name="nombre" id="edit_nombre" class="form-control" required>
          </div>
          <div class="form-group">
            <label>Correo electrónico *</label>
            <input type="email" name="email" id="edit_email" class="form-control" required>
          </div>
          <div class="form-group">
            <label>Nueva contraseña <small style="color:#aaa;">(dejar vacío para no cambiar)</small></label>
            <input type="password" name="password" class="form-control" placeholder="Nueva contraseña...">
          </div>
          <div class="form-group">
            <label>Rol *</label>
            <select name="id_rol" id="edit_rol" class="form-control" required>
              <?php foreach ($roles as $r): ?>
                <option value="<?= $r['id_rol'] ?>"><?= ucfirst($r['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Sucursal</label>
            <select name="id_sucursal" id="edit_sucursal" class="form-control">
              <option value="">Sin sucursal (Admin global)</option>
              <?php foreach ($sucursales as $s): ?>
                <option value="<?= $s['id_sucursal'] ?>"><?= htmlspecialchars($s['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
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
function toggleSucursal(idRol) {
  // rol 1 = admin (no necesita sucursal), rol 2 = empleado (sí necesita)
  const campo = document.getElementById('campoSucursal');
  campo.style.opacity = idRol == 1 ? '0.4' : '1';
}

function abrirEditar(u) {
  document.getElementById('edit_id').value       = u.id_usuario;
  document.getElementById('edit_nombre').value   = u.nombre;
  document.getElementById('edit_email').value    = u.email;
  document.getElementById('edit_rol').value      = u.id_rol;
  document.getElementById('edit_sucursal').value = u.id_sucursal || '';
  abrirModal('modalEditar');
}
</script>
</body>
</html>