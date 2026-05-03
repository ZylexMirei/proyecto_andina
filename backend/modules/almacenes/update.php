<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderJSON(["error" => "Método no permitido"], 405);
}

verificarAutenticacion();
verificarRol(['Administrador']);

$data = json_decode(file_get_contents("php://input"), true) ?: $_POST;

verificarCSRF($data['csrf_token'] ?? '');

$id_almacen = intval($data['id_almacen'] ?? 0);
$nombre = sanitizar_input($data['nombre'] ?? '');
$direccion = sanitizar_input($data['direccion'] ?? '');
$responsable = sanitizar_input($data['responsable'] ?? '');
$telefono = sanitizar_input($data['telefono'] ?? '');
$estado = sanitizar_input($data['estado'] ?? 'Activo');

if ($id_almacen <= 0) {
    responderJSON(["error" => "ID de almacén inválido"], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $query = "UPDATE almacenes SET nombre = :nombre, direccion = :direccion, 
              responsable = :responsable, telefono = :telefono, estado = :estado 
              WHERE id_almacen = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':nombre', $nombre);
    $stmt->bindParam(':direccion', $direccion);
    $stmt->bindParam(':responsable', $responsable);
    $stmt->bindParam(':telefono', $telefono);
    $stmt->bindParam(':estado', $estado);
    $stmt->bindParam(':id', $id_almacen);
    $stmt->execute();

    responderJSON([
        "exito" => true,
        "mensaje" => "Almacén actualizado exitosamente"
    ]);

} catch (PDOException $e) {
    error_log("Error al actualizar almacén: " . $e->getMessage());
    responderJSON(["error" => "Error al actualizar almacén"], 500);
}