<?php
// ============================================================
//  EAT AMOR — Cerrar Sesión
//  Archivo: auth/logout.php
// ============================================================

require_once __DIR__ . '/../config/session.php';

$_SESSION = [];
session_destroy();

header('Location: ' . BASE_URL . '/index.php?msg=sesion_cerrada');
exit;