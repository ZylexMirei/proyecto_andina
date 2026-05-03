<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderJSON(["error" => "Método no permitido"], 405);
}

$data = json_decode(file_get_contents("php://input"), true) ?: $_POST;

verificarCSRF($data['csrf_token'] ?? '');

$id_usuario = intval($data['id_usuario'] ?? 0);
$codigo_ingresado = sanitizar_input($data['codigo_otp'] ?? '');

if (empty($id_usuario) || strlen($codigo_ingresado) !== 6) {
    responderJSON(["error" => "Datos inválidos"], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $query = "SELECT id_otp, codigo FROM codigos_otp 
              WHERE id_usuario = :id_usuario 
              AND activo = 1 
              AND expira_en > NOW() 
              ORDER BY creado_en DESC 
              LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id_usuario', $id_usuario);
    $stmt->execute();

    if ($stmt->rowCount() === 0) {
        responderJSON(["error" => "Código OTP expirado o no encontrado"], 400);
    }

    $otp = $stmt->fetch();

    if ($otp['codigo'] !== $codigo_ingresado) {
        responderJSON(["error" => "Código OTP incorrecto"], 400);
    }

    $db->beginTransaction();

    $updateOTP = "UPDATE codigos_otp SET activo = 0 WHERE id_otp = :id_otp";
    $stmtOTP = $db->prepare($updateOTP);
    $stmtOTP->bindParam(':id_otp', $otp['id_otp']);
    $stmtOTP->execute();

    $updateUser = "UPDATE usuarios SET email_verificado = 1 WHERE id_usuario = :id_usuario";
    $stmtUser = $db->prepare($updateUser);
    $stmtUser->bindParam(':id_usuario', $id_usuario);
    $stmtUser->execute();

    $db->commit();

    responderJSON([
        "exito" => true,
        "mensaje" => "Correo verificado exitosamente"
    ]);

} catch (Exception $e) {
    if (isset($db)) $db->rollBack();
    error_log("Error en verificación OTP: " . $e->getMessage());
    responderJSON(["error" => "Error al verificar código"], 500);
}