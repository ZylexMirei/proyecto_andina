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
        responderJSON(["error" => "No autorizado. Inicie sesión."], 401);
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
        responderJSON(["error" => "Token CSRF inválido"], 403);
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
        // Cargar configuración de variables de entorno
        $mail_host = getEnv('MAIL_HOST', 'smtp.gmail.com');
        $mail_port = getEnv('MAIL_PORT', '587');
        $mail_username = getEnv('MAIL_USERNAME');
        $mail_password = getEnv('MAIL_PASSWORD');
        $mail_from = getEnv('MAIL_FROM_ADDRESS');
        $mail_from_name = getEnv('MAIL_FROM_NAME', 'Distribuidora Andina SRL');
        $mail_encryption = getEnv('MAIL_ENCRYPTION', 'tls'); // tls o ssl

        if (empty($mail_username) || empty($mail_password)) {
            error_log("Variables de correo no configuradas. OTP para {$email}: {$codigo_otp}");
            return false;
        }

        if (empty(trim((string) $mail_from))) {
            $mail_from = $mail_username;
        }

        // Configuración del servidor SMTP
        $mail->isSMTP();
        $mail->Host       = $mail_host;
        $mail->SMTPAuth   = true;
        $mail->Username   = $mail_username;
        $mail->Password   = $mail_password;
        $mail->SMTPSecure = ($mail_encryption === 'ssl') ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int) $mail_port;
        $mail->CharSet    = 'UTF-8';

        // Remitente y destinatario
        $mail->setFrom($mail_from, $mail_from_name);
        $mail->addAddress($email);

        // Contenido del correo
        $mail->isHTML(true);
        $mail->Subject = 'Código de Verificación - Distribuidora Andina';
        $mail->Body    = "
            <div style='font-family: \"Segoe UI\", Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 30px; background-color: #ffffff; border: 1px solid #eaeaea; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.05);'>
                <div style='text-align: center; border-bottom: 2px solid #f0f0f0; padding-bottom: 20px; margin-bottom: 20px;'>
                    <h2 style='color: #2563eb; margin: 0; font-size: 24px; letter-spacing: 1px;'>Distribuidora Andina</h2>
                    <p style='color: #64748b; font-size: 14px; margin-top: 5px;'>Seguridad de la cuenta</p>
                </div>
                <p style='font-size: 16px; color: #334155; line-height: 1.6;'>Hola,</p>
                <p style='font-size: 16px; color: #334155; line-height: 1.6;'>Se ha solicitado un código de acceso para tu cuenta. Tu código de verificación es:</p>
                <div style='background-color: #f1f5f9; padding: 25px; text-align: center; border-radius: 8px; margin: 30px 0; border: 1px dashed #cbd5e1;'>
                    <span style='font-size: 38px; font-weight: 800; letter-spacing: 12px; color: #0f172a;'>{$codigo_otp}</span>
                </div>
                <p style='font-size: 15px; color: #475569;'>Este código expira en <strong>10 minutos</strong>.</p>
                <div style='margin-top: 40px; padding-top: 20px; border-top: 1px solid #f0f0f0; text-align: center;'>
                    <p style='font-size: 13px; color: #94a3b8;'>Si no solicitaste este código, puedes ignorar este mensaje o contactar a soporte si tienes dudas.</p>
                    <p style='font-size: 12px; color: #cbd5e1; margin-top: 10px;'>&copy; " . date('Y') . " Distribuidora Andina SRL. Todos los derechos reservados.</p>
                </div>
            </div>
        ";
        $mail->AltBody = "Hola,\n\nTu código de verificación es: {$codigo_otp}.\n\nEste código expira en 10 minutos.\nSi no solicitaste este código, ignora este mensaje.\n\nDistribuidora Andina SRL";

        $mail->send();
        error_log("OTP enviado a {$email}");
        return true;
        
    } catch (PHPMailerException $e) {
        error_log("Error al enviar correo: " . $mail->ErrorInfo);
        error_log("OTP (fallback log) para {$email}: {$codigo_otp}");
        return false;
    }
}

/** Alias usado por backend/test_api.php - devuelve true si el correo salió por SMTP */
function enviarOTP($email, $codigo_otp) {
    return enviarCorreoOTP($email, $codigo_otp);
}

