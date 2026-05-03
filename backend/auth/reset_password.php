<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderJSON(["error" => "Método no permitido"], 405);
}

$data = json_decode(file_get_contents("php://input"), true) ?: $_POST;

verificarCSRF($data['csrf_token'] ?? '');

$id_usuario = intval($data['id_usuario'] ?? 0);
$codigo_otp = sanitizar_input($data['codigo_otp'] ?? '');
$nueva_password = $data['nueva_password'] ?? '';
$confirmar_password = $data['confirmar_password'] ?? '';

$errores = [];
if ($id_usuario <= 0) $errores[] = "ID de usuario inválido";
if (strlen($codigo_otp) !== 6) $errores[] = "Código OTP inválido";
if (!validarPassword($nueva_password)) $errores[] = "Contraseña debe tener al menos 8 caracteres";
if ($nueva_password !== $confirmar_password) $errores[] = "Las contraseñas no coinciden";

if (!empty($errores)) {
    responderJSON(["error" => "Errores de validación", "detalles" => $errores], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Verificar OTP
    $otpQuery = "SELECT id_otp FROM codigos_otp 
                 WHERE id_usuario = :id AND codigo = :codigo AND tipo = 'recuperacion' 
                 AND activo = 1 AND expira_en > NOW()";
    $otpStmt = $db->prepare($otpQuery);
    $otpStmt->bindParam(':id', $id_usuario);
    $otpStmt->bindParam(':codigo', $codigo_otp);
    $otpStmt->execute();

    if ($otpStmt->rowCount() === 0) {
        responderJSON(["error" => "Código OTP inválido o expirado"], 400);
    }

    $otp = $otpStmt->fetch();

    $db->beginTransaction();

    // Invalidar OTP
    $invQuery = "UPDATE codigos_otp SET activo = 0 WHERE id_otp = :id_otp";
    $invStmt = $db->prepare($invQuery);
    $invStmt->bindParam(':id_otp', $otp['id_otp']);
    $invStmt->execute();

    // Actualizar contraseña
    $password_hash = password_hash($nueva_password, PASSWORD_BCRYPT, ['cost' => 10]);
    $updateQuery = "UPDATE usuarios SET password_hash = :hash WHERE id_usuario = :id";
    $updateStmt = $db->prepare($updateQuery);
    $updateStmt->bindParam(':hash', $password_hash);
    $updateStmt->bindParam(':id', $id_usuario);
    $updateStmt->execute();

    $db->commit();

    responderJSON([
        "exito" => true,
        "mensaje" => "Contraseña restablecida exitosamente"
    ]);

} catch (Exception $e) {
    if (isset($db)) $db->rollBack();
    error_log("Error en reset password: " . $e->getMessage());
    responderJSON(["error" => "Error al restablecer contraseña"], 500);
}