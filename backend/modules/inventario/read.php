<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderJSON(["error" => "Método no permitido"], 405);
}

verificarAutenticacion();

$id_almacen = intval($_GET['id_almacen'] ?? 0);
$estado = sanitizar_input($_GET['estado'] ?? '');

try {
    $database = new Database();
    $db = $database->getConnection();

    $where = "WHERE 1=1";
    $params = [];

    if ($id_almacen > 0) {
        $where .= " AND i.id_almacen = :id_almacen";
        $params[':id_almacen'] = $id_almacen;
    }

    // Filtrar por nivel de stock
    switch ($estado) {
        case 'critico':
            $where .= " AND i.cantidad_actual <= i.stock_minimo";
            break;
        case 'alerta':
            $where .= " AND i.cantidad_actual > i.stock_minimo AND i.cantidad_actual <= (i.stock_minimo * 1.3)";
            break;
        case 'normal':
            $where .= " AND i.cantidad_actual > (i.stock_minimo * 1.3)";
            break;
    }

    $query = "SELECT i.*, p.codigo as producto_codigo, p.nombre as producto_nombre, 
                     p.imagen_principal, a.nombre as almacen_nombre,
                     CASE 
                        WHEN i.cantidad_actual <= i.stock_minimo THEN 'critico'
                        WHEN i.cantidad_actual <= (i.stock_minimo * 1.3) THEN 'alerta'
                        ELSE 'normal'
                     END as nivel_stock
              FROM inventario i
              JOIN productos p ON i.id_producto = p.id_producto
              JOIN almacenes a ON i.id_almacen = a.id_almacen
              {$where}
              ORDER BY i.cantidad_actual ASC";
    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();

    $inventario = $stmt->fetchAll();

    responderJSON([
        "exito" => true,
        "inventario" => $inventario
    ]);

} catch (PDOException $e) {
    error_log("Error al leer inventario: " . $e->getMessage());
    responderJSON(["error" => "Error al obtener inventario"], 500);
}