<?php
// ============================================================
//  EAT AMOR — index.php (raíz del proyecto)
//  Redirige al login o al dashboard según sesión
// ============================================================

define('ROOT', __DIR__);
require_once ROOT . '/config/database.php';
require_once ROOT . '/config/session.php';

// Si ya hay sesión, redirigir al dashboard correcto
if (estaLogueado()) {
    esAdmin()
        ? header('Location: ' . BASE_URL . '/admin/dashboard.php')
        : header('Location: ' . BASE_URL . '/empleado/dashboard.php');
    exit;
}

// Leer mensajes de URL
$error = $_GET['error'] ?? '';
$msg   = $_GET['msg']   ?? '';

$mensajesError = [
    'credenciales_invalidas' => 'Usuario o contraseña incorrectos.',
    'campos_vacios'          => 'Por favor completa todos los campos.',
    'sesion_expirada'        => 'Tu sesión ha expirado. Inicia sesión nuevamente.',
];

$mensajesOk = [
    'sesion_cerrada' => 'Sesión cerrada correctamente.',
];

$pageTitle = 'Iniciar Sesión';
require_once ROOT . '/includes/header.php';
?>

<style>
/* ── LOGIN PAGE ─────────────────────────────────────────── */
body {
  background: linear-gradient(135deg, var(--negro) 0%, var(--negro-suave) 50%, #3a1a0a 100%);
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 20px;
  position: relative;
  overflow: hidden;
}

/* Patrón decorativo fondo */
body::before {
  content: '';
  position: fixed;
  inset: 0;
  background-image:
    radial-gradient(circle at 20% 20%, rgba(192,57,43,.15) 0%, transparent 50%),
    radial-gradient(circle at 80% 80%, rgba(233,30,140,.1) 0%, transparent 50%),
    radial-gradient(circle at 50% 50%, rgba(212,175,55,.05) 0%, transparent 70%);
  pointer-events: none;
}

.login-brand {
  text-align: center;
  margin-bottom: 32px;
  animation: fadeIn .5s ease both;
}

.login-brand .brand-icon {
  width: 72px; height: 72px;
  background: linear-gradient(135deg, var(--rojo), var(--rosa));
  border-radius: 20px;
  display: flex; align-items: center; justify-content: center;
  font-size: 36px;
  margin: 0 auto 16px;
  box-shadow: 0 8px 32px rgba(192,57,43,.4);
}

.login-brand h1 {
  font-family: var(--font-display);
  font-size: 32px;
  color: var(--beige);
  margin-bottom: 4px;
}

.login-brand p {
  color: var(--gris-400);
  font-size: 14px;
}

.login-card {
  background: rgba(255,255,255,.97);
  backdrop-filter: blur(20px);
  border-radius: var(--radius-lg);
  padding: 40px;
  width: 100%;
  max-width: 420px;
  box-shadow: var(--shadow-lg), 0 0 0 1px rgba(255,255,255,.1);
  animation: fadeIn .5s ease .1s both;
}

.login-card h2 {
  font-family: var(--font-display);
  font-size: 24px;
  margin-bottom: 6px;
  color: var(--negro);
}

.login-card .login-sub {
  color: var(--gris-600);
  font-size: 14px;
  margin-bottom: 28px;
}

.login-divider {
  border: none;
  border-top: 1px solid var(--gris-200);
  margin: 20px 0;
}

.login-footer {
  text-align: center;
  color: var(--gris-400);
  font-size: 12px;
  margin-top: 28px;
  animation: fadeIn .5s ease .2s both;
}

.pass-toggle {
  position: absolute;
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  cursor: pointer;
  font-size: 18px;
  color: var(--gris-400);
  transition: color .2s;
}
.pass-toggle:hover { color: var(--rojo); }
</style>

<!-- Marca -->
<div class="login-brand">
  <div class="brand-icon">🥞</div>
  <h1>Eat Amor</h1>
  <p>Control de entradas y salidas</p>
</div>

<!-- Tarjeta Login -->
<div class="login-card">
  <h2>Bienvenido</h2>
  <p class="login-sub">Inicia sesión para acceder al sistema</p>

  <!-- Mensajes de error / éxito -->
  <?php if ($error && isset($mensajesError[$error])): ?>
    <div class="alert alert-error" data-dismiss="4000">
      ⚠️ <?= $mensajesError[$error] ?>
    </div>
  <?php endif; ?>

  <?php if ($msg && isset($mensajesOk[$msg])): ?>
    <div class="alert alert-success" data-dismiss="3000">
      ✅ <?= $mensajesOk[$msg] ?>
    </div>
  <?php endif; ?>

  <!-- Formulario -->
  <form action="<?= BASE_URL ?>/auth/login.php" method="POST" novalidate>

    <div class="form-group">
      <label for="email">Correo electrónico</label>
      <div class="input-group">
        <span class="input-icon">👤</span>
        <input
          type="email"
          id="email"
          name="email"
          class="form-control"
          placeholder="usuario@eatamor.com"
          required
          autocomplete="email"
          value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
        >
      </div>
    </div>

    <div class="form-group">
      <label for="password">Contraseña</label>
      <div class="input-group" style="position:relative;">
        <span class="input-icon">🔒</span>
        <input
          type="password"
          id="password"
          name="password"
          class="form-control"
          placeholder="Ingresa tu contraseña"
          required
          autocomplete="current-password"
        >
        <button type="button" class="pass-toggle" onclick="togglePass()" title="Mostrar/ocultar">
          
        </button>
      </div>
    </div>

    <hr class="login-divider">

    <button type="submit" class="btn btn-primary btn-full btn-lg">
       Iniciar Sesión
    </button>

  </form>
</div>

<p class="login-footer">© 2024 Eat Amor — Sistema de Inventario. Todos los derechos reservados.</p>

<script>
function togglePass() {
  const input = document.getElementById('password');
  input.type = input.type === 'password' ? 'text' : 'password';
}
</script>

<?php require_once ROOT . '/includes/footer.php'; ?>