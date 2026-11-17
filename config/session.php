<?php
// ============================================================
//  EAT AMOR — Helpers de Sesión y Autenticación
//  Archivo: config/session.php
// ============================================================

// Sesión única por navegador/pestaña usando cookie personalizada
if (session_status() === PHP_SESSION_NONE) {
    // Nombre de sesión único permite múltiples sesiones en distintos navegadores
    session_name('eat_amor_session');
    session_set_cookie_params([
        'lifetime' => 86400,    // 24 horas
        'path'     => '/',
        'secure'   => false,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// BASE_URL solo se define si no existe ya
if (!defined('BASE_URL')) {
    define('BASE_URL', 'http://localhost/eat_amor');
}

// ── Verificar si hay sesión activa ──────────────────────────
function estaLogueado(): bool {
    return isset($_SESSION['id_usuario']);
}

function usuarioActual(): array {
    return $_SESSION['usuario'] ?? [];
}

function rolActual(): string {
    return $_SESSION['rol'] ?? '';
}

function sucursalActual(): ?int {
    return isset($_SESSION['id_sucursal']) ? (int)$_SESSION['id_sucursal'] : null;
}

function esAdmin(): bool {
    return rolActual() === 'administrador';
}

// ── Proteger página: redirige si no está logueado ───────────
function requireLogin(): void {
    if (!estaLogueado()) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

// ── Proteger página: solo administrador ─────────────────────
function requireAdmin(): void {
    requireLogin();
    if (!esAdmin()) {
        header('Location: ' . BASE_URL . '/empleado/dashboard.php');
        exit;
    }
}

// ── Proteger página: solo empleado ──────────────────────────
function requireEmpleado(): void {
    requireLogin();
    if (esAdmin()) {
        header('Location: ' . BASE_URL . '/admin/dashboard.php');
        exit;
    }
}

// ── Respuesta JSON estandarizada ─────────────────────────────
function jsonResponse(bool $ok, string $msg, array $data = []): void {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $ok,
        'message' => $msg,
        'data'    => $data,
    ]);
    exit;
}