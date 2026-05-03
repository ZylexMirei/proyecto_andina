<?php
// includes/functions.php

// Cargar variables de entorno
require_once __DIR__ . '/../config/env.php';

// Cargar PHPMailer si existe
$phpmailer_path = __DIR__ . '/../vendor/autoload.php';
if (file_exists($phpmailer_path)) {
    require_once $phpmailer_path;
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

function sanitizar_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Alias para compatibilidad
function limpiar_input($data) {
    return sanitizar_input($data);
}

function estaLogueado() {
    return isset($_SESSION['id_usuario']) && !empty($_SESSION['id_usuario']);
}

function responderJSON($data, $codigo = 200) {
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

function verificarAutenticacion() {
    if (!isset($_SESSION['id_usuario'])) {
        responderJSON(["error" => "No autorizado. Inicie sesi?n."], 401);
    }
}

function verificarRol($roles_permitidos) {
    verificarAutenticacion();
    if (!in_array($_SESSION['rol'], $roles_permitidos)) {
        responderJSON(["error" => "Acceso denegado. Rol no autorizado."], 403);
    }
}

function generarCSRF() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verificarCSRF($token) {
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        responderJSON(["error" => "Token CSRF inv?lido"], 403);
    }
}

function generarOTP() {
    return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function manejarOTP(PDO $db, int $id_usuario, string $email, string $tipo = 'registro') {
    $config = require __DIR__ . '/../config/app.php';
    $duracion_minutos = $config['otp']['duracion_minutos'] ?? 10;

    // 1. Invalidar OTPs anteriores del mismo tipo para ese usuario
    $invalidateQuery = "UPDATE codigos_otp SET activo = 0 WHERE id_usuario = :id_usuario AND tipo = :tipo";
    $invalidateStmt = $db->prepare($invalidateQuery);
    $invalidateStmt->bindParam(':id_usuario', $id_usuario);
    $invalidateStmt->bindParam(':tipo', $tipo);
    $invalidateStmt->execute();

    // 2. Generar y guardar el nuevo OTP
    $codigo_otp = generarOTP();
    $expira_en = date('Y-m-d H:i:s', strtotime("+{$duracion_minutos} minutes"));

    $insertQuery = "INSERT INTO codigos_otp (id_usuario, codigo, tipo, expira_en) 
                    VALUES (:id_usuario, :codigo, :tipo, :expira_en)";
    $insertStmt = $db->prepare($insertQuery);
    $insertStmt->bindParam(':id_usuario', $id_usuario);
    $insertStmt->bindParam(':codigo', $codigo_otp);
    $insertStmt->bindParam(':tipo', $tipo);
    $insertStmt->bindParam(':expira_en', $expira_en);
    $insertStmt->execute();

    // 3. Enviar el correo
    enviarCorreoOTP($email, $codigo_otp);

    // Retornar el código generado para poder mostrarlo en logs o respuestas de API si es necesario
    return $codigo_otp;
}

function enviarCorreoOTP($email, $codigo_otp) {
    $phpmailer_path = __DIR__ . '/../vendor/autoload.php';
    
    if (!file_exists($phpmailer_path)) {
        error_log("PHPMailer no instalado. OTP para {$email}: {$codigo_otp}");
        return false;
    }
    
    require_once $phpmailer_path;
    
    $mail = new PHPMailer(true);
    
    try {
        // Cargar configuraci?n de variables de entorno
        $mail_host = getEnv('MAIL_HOST', 'smtp.gmail.com');
        $mail_port = getEnv('MAIL_PORT', '587');
        $mail_username = getEnv('MAIL_USERNAME');
        $mail_password = getEnv('MAIL_PASSWORD');
        $mail_from = getEnv('MAIL_FROM_ADDRESS');
        $mail_from_name = getEnv('MAIL_FROM_NAME', 'Distribuidora Andina SRL');

        if (empty($mail_username) || empty($mail_password)) {
            error_log("Variables de correo no configuradas. OTP para {$email}: {$codigo_otp}");
            return false;
        }

        if (empty(trim((string) $mail_from))) {
            $mail_from = $mail_username;
        }

        // Configuraci?n del servidor SMTP
        $mail->isSMTP();
        $mail->Host       = $mail_host;
        $mail->SMTPAuth   = true;
        $mail->Username   = $mail_username;
        $mail->Password   = $mail_password;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int) $mail_port;
        $mail->CharSet    = 'UTF-8';

        // Remitente y destinatario
        $mail->setFrom($mail_from, $mail_from_name);
        $mail->addAddress($email);

        // Contenido del correo
        $mail->isHTML(true);
        $mail->Subject = 'C?digo de Verificaci?n - Distribuidora Andina';
        $mail->Body    = "
            <div style='font-family: Arial, sans-serif; max-width: 500px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 10px;'>
                <h2 style='color: #0d6efd; text-align: center;'>Distribuidora Andina</h2>
                <p style='font-size: 16px;'>Hola,</p>
                <p style='font-size: 16px;'>Tu c?digo de verificaci?n es:</p>
                <div style='background-color: #f8f9fa; padding: 20px; text-align: center; border-radius: 5px; margin: 20px 0;'>
                    <span style='font-size: 32px; font-weight: bold; letter-spacing: 10px; color: #0d6efd;'>{$codigo_otp}</span>
                </div>
                <p style='font-size: 14px; color: #666;'>Este c?digo expira en <strong>10 minutos</strong>.</p>
                <p style='font-size: 14px; color: #666;'>Si no solicitaste este c?digo, ignora este mensaje.</p>
            </div>
        ";
        $mail->AltBody = "Tu c?digo de verificaci?n es: {$codigo_otp}. Expira en 10 minutos.";

        $mail->send();
        error_log("OTP enviado a {$email}");
        return true;
        
    } catch (PHPMailerException $e) {
        error_log("Error al enviar correo: " . $mail->ErrorInfo);
        error_log("OTP (fallback log) para {$email}: {$codigo_otp}");
        return false;
    }
}

/** Alias usado por backend/test_api.php ? devuelve true si el correo sali? por SMTP */
function enviarOTP($email, $codigo_otp) {
    return enviarCorreoOTP($email, $codigo_otp);
}

function obtenerIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

function validarEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validarPassword($password) {
    return strlen($password) >= 8;
}

function tienePermiso($id_modulo, $accion = 'ver') {
    if (!isset($_SESSION['id_usuario'])) return false;
    
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT rm.* FROM rol_modulo rm
              JOIN usuarios u ON u.id_rol = rm.id_rol
              WHERE u.id_usuario = :id_usuario AND rm.id_modulo = :id_modulo";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id_usuario', $_SESSION['id_usuario']);
    $stmt->bindParam(':id_modulo', $id_modulo);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) return false;
    
    $permiso = $stmt->fetch();
    
    switch ($accion) {
        case 'crear': return $permiso['puede_crear'];
        case 'editar': return $permiso['puede_editar'];
        case 'eliminar': return $permiso['puede_eliminar'];
        default: return $permiso['puede_ver'];
    }
}