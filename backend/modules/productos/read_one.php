<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderJSON(["error" => "Método no permitido"], 405);
}

verificarAutenticacion();

$id_producto = intval($_GET['id'] ?? 0);

if ($id_producto <= 0) {
    responderJSON(["error" => "ID de producto inválido"], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $query = "SELECT p.*, c.nombre as categoria_nombre
              FROM productos p
              LEFT JOIN categorias c ON p.id_categoria = c.id_categoria
              WHERE p.id_producto = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $id_producto);
    $stmt->execute();

    if ($stmt->rowCount() === 0) {
        responderJSON(["error" => "Producto no encontrado"], 404);
    }

    $producto = $stmt->fetch();

    $imgQuery = "SELECT id_imagen, url_imagen, orden FROM imagenes_producto WHERE id_producto = :id ORDER BY orden";
    $imgStmt = $db->prepare($imgQuery);
    $imgStmt->bindParam(':id', $id_producto);
    $imgStmt->execute();
    $producto['imagenes_extra'] = $imgStmt->fetchAll();

    $invQuery = "SELECT i.*, a.nombre as almacen_nombre
                 FROM inventario i
                 JOIN almacenes a ON i.id_almacen = a.id_almacen
                 WHERE i.id_producto = :id";
    $invStmt = $db->prepare($invQuery);
    $invStmt->bindParam(':id', $id_producto);
    $invStmt->execute();
    $producto['inventario'] = $invStmt->fetchAll();

    responderJSON([
        "exito" => true,
        "producto" => $producto
    ]);

} catch (PDOException $e) {
    error_log("Error al leer producto: " . $e->getMessage());
    responderJSON(["error" => "Error al obtener producto"], 500);
}