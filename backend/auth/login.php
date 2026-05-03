<?php
// auth/login.php
session_start();
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
    
    $login_identifier = limpiar_input($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($login_identifier) || empty($password)) {
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
                     WHERE (u.username = :login1 OR u.email = :login2)
                     AND u.estado = 'Activo'";
            
            $stmt = $db->prepare($query);
            $stmt->bindValue(':login1', $login_identifier);
            $stmt->bindValue(':login2', $login_identifier);
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
                        $logStmt->bindParam(':username', $login_identifier);
                        $logStmt->bindParam(':ip', $_SERVER['REMOTE_ADDR']);
                        $logStmt->execute();
                        
                        header("Location: ../dashboard/index.php");
                        exit();
                    } else {
                        $error = "Debe verificar su correo electrónico. Revise su bandeja de entrada.";
                    }
                } else {
                    // Incrementar intentos fallidos
                    $updateQuery = "UPDATE usuarios SET intentos_fallidos = intentos_fallidos + 1 WHERE username = :login1 OR email = :login2";
                    $updateStmt = $db->prepare($updateQuery);
                    $updateStmt->bindValue(':login1', $login_identifier);
                    $updateStmt->bindValue(':login2', $login_identifier);
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
    <style>
        /* Fondo oscuro y elegante */
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e1e38 100%);
            color: #f8fafc;
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
        }
        .shapes-container {
            position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
            z-index: -1; overflow: hidden;
        }
        .shape {
            position: absolute;
            background: rgba(134, 239, 172, 0.25);
            backdrop-filter: blur(5px);
            bottom: -150px;
            animation: floatUp linear infinite;
        }
        .circle { left: 10%; width: 80px; height: 80px; border-radius: 50%; animation-duration: 9s; }
        .square { left: 30%; width: 70px; height: 70px; border-radius: 15px; animation-duration: 12s; animation-delay: 2s; }
        .triangle { left: 50%; width: 75px; height: 75px; clip-path: polygon(50% 0%, 0% 100%, 100% 100%); animation-duration: 10s; animation-delay: 1s; }
        .circle-small { left: 70%; width: 40px; height: 40px; border-radius: 50%; animation-duration: 8s; animation-delay: 4s; }
        .square-small { left: 85%; width: 50px; height: 50px; border-radius: 10px; animation-duration: 11s; animation-delay: 0s; }
        .diamond { left: 20%; width: 60px; height: 60px; clip-path: polygon(50% 0%, 100% 50%, 50% 100%, 0% 50%); animation-duration: 13s; animation-delay: 3s; }

        @keyframes floatUp {
            0% { transform: translateY(0) rotate(0deg); opacity: 1; }
            100% { transform: translateY(-120vh) rotate(360deg); opacity: 0; }
        }

        .card.shadow {
            background: rgba(30, 41, 59, 0.85);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #f8fafc;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5) !important;
        }
        .card .text-muted { color: #94a3b8 !important; }
        .form-control, .input-group-text {
            background-color: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: #f8fafc;
        }
        .form-control:focus {
            background-color: rgba(15, 23, 42, 0.9);
            color: #fff;
            border-color: #4ade80;
            box-shadow: 0 0 0 0.25rem rgba(74, 222, 128, 0.25);
        }
        .text-primary { color: #4ade80 !important; }
        .btn-primary { background-color: #22c55e; border-color: #22c55e; color: #0f172a; font-weight: bold; }
        .btn-primary:hover { background-color: #16a34a; border-color: #16a34a; }
        a { color: #4ade80; }
        a:hover { color: #22c55e; }
    </style>
</head>
<body>
    <div class="shapes-container">
        <div class="shape circle"></div>
        <div class="shape square"></div>
        <div class="shape triangle"></div>
        <div class="shape circle-small"></div>
        <div class="shape square-small"></div>
        <div class="shape diamond"></div>
    </div>
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
                                <label for="username" class="form-label">Usuario o Correo Electrónico</label>
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