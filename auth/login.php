<?php
// ============================================================
//  EAT AMOR — Lógica de Login
//  Archivo: auth/login.php
//  Recibe POST desde index.php (formulario de login)
// ============================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';

// Si ya está logueado, redirigir según rol
if (estaLogueado()) {
    esAdmin()
        ? header('Location: ' . BASE_URL . '/admin/dashboard.php')
        : header('Location: ' . BASE_URL . '/empleado/dashboard.php');
    exit;
}

// Solo aceptar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$email    = trim($_POST['email']    ?? '');
$password = trim($_POST['password'] ?? '');

// Validación básica
if (empty($email) || empty($password)) {
    header('Location: ' . BASE_URL . '/index.php?error=campos_vacios');
    exit;
}

$pdo = getDB();

// Buscar usuario activo con su rol y sucursal
$stmt = $pdo->prepare('
    SELECT
        u.id_usuario,
        u.nombre,
        u.email,
        u.password_hash,
        u.id_sucursal,
        r.nombre   AS rol,
        s.nombre   AS sucursal_nombre
    FROM usuarios u
    JOIN roles r ON r.id_rol = u.id_rol
    LEFT JOIN sucursales s ON s.id_sucursal = u.id_sucursal
    WHERE u.email = ? AND u.activo = 1
    LIMIT 1
');
$stmt->execute([$email]);
$usuario = $stmt->fetch();

// Verificar contraseña
if (!$usuario || !password_verify($password, $usuario['password_hash'])) {
    header('Location: ' . BASE_URL . '/index.php?error=credenciales_invalidas');
    exit;
}

// ── Crear sesión ─────────────────────────────────────────────
session_regenerate_id(true);

$_SESSION['id_usuario']       = $usuario['id_usuario'];
$_SESSION['rol']              = $usuario['rol'];
$_SESSION['id_sucursal']      = $usuario['id_sucursal'];  // null si es admin
$_SESSION['usuario'] = [
    'id'               => $usuario['id_usuario'],
    'nombre'           => $usuario['nombre'],
    'email'            => $usuario['email'],
    'rol'              => $usuario['rol'],
    'id_sucursal'      => $usuario['id_sucursal'],
    'sucursal_nombre'  => $usuario['sucursal_nombre'],
];

// ── Redirigir según rol ──────────────────────────────────────
if ($usuario['rol'] === 'administrador') {
    header('Location: ' . BASE_URL . '/admin/dashboard.php');
} else {
    header('Location: ' . BASE_URL . '/empleado/dashboard.php');
}
exit;