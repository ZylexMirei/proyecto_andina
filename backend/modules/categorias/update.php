<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderJSON(["error" => "Método no permitido"], 405);
}

verificarAutenticacion();
verificarRol(['Administrador', 'Gerente']);

$data = json_decode(file_get_contents("php://input"), true) ?: $_POST;

verificarCSRF($data['csrf_token'] ?? '');

$id_categoria = intval($data['id_categoria'] ?? 0);
$nombre = sanitizar_input($data['nombre'] ?? '');
$descripcion = sanitizar_input($data['descripcion'] ?? '');
$imagen_url = sanitizar_input($data['imagen_url'] ?? '');

if ($id_categoria <= 0) {
    responderJSON(["error" => "ID de categoría inválido"], 400);
}

if (empty($nombre)) {
    responderJSON(["error" => "Nombre es requerido"], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $query = "UPDATE categorias SET nombre = :nombre, descripcion = :descripcion, imagen_url = :imagen 
              WHERE id_categoria = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':nombre', $nombre);
    $stmt->bindParam(':descripcion', $descripcion);
    $stmt->bindParam(':imagen', $imagen_url);
    $stmt->bindParam(':id', $id_categoria);
    $stmt->execute();

    if ($stmt->rowCount() === 0) {
        responderJSON(["error" => "Categoría no encontrada o sin cambios"], 404);
    }

    responderJSON([
        "exito" => true,
        "mensaje" => "Categoría actualizada exitosamente"
    ]);

} catch (PDOException $e) {
    error_log("Error al actualizar categoría: " . $e->getMessage());
    responderJSON(["error" => "Error al actualizar categoría"], 500);
}