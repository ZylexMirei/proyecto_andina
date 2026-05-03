<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderJSON(["error" => "Método no permitido"], 405);
}

verificarAutenticacion();
verificarRol(['Administrador']);

$data = json_decode(file_get_contents("php://input"), true) ?: $_POST;

verificarCSRF($data['csrf_token'] ?? '');

$nombre = sanitizar_input($data['nombre'] ?? '');
$direccion = sanitizar_input($data['direccion'] ?? '');
$responsable = sanitizar_input($data['responsable'] ?? '');
$telefono = sanitizar_input($data['telefono'] ?? '');

if (empty($nombre)) {
    responderJSON(["error" => "Nombre de almacén es requerido"], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $query = "INSERT INTO almacenes (nombre, direccion, responsable, telefono) 
              VALUES (:nombre, :direccion, :responsable, :telefono)";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':nombre', $nombre);
    $stmt->bindParam(':direccion', $direccion);
    $stmt->bindParam(':responsable', $responsable);
    $stmt->bindParam(':telefono', $telefono);
    $stmt->execute();

    responderJSON([
        "exito" => true,
        "mensaje" => "Almacén creado exitosamente",
        "id_almacen" => $db->lastInsertId()
    ], 201);

} catch (PDOException $e) {
    error_log("Error al crear almacén: " . $e->getMessage());
    responderJSON(["error" => "Error al crear almacén"], 500);
}