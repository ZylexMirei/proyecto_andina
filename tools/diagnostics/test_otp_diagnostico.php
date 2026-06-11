<?php
// test_otp_diagnostico.php - Diagnóstico completo del sistema de OTP

header('Content-Type: text/html; charset=utf-8');
echo "<html><head><title>Diagnóstico OTP</title><style>
body { font-family: Arial; margin: 20px; }
.ok { color: green; font-weight: bold; }
.error { color: red; font-weight: bold; }
.warning { color: orange; font-weight: bold; }
.section { background: #f0f0f0; padding: 15px; margin: 10px 0; border-radius: 5px; border-left: 4px solid #0066cc; }
pre { background: #fff; border: 1px solid #ddd; padding: 10px; overflow-x: auto; }
</style></head><body>";

echo "<h1>🔍 Diagnóstico Completo de OTP</h1>";

// 1. Cargar variables de entorno
echo "<div class='section'><h2>1️⃣ Variables de Entorno</h2>";
require_once __DIR__ . '/backend/config/env.php';

$config = [
    'MAIL_HOST' => getEnv('MAIL_HOST'),
    'MAIL_PORT' => getEnv('MAIL_PORT'),
    'MAIL_USERNAME' => getEnv('MAIL_USERNAME'),
    'MAIL_PASSWORD' => getEnv('MAIL_PASSWORD'),
    'MAIL_FROM_ADDRESS' => getEnv('MAIL_FROM_ADDRESS'),
    'MAIL_FROM_NAME' => getEnv('MAIL_FROM_NAME'),
];

foreach ($config as $key => $value) {
    if (empty($value)) {
        echo "<p class='error'>❌ $key = (vacío)</p>";
    } else {
        if ($key === 'MAIL_PASSWORD') {
            echo "<p class='ok'>✓ $key = (configurado - " . strlen($value) . " caracteres)</p>";
        } else {
            echo "<p class='ok'>✓ $key = $value</p>";
        }
    }
}
echo "</div>";

// 2. Verificar PHPMailer
echo "<div class='section'><h2>2️⃣ PHPMailer</h2>";
$phpmailer_path = __DIR__ . '/backend/vendor/autoload.php';
if (file_exists($phpmailer_path)) {
    echo "<p class='ok'>✓ PHPMailer autoload encontrado</p>";
    require_once $phpmailer_path;
    
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        echo "<p class='ok'>✓ Clase PHPMailer cargada correctamente</p>";
    } catch (Exception $e) {
        echo "<p class='error'>❌ Error al instanciar PHPMailer: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p class='error'>❌ PHPMailer autoload NO encontrado en: $phpmailer_path</p>";
}
echo "</div>";

// 3. Verificar Database
echo "<div class='section'><h2>3️⃣ Conexión a Base de Datos</h2>";
try {
    require_once __DIR__ . '/backend/config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    if ($db) {
        echo "<p class='ok'>✓ Conexión a BD exitosa</p>";
        
        // Verificar tabla codigos_otp
        try {
            $query = "SELECT COUNT(*) as total FROM codigos_otp";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $result = $stmt->fetch();
            echo "<p class='ok'>✓ Tabla codigos_otp existe</p>";
            echo "<p>  Total de OTP registrados: " . $result['total'] . "</p>";
        } catch (Exception $e) {
            echo "<p class='error'>❌ Error al acceder a codigos_otp: " . $e->getMessage() . "</p>";
        }
        
        // Verificar tabla usuarios
        try {
            $query = "SELECT COUNT(*) as total FROM usuarios";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $result = $stmt->fetch();
            echo "<p class='ok'>✓ Tabla usuarios existe</p>";
            echo "<p>  Total de usuarios: " . $result['total'] . "</p>";
        } catch (Exception $e) {
            echo "<p class='error'>❌ Error al acceder a usuarios: " . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p class='error'>❌ No se pudo conectar a la BD</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ Error al conectar: " . $e->getMessage() . "</p>";
}
echo "</div>";

// 4. Prueba de envío SMTP directo
echo "<div class='section'><h2>4️⃣ Prueba de SMTP Directo</h2>";
try {
    require_once __DIR__ . '/backend/vendor/autoload.php';
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    $mail->isSMTP();
    $mail->Host       = getEnv('MAIL_HOST');
    $mail->SMTPAuth   = true;
    $mail->Username   = getEnv('MAIL_USERNAME');
    $mail->Password   = getEnv('MAIL_PASSWORD');
    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = (int) getEnv('MAIL_PORT');
    $mail->CharSet    = 'UTF-8';
    
    echo "<p>Intentando conectar a SMTP...</p>";
    echo "<p>Host: " . getEnv('MAIL_HOST') . ":" . getEnv('MAIL_PORT') . "</p>";
    echo "<p>Usuario: " . getEnv('MAIL_USERNAME') . "</p>";
    
    // No enviar, solo probar conexión
    $mail->setFrom(getEnv('MAIL_FROM_ADDRESS'), getEnv('MAIL_FROM_NAME'));
    $mail->addAddress(getEnv('MAIL_USERNAME'));
    $mail->Subject = 'Prueba de Conexión SMTP';
    $mail->Body = 'Si recibiste este correo, SMTP funciona correctamente.';
    $mail->AltBody = 'Si recibiste este correo, SMTP funciona correctamente.';
    
    // Intentar envío
    if ($mail->send()) {
        echo "<p class='ok'>✓ CORREO DE PRUEBA ENVIADO EXITOSAMENTE</p>";
        echo "<p>Si recibiste el correo, todo funciona. El problema puede estar en:</p>";
        echo "<ul>
            <li>Filtros de spam/basura de Gmail</li>
            <li>Bloqueo de correos de terceros</li>
            <li>Filtros del servidor</li>
        </ul>";
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ Error en SMTP: " . $e->getMessage() . "</p>";
    echo "<p class='warning'>⚠️ Esto podría significar:</p>";
    echo "<ul>
        <li>Contraseña de aplicación incorrecta</li>
        <li>Autenticación de 2FA no está habilitada en Gmail</li>
        <li>Firewall bloqueando puerto 587</li>
        <li>Cuenta de Gmail tiene restricciones</li>
    </ul>";
}
echo "</div>";

// 5. Información útil
echo "<div class='section'><h2>5️⃣ Información de Seguridad de Gmail</h2>";
echo "<p><strong>Para que funcione OTP por correo en Gmail:</strong></p>";
echo "<ol>
    <li>Habilita <strong>Verificación en 2 pasos</strong> en tu cuenta Google</li>
    <li>Ve a: <a href='https://myaccount.google.com/apppasswords' target='_blank'>myaccount.google.com/apppasswords</a></li>
    <li>Selecciona: Correo (Mail) → Windows (u otro dispositivo)</li>
    <li>Copia la contraseña de 16 caracteres</li>
    <li>Pégala en .env como MAIL_PASSWORD (sin espacios extra)</li>
    <li>Reinicia el servidor/servicio</li>
</ol>";
echo "</div>";

// 6. Próximos pasos
echo "<div class='section' style='border-left-color: #00aa00;'><h2>6️⃣ Próximos Pasos</h2>";
echo "<ol>
    <li>Si ves ✓ arriba: SMTP funciona. Revisa tu carpeta de SPAM/BASURA en Gmail.</li>
    <li>Si ves ❌ en SMTP: La contraseña o configuración de Gmail es incorrecta.</li>
    <li>Genera una nueva contraseña de aplicación en Google.</li>
    <li>Actualiza el .env y guarda el archivo.</li>
    <li>Intenta registrarte nuevamente.</li>
</ol>";
echo "</div>";

echo "</body></html>";
?>
