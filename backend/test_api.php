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
        $password = $data['password'] ?? '';
        $confirmar = trim($data['confirmar_password'] ?? $data['confirm'] ?? '');
        $username_manual = trim($data['username'] ?? '');

        if (empty($nombre_completo) || empty($email) || empty($password)) {
            echo json_encode(["error" => "Todos los campos son requeridos"]); exit();
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(["error" => "Email no valido"]); exit();
        }
        if ($password !== $confirmar) {
            echo json_encode(["error" => "Las contraseñas no coinciden"]); exit();
        }
        if (strlen($password) < 8) {
            echo json_encode(["error" => "Minimo 8 caracteres"]); exit();
        }

        try {
            $db = (new Database())->getConnection();
            
            $check = $db->prepare("SELECT id_usuario FROM usuarios WHERE email = :email");
            $check->bindParam(':email', $email);
            $check->execute();
            if ($check->rowCount() > 0) {
                echo json_encode(["error" => "Este correo ya esta registrado"]); exit();
            }

            $db->beginTransaction();
            
            $nombres = explode(' ', $nombre_completo, 2);
            $nombre = $nombres[0];
            $apellido = $nombres[1] ?? '';
            
            $stmt = $db->prepare("INSERT INTO empleados (nombre, apellido) VALUES (:n, :a)");
            $stmt->bindParam(':n', $nombre);
            $stmt->bindParam(':a', $apellido);
            $stmt->execute();
            $id_empleado = $db->lastInsertId();
            
            $hash = password_hash($password, PASSWORD_BCRYPT);
            if ($username_manual !== '') {
                $username = strtolower(preg_replace('/[^a-z0-9._-]/i', '', $username_manual));
                if ($username === '') {
                    $username = strtolower(substr($nombre, 0, 1) . str_replace(' ', '', $apellido));
                }
            } else {
                $username = strtolower(substr($nombre, 0, 1) . str_replace(' ', '', $apellido));
            }

            $uq = $db->prepare("SELECT id_usuario FROM usuarios WHERE username = :u LIMIT 1");
            $uq->bindParam(':u', $username);
            $uq->execute();
            if ($uq->rowCount() > 0) {
                $username = $username . '_' . substr((string) microtime(true), -4);
            }
            
            $stmt = $db->prepare("INSERT INTO usuarios (id_empleado, id_rol, username, password_hash, email) VALUES (:e, 3, :u, :p, :em)");
            $stmt->bindParam(':e', $id_empleado);
            $stmt->bindParam(':u', $username);
            $stmt->bindParam(':p', $hash);
            $stmt->bindParam(':em', $email);
            $stmt->execute();
            $id_usuario = $db->lastInsertId();
            
            $codigo = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $expira = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            
            $stmt = $db->prepare("INSERT INTO codigos_otp (id_usuario, codigo, tipo, expira_en) VALUES (:id, :cod, 'registro', :exp)");
            $stmt->bindParam(':id', $id_usuario);
            $stmt->bindParam(':cod', $codigo);
            $stmt->bindParam(':exp', $expira);
            $stmt->execute();
            
            $db->commit();
            
            $correo_ok = enviarOTP($email, $codigo);
            
            $payload = [
                "exito" => true,
                "mensaje" => $correo_ok
                    ? "Usuario registrado. Revisa tu correo para el código OTP."
                    : "Usuario registrado. No se pudo enviar el correo (revisa MAIL_* en .env). Usa el código mostrado abajo o el log del servidor.",
                "id_usuario" => $id_usuario,
                "username" => $username,
                "codigo_otp" => $codigo,
                "email_enviado" => $correo_ok,
            ];
            echo json_encode($payload, JSON_UNESCAPED_UNICODE);
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
            echo json_encode(["error" => "Usuario y contraseña requeridos"]); exit();
        }

        try {
            $db = (new Database())->getConnection();
            
            $stmt = $db->prepare(
                "SELECT u.*, r.nombre as rol, " .
                "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', e.nombre, e.apellido)), ''), u.username) AS nombre_completo " .
                "FROM usuarios u " .
                "JOIN roles r ON u.id_rol = r.id_rol " .
                "LEFT JOIN empleados e ON u.id_empleado = e.id_empleado " .
                "WHERE u.username = :u"
            );
            $stmt->bindParam(':u', $username);
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                echo json_encode(["error" => "Usuario o contraseña incorrectos"]); exit();
            }
            
            $user = $stmt->fetch();
            
            if (!password_verify($password, $user['password_hash'])) {
                echo json_encode(["error" => "Usuario o contraseña incorrectos"]); exit();
            }
            
            if ($user['estado'] !== 'Activo') {
                echo json_encode(["error" => "Cuenta desactivada"]); exit();
            }

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
                $lu->bindParam(':e', $em);
                $lu->execute();
                $row = $lu->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $id = (int) $row['id_usuario'];
                }
            }

            if ($id <= 0 || strlen($codigo) !== 6) {
                echo json_encode(["error" => "Datos invalidos (email o codigo)"]); exit();
            }

            $stmt = $db->prepare("SELECT id_otp, codigo FROM codigos_otp WHERE id_usuario = :id AND activo = 1 AND expira_en > NOW() ORDER BY creado_en DESC LIMIT 1");
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                echo json_encode(["error" => "Codigo expirado"]); exit();
            }
            
            $otp = $stmt->fetch();
            
            if ($otp['codigo'] !== $codigo) {
                echo json_encode(["error" => "Codigo incorrecto"]); exit();
            }
            
            $db->beginTransaction();
            $stmt = $db->prepare("UPDATE codigos_otp SET activo = 0 WHERE id_otp = :id");
            $stmt->bindParam(':id', $otp['id_otp']);
            $stmt->execute();
            
            $stmt = $db->prepare("UPDATE usuarios SET email_verificado = 1 WHERE id_usuario = :id");
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            $db->commit();
            
            echo json_encode(["exito" => true, "mensaje" => "Cuenta verificada"]);
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
            
            $stmt = $db->prepare("UPDATE codigos_otp SET activo = 0 WHERE id_usuario = :id AND tipo = 'registro'");
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            $codigo = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $expira = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            
            $stmt = $db->prepare("INSERT INTO codigos_otp (id_usuario, codigo, tipo, expira_en) VALUES (:id, :cod, 'registro', :exp)");
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':cod', $codigo);
            $stmt->bindParam(':exp', $expira);
            $stmt->execute();
            
            $db->commit();
            enviarOTP($email, $codigo);
            
            echo json_encode([
                "exito" => true,
                "mensaje" => "Nuevo codigo enviado a {$email}",
                "codigo_otp" => $codigo
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            if (isset($db)) $db->rollBack();
            echo json_encode(["error" => $e->getMessage()]);
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
                "dashboard"
            ]
        ], JSON_UNESCAPED_UNICODE);
        break;
}