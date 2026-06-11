<?php
// test_otp_send.php - Prueba de envío de OTP
header('Content-Type: text/html; charset=utf-8');

echo "<h1>Prueba de Envío de OTP</h1>";

// Cargar variables de entorno
require_once __DIR__ . '/backend/config/env.php';
require_once __DIR__ . '/backend/includes/functions.php';

echo "<h2>1. Verificando variables de entorno</h2>";
echo "<pre>";
echo "MAIL_HOST: " . (getEnv('MAIL_HOST') ?: "❌ NO CONFIGURADO") . "\n";
echo "MAIL_PORT: " . (getEnv('MAIL_PORT') ?: "❌ NO CONFIGURADO") . "\n";
echo "MAIL_USERNAME: " . (getEnv('MAIL_USERNAME') ?: "❌ NO CONFIGURADO") . "\n";
echo "MAIL_PASSWORD: " . (getEnv('MAIL_PASSWORD') ? "✓ (configurado)" : "❌ NO CONFIGURADO") . "\n";
echo "MAIL_FROM_ADDRESS: " . (getEnv('MAIL_FROM_ADDRESS') ?: "❌ NO CONFIGURADO") . "\n";
echo "MAIL_FROM_NAME: " . (getEnv('MAIL_FROM_NAME') ?: "Sin nombre") . "\n";
echo "</pre>";

echo "<h2>2. Verificando PHPMailer</h2>";
$phpmailer_path = __DIR__ . '/backend/vendor/autoload.php';
if (file_exists($phpmailer_path)) {
    echo "✓ PHPMailer disponible\n";
    echo "<pre>";
    echo "Ruta: $phpmailer_path\n";
    echo "</pre>";
} else {
    echo "❌ PHPMailer NO encontrado\n";
    echo "<pre>Ejecuta: cd backend && composer install</pre>";
}

echo "<h2>3. Generando código OTP de prueba</h2>";
$codigo_prueba = generarOTP();
echo "<p>Código generado: <strong>$codigo_prueba</strong></p>";

echo "<h2>4. Enviando correo de prueba</h2>";
$email_prueba = getEnv('MAIL_USERNAME');

if (empty($email_prueba)) {
    echo "❌ Error: No hay email configurado en MAIL_USERNAME";
} else {
    echo "Enviando a: <strong>$email_prueba</strong><br>";
    $resultado = enviarCorreoOTP($email_prueba, $codigo_prueba);
    
    if ($resultado) {
        echo "<p style='color: green; font-weight: bold;'>✓ Correo enviado exitosamente</p>";
    } else {
        echo "<p style='color: red; font-weight: bold;'>❌ Error al enviar correo</p>";
    }
}

echo "<h2>5. Revisar log de errores</h2>";
echo "<p>Si hay problemas, revisa: <code>error_log</code></p>";
echo "<pre style='background: #f0f0f0; padding: 10px; border-radius: 5px;'>";
echo "Los errores se registran automáticamente. Comprueba el archivo de error_log del servidor.";
echo "</pre>";
?>
