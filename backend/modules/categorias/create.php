<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderJSON(["error" => "Método no permitido"], 405);
}

verificarAutenticacion();
verificarRol(['Administrador', 'Gerente']);

$data = json_decode(file_get_contents("php://input"), true) ?: $_POST;

verificarCSRF($data['csrf_token'] ?? '');

$nombre = sanitizar_input($data['nombre'] ?? '');
$descripcion = sanitizar_input($data['descripcion'] ?? '');
$imagen_url = sanitizar_input($data['imagen_url'] ?? '');

if (empty($nombre)) {
    responderJSON(["error" => "Nombre de categoría es requerido"], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $checkQuery = "SELECT id_categoria FROM categorias WHERE nombre = :nombre";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bindParam(':nombre', $nombre);
    $checkStmt->execute();

    if ($checkStmt->rowCount() > 0) {
        responderJSON(["error" => "Ya existe una categoría con ese nombre"], 409);
    }

    $query = "INSERT INTO categorias (nombre, descripcion, imagen_url) VALUES (:nombre, :descripcion, :imagen)";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':nombre', $nombre);
    $stmt->bindParam(':descripcion', $descripcion);
    $stmt->bindParam(':imagen', $imagen_url);
    $stmt->execute();

    responderJSON([
        "exito" => true,
        "mensaje" => "Categoría creada exitosamente",
        "id_categoria" => $db->lastInsertId()
    ], 201);

} catch (PDOException $e) {
    error_log("Error al crear categoría: " . $e->getMessage());
    responderJSON(["error" => "Error al crear categoría"], 500);
}