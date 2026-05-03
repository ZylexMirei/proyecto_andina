<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderJSON(["error" => "Método no permitido"], 405);
}

verificarAutenticacion();

$id_pedido = intval($_GET['id_pedido'] ?? 0);

if ($id_pedido <= 0) {
    responderJSON(["error" => "ID de pedido inválido"], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Verificar que el pedido existe
    $checkQuery = "SELECT id_pedido, codigo_pedido, estado, id_cliente FROM pedidos WHERE id_pedido = :id";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bindParam(':id', $id_pedido);
    $checkStmt->execute();

    if ($checkStmt->rowCount() === 0) {
        responderJSON(["error" => "Pedido no encontrado"], 404);
    }

    $pedido = $checkStmt->fetch();

    // Calcular totales con SUM SQL
    $query = "SELECT 
                p.id_pedido, 
                p.codigo_pedido, 
                p.estado,
                p.fecha_pedido,
                c.razon_social as cliente_nombre,
                SUM(dp.cantidad) as total_unidades,
                SUM(dp.cantidad * dp.precio_unitario) as subtotal,
                COUNT(dp.id_detalle) as lineas_detalle,
                MIN(dp.precio_unitario) as precio_minimo,
                MAX(dp.precio_unitario) as precio_maximo,
                AVG(dp.precio_unitario) as precio_promedio
              FROM pedidos p
              JOIN detalle_pedido dp ON p.id_pedido = dp.id_pedido
              LEFT JOIN clientes c ON p.id_cliente = c.id_cliente
              WHERE p.id_pedido = :id_pedido
              GROUP BY p.id_pedido, p.codigo_pedido, p.estado, p.fecha_pedido, c.razon_social";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id_pedido', $id_pedido);
    $stmt->execute();

    if ($stmt->rowCount() === 0) {
        responderJSON([
            "exito" => true,
            "pedido" => [
                "id_pedido" => $pedido['id_pedido'],
                "codigo_pedido" => $pedido['codigo_pedido'],
                "estado" => $pedido['estado'],
                "total_unidades" => 0,
                "subtotal" => 0,
                "impuesto" => 0,
                "total_pedido" => 0,
                "lineas_detalle" => 0,
                "precio_minimo" => 0,
                "precio_maximo" => 0,
                "precio_promedio" => 0
            ]
        ]);
    }

    $resultado = $stmt->fetch();

    // Cargar configuración de la aplicación para valores como el IVA
    $config = require __DIR__ . '/../../config/app.php';
    $porcentaje_iva = $config['impuestos']['iva_porcentaje'];

    $subtotal = floatval($resultado['subtotal']);
    $impuesto = $subtotal * ($porcentaje_iva / 100);
    $total = $subtotal + $impuesto;

    // Obtener detalles individuales
    $detalleQuery = "SELECT dp.*, p.codigo as producto_codigo, p.nombre as producto_nombre,
                            (dp.cantidad * dp.precio_unitario) as subtotal_linea
                     FROM detalle_pedido dp
                     JOIN productos p ON dp.id_producto = p.id_producto
                     WHERE dp.id_pedido = :id_pedido
                     ORDER BY dp.id_detalle";
    $detalleStmt = $db->prepare($detalleQuery);
    $detalleStmt->bindParam(':id_pedido', $id_pedido);
    $detalleStmt->execute();
    $detalles = $detalleStmt->fetchAll();

    responderJSON([
        "exito" => true,
        "pedido" => [
            "id_pedido" => intval($resultado['id_pedido']),
            "codigo_pedido" => $resultado['codigo_pedido'],
            "estado" => $resultado['estado'],
            "fecha_pedido" => $resultado['fecha_pedido'],
            "cliente_nombre" => $resultado['cliente_nombre'],
            "total_unidades" => intval($resultado['total_unidades']),
            "subtotal" => round($subtotal, 2),
            "impuesto" => round($impuesto, 2),
            "impuesto_porcentaje" => $porcentaje_iva,
            "total_pedido" => round($total, 2),
            "lineas_detalle" => intval($resultado['lineas_detalle']),
            "precio_minimo" => floatval($resultado['precio_minimo']),
            "precio_maximo" => floatval($resultado['precio_maximo']),
            "precio_promedio" => round(floatval($resultado['precio_promedio']), 2)
        ],
        "detalles" => $detalles
    ]);

} catch (PDOException $e) {
    error_log("Error al calcular total del pedido: " . $e->getMessage());
    responderJSON(["error" => "Error al calcular total del pedido"], 500);
}