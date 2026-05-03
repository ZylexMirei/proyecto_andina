<?php
// auth/register.php
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
</head>
<body class="bg-light">
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