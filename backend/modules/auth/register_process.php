<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderJSON(["error" => "Método no permitido"], 405);
}

$data = json_decode(file_get_contents("php://input"), true) ?: $_POST;

verificarCSRF($data['csrf_token'] ?? '');

$nombre_completo = sanitizar_input($data['nombre_completo'] ?? '');
$email = sanitizar_input($data['email'] ?? '');
$password = $data['password'] ?? '';
$confirmar_password = $data['confirmar_password'] ?? '';

$errores = [];

if (empty($nombre_completo)) $errores[] = "Nombre completo es requerido";
if (empty($email)) $errores[] = "Email es requerido";
elseif (!validarEmail($email)) $errores[] = "Formato de email inválido";
if (empty($password)) $errores[] = "Contraseña es requerida";
elseif (!validarPassword($password)) $errores[] = "La contraseña debe tener al menos 8 caracteres";
if ($password !== $confirmar_password) $errores[] = "Las contraseñas no coinciden";

if (!empty($errores)) {
    responderJSON(["error" => "Errores de validación", "detalles" => $errores], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // 1. Verificar si el email ya existe, ya que debe ser único.
    $checkQuery = "SELECT id_usuario FROM usuarios WHERE email = :email";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bindParam(':email', $email);
    $checkStmt->execute();

    if ($checkStmt->rowCount() > 0) {
        responderJSON(["error" => "El email ya se encuentra registrado"], 409);
    }

    // 2. Generar un nombre de usuario único de forma más robusta.
    $nombres = explode(' ', $nombre_completo, 2);
    $base_username = strtolower(substr($nombres[0], 0, 1) . preg_replace('/[^a-z0-9]/i', '', $nombres[1] ?? ''));
    if (empty($base_username)) { // Fallback si el nombre es inusual
        $base_username = strtolower(preg_replace('/[^a-z0-9]/i', '', explode('@', $email)[0]));
    }

    $username_temp = $base_username;
    $counter = 1;
    $userCheckQuery = "SELECT id_usuario FROM usuarios WHERE username = :username";
    $userCheckStmt = $db->prepare($userCheckQuery);
    do {
        $userCheckStmt->bindParam(':username', $username_temp);
        $userCheckStmt->execute();
        if ($userCheckStmt->rowCount() > 0) $username_temp = $base_username . $counter++;
    } while ($userCheckStmt->rowCount() > 0);

    $db->beginTransaction();

    $queryEmpleado = "INSERT INTO empleados (nombre, apellido) VALUES (:nombre, :apellido)";
    $stmtEmpleado = $db->prepare($queryEmpleado);
    $nombre = $nombres[0];
    $apellido = $nombres[1] ?? '';
    $stmtEmpleado->bindParam(':nombre', $nombre);
    $stmtEmpleado->bindParam(':apellido', $apellido);
    $stmtEmpleado->execute();
    $id_empleado = $db->lastInsertId();

    $config = require __DIR__ . '/../../config/app.php';
    $password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
    $rol_empleado = $config['roles']['default_empleado'];

    $queryUsuario = "INSERT INTO usuarios (id_empleado, id_rol, username, password_hash, email) 
                     VALUES (:id_empleado, :id_rol, :username, :password_hash, :email)";
    $stmtUsuario = $db->prepare($queryUsuario);
    $stmtUsuario->bindParam(':id_empleado', $id_empleado);
    $stmtUsuario->bindParam(':id_rol', $rol_empleado);
    $stmtUsuario->bindParam(':username', $username_temp);
    $stmtUsuario->bindParam(':password_hash', $password_hash);
    $stmtUsuario->bindParam(':email', $email);
    $stmtUsuario->execute();
    $id_usuario = $db->lastInsertId();

    $codigo_otp = generarOTP();
    $expira_en = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    $queryOTP = "INSERT INTO codigos_otp (id_usuario, codigo, tipo, expira_en) 
                 VALUES (:id_usuario, :codigo, 'registro', :expira_en)";
    $stmtOTP = $db->prepare($queryOTP);
    $stmtOTP->bindParam(':id_usuario', $id_usuario);
    $stmtOTP->bindParam(':codigo', $codigo_otp);
    $stmtOTP->bindParam(':expira_en', $expira_en);
    $stmtOTP->execute();

    enviarCorreoOTP($email, $codigo_otp);

    $db->commit();

    responderJSON([
        "exito" => true,
        "mensaje" => "Usuario registrado. Verifique su correo con el código OTP.",
        "id_usuario" => $id_usuario,
        "email" => $email,
        "username" => $username_temp
    ], 201);

} catch (Exception $e) {
    if (isset($db)) $db->rollBack();
    error_log("Error en registro: " . $e->getMessage());
    responderJSON(["error" => "Error al registrar usuario"], 500);
}