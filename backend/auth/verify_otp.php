<?php
// auth/verify_otp.php
require_once __DIR__ . '/../includes/functions.php';

// Verificar que viene del registro
if (!isset($_SESSION['id_usuario_pendiente'])) {
    header("Location: login.php");
    exit();
}

$error = '';
$email = $_SESSION['email_pendiente'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    verificarCSRF($_POST['csrf_token']);
    
    $codigo_ingresado = limpiar_input($_POST['codigo_otp']);
    
    if (strlen($codigo_ingresado) !== 6) {
        $error = "El código debe tener 6 dígitos.";
    } else {
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            $id_usuario = $_SESSION['id_usuario_pendiente'];
            
            // Buscar código OTP válido
            $query = "SELECT * FROM codigos_otp 
                     WHERE id_usuario = :id_usuario 
                     AND activo = 1 
                     AND expira_en > NOW() 
                     ORDER BY creado_en DESC 
                     LIMIT 1";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id_usuario', $id_usuario);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $otp = $stmt->fetch();
                
                if ($otp['codigo'] === $codigo_ingresado) {
                    // Código correcto - Activar cuenta
                    $db->beginTransaction();
                    
                    // Marcar OTP como usado
                    $updateOTP = "UPDATE codigos_otp SET activo = 0 WHERE id_otp = :id_otp";
                    $stmtOTP = $db->prepare($updateOTP);
                    $stmtOTP->bindParam(':id_otp', $otp['id_otp']);
                    $stmtOTP->execute();
                    
                    // Verificar email
                    $updateUser = "UPDATE usuarios SET email_verificado = 1 WHERE id_usuario = :id_usuario";
                    $stmtUser = $db->prepare($updateUser);
                    $stmtUser->bindParam(':id_usuario', $id_usuario);
                    $stmtUser->execute();
                    
                    $db->commit();
                    
                    // Limpiar sesión temporal
                    unset($_SESSION['id_usuario_pendiente']);
                    unset($_SESSION['email_pendiente']);
                    
                    $_SESSION['mensaje'] = [
                        'tipo' => 'exito',
                        'texto' => '¡Cuenta verificada exitosamente! Ya puede iniciar sesión.'
                    ];
                    
                    header("Location: login.php");
                    exit();
                } else {
                    $error = "Código OTP incorrecto. Verifique e intente nuevamente.";
                }
            } else {
                $error = "El código ha expirado o no es válido. Solicite uno nuevo.";
            }
        } catch (PDOException $e) {
            $db->rollBack();
            $error = "Error al verificar. Intente nuevamente.";
            error_log("Error en verificación OTP: " . $e->getMessage());
        }
    }
}

// Reenviar OTP
if (isset($_GET['reenviar']) && $_GET['reenviar'] == 1) {
    try {
        $database = new Database();
        $db = $database->getConnection();
        $id_usuario = $_SESSION['id_usuario_pendiente'];
        
        // Invalidar OTPs anteriores
        $invalidateQuery = "UPDATE codigos_otp SET activo = 0 WHERE id_usuario = :id_usuario AND tipo = 'registro'";
        $invalidateStmt = $db->prepare($invalidateQuery);
        $invalidateStmt->bindParam(':id_usuario', $id_usuario);
        $invalidateStmt->execute();
        
        // Generar nuevo OTP
        $codigo_otp = generarOTP();
        $expira_en = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        
        $insertQuery = "INSERT INTO codigos_otp (id_usuario, codigo, tipo, expira_en) 
                       VALUES (:id_usuario, :codigo, 'registro', :expira_en)";
        $insertStmt = $db->prepare($insertQuery);
        $insertStmt->bindParam(':id_usuario', $id_usuario);
        $insertStmt->bindParam(':codigo', $codigo_otp);
        $insertStmt->bindParam(':expira_en', $expira_en);
        $insertStmt->execute();
        
        enviarCorreoOTP($email, $codigo_otp);
        
        $exito = "Nuevo código OTP enviado a {$email}";
    } catch (PDOException $e) {
        $error = "Error al reenviar el código.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificar Cuenta - Distribuidora Andina</title>
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
                            <i class="fas fa-shield-alt fa-3x text-primary"></i>
                            <h3 class="mt-3">Verificar Cuenta</h3>
                            <p class="text-muted">
                                Hemos enviado un código OTP de 6 dígitos a:<br>
                                <strong><?php echo $email; ?></strong>
                            </p>
                        </div>
                        
                        <?php if (isset($error) && $error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <?php if (isset($exito)): ?>
                            <div class="alert alert-success"><?php echo $exito; ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo generarCSRF(); ?>">
                            
                            <div class="mb-3">
                                <label for="codigo_otp" class="form-label">Código OTP</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-key"></i></span>
                                    <input type="text" class="form-control" id="codigo_otp" name="codigo_otp" 
                                           maxlength="6" placeholder="000000" required>
                                </div>
                                <small class="text-muted">El código expira en 10 minutos.</small>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100 mb-3">
                                <i class="fas fa-check-circle"></i> Verificar Cuenta
                            </button>
                        </form>
                        
                        <div class="text-center">
                            <a href="?reenviar=1" class="text-decoration-none">
                                <i class="fas fa-redo"></i> Reenviar Código
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>