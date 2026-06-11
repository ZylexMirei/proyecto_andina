<?php
// test_otp_simple.php - Prueba simple sin sesiones

header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/backend/config/env.php';
require_once __DIR__ . '/backend/config/database.php';

echo "=== PRUEBA SIMPLE DE OTP ===\n\n";

echo "1. Conectando a BD...\n";
try {
    $db = (new Database())->getConnection();
    echo "✓ Conexión OK\n\n";
} catch (Exception $e) {
    die("✗ Error: " . $e->getMessage());
}

echo "2. Creando un usuario de prueba...\n";
$nombre = "Test User";
$email = "otp_test_" . time() . "@test.com";
$password = password_hash("TestPassword123", PASSWORD_BCRYPT);

// Verificar que no exista
$check = $db->prepare("SELECT id_usuario FROM usuarios WHERE email = :email");
$check->bindValue(':email', $email);
$check->execute();

if ($check->rowCount() > 0) {
    die("Email ya existe: $email\n");
}

// Crear empleado
$stmt = $db->prepare("INSERT INTO empleados (nombre, apellido) VALUES (:n, :a)");
$stmt->bindValue(':n', $nombre);
$stmt->bindValue(':a', '');
$stmt->execute();
$id_empleado = $db->lastInsertId();
echo "✓ Empleado creado: ID $id_empleado\n";

// Crear usuario
$username = 'testuser_' . time();
$stmt = $db->prepare("INSERT INTO usuarios (id_empleado, id_rol, username, password_hash, email) VALUES (:e, 3, :u, :p, :em)");
$stmt->bindValue(':e', $id_empleado);
$stmt->bindValue(':u', $username);
$stmt->bindValue(':p', $password);
$stmt->bindValue(':em', $email);
$stmt->execute();
$id_usuario = $db->lastInsertId();
echo "✓ Usuario creado: ID $id_usuario, Email: $email\n\n";

echo "3. Generando OTP...\n";
$codigo = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$expira = date('Y-m-d H:i:s', strtotime('+10 minutes'));

echo "OTP generado: $codigo\n";
echo "Expira en: $expira\n\n";

echo "4. Guardando OTP en BD...\n";
$stmt = $db->prepare("INSERT INTO codigos_otp (id_usuario, codigo, tipo, expira_en) VALUES (:id, :cod, 'registro', :exp)");
$stmt->bindValue(':id', $id_usuario);
$stmt->bindValue(':cod', $codigo);
$stmt->bindValue(':exp', $expira);
$stmt->execute();
echo "✓ OTP guardado en BD\n\n";

echo "5. Verificando OTP en BD...\n";
$verify = $db->prepare("SELECT * FROM codigos_otp WHERE id_usuario = :id ORDER BY creado_en DESC LIMIT 1");
$verify->bindValue(':id', $id_usuario);
$verify->execute();

if ($verify->rowCount() > 0) {
    $otp_bd = $verify->fetch();
    echo "✓ OTP encontrado en BD\n";
    echo "  - ID OTP: " . $otp_bd['id_otp'] . "\n";
    echo "  - Código: " . $otp_bd['codigo'] . "\n";
    echo "  - Tipo: " . $otp_bd['tipo'] . "\n";
    echo "  - Activo: " . $otp_bd['activo'] . "\n";
    echo "  - Creado: " . $otp_bd['creado_en'] . "\n";
    echo "  - Expira: " . $otp_bd['expira_en'] . "\n\n";
} else {
    echo "✗ OTP NO encontrado en BD\n\n";
}

echo "6. Intentando enviar OTP por correo...\n";
require_once __DIR__ . '/backend/vendor/autoload.php';
require_once __DIR__ . '/backend/includes/functions.php';

// Generar correctamente el OTP (ya lo tenemos)
$correo_ok = enviarOTP($email, $codigo);

if ($correo_ok) {
    echo "✓ CORREO ENVIADO a: $email\n";
} else {
    echo "✗ No se pudo enviar correo (pero OTP está en BD)\n";
}

echo "\n7. Resumen:\n";
echo "Usuario: $email\n";
echo "OTP: $codigo\n";
echo "ID Usuario: $id_usuario\n";
echo "\n";
echo "Si no recibiste el correo, el OTP sigue siendo válido.\n";
echo "Revisa la carpeta SPAM de Gmail.\n";
?>
