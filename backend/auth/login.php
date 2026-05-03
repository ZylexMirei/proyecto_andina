<?php
// auth/login.php
require_once __DIR__ . '/../includes/functions.php';

// Si ya está logueado, redirigir al dashboard
if (estaLogueado()) {
    header("Location: ../dashboard/index.php");
    exit();
}

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Verificar CSRF
    verificarCSRF($_POST['csrf_token']);
    
    $username = limpiar_input($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = "Por favor, complete todos los campos.";
    } else {
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            // Consulta preparada (Anti SQL-Injection)
            $query = "SELECT u.*, r.nombre as rol, e.nombre as nombre_empleado, e.apellido 
                     FROM usuarios u 
                     JOIN roles r ON u.id_rol = r.id_rol 
                     JOIN empleados e ON u.id_empleado = e.id_empleado 
                     WHERE u.username = :username 
                     AND u.estado = 'Activo'";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            
            if ($stmt->rowCount() == 1) {
                $usuario = $stmt->fetch();
                
                // Verificar contraseña con BCRYPT
                if (password_verify($password, $usuario['password_hash'])) {
                    
                    // Verificar si el email está verificado
                    if ($usuario['email_verificado']) {
                        // Iniciar sesión
                        $_SESSION['id_usuario'] = $usuario['id_usuario'];
                        $_SESSION['username'] = $usuario['username'];
                        $_SESSION['nombre'] = $usuario['nombre_empleado'] . ' ' . $usuario['apellido'];
                        $_SESSION['rol'] = $usuario['rol'];
                        $_SESSION['id_empleado'] = $usuario['id_empleado'];
                        
                        // Actualizar último acceso
                        $updateQuery = "UPDATE usuarios SET ultimo_acceso = NOW(), intentos_fallidos = 0 WHERE id_usuario = :id";
                        $updateStmt = $db->prepare($updateQuery);
                        $updateStmt->bindParam(':id', $usuario['id_usuario']);
                        $updateStmt->execute();
                        
                        // Registrar log de acceso exitoso
                        $logQuery = "INSERT INTO log_accesos (id_usuario, username, exito, ip_address) 
                                    VALUES (:id, :username, 1, :ip)";
                        $logStmt = $db->prepare($logQuery);
                        $logStmt->bindParam(':id', $usuario['id_usuario']);
                        $logStmt->bindParam(':username', $username);
                        $logStmt->bindParam(':ip', $_SERVER['REMOTE_ADDR']);
                        $logStmt->execute();
                        
                        header("Location: ../dashboard/index.php");
                        exit();
                    } else {
                        $error = "Debe verificar su correo electrónico. Revise su bandeja de entrada.";
                    }
                } else {
                    // Incrementar intentos fallidos
                    $updateQuery = "UPDATE usuarios SET intentos_fallidos = intentos_fallidos + 1 WHERE username = :username";
                    $updateStmt = $db->prepare($updateQuery);
                    $updateStmt->bindParam(':username', $username);
                    $updateStmt->execute();
                    
                    $error = "Usuario o contraseña incorrectos.";
                }
            } else {
                $error = "Usuario o contraseña incorrectos.";
            }
        } catch (PDOException $e) {
            error_log("Error en login: " . $e->getMessage());
            $error = "Error del sistema. Intente nuevamente.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Distribuidora Andina</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-5">
                <div class="card shadow">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <i class="fas fa-boxes fa-3x text-primary"></i>
                            <h3 class="mt-3">Distribuidora Andina</h3>
                            <p class="text-muted">Sistema de Gestión de Cadena de Suministro</p>
                        </div>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo generarCSRF(); ?>">
                            
                            <div class="mb-3">
                                <label for="username" class="form-label">Usuario</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" class="form-control" id="username" name="username" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">Contraseña</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100 mb-3">
                                <i class="fas fa-sign-in-alt"></i> Iniciar Sesión
                            </button>
                            
                            <div class="text-center">
                                <a href="register.php" class="text-decoration-none">¿No tienes cuenta? Regístrate</a><br>
                                <a href="forgot_password.php" class="text-decoration-none">¿Olvidaste tu contraseña?</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>