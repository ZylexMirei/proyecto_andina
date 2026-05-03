<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderJSON(["error" => "Método no permitido"], 405);
}

verificarAutenticacion();
verificarRol(['Administrador']);

$data = json_decode(file_get_contents("php://input"), true) ?: $_POST;

$id_producto = intval($data['id_producto'] ?? 0);

if ($id_producto <= 0) {
    responderJSON(["error" => "ID de producto inválido"], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Eliminación lógica
    $query = "UPDATE productos SET estado = 'Inactivo' WHERE id_producto = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $id_producto);
    $stmt->execute();

    responderJSON([
        "exito" => true,
        "mensaje" => "Producto desactivado exitosamente"
    ]);

} catch (PDOException $e) {
    error_log("Error al eliminar producto: " . $e->getMessage());
    responderJSON(["error" => "Error al desactivar producto"], 500);
}