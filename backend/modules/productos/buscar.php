<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderJSON(["error" => "Método no permitido"], 405);
}

verificarAutenticacion();

$termino = sanitizar_input($_GET['q'] ?? '');

if (strlen($termino) < 2) {
    responderJSON(["error" => "Ingrese al menos 2 caracteres"], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $query = "SELECT p.id_producto, p.codigo, p.nombre, p.precio_referencia, p.imagen_principal,
                     c.nombre as categoria_nombre
              FROM productos p
              LEFT JOIN categorias c ON p.id_categoria = c.id_categoria
              WHERE p.estado = 'Activo'
              AND (p.nombre LIKE :q1 OR p.codigo LIKE :q2 OR p.descripcion LIKE :q3)
              ORDER BY p.nombre
              LIMIT 15";
    $stmt = $db->prepare($query);
    $param = "%{$termino}%";
    $stmt->bindParam(':q1', $param);
    $stmt->bindParam(':q2', $param);
    $stmt->bindParam(':q3', $param);
    $stmt->execute();

    responderJSON([
        "exito" => true,
        "resultados" => $stmt->fetchAll()
    ]);

} catch (PDOException $e) {
    error_log("Error al buscar productos: " . $e->getMessage());
    responderJSON(["error" => "Error al buscar productos"], 500);
}