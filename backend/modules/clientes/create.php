<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderJSON(["error" => "Método no permitido"], 405);
}

verificarAutenticacion();

$data = json_decode(file_get_contents("php://input"), true) ?: $_POST;

verificarCSRF($data['csrf_token'] ?? '');

$razon_social = sanitizar_input($data['razon_social'] ?? '');
$nit_ci = sanitizar_input($data['nit_ci'] ?? '');
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

    if (!empty($nit_ci)) {
        $checkQuery = "SELECT id_cliente FROM clientes WHERE nit_ci = :nit";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->bindParam(':nit', $nit_ci);
        $checkStmt->execute();

        if ($checkStmt->rowCount() > 0) {
            responderJSON(["error" => "Ya existe un cliente con ese NIT/CI"], 409);
        }
    }

    $insertQuery = "INSERT INTO clientes (razon_social, nit_ci, contacto, telefono, email, direccion) 
                    VALUES (:razon, :nit, :contacto, :telefono, :email, :direccion)";
    $insertStmt = $db->prepare($insertQuery);
    $insertStmt->bindParam(':razon', $razon_social);
    $insertStmt->bindParam(':nit', $nit_ci);
    $insertStmt->bindParam(':contacto', $contacto);
    $insertStmt->bindParam(':telefono', $telefono);
    $insertStmt->bindParam(':email', $email);
    $insertStmt->bindParam(':direccion', $direccion);
    $insertStmt->execute();

    responderJSON([
        "exito" => true,
        "mensaje" => "Cliente registrado exitosamente",
        "id_cliente" => $db->lastInsertId()
    ], 201);

} catch (PDOException $e) {
    error_log("Error al crear cliente: " . $e->getMessage());
    responderJSON(["error" => "Error al registrar cliente"], 500);
}