function enviarCorreoPedido($email, $nombre, $codigo_pedido, $items, $total) {
    $phpmailer_path = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($phpmailer_path)) {
        error_log("PHPMailer no instalado. No se pudo enviar factura a {$email}");
        return false;
    }
    
    require_once $phpmailer_path;
    $mail = new PHPMailer(true);
    
    try {
        $mail_host = getEnv('MAIL_HOST', 'smtp.gmail.com');
        $mail_port = getEnv('MAIL_PORT', '587');
        $mail_username = getEnv('MAIL_USERNAME');
        $mail_password = getEnv('MAIL_PASSWORD');
        $mail_from = getEnv('MAIL_FROM_ADDRESS');
        $mail_from_name = getEnv('MAIL_FROM_NAME', 'Distribuidora Andina SRL');
        $mail_encryption = getEnv('MAIL_ENCRYPTION', 'tls');

        if (empty($mail_username) || empty($mail_password)) return false;
        if (empty(trim((string) $mail_from))) $mail_from = $mail_username;

        $mail->isSMTP();
        $mail->Host       = $mail_host;
        $mail->SMTPAuth   = true;
        $mail->Username   = $mail_username;
        $mail->Password   = $mail_password;
        $mail->SMTPSecure = ($mail_encryption === 'ssl') ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int) $mail_port;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom($mail_from, $mail_from_name);
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = "Confirmación de Pedido {$codigo_pedido} - Factura Comercial";
        
        $itemsHtml = '';
        foreach ($items as $item) {
            $sub = number_format($item['cantidad'] * $item['precio'], 2);
            $precio = number_format($item['precio'], 2);
            $itemsHtml .= "<tr>
                <td style='padding: 12px; border-bottom: 1px solid #eee;'>{$item['nombre']}</td>
                <td style='padding: 12px; border-bottom: 1px solid #eee; text-align: center;'>{$item['cantidad']}</td>
                <td style='padding: 12px; border-bottom: 1px solid #eee; text-align: right;'>Bs. {$precio}</td>
                <td style='padding: 12px; border-bottom: 1px solid #eee; text-align: right;'>Bs. {$sub}</td>
            </tr>";
        }
        $totalFmt = number_format($total, 2);
        $fecha = date('d/m/Y H:i');

        $mail->Body = "
            <div style='font-family: \"Helvetica Neue\", Helvetica, Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 0; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05);'>
                <div style='background: #00a859; padding: 20px; text-align: center; color: white;'>
                    <h2 style='margin: 0; font-size: 24px; letter-spacing: 1px;'>Distribuidora Andina</h2>
                    <p style='margin: 5px 0 0 0; font-size: 14px; opacity: 0.9;'>Factura Comercial - Recibo de Compra</p>
                </div>
                <div style='padding: 30px;'>
                    <p style='font-size: 16px; color: #334155;'>Hola <strong>{$nombre}</strong>,</p>
                    <p style='font-size: 15px; color: #475569; line-height: 1.6;'>Hemos recibido tu pago correctamente y tu pedido ha sido confirmado. Aquí tienes los detalles de tu compra:</p>
                    <div style='background: #f8fafc; padding: 15px; border-radius: 8px; margin: 20px 0; border: 1px solid #e2e8f0; display: flex; justify-content: space-between;'>
                        <div><p style='margin: 0; font-size: 12px; color: #64748b; text-transform: uppercase;'>Nro. Pedido</p><p style='margin: 2px 0 0 0; font-weight: bold; color: #0f172a;'>{$codigo_pedido}</p></div>
                        <div style='text-align: right;'><p style='margin: 0; font-size: 12px; color: #64748b; text-transform: uppercase;'>Fecha</p><p style='margin: 2px 0 0 0; font-weight: bold; color: #0f172a;'>{$fecha}</p></div>
                    </div>
                    <table style='width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 14px;'>
                        <thead><tr style='background: #f1f5f9; color: #475569;'><th style='padding: 10px 12px; text-align: left;'>Producto</th><th style='padding: 10px 12px; text-align: center;'>Cant.</th><th style='padding: 10px 12px; text-align: right;'>P. Unit</th><th style='padding: 10px 12px; text-align: right;'>Subtotal</th></tr></thead>
                        <tbody>{$itemsHtml}</tbody>
                        <tfoot><tr><td colspan='3' style='padding: 20px 12px 10px; text-align: right; font-weight: bold; color: #475569;'>TOTAL PAGADO:</td><td style='padding: 20px 12px 10px; text-align: right; font-weight: 800; color: #00a859; font-size: 18px;'>Bs. {$totalFmt}</td></tr></tfoot>
                    </table>
                </div>
                <div style='background: #f8fafc; padding: 20px; text-align: center; border-top: 1px solid #e2e8f0;'><p style='font-size: 13px; color: #64748b; margin: 0;'>Te contactaremos pronto para coordinar el envío de tus productos.</p></div>
            </div>";
        $mail->send();
        return true;
    } catch (PHPMailerException $e) { return false; }
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