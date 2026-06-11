<?php
// test_mongodb.php - Prueba de conexión, escritura y lectura en MongoDB

header('Content-Type: text/html; charset=utf-8');
echo "<style>body{font-family: Arial; padding: 20px;} .ok{color: green; font-weight: bold;} .error{color: red; font-weight: bold;} .card{background: #f9f9f9; border: 1px solid #ddd; padding: 15px; border-radius: 8px; margin-bottom: 20px;}</style>";

echo "<h1>🔍 Prueba de Conexión a MongoDB</h1>";

// Habilitar errores 
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<div class='card'>";
echo "<h2>1. Verificando requerimientos del servidor...</h2>";
if (!extension_loaded('mongodb')) {
    echo "<p class='error'>❌ La extensión de PHP 'mongodb' NO está instalada o habilitada en tu servidor WAMP.</p>";
    echo "<p>Debes habilitarla en tu <code>php.ini</code> para que PHP sepa cómo comunicarse con MongoDB.</p>";
    die("</div>");
} else {
    echo "<p class='ok'>✓ Extensión de MongoDB habilitada en PHP.</p>";
}
echo "</div>";

require_once __DIR__ . '/backend/config/mongodb.php';
require_once __DIR__ . '/backend/config/auditoria.php';

echo "<div class='card'>";
echo "<h2>2. Probando la conexión a Atlas...</h2>";
try {
    $manager = MongoConnection::getManager();
    $database = MongoConnection::getDatabaseName();

    $command = new MongoDB\Driver\Command(['ping' => 1]);
    $manager->executeCommand($database, $command);

    echo "<p class='ok'>✓ Conexión exitosa al cluster de MongoDB Atlas.</p>";
    echo "<p>Base de datos activa: <strong>" . htmlspecialchars($database) . "</strong></p>";
} catch (Throwable $e) {
    echo "<p class='error'>❌ Error de conexión: " . $e->getMessage() . "</p>";
    echo "<p>💡 Asegúrate de que la IP de tu red actual está agregada a la <em>'IP Access List'</em> (Network Access) en el panel de control de MongoDB Atlas.</p>";
    die("</div>");
}
echo "</div>";

echo "<div class='card'>";
echo "<h2>3. Probando la Auditoría (Escritura y Lectura)...</h2>";
$usuario_prueba = "usuario_demo_" . time();

try {
    registrarAuditoria($usuario_prueba, "Tester", "Prueba de validación MongoDB", "Sistema Principal");
    echo "<p class='ok'>✓ Registro de auditoría guardado exitosamente.</p>";

    $query = new MongoDB\Driver\Query(['usuario' => $usuario_prueba], ['limit' => 1]);
    $rows = MongoConnection::getManager()->executeQuery($database . '.auditoria_usuarios', $query);
    $resultado = current($rows->toArray());

    echo "<p>Recuperando el registro desde la nube:</p>";
    echo "<pre style='background: #fff; padding: 10px; border-left: 4px solid #00a859;'>";
    print_r((array) $resultado);
    echo "</pre>";
} catch (Throwable $e) {
    echo "<p class='error'>❌ Error en la operación: " . $e->getMessage() . "</p>";
}
echo "</div>";