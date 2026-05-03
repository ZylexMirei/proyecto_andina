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

    // Productos en estado crítico
    $criticoQuery = "SELECT i.*, p.codigo, p.nombre, p.imagen_principal, a.nombre as almacen_nombre
                     FROM inventario i
                     JOIN productos p ON i.id_producto = p.id_producto
                     JOIN almacenes a ON i.id_almacen = a.id_almacen
                     WHERE i.cantidad_actual <= i.stock_minimo AND i.stock_minimo > 0
                     ORDER BY i.cantidad_actual ASC";
    $criticoStmt = $db->prepare($criticoQuery);
    $criticoStmt->execute();
    $criticos = $criticoStmt->fetchAll();

    // Productos agotados
    $agotadoQuery = "SELECT i.*, p.codigo, p.nombre, p.imagen_principal, a.nombre as almacen_nombre
                     FROM inventario i
                     JOIN productos p ON i.id_producto = p.id_producto
                     JOIN almacenes a ON i.id_almacen = a.id_almacen
                     WHERE i.cantidad_actual = 0
                     ORDER BY p.nombre ASC";
    $agotadoStmt = $db->prepare($agotadoQuery);
    $agotadoStmt->execute();
    $agotados = $agotadoStmt->fetchAll();

    // Productos próximos a vencer (si tienen fecha)
    $vencerQuery = "SELECT p.*, i.cantidad_actual, a.nombre as almacen_nombre
                    FROM productos p
                    JOIN inventario i ON p.id_producto = i.id_producto
                    JOIN almacenes a ON i.id_almacen = a.id_almacen
                    WHERE p.fecha_vencimiento IS NOT NULL 
                    AND p.fecha_vencimiento <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                    AND p.fecha_vencimiento >= CURDATE()
                    AND i.cantidad_actual > 0
                    AND p.estado = 'Activo'
                    ORDER BY p.fecha_vencimiento ASC";
    $vencerStmt = $db->prepare($vencerQuery);
    $vencerStmt->execute();
    $por_vencer = $vencerStmt->fetchAll();

    responderJSON([
        "exito" => true,
        "alertas" => [
            "criticos" => [
                "total" => count($criticos),
                "productos" => $criticos
            ],
            "agotados" => [
                "total" => count($agotados),
                "productos" => $agotados
            ],
            "por_vencer" => [
                "total" => count($por_vencer),
                "productos" => $por_vencer
            ]
        ]
    ]);

} catch (PDOException $e) {
    error_log("Error en alertas stock: " . $e->getMessage());
    responderJSON(["error" => "Error al obtener alertas de stock"], 500);
}