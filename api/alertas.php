<?php
// ============================================================
//  EAT AMOR — API Alertas de Stock Bajo
//  Archivo: api/alertas.php
//  Método: GET
//  Parámetros: id_sucursal (opcional)
// ============================================================

define('ROOT', dirname(__DIR__));
define('BASE_URL', 'http://localhost/eat_amor');
require_once ROOT . '/config/database.php';
require_once ROOT . '/config/session.php';

header('Content-Type: application/json');

// Verifico que haya sesión activa antes de responder
if (!estaLogueado()) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$pdo        = getDB();
$idSucursal = isset($_GET['id_sucursal']) ? (int)$_GET['id_sucursal'] : 0;

// Si es empleado, fuerzo el filtro a su sucursal
if (!esAdmin()) {
    $idSucursal = sucursalActual();
}

$whereSQL = $idSucursal ? "AND i.id_sucursal = $idSucursal" : "";

// Obtengo todos los productos con stock actual menor al mínimo
$stmt = $pdo->query("
    SELECT
        p.id_producto,
        p.nombre         AS producto,
        c.nombre         AS categoria,
        s.nombre         AS sucursal,
        s.id_sucursal,
        i.stock_actual,
        i.stock_minimo,
        um.abreviatura   AS unidad,
        (i.stock_minimo - i.stock_actual) AS faltante
    FROM inventario i
    JOIN productos       p  ON p.id_producto  = i.id_producto
    JOIN categorias      c  ON c.id_categoria = p.id_categoria
    JOIN sucursales      s  ON s.id_sucursal  = i.id_sucursal
    JOIN unidades_medida um ON um.id_unidad   = p.id_unidad
    WHERE i.stock_actual < i.stock_minimo
      AND p.activo = 1
      AND s.activa = 1
      $whereSQL
    ORDER BY faltante DESC
    LIMIT 50
");

$alertas = $stmt->fetchAll();
$total   = count($alertas);

echo json_encode([
    'success' => true,
    'total'   => $total,
    'data'    => $alertas
]);