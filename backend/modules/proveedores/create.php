<?php
// modules/proveedores/create.php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderJSON(["error" => "Método no permitido"], 405);
}

verificarAutenticacion();

$data = json_decode(file_get_contents("php://input"), true) ?: $_POST;

verificarCSRF($data['csrf_token'] ?? '');

$razon_social = sanitizar_input($data['razon_social'] ?? '');
$nit = sanitizar_input($data['nit'] ?? '');
$contacto = sanitizar_input($data['contacto'] ?? '');
$telefono = sanitizar_input($data['telefono'] ?? '');
$email = sanitizar_input($data['email'] ?? '');
$direccion = sanitizar_input($data['direccion'] ?? '');

if (empty($razon_social)) {
    responderJSON(["error" => "Razón social es requerida"], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    if (!empty($nit)) {
        $checkQuery = "SELECT id_proveedor FROM proveedores WHERE nit = :nit";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->bindParam(':nit', $nit);
        $checkStmt->execute();

        if ($checkStmt->rowCount() > 0) {
            responderJSON(["error" => "Ya existe un proveedor con ese NIT"], 409);
        }
    }

    $insertQuery = "INSERT INTO proveedores (razon_social, nit, contacto, telefono, email, direccion) 
                    VALUES (:razon, :nit, :contacto, :telefono, :email, :direccion)";
    $insertStmt = $db->prepare($insertQuery);
    $insertStmt->bindParam(':razon', $razon_social);
    $insertStmt->bindParam(':nit', $nit);
    $insertStmt->bindParam(':contacto', $contacto);
    $insertStmt->bindParam(':telefono', $telefono);
    $insertStmt->bindParam(':email', $email);
    $insertStmt->bindParam(':direccion', $direccion);
    $insertStmt->execute();

    responderJSON([
        "exito" => true,
        "mensaje" => "Proveedor registrado exitosamente",
        "id_proveedor" => $db->lastInsertId()
    ], 201);

} catch (PDOException $e) {
    error_log("Error al crear proveedor: " . $e->getMessage());
    responderJSON(["error" => "Error al registrar proveedor"], 500);
}