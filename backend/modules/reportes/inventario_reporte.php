<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderJSON(["error" => "Método no permitido"], 405);
}

verificarAutenticacion();
verificarRol(['Administrador', 'Gerente']);

$id_almacen = intval($_GET['id_almacen'] ?? 0);
$tipo_reporte = sanitizar_input($_GET['tipo'] ?? 'completo'); // completo, resumen, valorizado

try {
    $database = new Database();
    $db = $database->getConnection();
    $params = [];

    switch ($tipo_reporte) {
        case 'resumen':
            $query = "SELECT a.nombre as almacen, 
                             COUNT(i.id_inventario) as total_productos,
                             SUM(i.cantidad_actual) as total_unidades,
                             SUM(CASE WHEN i.cantidad_actual <= i.stock_minimo AND i.stock_minimo > 0 THEN 1 ELSE 0 END) as productos_criticos,
                             SUM(CASE WHEN i.cantidad_actual = 0 THEN 1 ELSE 0 END) as productos_agotados
                      FROM inventario i
                      JOIN almacenes a ON i.id_almacen = a.id_almacen";
            if ($id_almacen > 0) {
                $query .= " WHERE i.id_almacen = :id_almacen";
                $params[':id_almacen'] = $id_almacen;
            }
            $query .= " GROUP BY i.id_almacen";
            break;

        case 'valorizado':
            $query = "SELECT i.id_producto, p.codigo, p.nombre, 
                             SUM(i.cantidad_actual) as stock_total,
                             p.precio_referencia,
                             SUM(i.cantidad_actual * p.precio_referencia) as valor_total
                      FROM inventario i
                      JOIN productos p ON i.id_producto = p.id_producto";
            if ($id_almacen > 0) {
                $query .= " WHERE i.id_almacen = :id_almacen";
                $params[':id_almacen'] = $id_almacen;
            }
            $query .= " GROUP BY i.id_producto
                      ORDER BY valor_total DESC";
            break;

        default: // completo
            $query = "SELECT i.*, p.codigo, p.nombre, p.descripcion, p.precio_referencia,
                             p.imagen_principal, a.nombre as almacen_nombre,
                             CASE 
                                WHEN i.cantidad_actual = 0 THEN 'Agotado'
                                WHEN i.cantidad_actual <= i.stock_minimo THEN 'Crítico'
                                WHEN i.cantidad_actual <= (i.stock_minimo * 1.3) THEN 'Alerta'
                                ELSE 'Normal'
                             END as estado_stock,
                             (i.cantidad_actual * p.precio_referencia) as valor_inventario
                      FROM inventario i
                      JOIN productos p ON i.id_producto = p.id_producto
                      JOIN almacenes a ON i.id_almacen = a.id_almacen";
            if ($id_almacen > 0) {
                $query .= " WHERE i.id_almacen = :id_almacen";
                $params[':id_almacen'] = $id_almacen;
            }
            $query .= " ORDER BY a.nombre, p.nombre";
            break;
    }

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $resultado = $stmt->fetchAll();

    // Calcular totales generales
    $totales = [
        'total_productos' => count($resultado),
        'valor_total_inventario' => 0,
        'productos_criticos' => 0,
        'productos_agotados' => 0
    ];

    foreach ($resultado as $row) {
        if (isset($row['valor_inventario'])) {
            $totales['valor_total_inventario'] += floatval($row['valor_inventario']);
        }
        if (isset($row['valor_total'])) {
            $totales['valor_total_inventario'] += floatval($row['valor_total']);
        }
        if (isset($row['estado_stock']) && $row['estado_stock'] === 'Crítico') {
            $totales['productos_criticos']++;
        }
        if (isset($row['estado_stock']) && $row['estado_stock'] === 'Agotado') {
            $totales['productos_agotados']++;
        }
    }

    responderJSON([
        "exito" => true,
        "tipo_reporte" => $tipo_reporte,
        "totales" => $totales,
        "datos" => $resultado
    ]);

} catch (PDOException $e) {
    error_log("Error en reporte inventario: " . $e->getMessage());
    responderJSON(["error" => "Error al generar reporte de inventario"], 500);
}