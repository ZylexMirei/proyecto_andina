<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderJSON(["error" => "Método no permitido"], 405);
}

verificarAutenticacion();
verificarRol(['Administrador', 'Gerente']);

try {
    $database = new Database();
    $db = $database->getConnection();

    $stats = [];

    // Total productos activos
    $query = "SELECT COUNT(*) as total FROM productos WHERE estado = 'Activo'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['total_productos'] = intval($stmt->fetch()['total']);

    // Total clientes activos
    $query = "SELECT COUNT(*) as total FROM clientes WHERE estado = 'Activo'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['total_clientes'] = intval($stmt->fetch()['total']);

    // Total proveedores activos
    $query = "SELECT COUNT(*) as total FROM proveedores WHERE estado = 'Activo'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['total_proveedores'] = intval($stmt->fetch()['total']);

    // Total usuarios activos
    $query = "SELECT COUNT(*) as total FROM usuarios WHERE estado = 'Activo'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['total_usuarios'] = intval($stmt->fetch()['total']);

    // Productos en alerta (stock <= minimo)
    $query = "SELECT COUNT(*) as total FROM inventario WHERE cantidad_actual <= stock_minimo AND stock_minimo > 0";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['productos_alerta'] = intval($stmt->fetch()['total']);

    // Productos en estado crítico (stock = 0)
    $query = "SELECT COUNT(*) as total FROM inventario WHERE cantidad_actual = 0";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['productos_criticos'] = intval($stmt->fetch()['total']);

    // Pedidos pendientes
    $query = "SELECT COUNT(*) as total FROM pedidos WHERE estado IN ('Pendiente', 'Confirmado')";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['pedidos_pendientes'] = intval($stmt->fetch()['total']);

    // Órdenes de compra pendientes
    $query = "SELECT COUNT(*) as total FROM ordenes_compra WHERE estado = 'Pendiente'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['ordenes_pendientes'] = intval($stmt->fetch()['total']);

    // Ventas del mes actual
    $query = "SELECT COALESCE(SUM(dp.cantidad * dp.precio_unitario), 0) as total 
              FROM pedidos p 
              JOIN detalle_pedido dp ON p.id_pedido = dp.id_pedido 
              WHERE p.estado != 'Cancelado' 
              AND MONTH(p.fecha_pedido) = MONTH(CURDATE()) 
              AND YEAR(p.fecha_pedido) = YEAR(CURDATE())";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['ventas_mes_actual'] = floatval($stmt->fetch()['total']);

    // Compras del mes actual
    $query = "SELECT COALESCE(SUM(doc.cantidad * doc.precio_unitario), 0) as total 
              FROM ordenes_compra oc 
              JOIN detalle_orden_compra doc ON oc.id_orden_compra = doc.id_orden_compra 
              WHERE oc.estado IN ('Aprobada', 'Recibida')
              AND MONTH(oc.fecha_orden) = MONTH(CURDATE()) 
              AND YEAR(oc.fecha_orden) = YEAR(CURDATE())";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['compras_mes_actual'] = floatval($stmt->fetch()['total']);

    // Top 5 productos más vendidos
    $query = "SELECT p.nombre, p.codigo, SUM(dp.cantidad) as total_vendido
              FROM detalle_pedido dp
              JOIN pedidos pe ON dp.id_pedido = pe.id_pedido
              JOIN productos p ON dp.id_producto = p.id_producto
              WHERE pe.estado != 'Cancelado'
              GROUP BY dp.id_producto
              ORDER BY total_vendido DESC
              LIMIT 5";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['top_productos'] = $stmt->fetchAll();

    // Últimos 5 pedidos
    $query = "SELECT p.codigo_pedido, c.razon_social as cliente, p.estado, p.fecha_pedido
              FROM pedidos p
              JOIN clientes c ON p.id_cliente = c.id_cliente
              ORDER BY p.created_at DESC
              LIMIT 5";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['ultimos_pedidos'] = $stmt->fetchAll();

    responderJSON([
        "exito" => true,
        "estadisticas" => $stats
    ]);

} catch (PDOException $e) {
    error_log("Error en dashboard stats: " . $e->getMessage());
    responderJSON(["error" => "Error al obtener estadísticas"], 500);
}