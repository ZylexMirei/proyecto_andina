<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderJSON(["error" => "Método no permitido"], 405);
}

$data = json_decode(file_get_contents("php://input"), true) ?: $_POST;

$id_usuario = intval($data['id_usuario'] ?? 0);
$email = sanitizar_input($data['email'] ?? '');

if (empty($id_usuario) || empty($email)) {
    responderJSON(["error" => "ID de usuario y email requeridos"], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $db->beginTransaction();

    $invalidateQuery = "UPDATE codigos_otp SET activo = 0 WHERE id_usuario = :id_usuario AND tipo = 'registro'";
    $invalidateStmt = $db->prepare($invalidateQuery);
    $invalidateStmt->bindParam(':id_usuario', $id_usuario);
    $invalidateStmt->execute();

    $codigo_otp = generarOTP();
    $expira_en = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    $insertQuery = "INSERT INTO codigos_otp (id_usuario, codigo, tipo, expira_en) 
                    VALUES (:id_usuario, :codigo, 'registro', :expira_en)";
    $insertStmt = $db->prepare($insertQuery);
    $insertStmt->bindParam(':id_usuario', $id_usuario);
    $insertStmt->bindParam(':codigo', $codigo_otp);
    $insertStmt->bindParam(':expira_en', $expira_en);
    $insertStmt->execute();

    enviarCorreoOTP($email, $codigo_otp);

    $db->commit();

    responderJSON([
        "exito" => true,
        "mensaje" => "Nuevo código OTP enviado a {$email}"
    ]);

} catch (Exception $e) {
    if (isset($db)) $db->rollBack();
    error_log("Error en reenvío OTP: " . $e->getMessage());
    responderJSON(["error" => "Error al reenviar código"], 500);
}