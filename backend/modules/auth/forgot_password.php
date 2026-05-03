<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderJSON(["error" => "Método no permitido"], 405);
}

$data = json_decode(file_get_contents("php://input"), true) ?: $_POST;

verificarCSRF($data['csrf_token'] ?? '');

$email = sanitizar_input($data['email'] ?? '');

if (empty($email) || !validarEmail($email)) {
    responderJSON(["error" => "Email válido es requerido"], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $query = "SELECT id_usuario, username FROM usuarios WHERE email = :email AND estado = 'Activo'";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':email', $email);
    $stmt->execute();

    if ($stmt->rowCount() === 0) {
        // Por seguridad, no revelamos si el email existe o no
        responderJSON([
            "exito" => true,
            "mensaje" => "Si el email está registrado, recibirás un código de recuperación."
        ]);
    }

    $usuario = $stmt->fetch();

    $db->beginTransaction();

    // Invalidar OTPs anteriores
    $invQuery = "UPDATE codigos_otp SET activo = 0 WHERE id_usuario = :id AND tipo = 'recuperacion'";
    $invStmt = $db->prepare($invQuery);
    $invStmt->bindParam(':id', $usuario['id_usuario']);
    $invStmt->execute();

    // Generar nuevo OTP
    $codigo_otp = generarOTP();
    $expira_en = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    $otpQuery = "INSERT INTO codigos_otp (id_usuario, codigo, tipo, expira_en) 
                 VALUES (:id, :codigo, 'recuperacion', :expira)";
    $otpStmt = $db->prepare($otpQuery);
    $otpStmt->bindParam(':id', $usuario['id_usuario']);
    $otpStmt->bindParam(':codigo', $codigo_otp);
    $otpStmt->bindParam(':expira', $expira_en);
    $otpStmt->execute();

    enviarCorreoOTP($email, $codigo_otp);

    $db->commit();

    responderJSON([
        "exito" => true,
        "mensaje" => "Si el email está registrado, recibirás un código de recuperación.",
    ]);

} catch (Exception $e) {
    if (isset($db)) $db->rollBack();
    error_log("Error en forgot password: " . $e->getMessage());
    responderJSON(["error" => "Error al procesar solicitud"], 500);
}