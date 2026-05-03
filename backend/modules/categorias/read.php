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

    $query = "SELECT c.*, 
                     (SELECT COUNT(*) FROM productos WHERE id_categoria = c.id_categoria AND estado = 'Activo') as total_productos
              FROM categorias c
              ORDER BY c.nombre ASC";
    $stmt = $db->prepare($query);
    $stmt->execute();

    responderJSON([
        "exito" => true,
        "categorias" => $stmt->fetchAll()
    ]);

} catch (PDOException $e) {
    error_log("Error al leer categorías: " . $e->getMessage());
    responderJSON(["error" => "Error al obtener categorías"], 500);
}