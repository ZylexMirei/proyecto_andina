<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderJSON(["error" => "Método no permitido"], 405);
}

verificarAutenticacion();

try {
    $database = new Database();
    $db = $database->getConnection();

    $query = "SELECT a.*,
                     (SELECT COUNT(*) FROM inventario WHERE id_almacen = a.id_almacen) as total_productos,
                     (SELECT SUM(cantidad_actual) FROM inventario WHERE id_almacen = a.id_almacen) as total_stock
              FROM almacenes a
              ORDER BY a.nombre ASC";
    $stmt = $db->prepare($query);
    $stmt->execute();

    $almacenes = $stmt->fetchAll();

    // Para cada almacén, calcular ocupación
    foreach ($almacenes as &$almacen) {
        $ocupQuery = "SELECT SUM(cantidad_actual) as ocupado, SUM(stock_maximo) as capacidad_total 
                      FROM inventario WHERE id_almacen = :id";
        $ocupStmt = $db->prepare($ocupQuery);
        $ocupStmt->bindParam(':id', $almacen['id_almacen']);
        $ocupStmt->execute();
        $ocupacion = $ocupStmt->fetch();

        $almacen['capacidad_total'] = intval($ocupacion['capacidad_total'] ?? 0);
        $almacen['ocupado'] = intval($ocupacion['ocupado'] ?? 0);
        $almacen['porcentaje_ocupacion'] = $almacen['capacidad_total'] > 0 
            ? round(($almacen['ocupado'] / $almacen['capacidad_total']) * 100, 2) 
            : 0;
    }

    responderJSON([
        "exito" => true,
        "almacenes" => $almacenes
    ]);

} catch (PDOException $e) {
    error_log("Error al leer almacenes: " . $e->getMessage());
    responderJSON(["error" => "Error al obtener almacenes"], 500);
}