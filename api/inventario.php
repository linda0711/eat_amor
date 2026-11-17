<?php
// ============================================================
//  EAT AMOR — API Inventario
//  Archivo: api/inventario.php
//  Métodos: GET (consultar stock) / POST (actualizar stock mínimo)
// ============================================================

define('ROOT', dirname(__DIR__));
define('BASE_URL', 'http://localhost/eat_amor');
require_once ROOT . '/config/database.php';
require_once ROOT . '/config/session.php';

header('Content-Type: application/json');

if (!estaLogueado()) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$pdo    = getDB();
$method = $_SERVER['REQUEST_METHOD'];

// ── GET: consultar stock de un producto en una sucursal ────
if ($method === 'GET') {
    $idProducto = isset($_GET['id_producto']) ? (int)$_GET['id_producto'] : 0;
    $idSucursal = isset($_GET['id_sucursal']) ? (int)$_GET['id_sucursal'] : 0;

    // Si es empleado, solo puede consultar su sucursal
    if (!esAdmin()) {
        $idSucursal = sucursalActual();
    }

    if (!$idProducto) {
        // Si no se especifica producto, devuelvo todo el inventario
        // de la sucursal con su estado de stock
        $where = $idSucursal ? "WHERE i.id_sucursal = $idSucursal" : "";

        $stmt = $pdo->query("
            SELECT
                p.id_producto,
                p.nombre        AS producto,
                c.nombre        AS categoria,
                s.nombre        AS sucursal,
                i.stock_actual,
                i.stock_minimo,
                um.abreviatura  AS unidad,
                CASE
                    WHEN i.stock_actual <= 0              THEN 'sin_stock'
                    WHEN i.stock_actual < i.stock_minimo  THEN 'stock_bajo'
                    ELSE 'ok'
                END AS estado
            FROM inventario i
            JOIN productos       p  ON p.id_producto  = i.id_producto
            JOIN categorias      c  ON c.id_categoria = p.id_categoria
            JOIN sucursales      s  ON s.id_sucursal  = i.id_sucursal
            JOIN unidades_medida um ON um.id_unidad   = p.id_unidad
            WHERE p.activo = 1 AND s.activa = 1
            $where
            ORDER BY p.nombre ASC
        ");

        $inventario = $stmt->fetchAll();
        echo json_encode([
            'success' => true,
            'total'   => count($inventario),
            'data'    => $inventario
        ]);
        exit;
    }

    // Consulta específica de un producto en una sucursal
    $stmt = $pdo->prepare("
        SELECT
            p.id_producto,
            p.nombre        AS producto,
            c.nombre        AS categoria,
            s.nombre        AS sucursal,
            i.stock_actual,
            i.stock_minimo,
            um.nombre       AS unidad,
            um.abreviatura,
            CASE
                WHEN i.stock_actual <= 0             THEN 'sin_stock'
                WHEN i.stock_actual < i.stock_minimo THEN 'stock_bajo'
                ELSE 'ok'
            END AS estado
        FROM inventario i
        JOIN productos       p  ON p.id_producto  = i.id_producto
        JOIN categorias      c  ON c.id_categoria = p.id_categoria
        JOIN sucursales      s  ON s.id_sucursal  = i.id_sucursal
        JOIN unidades_medida um ON um.id_unidad   = p.id_unidad
        WHERE i.id_producto = ? AND i.id_sucursal = ?
        LIMIT 1
    ");
    $stmt->execute([$idProducto, $idSucursal]);
    $registro = $stmt->fetch();

    if (!$registro) {
        echo json_encode([
            'success' => false,
            'message' => 'Producto no encontrado en esta sucursal'
        ]);
        exit;
    }

    echo json_encode(['success' => true, 'data' => $registro]);
    exit;
}

// ── POST: actualizar stock mínimo (solo admin) ─────────────
if ($method === 'POST') {
    if (!esAdmin()) {
        echo json_encode(['success' => false, 'message' => 'Sin permisos']);
        exit;
    }

    $data       = json_decode(file_get_contents('php://input'), true);
    $idProducto = (int)($data['id_producto'] ?? 0);
    $idSucursal = (int)($data['id_sucursal'] ?? 0);
    $stockMin   = (float)($data['stock_minimo'] ?? 0);

    if (!$idProducto || !$idSucursal) {
        echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
        exit;
    }

    $pdo->prepare("
        UPDATE inventario
        SET stock_minimo = ?
        WHERE id_producto = ? AND id_sucursal = ?
    ")->execute([$stockMin, $idProducto, $idSucursal]);

    echo json_encode([
        'success' => true,
        'message' => 'Stock mínimo actualizado correctamente'
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Método no permitido']);