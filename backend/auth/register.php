<?php
// auth/register.php
session_start();
require_once __DIR__ . '/../includes/functions.php';

if (estaLogueado()) {
    header("Location: ../dashboard/index.php");
    exit();
}

$error = '';
$exito = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    verificarCSRF($_POST['csrf_token']);
    
    $nombre_completo = limpiar_input($_POST['nombre_completo']);
    $email = limpiar_input($_POST['email']);
    $password = $_POST['password'];
    $confirmar_password = $_POST['confirmar_password'];
    
    // Validaciones
    if (empty($nombre_completo) || empty($email) || empty($password) || empty($confirmar_password)) {
        $error = "Todos los campos son obligatorios.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "El formato del correo electrónico no es válido.";
    } elseif (strlen($password) < 8) {
        $error = "La contraseña debe tener al menos 8 caracteres.";
    } elseif ($password !== $confirmar_password) {
        $error = "Las contraseñas no coinciden.";
    } else {
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            // Verificar si el email ya existe
            $checkQuery = "SELECT id_usuario FROM usuarios WHERE email = :email";
            $checkStmt = $db->prepare($checkQuery);
            $checkStmt->bindParam(':email', $email);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() > 0) {
                $error = "Este correo electrónico ya está registrado.";
            } else {
                // Iniciar transacción
                $db->beginTransaction();
                
                try {
                    // Separar nombre y apellido
                    $nombres = explode(' ', $nombre_completo, 2);
                    $nombre = $nombres[0];
                    $apellido = isset($nombres[1]) ? $nombres[1] : '';
                    
                    // Insertar empleado
                    $queryEmpleado = "INSERT INTO empleados (nombre, apellido) VALUES (:nombre, :apellido)";
                    $stmtEmpleado = $db->prepare($queryEmpleado);
                    $stmtEmpleado->bindParam(':nombre', $nombre);
                    $stmtEmpleado->bindParam(':apellido', $apellido);
                    $stmtEmpleado->execute();
                    $id_empleado = $db->lastInsertId();
                    
                    // Hash de contraseña
                    $password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
                    
                    // Determinar username (primera letra del nombre + apellido)
                    $username = strtolower(substr($nombre, 0, 1) . str_replace(' ', '', $apellido));
                    
                    // Insertar usuario
                    $queryUsuario = "INSERT INTO usuarios (id_empleado, id_rol, username, password_hash, email) 
                                    VALUES (:id_empleado, :id_rol, :username, :password_hash, :email)";
                    $stmtUsuario = $db->prepare($queryUsuario);
                    $rol_empleado = 3; // Rol: Empleado por defecto
                    $stmtUsuario->bindParam(':id_empleado', $id_empleado);
                    $stmtUsuario->bindParam(':id_rol', $rol_empleado);
                    $stmtUsuario->bindParam(':username', $username);
                    $stmtUsuario->bindParam(':password_hash', $password_hash);
                    $stmtUsuario->bindParam(':email', $email);
                    $stmtUsuario->execute();
                    $id_usuario = $db->lastInsertId();
                    
                    // Generar OTP
                    $codigo_otp = generarOTP();
                    $expira_en = date('Y-m-d H:i:s', strtotime('+10 minutes'));
                    
                    $queryOTP = "INSERT INTO codigos_otp (id_usuario, codigo, tipo, expira_en) 
                                VALUES (:id_usuario, :codigo, 'registro', :expira_en)";
                    $stmtOTP = $db->prepare($queryOTP);
                    $stmtOTP->bindParam(':id_usuario', $id_usuario);
                    $stmtOTP->bindParam(':codigo', $codigo_otp);
                    $stmtOTP->bindParam(':expira_en', $expira_en);
                    $stmtOTP->execute();
                    
                    // Enviar OTP por correo
                    enviarCorreoOTP($email, $codigo_otp);
                    
                    $db->commit();
                    
                    // Guardar id_usuario en sesión temporal para verificación
                    $_SESSION['id_usuario_pendiente'] = $id_usuario;
                    $_SESSION['email_pendiente'] = $email;
                    
                    header("Location: verify_otp.php");
                    exit();
                    
                } catch (Exception $e) {
                    $db->rollBack();
                    $error = "Error al registrar. Intente nuevamente.";
                    error_log("Error en registro: " . $e->getMessage());
                }
            }
        } catch (PDOException $e) {
            $error = "Error del sistema. Intente nuevamente.";
            error_log("Error en registro: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - Distribuidora Andina</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        /* Fondo oscuro y elegante (sin blanco) */
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e1e38 100%);
            color: #f8fafc;
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
        }
        /* Contenedor de las formas animadas */
        .shapes-container {
            position: fixed;
            top: 0; left: 0; width: 100vw; height: 100vh;
            z-index: -1;
            overflow: hidden;
        }
        /* Estilo base de las formitas (Verde clarito) */
        .shape {
            position: absolute;
            background: rgba(134, 239, 172, 0.25); /* Verde con opacidad */
            backdrop-filter: blur(5px);
            bottom: -150px;
            animation: floatUp linear infinite;
        }
        /* Distintas formitas y velocidades */
        .circle { left: 10%; width: 80px; height: 80px; border-radius: 50%; animation-duration: 9s; }
        .square { left: 30%; width: 70px; height: 70px; border-radius: 15px; animation-duration: 12s; animation-delay: 2s; }
        .triangle { left: 50%; width: 75px; height: 75px; clip-path: polygon(50% 0%, 0% 100%, 100% 100%); animation-duration: 10s; animation-delay: 1s; }
        .circle-small { left: 70%; width: 40px; height: 40px; border-radius: 50%; animation-duration: 8s; animation-delay: 4s; }
        .square-small { left: 85%; width: 50px; height: 50px; border-radius: 10px; animation-duration: 11s; animation-delay: 0s; }
        .diamond { left: 20%; width: 60px; height: 60px; clip-path: polygon(50% 0%, 100% 50%, 50% 100%, 0% 50%); animation-duration: 13s; animation-delay: 3s; }

        /* Animación más rápida */
        @keyframes floatUp {
            0% { transform: translateY(0) rotate(0deg); opacity: 1; }
            100% { transform: translateY(-120vh) rotate(360deg); opacity: 0; }
        }

        /* Estilos de la tarjeta oscurecida */
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
    <!-- Animación de fondo -->
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
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <i class="fas fa-user-plus fa-3x text-primary"></i>
                            <h3 class="mt-3">Crear Cuenta</h3>
                        </div>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" id="registerForm">
                            <input type="hidden" name="csrf_token" value="<?php echo generarCSRF(); ?>">
                            
                            <div class="mb-3">
                                <label for="nombre_completo" class="form-label">Nombre Completo</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" class="form-control" id="nombre_completo" name="nombre_completo" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Correo Electrónico</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    <input type="email" class="form-control" id="email" name="email" required>
                                </div>
                                <small class="text-muted">Recibirás un código OTP para verificar tu cuenta.</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">Contraseña</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="password" name="password" 
                                           minlength="8" required>
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <small class="text-muted">Mínimo 8 caracteres.</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="confirmar_password" class="form-label">Confirmar Contraseña</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="confirmar_password" 
                                           name="confirmar_password" minlength="8" required>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100 mb-3">
                                <i class="fas fa-user-plus"></i> Registrarse
                            </button>
                            
                            <div class="text-center">
                                <a href="login.php" class="text-decoration-none">¿Ya tienes cuenta? Inicia Sesión</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle visibilidad de contraseña
        document.getElementById('togglePassword').addEventListener('click', function() {
            const password = document.getElementById('password');
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            this.querySelector('i').classList.toggle('fa-eye');
            this.querySelector('i').classList.toggle('fa-eye-slash');
        });
    </script>
</body>
</html>