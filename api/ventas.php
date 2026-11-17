<?php
// ============================================================
//  EAT AMOR — API Ventas con descuentos reales en ml/g
//  Archivo: api/ventas.php
// ============================================================

define('ROOT', dirname(__DIR__));
define('BASE_URL', 'http://localhost/eat_amor');
require_once ROOT . '/config/database.php';
require_once ROOT . '/config/session.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success'=>false,'message'=>'Método no permitido']); exit;
}

$data       = json_decode(file_get_contents('php://input'), true);
$idSucursal = (int)($data['id_sucursal'] ?? 0);
$idUsuario  = (int)($data['id_usuario']  ?? 0);
$detalle    = $data['detalle']   ?? [];
$descuento  = (float)($data['descuento'] ?? 0);

if (!$idSucursal || !$idUsuario || empty($detalle)) {
    echo json_encode(['success'=>false,'message'=>'Datos incompletos']); exit;
}

// ============================================================
//  TABLA DE DESCUENTOS REALES
//  Formato: 'Nombre item' => ['Producto inventario' => cantidad]
//  Unidades según la BD:
//    leche → litros (l)  → 0.5 = 500ml, 0.2 = 200ml
//    polvos → gramos (g) → 30 = 3 medidas de ~10g c/u
//    harina → por porción → se maneja en porciones
//    nutella/cajeta → bote (se descuenta fracción)
//    queso manchego → gramos
//    galleta oreo → piezas (pza)
// ============================================================
$descuentos = [

    // ── CREPAS DULCES ─────────────────────────────────────
    // 1 cucharon = 240ml de mezcla, 1L prepara 400ml → 1 crepa = 0.6 porciones de harina
    'Crepa Sencilla'           => ['Harina de crepas' => 0.6, 'Lechera' => 0.1],
    'Queso Zarza'              => ['Harina de crepas' => 0.6, 'Queso philadelphia' => 1, 'Mermelada de zarzamora' => 0.2],
    'Mazapán'                  => ['Harina de crepas' => 0.6, 'Mazapán' => 3, 'Lechera' => 0.1],
    'Cajetosa'                 => ['Harina de crepas' => 0.6, 'Cajeta' => 0.15],
    'Queso Frutal'             => ['Harina de crepas' => 0.6, 'Queso philadelphia' => 1],
    'Fresona'                  => ['Harina de crepas' => 0.6, 'Fresas' => 1, 'Nutella' => 0.1],
    'Fresa Oreo'               => ['Harina de crepas' => 0.6, 'Fresas' => 1, 'Nutella' => 0.1, 'Galleta Oreo' => 3],
    'Amarilla'                 => ['Harina de crepas' => 0.6, 'Queso philadelphia' => 1, 'Lechera' => 0.1],
    'Monito'                   => ['Harina de crepas' => 0.6, 'Queso philadelphia' => 1, 'Nutella' => 0.1, 'Plátanos' => 1],
    'Conserva de Frutos Rojos' => ['Harina de crepas' => 0.6, 'Queso philadelphia' => 1, 'Mermelada de frutos rojos' => 0.2],
    'Crepa Conejo'             => ['Harina de crepas' => 0.6, 'Plátanos' => 1, 'Cajeta' => 0.15, 'Conejo de chocolate' => 1],
    'Cookies & Cream'          => ['Harina de crepas' => 0.6, 'Queso philadelphia' => 1, 'Nutella' => 0.1, 'Galleta Oreo' => 3],
    'Crepa Madre'              => ['Harina de crepas' => 0.6, 'Rompope' => 0.1, 'Lechera' => 0.1, 'Nuez' => 50],
    'Doraditas'                => ['Harina de crepas' => 0.6, 'Doraditas' => 1, 'Cajeta' => 0.15],
    'Manzana Canela'           => ['Harina de crepas' => 0.6, 'Manzana canela (porción)' => 1],
    'Banana Split'             => ['Harina de crepas' => 0.6, 'Plátanos' => 1, 'Helado de fresa' => 1, 'Helado de vainilla' => 1, 'Helado de chocolate' => 1],
    'Ferrero Almendra'         => ['Harina de crepas' => 0.6, 'Nutella' => 0.1, 'Almendras fileteadas' => 50, 'Helado de chocolate' => 1, 'Ferreros' => 1],
    'Mordisko'                 => ['Harina de crepas' => 0.6, 'Nutella' => 0.1, 'Plátanos' => 1, 'Mordisko' => 1],
    'Mini Magnum'              => ['Harina de crepas' => 0.6, 'Fresas' => 1, 'Lechera' => 0.1, 'Mini Magnum' => 1],
    'Super Crepa'              => ['Harina de crepas' => 0.6, 'Nutella' => 0.1, 'Plátanos' => 1, 'Fresas' => 1, 'Kinder Delice' => 1],
    'Choco Bueno'              => ['Harina de crepas' => 0.6, 'Nutella' => 0.1, 'Fresas' => 1, 'Kinder Bueno' => 1],
    'Kinder Vainilla'          => ['Harina de crepas' => 0.6, 'Nutella' => 0.1, 'Fresas' => 1, 'Helado de vainilla' => 1, 'Huevo Kinder' => 1],
    'Ferrero Fresa'            => ['Harina de crepas' => 0.6, 'Plátanos' => 1, 'Fresas' => 1, 'Nutella' => 0.1, 'Paleta ferrero' => 1],
    'Cajeta Frutal'            => ['Harina de crepas' => 0.6, 'Queso philadelphia' => 1, 'Plátanos' => 1, 'Fresas' => 1, 'Cajeta' => 0.15, 'Nuez' => 50, 'Helado de vainilla' => 1],

    // ── CREPAS SALADAS ────────────────────────────────────
    // 70g queso manchego, 3 rebanadas jamón, 15 rebanadas peperoni
    'Jamón'     => ['Harina de crepas' => 0.6, 'Queso manchego' => 70, 'Jamón' => 3],
    'Peperoni'  => ['Harina de crepas' => 0.6, 'Queso manchego' => 70, 'Pepperoni congelado' => 15],
    'Hawaiana'  => ['Harina de crepas' => 0.6, 'Queso manchego' => 70, 'Jamón' => 3, 'Piña en almíbar' => 0.2],
    'La Vaquita'=> ['Harina de crepas' => 0.6, 'Queso philadelphia' => 1, 'Queso manchego' => 70, 'Jamón' => 3],
    'Crepizza'  => ['Harina de crepas' => 0.6, 'Salsa Preggo' => 0.15, 'Queso manchego' => 70],
    'Marinero'  => ['Harina de crepas' => 0.6, 'Queso manchego' => 70, 'Atún' => 1],
    'Suadero'   => ['Harina de crepas' => 0.6, 'Queso manchego' => 70, 'Suadero' => 1],
    'Pastor'    => ['Harina de crepas' => 0.6, 'Queso manchego' => 70, 'Pastor' => 1],
    'Boneless'  => ['Harina de crepas' => 0.6, 'Queso manchego' => 70, 'Boneless' => 1],
    'Choriqueso'=> ['Harina de crepas' => 0.6, 'Queso manchego' => 70, 'Chori queso (longaniza)' => 1],

    // ── WAFFLES DULCES ────────────────────────────────────
    // 12 waffles por litro de mezcla → 1 waffle = 0.083 porciones
    'Waffle Sencillo'          => ['Harina de waffles' => 0.083, 'Lechera' => 0.1],
    'Fresa Leche'              => ['Harina de waffles' => 0.083, 'Fresas' => 1, 'Lechera' => 0.1],
    'Nutella Fresa'            => ['Harina de waffles' => 0.083, 'Nutella' => 0.1, 'Fresas' => 1],
    'De la Abuela'             => ['Harina de waffles' => 0.083, 'Cajeta' => 0.15, 'Nuez' => 50],
    'Galletón'                 => ['Harina de waffles' => 0.083, 'Nutella' => 0.1, 'Galleta Oreo' => 3],
    'Batido'                   => ['Harina de waffles' => 0.083, 'Lechera' => 0.1],
    'Banana Oreo'              => ['Harina de waffles' => 0.083, 'Plátanos' => 1, 'Lechera' => 0.1, 'Galleta Oreo' => 4, 'Helado de vainilla' => 1],
    'Monjita'                  => ['Harina de waffles' => 0.083, 'Lechera' => 0.1, 'Rompope' => 0.1, 'Helado de vainilla' => 1, 'Nuez' => 50],
    'Sorpresa'                 => ['Harina de waffles' => 0.083, 'Plátanos' => 1, 'Huevo Kinder' => 1],
    'Waffle Bueno'             => ['Harina de waffles' => 0.083, 'Nutella' => 0.1, 'Fresas' => 1, 'Kinder Bueno' => 1],
    'Waffle Frutos Rojos'      => ['Harina de waffles' => 0.083, 'Mermelada de frutos rojos' => 0.2, 'Helado de vainilla' => 1],
    'Super Waffle'             => ['Harina de waffles' => 0.083, 'Nutella' => 0.1, 'Plátanos' => 1, 'Fresas' => 1, 'Kinder Delice' => 1],
    'Waffle Ferrero'           => ['Harina de waffles' => 0.083, 'Nutella' => 0.1, 'Fresas' => 1, 'Nuez' => 50, 'Helado de vainilla' => 1, 'Ferreros' => 1],
    'Waffle Frutas Ferrero'    => ['Harina de waffles' => 0.083, 'Fresas' => 1, 'Nutella' => 0.1, 'Almendras fileteadas' => 50, 'Paleta ferrero' => 1],
    'Waffle Magnum Frutal'     => ['Harina de waffles' => 0.083, 'Lechera' => 0.1, 'Nuez' => 50, 'Mini Magnum' => 1],
    'Waffle Vainilla Nutella'  => ['Harina de waffles' => 0.083, 'Nutella' => 0.1, 'Kinder Bueno' => 1, 'Nuez' => 50, 'Helado de vainilla' => 1],

    // ── WAFFLES SALADOS ───────────────────────────────────
    'Waffle Pizza Peperoni'    => ['Harina de waffles' => 0.083, 'Salsa Preggo' => 0.15, 'Queso manchego' => 70, 'Pepperoni congelado' => 15],
    'Waffle Pizza Hawaiana'    => ['Harina de waffles' => 0.083, 'Salsa Preggo' => 0.15, 'Queso manchego' => 70, 'Jamón' => 3, 'Piña en almíbar' => 0.2],

    // ── BEBIDAS ESPECIALES (jarras) ───────────────────────
    // 200ml de leche (0.2l)
    'Unicornio'      => ['Leche entera' => 0.2],
    'Chocoreta'      => ['Leche entera' => 0.2, 'Chocoretas' => 40],
    'Danonino'       => ['Danonino' => 1, 'Leche entera' => 0.2],
    'Yakult'         => ['Yakult' => 4, 'Leche entera' => 0.2],
    'Chamoyada Pelón'=> ['Paleta pelón' => 1, 'Chamoy' => 0.05],
    'Conejo'         => ['Leche entera' => 0.2, 'Cajeta' => 0.1, 'Conejo de chocolate' => 1],
    'Choco Kinder'   => ['Leche entera' => 0.2, 'Kinder Bueno' => 1],
    'Frappe Ferrero' => ['Leche entera' => 0.2, 'Nutella' => 0.1, 'Ferreros' => 2],
    'Delice'         => ['Leche entera' => 0.2, 'Kinder Delice' => 1],
    'Mini Vainilla'  => ['Leche entera' => 0.2, 'Helado de vainilla' => 1],
    'Bebi Mordisko'  => ['Leche entera' => 0.2, 'Mordisko' => 1, 'Galleta Oreo' => 9],
    'Chocolatoso'    => ['Leche entera' => 0.2, 'Chocolate Nesquik' => 30],

    // ── SORBETOS (200ml agua) ─────────────────────────────
    // 3 medidas de pulpa por sorbeto
    'Sorbeto Horchata'     => ['Pulpa de horchata' => 3],
    'Sorbeto Frutos Rojos' => ['Pulpa de frutos rojos' => 3],
    'Sorbeto Mango'        => ['Pulpa de mango' => 3],
    'Sorbeto Tamarindo'    => ['Pulpa de tamarindo' => 3],
    'Sorbeto Piña Colada'  => ['Pulpa piña colada' => 3],
    'Sorbeto Pepino Limón' => ['Pulpa limón pepino' => 3],
    'Sorbeto Fresa'        => ['Pulpa de fresa' => 3],

    // ── MALTEADAS (200ml leche) ───────────────────────────
    'Malteada Fresa'      => ['Leche entera' => 0.2, 'Helado de fresa' => 1],
    'Malteada Vainilla'   => ['Leche entera' => 0.2, 'Helado de vainilla' => 1],
    'Malteada Chocolate'  => ['Leche entera' => 0.2, 'Helado de chocolate' => 1, 'Chocolate Nesquik' => 30],
    'Malteada Oreo'       => ['Leche entera' => 0.2, 'Helado de vainilla' => 1, 'Galleta Oreo' => 4],
    'Malteada Nutella'    => ['Leche entera' => 0.2, 'Helado de vainilla' => 1, 'Nutella' => 0.1],
    'Malteada Mazapán'    => ['Leche entera' => 0.2, 'Helado de vainilla' => 1, 'Mazapán' => 3],

    // ── FRAPPÉS (200ml leche + 3 medidas polvo = 30g) ─────
    'Frappé Chicle'        => ['Leche entera' => 0.2, 'Concentrado de chicle' => 30],
    'Frappé Chocolate Blanco'=> ['Leche entera' => 0.2, 'Chocolate blanco' => 30],
    'Frappé Chocolate'     => ['Leche entera' => 0.2, 'Chocolate Nesquik' => 30],
    'Frappé Frapuchino'    => ['Leche entera' => 0.2, 'Capuchino en polvo' => 30],
    'Frappé Mazapán'       => ['Leche entera' => 0.2, 'Mazapán' => 3],
    'Frappé Galleta Oreo'  => ['Leche entera' => 0.2, 'Galleta Oreo' => 9],
    'Frappé Moka'          => ['Leche entera' => 0.2, 'Moka' => 30],
    'Frappé Nutella'       => ['Leche entera' => 0.2, 'Nutella' => 0.1],
    'Frappé Fresa'         => ['Leche entera' => 0.2, 'Pulpa de fresa' => 3],
    'Frappé Chai'          => ['Leche entera' => 0.2, 'Chai negro' => 30],
    'Frappé Taro'          => ['Leche entera' => 0.2, 'Taro' => 30],
    'Frappé Matcha'        => ['Leche entera' => 0.2, 'Matcha' => 30],

    // ── CHAMOYADAS (200ml agua + 3 medidas pulpa) ─────────
    'Chamoyada Mango'        => ['Pulpa de mango' => 3, 'Chamoy' => 0.05],
    'Chamoyada Tamarindo'    => ['Pulpa de tamarindo' => 3, 'Chamoy' => 0.05],
    'Chamoyada Pepino Limón' => ['Pulpa limón pepino' => 3, 'Chamoy' => 0.05],
    'Chamoyada Fresa'        => ['Pulpa de fresa' => 3, 'Chamoy' => 0.05],

    // ── BEBIDAS CALIENTES (500ml leche + 15g café) ────────
    'Espresso'               => ['Café (cafetera)' => 15],
    'Americano'              => ['Café (cafetera)' => 15],
    'Capuchino'              => ['Leche entera' => 0.5, 'Café (cafetera)' => 15],
    'Tizana de Jamaica'      => ['Tisana Jamaica Arándano' => 30],
    'Chocolate Caliente'     => ['Leche entera' => 0.5, 'Chocolate Nesquik' => 30],
    'Chocolate Blanco Caliente'=> ['Leche entera' => 0.5, 'Chocolate blanco' => 30],
    'Moka Caliente'          => ['Leche entera' => 0.5, 'Moka' => 30, 'Café (cafetera)' => 15],
    'Chai Caliente'          => ['Leche entera' => 0.5, 'Chai negro' => 30],
    'Matcha Caliente'        => ['Leche entera' => 0.5, 'Matcha' => 30],
    'Capuchino de Mazapán'   => ['Leche entera' => 0.5, 'Café (cafetera)' => 15, 'Mazapán' => 3],
    'Capuchino de Cajeta'    => ['Leche entera' => 0.5, 'Café (cafetera)' => 15, 'Cajeta' => 0.1],
    'Capuchino de Nutella'   => ['Leche entera' => 0.5, 'Café (cafetera)' => 15, 'Nutella' => 0.1],
    'Capuchino de Rompope'   => ['Leche entera' => 0.5, 'Café (cafetera)' => 15, 'Rompope' => 0.1],

    // ── BEBIDAS FRÍAS (500ml leche) ───────────────────────
    'Café con Leche Frío'  => ['Leche entera' => 0.5, 'Café (cafetera)' => 15],
    'Americano Frío'       => ['Café (cafetera)' => 15],
    'Latte Frío de Mazapán'=> ['Leche entera' => 0.5, 'Mazapán' => 3],
    'Matcha Frío'          => ['Leche entera' => 0.5, 'Matcha' => 30],
    'Taro Frío'            => ['Leche entera' => 0.5, 'Taro' => 30],
    'Chai Frío'            => ['Leche entera' => 0.5, 'Chai negro' => 30],
    'Latte Frío de Bayles' => ['Leche entera' => 0.5],

    // ── BEBIDAS CON GLAMOUR ───────────────────────────────
    'Bebida Glamour Frambuesa' => ['Agua de garrafón' => 0.2],
    'Bebida Glamour Mora Azul' => ['Agua de garrafón' => 0.2],

    // ── REFRESCOS Y AGUA ──────────────────────────────────
    'Coca-Cola'        => ['Coca-Cola lata' => 1],
    'Agua Embotellada' => ['Agua embotellada' => 1],
    'Agua Mineral'     => ['Agua mineral mini' => 1],

    // ── EXTRAS ────────────────────────────────────────────
    'Paleta Ferrero'       => ['Paleta ferrero' => 1],
    'Paleta Ferrero Extra' => ['Paleta ferrero' => 1],
    'Nuez/Almendra'        => ['Nuez' => 50],
    'Mermelada Extra'      => ['Mermelada de fresa' => 0.05],
    'Extra Clásico'        => ['Helado de vainilla' => 1, 'Conejo de chocolate' => 1, 'Galleta Oreo' => 1, 'Kinder Delice' => 1],
    'Extra Premium'        => ['Huevo Kinder' => 1, 'Kinder Bueno' => 1, 'Mini Magnum' => 1, 'Mordisko' => 1],
];

