<?php
// test_registro_completo.php - Prueba completa del flujo de registro

header('Content-Type: text/html; charset=utf-8');
echo "<html><head><title>Prueba de Registro OTP</title><style>
body { font-family: Arial; margin: 20px; background: #f5f5f5; }
.container { max-width: 800px; margin: 0 auto; }
.card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin: 20px 0; }
.ok { color: green; font-weight: bold; }
.error { color: red; font-weight: bold; }
.info { color: blue; font-weight: bold; }
input { padding: 8px; margin: 5px 0; width: 100%; }
button { padding: 10px 20px; background: #0066cc; color: white; border: none; border-radius: 4px; cursor: pointer; }
button:hover { background: #0052a3; }
.step { background: #f0f0f0; padding: 15px; margin: 10px 0; border-left: 4px solid #0066cc; border-radius: 4px; }
pre { background: #fff; border: 1px solid #ddd; padding: 10px; overflow-x: auto; }
</style></head><body>";

echo "<div class='container'>";
echo "<h1>🔧 Prueba Completa del Sistema de Registro y OTP</h1>";

// Si es POST, hacer el registro
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<div class='card'>";
    echo "<h2>Resultado del Registro</h2>";
    
    $nombre = $_POST['nombre'] ?? 'Test User';
    $email = $_POST['email'] ?? 'testuser@example.com';
    $password = $_POST['password'] ?? 'TestPassword123';
    
    echo "<p><strong>Datos enviados:</strong></p>";
    echo "<ul>";
    echo "<li>Nombre: $nombre</li>";
    echo "<li>Email: $email</li>";
    echo "<li>Contraseña: (oculta)</li>";
    echo "</ul>";
    
    require_once __DIR__ . '/backend/config/database.php';
    require_once __DIR__ . '/backend/includes/functions.php';
    
    try {
        $db = (new Database())->getConnection();
        
        // Verificar que no exista
        $check = $db->prepare("SELECT id_usuario FROM usuarios WHERE email = :email");
        $check->bindParam(':email', $email);
        $check->execute();
        
        if ($check->rowCount() > 0) {
            echo "<p class='error'>⚠️ Este email ya está registrado. Usando otro...</p>";
            $email = 'test_' . uniqid() . '@example.com';
        }
        
        $db->beginTransaction();
        
        $nombres = explode(' ', $nombre, 2);
        $nombre_p = $nombres[0];
        $apellido_p = $nombres[1] ?? '';
        
        // Insertar empleado
        $stmt = $db->prepare("INSERT INTO empleados (nombre, apellido) VALUES (:n, :a)");
        $stmt->bindParam(':n', $nombre_p);
        $stmt->bindParam(':a', $apellido_p);
        $stmt->execute();
        $id_empleado = $db->lastInsertId();
        echo "<p class='ok'>✓ Empleado creado: ID $id_empleado</p>";
        
        // Insertar usuario
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $username = strtolower(substr($nombre_p, 0, 1) . str_replace(' ', '', $apellido_p));
        
        $stmt = $db->prepare("INSERT INTO usuarios (id_empleado, id_rol, username, password_hash, email) VALUES (:e, 3, :u, :p, :em)");
        $stmt->bindParam(':e', $id_empleado);
        $stmt->bindParam(':u', $username);
        $stmt->bindParam(':p', $hash);
        $stmt->bindParam(':em', $email);
        $stmt->execute();
        $id_usuario = $db->lastInsertId();
        echo "<p class='ok'>✓ Usuario creado: ID $id_usuario, Username: $username</p>";
        
        // Generar y guardar OTP
        $codigo = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expira = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        
        $stmt = $db->prepare("INSERT INTO codigos_otp (id_usuario, codigo, tipo, expira_en) VALUES (:id, :cod, 'registro', :exp)");
        $stmt->bindParam(':id', $id_usuario);
        $stmt->bindParam(':cod', $codigo);
        $stmt->bindParam(':exp', $expira);
        $stmt->execute();
        echo "<p class='ok'>✓ OTP generado y guardado en BD: <strong>$codigo</strong></p>";
        
        // Intentar enviar OTP por correo
        echo "<p><strong>Intentando enviar OTP por correo...</strong></p>";
        $correo_ok = enviarOTP($email, $codigo);
        
        if ($correo_ok) {
            echo "<p class='ok'>✓ CORREO ENVIADO EXITOSAMENTE a: $email</p>";
        } else {
            echo "<p class='error'>❌ No se pudo enviar correo (pero el OTP está guardado en BD)</p>";
        }
        
        $db->commit();
        
        // Mostrar información de verificación
        echo "<div class='step'>";
        echo "<h3>📋 Información para Verificar OTP</h3>";
        echo "<p><strong>Email:</strong> $email</p>";
        echo "<p><strong>Código OTP:</strong> <span class='info'>$codigo</span></p>";
        echo "<p><strong>ID Usuario:</strong> $id_usuario</p>";
        echo "<p><strong>Expira en:</strong> $expira</p>";
        echo "<p><em>⏰ El código es válido por 10 minutos.</em></p>";
        echo "</div>";
        
        // Verificar OTP en BD
        echo "<div class='step'>";
        echo "<h3>🔍 Verificando OTP en Base de Datos</h3>";
        $verify = $db->prepare("SELECT * FROM codigos_otp WHERE id_usuario = :id AND activo = 1 ORDER BY creado_en DESC LIMIT 1");
        $verify->bindParam(':id', $id_usuario);
        $verify->execute();
        
        if ($verify->rowCount() > 0) {
            $otp_bd = $verify->fetch();
            echo "<p class='ok'>✓ OTP encontrado en BD</p>";
            echo "<pre>";
            print_r($otp_bd);
            echo "</pre>";
        } else {
            echo "<p class='error'>❌ OTP NO encontrado en BD</p>";
        }
        echo "</div>";
        
    } catch (Exception $e) {
        if (isset($db)) $db->rollBack();
        echo "<p class='error'>❌ Error: " . $e->getMessage() . "</p>";
        echo "<pre>" . $e->getTraceAsString() . "</pre>";
    }
    
    echo "<p><a href='" . $_SERVER['PHP_SELF'] . "'>← Volver a intentar</a></p>";
    echo "</div>";
} else {
    // Mostrar formulario
    echo "<div class='card'>";
    echo "<h2>1️⃣ Prueba de Registro</h2>";
    echo "<p>Este formulario probará el flujo completo de registro:</p>";
    echo "<ol>
        <li>Crear un empleado</li>
        <li>Crear un usuario</li>
        <li>Generar un código OTP</li>
        <li>Guardar el OTP en BD</li>
        <li>Intentar enviar OTP por correo SMTP</li>
        <li>Verificar que el OTP está en BD</li>
    </ol>";
    
    echo "<form method='POST'>";
    echo "<p>";
    echo "<label>Nombre Completo:</label>";
    echo "<input type='text' name='nombre' value='Juan Pérez' required>";
    echo "</p>";
    echo "<p>";
    echo "<label>Correo Electrónico:</label>";
    echo "<input type='email' name='email' value='test" . time() . "@example.com' required>";
    echo "</p>";
    echo "<p>";
    echo "<label>Contraseña (mín 8 caracteres):</label>";
    echo "<input type='password' name='password' value='TestPass123' required>";
    echo "</p>";
    echo "<button type='submit'>Registrarse y Probar OTP</button>";
    echo "</form>";
    echo "</div>";
    
    // Mostrar información del sistema
    echo "<div class='card'>";
    echo "<h2>2️⃣ Estado del Sistema</h2>";
    
    require_once __DIR__ . '/backend/config/env.php';
    require_once __DIR__ . '/backend/config/database.php';
    require_once __DIR__ . '/backend/includes/functions.php';
    
    echo "<p><strong>Configuración de Correo:</strong></p>";
    echo "<ul>";
    echo "<li>Host: " . getEnv('MAIL_HOST') . "</li>";
    echo "<li>Puerto: " . getEnv('MAIL_PORT') . "</li>";
    echo "<li>Usuario: " . getEnv('MAIL_USERNAME') . "</li>";
    echo "<li>Contraseña: " . (getEnv('MAIL_PASSWORD') ? "✓ Configurada" : "✗ No configurada") . "</li>";
    echo "</ul>";
    
    echo "<p><strong>Base de Datos:</strong></p>";
    try {
        $db = (new Database())->getConnection();
        
        $count_users = $db->query("SELECT COUNT(*) as total FROM usuarios")->fetch();
        $count_otp = $db->query("SELECT COUNT(*) as total FROM codigos_otp")->fetch();
        $count_activos = $db->query("SELECT COUNT(*) as total FROM codigos_otp WHERE activo = 1")->fetch();
        
        echo "<ul>";
        echo "<li>Total de usuarios: " . $count_users['total'] . "</li>";
        echo "<li>Total de OTP registrados: " . $count_otp['total'] . "</li>";
        echo "<li>OTP activos (no expirados): " . $count_activos['total'] . "</li>";
        echo "</ul>";
    } catch (Exception $e) {
        echo "<p class='error'>Error al conectar a BD: " . $e->getMessage() . "</p>";
    }
    
    echo "</div>";
    
    // Instrucciones
    echo "<div class='card' style='border-left-color: #00aa00;'>";
    echo "<h2>3️⃣ Si No Llega el Correo</h2>";
    echo "<ol>";
    echo "<li><strong>Abre tu cliente de correo</strong></li>";
    echo "<li>Revisa la carpeta <strong>SPAM o BASURA</strong></li>";
    echo "<li>Si no está ahí, comprueba que:</li>";
    echo "<ul>";
    echo "<li>Los datos en <strong>.env</strong> (MAIL_HOST, MAIL_PORT, etc.) son correctos para tu proveedor.</li>";
    echo "<li>Si usas Gmail, necesitas <strong>Verificación en 2 pasos</strong> y una <strong>contraseña de aplicación</strong>.</li>";
    echo "<li>Para otros proveedores, usualmente es tu contraseña normal.</li>";
    echo "<li>El servidor puede acceder a puerto 587 (no hay firewall)</li>";
    echo "</ul>";
    echo "</ol>";
    echo "</div>";
}

echo "</div>";
echo "</body></html>";
?>
