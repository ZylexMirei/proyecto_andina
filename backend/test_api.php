<?php
/**
 * test_api.php
 * 
 * Archivo de prueba para verificar que la API está funcionando correctamente.
 * Este archivo es SOLO PARA DESARROLLO. No incluir en producción.
 * 
 * Las credenciales reales están en archivo .env
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Manejar solicitudes preflight (OPTIONS) del frontend de forma limpia
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// =============================================
// ENRUTADOR
// =============================================
$accion = $_GET['accion'] ?? '';
$data = json_decode(file_get_contents("php://input"), true) ?: $_POST;

switch ($accion) {

    // ==================== REGISTRO ====================
    case 'registro':
    case 'register':
        $nombre_completo = trim($data['nombre_completo'] ?? $data['nombre'] ?? '');
        $email = trim($data['email'] ?? '');
        $username_form = trim($data['username'] ?? ''); // Leer el username del formulario
        $password = $data['password'] ?? '';
        $confirmar = trim($data['confirmar_password'] ?? $data['confirm'] ?? '');

        // 1. Validaciones estrictas y profesionales
        if (empty($nombre_completo) || empty($email) || empty($password) || empty($username_form)) {
            echo json_encode(["error" => "Por favor, complete todos los campos obligatorios."]); exit();
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(["error" => "El formato del correo electrónico es inválido. Ejemplo: usuario@correo.com"]); exit();
        }
        if (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
            echo json_encode(["error" => "Por seguridad, la contraseña debe tener mín. 8 caracteres, al menos una mayúscula y un número."]); exit();
        }
        if (!preg_match('/^[a-zA-Z0-9_]{4,20}$/', $username_form)) {
            echo json_encode(["error" => "El nombre de usuario solo puede contener letras, números y guiones bajos (4-20 caracteres)."]); exit();
        }

        try {
            $db = (new Database())->getConnection();
            
            $check = $db->prepare("SELECT id_usuario FROM usuarios WHERE email = :email");
            $check->bindParam(':email', $email);
            $check->execute();
            if ($check->rowCount() > 0) {
                echo json_encode(["error" => "Este correo ya esta registrado"]); exit();
            }
            
            // Verificar si el username ya existe
            $checkUser = $db->prepare("SELECT id_usuario FROM usuarios WHERE username = :username");
            $checkUser->bindParam(':username', $username_form);
            $checkUser->execute();
            if ($checkUser->rowCount() > 0) {
                echo json_encode(["error" => "Este nombre de usuario ya está en uso. Por favor, elige otro."]); exit();
            }

            $db->beginTransaction();
            
            $nombres = explode(' ', $nombre_completo, 2);
            $nombre = $nombres[0];
            $apellido = $nombres[1] ?? '';

            // 1. Determinar el rol por defecto (4 = Cliente)
            $id_rol = intval($data['id_rol'] ?? 4); 
            $id_empleado = null;

            if ($id_rol == 4) {
                // Si es Cliente, registrar en la tabla clientes
                $stmtC = $db->prepare("INSERT INTO clientes (razon_social, nit_ci, telefono, email) VALUES (:r, '', '', :e)");
                $stmtC->bindValue(':r', $nombre_completo);
                $stmtC->bindValue(':e', $email);
                $stmtC->execute();
            } else {
                // Si es Empleado/Gerente/Admin, registrar en la tabla empleados
                $stmtE = $db->prepare("INSERT INTO empleados (nombre, apellido) VALUES (:n, :a)");
                $stmtE->bindValue(':n', $nombre);
                $stmtE->bindValue(':a', $apellido);
                $stmtE->execute();
                $id_empleado = $db->lastInsertId();
            }
            
            $hash = password_hash($password, PASSWORD_BCRYPT);
            
            // 2. Crear credenciales de usuario 
            $stmt = $db->prepare("INSERT INTO usuarios (id_empleado, id_rol, username, password_hash, email) VALUES (:e, :r, :u, :p, :em)");
            $stmt->bindValue(':e', $id_empleado);
            $stmt->bindValue(':r', $id_rol);
            $stmt->bindValue(':u', $username_form);
            $stmt->bindValue(':p', $hash);
            $stmt->bindValue(':em', $email);
            $stmt->execute();
            $id_usuario = $db->lastInsertId();
            
            $codigo_otp = manejarOTP($db, $id_usuario, $email, 'registro');
            $db->commit();
            
            echo json_encode([
                "exito" => true,
                "mensaje" => "Usuario registrado. Revisa tu correo para el código OTP.",
                "id_usuario" => $id_usuario,
                "username" => $username_form,
                "email" => $email
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            if (isset($db)) $db->rollBack();
            echo json_encode(["error" => $e->getMessage()]);
        }
        break;

    // ==================== ADMIN CREAR USUARIO ====================
    case 'admin_crear_usuario':
        $nombre_completo = trim($data['nombre_completo'] ?? '');
        $email = trim($data['email'] ?? '');
        $username_form = trim($data['username'] ?? '');
        $password = $data['password'] ?? '';
        $rol_nombre = trim($data['rol'] ?? '');

        if (empty($nombre_completo) || empty($email) || empty($password) || empty($username_form) || empty($rol_nombre)) {
            echo json_encode(["error" => "Por favor, complete todos los campos."]); exit();
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(["error" => "El formato del correo electrónico es inválido."]); exit();
        }
        if (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
            echo json_encode(["error" => "La contraseña debe tener mín. 8 caracteres, 1 mayúscula y 1 número."]); exit();
        }
        if (!preg_match('/^[a-zA-Z0-9_]{4,20}$/', $username_form)) {
            echo json_encode(["error" => "El nombre de usuario solo puede contener letras, números y guiones bajos."]); exit();
        }

        try {
            $db = (new Database())->getConnection();
            
            $check = $db->prepare("SELECT id_usuario FROM usuarios WHERE email = :email");
            $check->bindValue(':email', $email);
            $check->execute();
            if ($check->rowCount() > 0) {
                echo json_encode(["error" => "Este correo ya está registrado."]); exit();
            }
            
            $checkUser = $db->prepare("SELECT id_usuario FROM usuarios WHERE username = :username");
            $checkUser->bindValue(':username', $username_form);
            $checkUser->execute();
            if ($checkUser->rowCount() > 0) {
                echo json_encode(["error" => "Este nombre de usuario ya está en uso."]); exit();
            }

            $stmtRol = $db->prepare("SELECT id_rol FROM roles WHERE nombre = :n LIMIT 1");
            $stmtRol->bindValue(':n', $rol_nombre);
            $stmtRol->execute();
            if ($stmtRol->rowCount() === 0) {
                echo json_encode(["error" => "Rol no válido"]); exit();
            }
            $id_rol = $stmtRol->fetch()['id_rol'];

            $db->beginTransaction();
            
            $nombres = explode(' ', $nombre_completo, 2);
            $stmtE = $db->prepare("INSERT INTO empleados (nombre, apellido) VALUES (:n, :a)");
            $stmtE->bindValue(':n', $nombres[0]);
            $stmtE->bindValue(':a', $nombres[1] ?? '');
            $stmtE->execute();
            $id_empleado = $db->lastInsertId();
            
            $hash = password_hash($password, PASSWORD_BCRYPT);
            
            $stmt = $db->prepare("INSERT INTO usuarios (id_empleado, id_rol, username, password_hash, email, email_verificado, estado) VALUES (:e, :r, :u, :p, :em, 1, 'Activo')");
            $stmt->bindValue(':e', $id_empleado);
            $stmt->bindValue(':r', $id_rol);
            $stmt->bindValue(':u', $username_form);
            $stmt->bindValue(':p', $hash);
            $stmt->bindValue(':em', $email);
            $stmt->execute();
            
            $db->commit();
            
            echo json_encode(["exito" => true, "mensaje" => "Usuario {$rol_nombre} creado y verificado exitosamente."], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            if (isset($db)) $db->rollBack();
            echo json_encode(["error" => $e->getMessage()]);
        }
        break;

    // ==================== LOGIN ====================
    case 'login':
        $username = trim($data['username'] ?? '');
        $password = $data['password'] ?? '';

        if (empty($username) || empty($password)) {
            echo json_encode(["error" => "Por favor, ingrese sus credenciales."]); exit();
        }

        try {
            $db = (new Database())->getConnection();
            
            $stmt = $db->prepare(
                "SELECT u.*, r.nombre as rol, " .
                "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', e.nombre, e.apellido)), ''), c.razon_social, u.username) AS nombre_completo " .
                "FROM usuarios u " .
                "JOIN roles r ON u.id_rol = r.id_rol " .
                "LEFT JOIN empleados e ON u.id_empleado = e.id_empleado " .
                "LEFT JOIN clientes c ON u.email = c.email " .
                "WHERE (u.username = :u1 OR u.email = :u2)"
            );
            $stmt->bindValue(':u1', $username);
            $stmt->bindValue(':u2', $username);
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                echo json_encode(["error" => "❌ Las credenciales proporcionadas no existen en nuestros registros."]); exit();
            }
            
            $user = $stmt->fetch();
            
            if ($user['estado'] !== 'Activo') {
                echo json_encode(["error" => "⚠️ Su cuenta se encuentra desactivada. Comuníquese con el administrador."]); exit();
            }
            
            if ($user['intentos_fallidos'] >= 5) {
                echo json_encode(["error" => "⛔ CUENTA BLOQUEADA por seguridad debido a múltiples intentos fallidos."]); exit();
            }

            if (!password_verify($password, $user['password_hash'])) {
                $upd = $db->prepare("UPDATE usuarios SET intentos_fallidos = intentos_fallidos + 1 WHERE id_usuario = :id");
                $upd->bindValue(':id', $user['id_usuario']);
                $upd->execute();
                
                $intentosRestantes = 4 - $user['intentos_fallidos'];
                if ($intentosRestantes <= 0) {
                    echo json_encode(["error" => "⛔ Cuenta bloqueada. Ha superado el límite de 5 intentos fallidos."]); exit();
                } else {
                    echo json_encode(["error" => "❌ Contraseña incorrecta. Le quedan $intentosRestantes intentos antes del bloqueo."]); exit();
                }
            }
            
            // Si todo está bien, resetear intentos a 0
            $upd = $db->prepare("UPDATE usuarios SET intentos_fallidos = 0, ultimo_acceso = NOW() WHERE id_usuario = :id");
            $upd->bindValue(':id', $user['id_usuario']);
            $upd->execute();

            $token = bin2hex(random_bytes(32));
            $idEmp = $user['id_empleado'];
            $datos = [
                "id_usuario" => (int) $user['id_usuario'],
                "id_empleado" => $idEmp !== null && $idEmp !== '' ? (int) $idEmp : null,
                "username" => $user['username'],
                "nombre" => $user['nombre_completo'],
                "rol" => $user['rol'],
                "email" => $user['email'],
                "token" => $token,
            ];

            echo json_encode([
                "exito" => true,
                "mensaje" => "Inicio de sesion exitoso",
                "datos" => $datos,
                "data" => $datos,
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            echo json_encode(["error" => $e->getMessage()]);
        }
        break;

    // ==================== VERIFICAR OTP ====================
    case 'verify_otp':
    case 'verificar_otp':
        $codigo = trim($data['codigo_otp'] ?? $data['codigo'] ?? '');
        $id = intval($data['id_usuario'] ?? 0);

        try {
            $db = (new Database())->getConnection();

            if ($id <= 0 && !empty($data['email'])) {
                $em = trim($data['email']);
                $lu = $db->prepare("SELECT id_usuario FROM usuarios WHERE email = :e LIMIT 1");
                $lu->bindValue(':e', $em);
                $lu->execute();
                $row = $lu->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $id = (int) $row['id_usuario'];
                }
            }

            if ($id <= 0) {
                echo json_encode(["error" => "No se ha podido identificar la cuenta a verificar."]); exit();
            }
            
            if (!preg_match('/^[0-9]{6}$/', $codigo)) {
                echo json_encode(["error" => "Formato inválido: El código debe contener exactamente 6 dígitos numéricos."]); exit();
            }

            $stmt = $db->prepare("SELECT id_otp, codigo FROM codigos_otp WHERE id_usuario = :id AND activo = 1 AND expira_en > NOW() ORDER BY creado_en DESC LIMIT 1");
            $stmt->bindValue(':id', $id);
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                echo json_encode(["error" => "El código ha expirado o ya fue utilizado. Por favor, solicita uno nuevo."]); exit();
            }
            
            $otp = $stmt->fetch();
            
            if ($otp['codigo'] !== $codigo) {
                echo json_encode(["error" => "❌ Código OTP incorrecto. Verifica los números enviados a tu correo e intenta nuevamente."]); exit();
            }
            
            $db->beginTransaction();
            // Invalidar el OTP usado
            $stmt = $db->prepare("UPDATE codigos_otp SET activo = 0 WHERE id_otp = :id");
            $stmt->bindValue(':id', $otp['id_otp']);
            $stmt->execute();
            
            // Marcar el email del usuario como verificado y resetear bloqueos
            $stmt = $db->prepare("UPDATE usuarios SET email_verificado = 1, intentos_fallidos = 0 WHERE id_usuario = :id");
            $stmt->bindValue(':id', $id);
            $stmt->execute();
            $db->commit();
            
            echo json_encode(["exito" => true, "mensaje" => "¡Cuenta verificada exitosamente!"]);
        } catch (Exception $e) {
            if (isset($db)) $db->rollBack();
            echo json_encode(["error" => $e->getMessage()]);
        }
        break;

    // ==================== REENVIAR OTP ====================
    case 'resend_otp':
    case 'reenviar_otp':
        $email = trim($data['email'] ?? '');
        $id = intval($data['id_usuario'] ?? 0);

        try {
            $db = (new Database())->getConnection();

            if ($id <= 0 && $email !== '') {
                $lu = $db->prepare("SELECT id_usuario FROM usuarios WHERE email = :e LIMIT 1");
                $lu->bindParam(':e', $email);
                $lu->execute();
                $row = $lu->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $id = (int) $row['id_usuario'];
                }
            }

            if ($id <= 0 || empty($email)) {
                echo json_encode(["error" => "Email o usuario no encontrado para reenviar OTP"]); exit();
            }

            $db->beginTransaction();
            // Usar la función centralizada para reenviar el OTP
            $codigo_otp = manejarOTP($db, $id, $email, 'registro');
            $db->commit();

            echo json_encode([
                "exito" => true,
                "mensaje" => "Nuevo código enviado a {$email}"
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            if (isset($db)) $db->rollBack();
            echo json_encode(["error" => $e->getMessage()]);
        }
        break;

    // ==================== RECUPERAR CONTRASEÑA ====================
    case 'recuperar_password':
        $email = trim($data['email'] ?? '');

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(["error" => "Por favor, ingrese un correo electrónico válido."]); exit();
        }

        try {
            $db = (new Database())->getConnection();
            
            $query = "SELECT id_usuario FROM usuarios WHERE email = :email";
            $stmt = $db->prepare($query);
            $stmt->bindValue(':email', $email);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $usuario = $stmt->fetch();
                $db->beginTransaction();
                // Reutilizamos la función mágica que ya teníamos para crear y enviar OTPs
                manejarOTP($db, $usuario['id_usuario'], $email, 'recuperacion');
                $db->commit();
            }

            echo json_encode([
                "exito" => true,
                "mensaje" => "Si el email está registrado, recibirás un código de recuperación."
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            if (isset($db)) $db->rollBack();
            echo json_encode(["error" => "Error al enviar el código de recuperación."]);
        }
        break;

    // ==================== RESTABLECER CONTRASEÑA ====================
    case 'restablecer_password':
        $email = trim($data['email'] ?? '');
        $codigo = trim($data['codigo'] ?? '');
        $password = $data['password'] ?? '';

        if (empty($email) || empty($codigo) || empty($password)) {
            echo json_encode(["error" => "Por favor, complete todos los campos."]); exit();
        }

        if (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
            echo json_encode(["error" => "Por seguridad, la contraseña debe tener mín. 8 caracteres, al menos una mayúscula y un número."]); exit();
        }

        try {
            $db = (new Database())->getConnection();
            
            $stmt = $db->prepare("SELECT id_usuario FROM usuarios WHERE email = :e LIMIT 1");
            $stmt->bindValue(':e', $email);
            $stmt->execute();
            if ($stmt->rowCount() === 0) {
                echo json_encode(["error" => "No se encontró el usuario asociado al correo."]); exit();
            }
            $usuario = $stmt->fetch();
            $id_usuario = $usuario['id_usuario'];

            $stmt = $db->prepare("SELECT id_otp, codigo FROM codigos_otp WHERE id_usuario = :id AND activo = 1 AND expira_en > NOW() AND tipo = 'recuperacion' ORDER BY creado_en DESC LIMIT 1");
            $stmt->bindValue(':id', $id_usuario);
            $stmt->execute();

            if ($stmt->rowCount() === 0) {
                echo json_encode(["error" => "El código ha expirado o ya fue utilizado. Por favor, solicita uno nuevo."]); exit();
            }

            $otp = $stmt->fetch();

            if ($otp['codigo'] !== $codigo) {
                echo json_encode(["error" => "❌ Código OTP incorrecto."]); exit();
            }

            $hash = password_hash($password, PASSWORD_BCRYPT);

            $db->beginTransaction();
            $upd = $db->prepare("UPDATE usuarios SET password_hash = :p, intentos_fallidos = 0 WHERE id_usuario = :id");
            $upd->bindValue(':p', $hash);
            $upd->bindValue(':id', $id_usuario);
            $upd->execute();

            $stmt = $db->prepare("UPDATE codigos_otp SET activo = 0 WHERE id_otp = :id");
            $stmt->bindValue(':id', $otp['id_otp']);
            $stmt->execute();

            $db->commit();

            echo json_encode(["exito" => true, "mensaje" => "Contraseña actualizada exitosamente."]);
        } catch (Exception $e) {
            if (isset($db)) $db->rollBack();
            echo json_encode(["error" => "Error al restablecer la contraseña."]);
        }
        break;

    // ==================== CREAR PRODUCTO ====================
    case 'create_producto':
        $codigo = trim($data['codigo'] ?? '');
        $nombre = trim($data['nombre'] ?? '');
        $descripcion = trim($data['descripcion'] ?? '');
        $precio = floatval($data['precio_referencia'] ?? 0);
        $imagen = trim($data['imagen_principal'] ?? '');
        $id_categoria = intval($data['id_categoria'] ?? 0);

        if (empty($codigo) || empty($nombre) || $precio <= 0) {
            echo json_encode(["error" => "Codigo, nombre y precio son requeridos"]); exit();
        }

        try {
            $db = (new Database())->getConnection();
            
            $check = $db->prepare("SELECT id_producto FROM productos WHERE codigo = :c");
            $check->bindParam(':c', $codigo);
            $check->execute();
            if ($check->rowCount() > 0) {
                echo json_encode(["error" => "El codigo ya existe"]); exit();
            }

            $stmt = $db->prepare("INSERT INTO productos (codigo, nombre, descripcion, id_categoria, precio_referencia, imagen_principal) VALUES (:cod, :nom, :des, :cat, :pre, :img)");
            $stmt->bindParam(':cod', $codigo);
            $stmt->bindParam(':nom', $nombre);
            $stmt->bindParam(':des', $descripcion);
            $stmt->bindParam(':cat', $id_categoria);
            $stmt->bindParam(':pre', $precio);
            $stmt->bindParam(':img', $imagen);
            $stmt->execute();
            
            echo json_encode([
                "exito" => true,
                "mensaje" => "Producto creado",
                "id_producto" => $db->lastInsertId()
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            echo json_encode(["error" => $e->getMessage()]);
        }
        break;

    // ==================== LISTAR PRODUCTOS ====================
    case 'listar_productos':
        try {
            $db = (new Database())->getConnection();
            $stmt = $db->prepare("SELECT p.*, c.nombre as categoria FROM productos p LEFT JOIN categorias c ON p.id_categoria = c.id_categoria WHERE p.estado = 'Activo' ORDER BY p.nombre");
            $stmt->execute();
            
            echo json_encode([
                "exito" => true,
                "total" => $stmt->rowCount(),
                "productos" => $stmt->fetchAll()
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            echo json_encode(["error" => $e->getMessage()]);
        }
        break;

    // ==================== CREAR CATEGORÍA ====================
    case 'create_categoria':
        $nombre = trim($data['nombre'] ?? '');
        $descripcion = trim($data['descripcion'] ?? '');

        if (empty($nombre)) {
            echo json_encode(["error" => "Nombre requerido"]); exit();
        }

        try {
            $db = (new Database())->getConnection();
            $stmt = $db->prepare("INSERT INTO categorias (nombre, descripcion) VALUES (:n, :d)");
            $stmt->bindParam(':n', $nombre);
            $stmt->bindParam(':d', $descripcion);
            $stmt->execute();
            
            echo json_encode([
                "exito" => true,
                "mensaje" => "Categoria creada",
                "id_categoria" => $db->lastInsertId()
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            echo json_encode(["error" => $e->getMessage()]);
        }
        break;

    // ==================== LISTAR CATEGORÍAS ====================
    case 'listar_categorias':
        try {
            $db = (new Database())->getConnection();
            $stmt = $db->prepare("SELECT c.*, (SELECT COUNT(*) FROM productos WHERE id_categoria = c.id_categoria AND estado = 'Activo') as total_productos FROM categorias c ORDER BY c.nombre");
            $stmt->execute();
            
            echo json_encode([
                "exito" => true,
                "categorias" => $stmt->fetchAll()
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            echo json_encode(["error" => $e->getMessage()]);
        }
        break;

    // ==================== VER INVENTARIO ====================
    case 'inventario':
        try {
            $db = (new Database())->getConnection();
            $stmt = $db->prepare("SELECT i.*, p.codigo, p.nombre as producto, a.nombre as almacen, CASE WHEN i.cantidad_actual <= i.stock_minimo AND i.stock_minimo > 0 THEN 'CRITICO' WHEN i.cantidad_actual = 0 THEN 'AGOTADO' WHEN i.cantidad_actual <= (i.stock_minimo * 1.3) THEN 'ALERTA' ELSE 'NORMAL' END as estado FROM inventario i JOIN productos p ON i.id_producto = p.id_producto JOIN almacenes a ON i.id_almacen = a.id_almacen ORDER BY i.cantidad_actual ASC");
            $stmt->execute();
            
            echo json_encode([
                "exito" => true,
                "total" => $stmt->rowCount(),
                "inventario" => $stmt->fetchAll()
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            echo json_encode(["error" => $e->getMessage()]);
        }
        break;

    // ==================== REGISTRAR MOVIMIENTO ====================
    case 'registrar_movimiento':
        $producto_id = intval($data['producto_id'] ?? 0);
        $cantidad = intval($data['cantidad'] ?? 0);
        $tipo = trim($data['tipo'] ?? '');
        $id_almacen = intval($data['almacen'] ?? 0);
        $observaciones = trim($data['observaciones'] ?? '');

        if ($producto_id <= 0 || $cantidad <= 0 || empty($tipo) || $id_almacen <= 0) {
            echo json_encode(["error" => "Por favor, complete todos los datos del movimiento."]); exit();
        }

        try {
            $db = (new Database())->getConnection();
            $db->beginTransaction();

            // Obtener el inventario actual
            $stmtInv = $db->prepare("SELECT id_inventario, cantidad_actual FROM inventario WHERE id_producto = :p AND id_almacen = :a LIMIT 1");
            $stmtInv->bindValue(':p', $producto_id);
            $stmtInv->bindValue(':a', $id_almacen);
            $stmtInv->execute();

            if ($stmtInv->rowCount() > 0) {
                $inv = $stmtInv->fetch();
                $nuevo_stock = $tipo === 'entrada' ? $inv['cantidad_actual'] + $cantidad : $inv['cantidad_actual'] - $cantidad;
                
                if ($nuevo_stock < 0) {
                    echo json_encode(["error" => "Stock insuficiente en el almacén para realizar esta salida."]); exit();
                }

                $upd = $db->prepare("UPDATE inventario SET cantidad_actual = :c WHERE id_inventario = :id");
                $upd->bindValue(':c', $nuevo_stock);
                $upd->bindValue(':id', $inv['id_inventario']);
                $upd->execute();
            } else {
                if ($tipo === 'salida' || $tipo === 'traslado') {
                    echo json_encode(["error" => "No hay stock registrado para este producto en el almacén seleccionado."]); exit();
                }
                // Si es un producto nuevo en ese almacén y entra stock, creamos el registro
                $ins = $db->prepare("INSERT INTO inventario (id_producto, id_almacen, cantidad_actual, stock_minimo, stock_maximo) VALUES (:p, :a, :c, 10, 1000)");
                $ins->bindValue(':p', $producto_id);
                $ins->bindValue(':a', $id_almacen);
                $ins->bindValue(':c', $cantidad);
                $ins->execute();
            }

            $db->commit();
            echo json_encode(["exito" => true, "mensaje" => "Movimiento de stock registrado exitosamente."]);
        } catch (Exception $e) {
            if (isset($db)) $db->rollBack();
            echo json_encode(["error" => "Error interno: " . $e->getMessage()]);
        }
        break;

    // ==================== LISTAR MOVIMIENTOS ====================
    case 'listar_movimientos':
        try {
            $db = (new Database())->getConnection();
            $stmt = $db->prepare(
                "SELECT ms.*, p.codigo as producto_codigo, p.nombre as producto_nombre,
                        ao.nombre as almacen_origen_nombre, ad.nombre as almacen_destino_nombre,
                        u.username as responsable_username
                 FROM movimientos_stock ms
                 JOIN productos p ON ms.id_producto = p.id_producto
                 LEFT JOIN almacenes ao ON ms.id_almacen_origen = ao.id_almacen
                 LEFT JOIN almacenes ad ON ms.id_almacen_destino = ad.id_almacen
                 LEFT JOIN usuarios u ON ms.id_usuario_responsable = u.id_usuario
                 ORDER BY ms.fecha_hora DESC LIMIT 5000"
            );
            $stmt->execute();
            echo json_encode(["exito" => true, "movimientos" => $stmt->fetchAll()], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            echo json_encode(["error" => $e->getMessage()]);
        }
        break;

    // ==================== LISTAR ALMACENES ====================
    case 'listar_almacenes':
        try {
            $db = (new Database())->getConnection();
            $stmt = $db->prepare("SELECT id_almacen, nombre FROM almacenes ORDER BY nombre");
            $stmt->execute();
            echo json_encode(["exito" => true, "almacenes" => $stmt->fetchAll()], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            echo json_encode(["error" => $e->getMessage()]);
        }
        break;

    // ==================== CREAR CLIENTE ====================
    case 'create_cliente':
        $razon_social = trim($data['razon_social'] ?? '');
        $nit_ci = trim($data['nit_ci'] ?? '');
        $telefono = trim($data['telefono'] ?? '');
        $email = trim($data['email'] ?? '');

        if (empty($razon_social)) {
            echo json_encode(["error" => "Razon social requerida"]); exit();
        }

        try {
            $db = (new Database())->getConnection();
            $stmt = $db->prepare("INSERT INTO clientes (razon_social, nit_ci, telefono, email) VALUES (:r, :n, :t, :e)");
            $stmt->bindParam(':r', $razon_social);
            $stmt->bindParam(':n', $nit_ci);
            $stmt->bindParam(':t', $telefono);
            $stmt->bindParam(':e', $email);
            $stmt->execute();
            
            echo json_encode([
                "exito" => true,
                "mensaje" => "Cliente creado",
                "id_cliente" => $db->lastInsertId()
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            echo json_encode(["error" => $e->getMessage()]);
        }
        break;

    // ==================== LISTAR CLIENTES ====================
    case 'listar_clientes':
        try {
            $db = (new Database())->getConnection();
            $stmt = $db->prepare("SELECT * FROM clientes WHERE estado = 'Activo' ORDER BY razon_social");
            $stmt->execute();
            
            echo json_encode([
                "exito" => true,
                "clientes" => $stmt->fetchAll()
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            echo json_encode(["error" => $e->getMessage()]);
        }
        break;

    // ==================== LISTAR USUARIOS ====================
    case 'listar_usuarios':
        try {
            $db = (new Database())->getConnection();
            $stmt = $db->prepare(
                "SELECT u.id_usuario as id, u.username, u.email, u.estado, 
                        u.ultimo_acceso, u.email_verificado as verificado,
                        r.nombre as rol, 
                        COALESCE(NULLIF(TRIM(CONCAT_WS(' ', e.nombre, e.apellido)), ''), c.razon_social, u.username) AS nombre 
                 FROM usuarios u 
                 JOIN roles r ON u.id_rol = r.id_rol 
                 LEFT JOIN empleados e ON u.id_empleado = e.id_empleado 
                 LEFT JOIN clientes c ON u.email = c.email 
                 ORDER BY u.id_usuario DESC"
            );
            $stmt->execute();
            
            $usuarios = array_map(function($user) {
                $user['verificado'] = (bool)$user['verificado'];
                return $user;
            }, $stmt->fetchAll());
            
            echo json_encode([
                "exito" => true,
                "usuarios" => $usuarios
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            echo json_encode(["error" => $e->getMessage()]);
        }
        break;

    // ==================== CAMBIAR ROL ====================
    case 'cambiar_rol':
        $id = intval($data['id'] ?? 0);
        $rol_nombre = trim($data['rol'] ?? '');

        if ($id <= 0 || empty($rol_nombre)) {
            echo json_encode(["error" => "ID y rol son requeridos"]); exit();
        }

        try {
            $db = (new Database())->getConnection();
            $stmt = $db->prepare("SELECT id_rol FROM roles WHERE nombre = :n LIMIT 1");
            $stmt->bindValue(':n', $rol_nombre);
            $stmt->execute();
            if ($stmt->rowCount() === 0) {
                echo json_encode(["error" => "Rol no válido"]); exit();
            }
            $id_rol = $stmt->fetch()['id_rol'];

            $upd = $db->prepare("UPDATE usuarios SET id_rol = :r WHERE id_usuario = :id");
            $upd->bindValue(':r', $id_rol);
            $upd->bindValue(':id', $id);
            $upd->execute();

            echo json_encode(["exito" => true, "mensaje" => "Rol actualizado correctamente"]);
        } catch (Exception $e) {
            echo json_encode(["error" => $e->getMessage()]);
        }
        break;

    // ==================== CAMBIAR ESTADO USUARIO ====================
    case 'cambiar_estado_usuario':
        $id = intval($data['id'] ?? 0);
        $estado = trim($data['estado'] ?? '');

        if ($id <= 0 || !in_array($estado, ['Activo', 'Inactivo'])) {
            echo json_encode(["error" => "ID y estado (Activo/Inactivo) son requeridos"]); exit();
        }

        try {
            $db = (new Database())->getConnection();
            $upd = $db->prepare("UPDATE usuarios SET estado = :e WHERE id_usuario = :id");
            $upd->bindValue(':e', $estado);
            $upd->bindValue(':id', $id);
            $upd->execute();

            echo json_encode(["exito" => true, "mensaje" => "Estado actualizado correctamente"]);
        } catch (Exception $e) {
            echo json_encode(["error" => $e->getMessage()]);
        }
        break;

    // ==================== LISTAR LOGS (BITÁCORA) ====================
    case 'listar_logs':
        try {
            $db = (new Database())->getConnection();
            // Obtenemos los últimos 1000 accesos ordenados por el más reciente
            $stmt = $db->prepare("SELECT * FROM log_accesos ORDER BY id_log DESC LIMIT 1000");
            $stmt->execute();
            
            $logs = array_map(function($l) {
                return [
                    'id' => $l['id_log'] ?? $l['id'] ?? 0,
                    'usuario' => $l['username'] ?? 'Desconocido',
                    'ip' => $l['ip_address'] ?? $l['ip'] ?? '0.0.0.0',
                    'resultado' => (isset($l['exito']) && $l['exito'] == 1) ? 'Éxito' : 'Fallido',
                    'fecha' => $l['fecha'] ?? $l['creado_en'] ?? $l['fecha_acceso'] ?? $l['timestamp'] ?? date('Y-m-d H:i:s')
                ];
            }, $stmt->fetchAll());
            
            echo json_encode(["exito" => true, "logs" => $logs], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            echo json_encode(["error" => $e->getMessage()]);
        }
        break;

    // ==================== LISTAR PEDIDOS ====================
    case 'listar_pedidos':
        try {
            $db = (new Database())->getConnection();
            // Usamos LEFT JOIN para que no se oculte ningún pedido aunque falte algún dato
            $stmt = $db->prepare(
                "SELECT p.id_pedido as id, p.codigo_pedido as codigo, p.id_cliente, 
                        COALESCE(c.razon_social, 'Cliente Eliminado/Desconocido') as cliente,
                        p.fecha_pedido as fecha, p.estado, p.id_usuario,
                        COALESCE(NULLIF(TRIM(CONCAT_WS(' ', e.nombre, e.apellido)), ''), us.username, 'Sistema') as creado_por,
                        COALESCE((SELECT SUM(cantidad * precio_unitario) FROM detalle_pedido WHERE id_pedido = p.id_pedido), 0) as total
                 FROM pedidos p
                 LEFT JOIN clientes c ON p.id_cliente = c.id_cliente
                 LEFT JOIN usuarios us ON p.id_usuario = us.id_usuario
                 LEFT JOIN empleados e ON us.id_empleado = e.id_empleado
                 ORDER BY p.fecha_pedido DESC LIMIT 1000"
            );
            $stmt->execute();
            echo json_encode(["exito" => true, "pedidos" => $stmt->fetchAll()], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            echo json_encode(["error" => $e->getMessage()]);
        }
        break;

    // ==================== DASHBOARD ====================
    case 'dashboard':
        try {
            $db = (new Database())->getConnection();
            
            // Totales
            $productos = $db->query("SELECT COUNT(*) as t FROM productos WHERE estado = 'Activo'")->fetch()['t'];
            $clientes = $db->query("SELECT COUNT(*) as t FROM clientes WHERE estado = 'Activo'")->fetch()['t'];
            $proveedores = $db->query("SELECT COUNT(*) as t FROM proveedores WHERE estado = 'Activo'")->fetch()['t'];
            $usuarios = $db->query("SELECT COUNT(*) as t FROM usuarios WHERE estado = 'Activo'")->fetch()['t'];
            
            // Alertas
            $criticos = $db->query("SELECT COUNT(*) as t FROM inventario WHERE cantidad_actual <= stock_minimo AND stock_minimo > 0")->fetch()['t'];
            $agotados = $db->query("SELECT COUNT(*) as t FROM inventario WHERE cantidad_actual = 0")->fetch()['t'];
            
            // Pedidos pendientes
            $pedidos = $db->query("SELECT COUNT(*) as t FROM pedidos WHERE estado IN ('Pendiente', 'Confirmado')")->fetch()['t'];
            
            echo json_encode([
                "exito" => true,
                "dashboard" => [
                    "productos_activos" => intval($productos),
                    "clientes_activos" => intval($clientes),
                    "proveedores_activos" => intval($proveedores),
                    "usuarios_activos" => intval($usuarios),
                    "productos_criticos" => intval($criticos),
                    "productos_agotados" => intval($agotados),
                    "pedidos_pendientes" => intval($pedidos)
                ]
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            echo json_encode(["error" => $e->getMessage()]);
        }
        break;

    // ==================== CREAR ALMACÉN ====================
    case 'create_almacen':
        $nombre = trim($data['nombre'] ?? '');
        $direccion = trim($data['direccion'] ?? '');

        if (empty($nombre)) {
            echo json_encode(["error" => "Nombre requerido"]); exit();
        }

        try {
            $db = (new Database())->getConnection();
            $stmt = $db->prepare("INSERT INTO almacenes (nombre, direccion) VALUES (:n, :d)");
            $stmt->bindParam(':n', $nombre);
            $stmt->bindParam(':d', $direccion);
            $stmt->execute();
            
            echo json_encode([
                "exito" => true,
                "mensaje" => "Almacen creado",
                "id_almacen" => $db->lastInsertId()
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            echo json_encode(["error" => $e->getMessage()]);
        }
        break;

    // ==================== LOGOUT ====================
    case 'logout':
        echo json_encode([
            "exito" => true,
            "mensaje" => "Sesión cerrada correctamente"
        ], JSON_UNESCAPED_UNICODE);
        break;

    default:
        echo json_encode([
            "error" => "Accion no especificada",
            "acciones_disponibles" => [
                "register",
                "registro",
                "login",
                "verify_otp",
                "verificar_otp",
                "resend_otp",
                "reenviar_otp",
                "create_producto",
                "listar_productos",
                "create_categoria",
                "listar_categorias",
                "inventario",
                "create_cliente",
                "listar_clientes",
                "create_almacen",
                "listar_usuarios",
                "cambiar_rol",
                "cambiar_estado_usuario",
                "dashboard"
            ]
        ], JSON_UNESCAPED_UNICODE);
        break;
}