// ── Mapeo de tipos de leche ────────────────────────────────
// Si el usuario eligió otro tipo de leche, sustituye Leche entera
$mapLeche = [
    'Leche deslactosada' => 'Leche deslactosada',
    'Leche de almendras' => 'Leche de almendras',
    'Agua de garrafón'   => 'Agua de garrafón',
    'Leche entera'       => 'Leche entera',
];

try {
    $pdo = getDB();
    $pdo->beginTransaction();

    // Crear venta
    $pdo->prepare("INSERT INTO ventas (id_sucursal, id_usuario, total) VALUES (?,?,0)")
        ->execute([$idSucursal, $idUsuario]);
    $idVenta = $pdo->lastInsertId();

    $total = 0;
    foreach ($detalle as $item) {
        $idItem    = (int)$item['id_item'];
        $cantidad  = (int)$item['cantidad'];
        $precio    = (float)$item['precio_unit'];
        $lecheItem = $item['leche'] ?? null; // tipo de leche elegida
        $total    += $cantidad * $precio;

        // Insertar detalle
        $pdo->prepare("INSERT INTO venta_detalle (id_venta,id_item,cantidad,precio_unit) VALUES (?,?,?,?)")
            ->execute([$idVenta, $idItem, $cantidad, $precio]);

        // Obtener nombre del item
        $stmtNombre = $pdo->prepare("SELECT nombre FROM menu_items WHERE id_item=?");
        $stmtNombre->execute([$idItem]);
        $nombre = $stmtNombre->fetchColumn();

        if (!$nombre || !isset($descuentos[$nombre])) continue;

        // Descontar ingredientes
        foreach ($descuentos[$nombre] as $nombreProd => $cantDesc) {
            // Sustituir tipo de leche si el usuario eligió una diferente
            if ($nombreProd === 'Leche entera' && $lecheItem && $lecheItem !== 'Leche entera') {
                $nombreProd = $mapLeche[$lecheItem] ?? 'Leche entera';
            }

            $cantTotal = $cantDesc * $cantidad;

            // Buscar producto en inventario
            $stmtProd = $pdo->prepare("SELECT id_producto FROM productos WHERE nombre=? AND activo=1 LIMIT 1");
            $stmtProd->execute([$nombreProd]);
            $idProd = $stmtProd->fetchColumn();

            if (!$idProd) continue;

            // Descontar stock (nunca baja de 0)
            $pdo->prepare("
                UPDATE inventario
                SET stock_actual = GREATEST(0, stock_actual - ?)
                WHERE id_sucursal=? AND id_producto=?
            ")->execute([$cantTotal, $idSucursal, $idProd]);

            // Registrar salida automática
            $pdo->prepare("
                INSERT INTO salidas
                (id_sucursal,id_producto,id_usuario,id_motivo,cantidad,cantidad_real,fecha,observaciones)
                VALUES (?,?,?,1,?,?,CURDATE(),'Venta automática POS')
            ")->execute([$idSucursal,$idProd,$idUsuario,$cantTotal,$cantTotal]);
        }
    }

    // Aplicar descuento al total
    $totalFinal = max(0, $total - $descuento);

    $pdo->prepare("UPDATE ventas SET total=? WHERE id_venta=?")->execute([$totalFinal, $idVenta]);
    $pdo->commit();

    echo json_encode([
        'success'   => true,
        'message'   => 'Venta registrada y stock actualizado',
        'id_venta'  => $idVenta,
        'total'     => $totalFinal,
        'subtotal'  => $total,
        'descuento' => $descuento
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success'=>false,'message'=>'Error: '.$e->getMessage()]);
}