<?php
// ============================================================
//  EAT AMOR — Configuración de Base de Datos
//  Archivo: config/database.php
// ============================================================

define('DB_HOST',     'localhost');
define('DB_NAME',     'eat_amor');
define('DB_USER',     'root');       // Usuario de XAMPP por defecto
define('DB_PASS',     '');           // Contraseña de XAMPP por defecto (vacía)
define('DB_CHARSET',  'utf8mb4');

// Zona horaria 
date_default_timezone_set('America/Mexico_City');

// ── Conexión PDO ─────────────────────────────────────────────
function getDB(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST, DB_NAME, DB_CHARSET
        );
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // En producción no mostrar el mensaje real
            error_log('DB Error: ' . $e->getMessage());
            die(json_encode([
                'error' => 'Error de conexión a la base de datos.'
            ]));
        }
    }

    return $pdo;
}