<?php
/**
 * api.php
 *
 * Archivo principal (Enrutador) de la API del sistema.
 *
 * Las credenciales reales están en archivo .env
 */

// Habilitar el reporte de errores para ver problemas ocultos de MySQL
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Token');

// Manejar solicitudes preflight (OPTIONS) del frontend de forma limpia
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/auditoria.php';

// =============================================
// ENRUTADOR
// =============================================
$accion = $_GET['accion'] ?? '';
$data = json_decode(file_get_contents("php://input"), true) ?: $_POST;

function requireApiAuth($roles_permitidos = []) {
    verificarAutenticacion();

    $headerToken = $_SERVER['HTTP_X_TOKEN'] ?? '';
    if ($headerToken !== '' && isset($_SESSION['api_token']) && !hash_equals($_SESSION['api_token'], $headerToken)) {
        responderJSON(["error" => "Token de sesión inválido"], 401);
    }

    if (!empty($roles_permitidos)) {
        verificarRol($roles_permitidos);
    }
}

function columnaExiste(PDO $db, $tabla, $columna) {
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $tabla) || !preg_match('/^[a-zA-Z0-9_]+$/', $columna)) {
        return false;
    }

    try {
        $stmt = $db->prepare("SHOW COLUMNS FROM `{$tabla}` LIKE :columna");
        $stmt->bindValue(':columna', $columna);
        $stmt->execute();
        return (bool) $stmt->fetch();
    } catch (Throwable $e) {
        return false;
    }
}

function normalizarImagenProducto($url) {
    $url = trim((string) $url);
    if ($url === '') return '';

    if (preg_match('/^(https?:\/\/|\/|\.{1,2}\/)/i', $url) !== 1) {
        return '';
    }

    return filter_var($url, FILTER_SANITIZE_URL);
}

function resolverCategoriaProducto(PDO $db, $categoria) {
    if (is_numeric($categoria) && intval($categoria) > 0) {
        return intval($categoria);
    }

    $nombre = sanitizar_input((string) $categoria);
    if ($nombre === '') return 0;

    $stmt = $db->prepare("SELECT id_categoria FROM categorias WHERE nombre = :nombre LIMIT 1");
    $stmt->bindValue(':nombre', $nombre);
    $stmt->execute();
    $id = $stmt->fetchColumn();
    if ($id) return (int) $id;

    $insert = $db->prepare("INSERT INTO categorias (nombre, descripcion) VALUES (:nombre, NULL)");
    $insert->bindValue(':nombre', $nombre);
    $insert->execute();

    return (int) $db->lastInsertId();
}

function obtenerAlmacenProducto(PDO $db, $id_almacen = 0) {
    if ($id_almacen > 0) return $id_almacen;

    try {
        $id = $db->query("SELECT id_almacen FROM almacenes ORDER BY id_almacen LIMIT 1")->fetchColumn();
        return $id ? (int) $id : 0;
    } catch (Throwable $e) {
        return 0;
    }
}

function guardarInventarioProducto(PDO $db, $id_producto, $data) {
    $stock = intval($data['stock'] ?? $data['cantidad_actual'] ?? 0);
    $stock_min = intval($data['stock_min'] ?? $data['stock_minimo'] ?? 10);
    $stock_max = intval($data['stock_max'] ?? $data['stock_maximo'] ?? 1000);
    $id_almacen = obtenerAlmacenProducto($db, intval($data['id_almacen'] ?? 0));

    if ($id_almacen <= 0) return;

    $stmt = $db->prepare("SELECT id_inventario FROM inventario WHERE id_producto = :p AND id_almacen = :a LIMIT 1");
    $stmt->bindValue(':p', $id_producto, PDO::PARAM_INT);
    $stmt->bindValue(':a', $id_almacen, PDO::PARAM_INT);
    $stmt->execute();
    $id_inventario = $stmt->fetchColumn();

    if ($id_inventario) {
        $upd = $db->prepare("UPDATE inventario SET cantidad_actual = :stock, stock_minimo = :min, stock_maximo = :max WHERE id_inventario = :id");
        $upd->bindValue(':id', $id_inventario, PDO::PARAM_INT);
        $upd->bindValue(':stock', $stock, PDO::PARAM_INT);
        $upd->bindValue(':min', $stock_min, PDO::PARAM_INT);
        $upd->bindValue(':max', $stock_max, PDO::PARAM_INT);
        $upd->execute();
        return;
    }

    $ins = $db->prepare("INSERT INTO inventario (id_producto, id_almacen, cantidad_actual, stock_minimo, stock_maximo) VALUES (:p, :a, :stock, :min, :max)");
    $ins->bindValue(':p', $id_producto, PDO::PARAM_INT);
    $ins->bindValue(':a', $id_almacen, PDO::PARAM_INT);
    $ins->bindValue(':stock', $stock, PDO::PARAM_INT);
    $ins->bindValue(':min', $stock_min, PDO::PARAM_INT);
    $ins->bindValue(':max', $stock_max, PDO::PARAM_INT);
    $ins->execute();
}

function obtenerPrecioProducto(PDO $db, $id_producto) {
    $stmt = $db->prepare("SELECT precio_referencia FROM productos WHERE id_producto = :id LIMIT 1");
    $stmt->bindValue(':id', $id_producto, PDO::PARAM_INT);
    $stmt->execute();
    return (float) ($stmt->fetchColumn() ?: 0);
}

function descontarStockPedido(PDO $db, $id_producto, $cantidad) {
    $stmt = $db->prepare("SELECT id_inventario, cantidad_actual FROM inventario WHERE id_producto = :id ORDER BY cantidad_actual DESC LIMIT 1");
    $stmt->bindValue(':id', $id_producto, PDO::PARAM_INT);
    $stmt->execute();
    $inv = $stmt->fetch();

    if (!$inv) {
        throw new Exception("El producto ID {$id_producto} no tiene inventario registrado");
    }

    if ((int) $inv['cantidad_actual'] < $cantidad) {
        throw new Exception("Stock insuficiente para producto ID {$id_producto}. Disponible: {$inv['cantidad_actual']}");
    }

    $upd = $db->prepare("UPDATE inventario SET cantidad_actual = cantidad_actual - :cantidad WHERE id_inventario = :id");
    $upd->bindValue(':cantidad', $cantidad, PDO::PARAM_INT);
    $upd->bindValue(':id', $inv['id_inventario'], PDO::PARAM_INT);
    $upd->execute();
}

function normalizarItemsPedido($items) {
    if (!is_array($items)) return [];

    $normalizados = [];
    foreach ($items as $item) {
        $id_producto = intval($item['id_producto'] ?? $item['producto_id'] ?? $item['id'] ?? 0);
        $cantidad = intval($item['cantidad'] ?? 0);
        $precio = floatval($item['precio_unitario'] ?? $item['precio'] ?? 0);

        if ($id_producto > 0 && $cantidad > 0) {
            $normalizados[] = [
                'id_producto' => $id_producto,
                'cantidad' => $cantidad,
                'precio' => $precio,
                'nombre' => sanitizar_input($item['nombre'] ?? $item['producto'] ?? "Producto {$id_producto}"),
            ];
        }
    }

    return $normalizados;
}

switch ($accion) {

    // ==================== REGISTRO CLIENTE ====================
    case 'registro_cliente':
        $nombre_completo = sanitizar_input($data['nombre_completo'] ?? $data['nombre'] ?? '');
        $email = sanitizar_input($data['email'] ?? '');
        $telefono = sanitizar_input($data['telefono'] ?? '');
        $direccion = sanitizar_input($data['direccion'] ?? '');
        $nit_ci = sanitizar_input($data['nit_ci'] ?? '');
        $password = $data['password'] ?? '';
        $username_form = sanitizar_input($data['username'] ?? '');

        if (empty($nombre_completo) || empty($email) || empty($password)) {
            echo json_encode(["error" => "Nombre, correo y contraseña son obligatorios."]); exit();
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(["error" => "El formato del correo electronico es invalido."]); exit();
        }
        if (strlen($password) < 8) {
            echo json_encode(["error" => "La contraseña debe tener al menos 8 caracteres."]); exit();
        }

        if ($username_form === '') {
            $base = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', strstr($email, '@', true) ?: 'cliente'));
            $base = trim($base, '_') ?: 'cliente';
            $username_form = substr($base, 0, 16);
        }

        try {
            $db = (new Database())->getConnection();

            $check = $db->prepare("SELECT id_usuario FROM usuarios WHERE email = :email");
            $check->bindValue(':email', $email);
            $check->execute();
            if ($check->fetch()) {
                echo json_encode(["error" => "Este correo ya esta registrado"]); exit();
            }

            $candidate = $username_form;
            $suffix = 1;
            while (true) {
                $checkUser = $db->prepare("SELECT id_usuario FROM usuarios WHERE username = :username");
                $checkUser->bindValue(':username', $candidate);
                $checkUser->execute();
                if (!$checkUser->fetch()) break;
                $candidate = substr($username_form, 0, 15) . $suffix;
                $suffix++;
            }
            $username_form = $candidate;

            $db->beginTransaction();

            $stmtC = $db->prepare("INSERT INTO clientes (razon_social, nit_ci, telefono, email, direccion) VALUES (:r, :nit, :tel, :email, :dir)");
            $stmtC->bindValue(':r', $nombre_completo);
            $stmtC->bindValue(':nit', $nit_ci !== '' ? $nit_ci : null);
            $stmtC->bindValue(':tel', $telefono !== '' ? $telefono : null);
            $stmtC->bindValue(':email', $email);
            $stmtC->bindValue(':dir', $direccion !== '' ? $direccion : null);
            $stmtC->execute();

            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $db->prepare("INSERT INTO usuarios (id_empleado, id_rol, username, password_hash, email) VALUES (NULL, 4, :u, :p, :email)");
            $stmt->bindValue(':u', $username_form);
            $stmt->bindValue(':p', $hash);
            $stmt->bindValue(':email', $email);
            $stmt->execute();
            $id_usuario = (int) $db->lastInsertId();

            manejarOTP($db, $id_usuario, $email, 'registro');
            $db->commit();

            echo json_encode([
                "exito" => true,
                "mensaje" => "Cliente registrado. Revisa tu correo para el codigo OTP.",
                "id_usuario" => $id_usuario,
                "username" => $username_form,
                "email" => $email
            ], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            if (isset($db) && $db->inTransaction()) $db->rollBack();
            echo json_encode(["error" => $e->getMessage()]);
        }
        break;

    // ==================== REGISTRO ====================
    case 'registro':
    case 'register':
        $nombre_completo = sanitizar_input($data['nombre_completo'] ?? $data['nombre'] ?? '');
        $email = sanitizar_input($data['email'] ?? '');
        $username_form = sanitizar_input($data['username'] ?? ''); // Leer el username del formulario
        $password = $data['password'] ?? '';
        $confirmar = sanitizar_input($data['confirmar_password'] ?? $data['confirm'] ?? '');

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

            // Registro publico: siempre crea clientes. Los roles internos se crean desde admin_crear_usuario.
            $id_rol = 4;
            $id_empleado = null;

            if ($id_rol == 4) {
                // Si es Cliente, registrar en la tabla clientes
                $stmtC = $db->prepare("INSERT INTO clientes (razon_social, nit_ci, telefono, email) VALUES (:r, NULL, NULL, :e)");
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
        requireApiAuth(['Administrador']);

        $nombre_completo = sanitizar_input($data['nombre_completo'] ?? '');
        $email = sanitizar_input($data['email'] ?? '');
        $username_form = sanitizar_input($data['username'] ?? '');
        $password = $data['password'] ?? '';
        $rol_nombre = sanitizar_input($data['rol'] ?? '');

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

    // ==================== ACTUALIZAR PERFIL CLIENTE ====================
    case 'actualizar_perfil':
        requireApiAuth();
        $id_usuario = $_SESSION['id_usuario'];
        $nombre = sanitizar_input($data['nombre'] ?? '');
        $email = sanitizar_input($data['email'] ?? '');
        $telefono = sanitizar_input($data['telefono'] ?? '');
        $direccion = sanitizar_input($data['direccion'] ?? '');
        $nit_ci = sanitizar_input($data['nit_ci'] ?? '');

        if (empty($nombre) || empty($email)) {
            echo json_encode(["error" => "Nombre y email son requeridos"]); exit();
        }

        try {
            $db = (new Database())->getConnection();

            // Verificar que el email no esté en uso por otro usuario
            $check = $db->prepare("SELECT id_usuario FROM usuarios WHERE email = :email AND id_usuario != :id");
            $check->bindValue(':email', $email);
            $check->bindValue(':id', $id_usuario);
            $check->execute();
            if ($check->rowCount() > 0) {
                echo json_encode(["error" => "Este correo ya está registrado por otro usuario"]); exit();
            }

            $db->beginTransaction();

            // Actualizar usuario
            $stmt = $db->prepare("UPDATE usuarios SET email = :email WHERE id_usuario = :id");
            $stmt->bindValue(':email', $email);
            $stmt->bindValue(':id', $id_usuario);
            $stmt->execute();

            // Buscar el cliente por el usuario autenticado; si el email cambio, el email anterior ya no es confiable.
            $stmtCliente = $db->prepare(
                "SELECT c.id_cliente
                 FROM usuarios u
                 LEFT JOIN clientes c ON c.email = u.email OR c.email = :email_actual
                 WHERE u.id_usuario = :id_usuario
                 LIMIT 1"
            );
            $stmtCliente->bindValue(':email_actual', $_SESSION['email'] ?? $email);
            $stmtCliente->bindValue(':id_usuario', $id_usuario);
            $stmtCliente->execute();

            $cliente = $stmtCliente->fetch();
            if ($cliente && !empty($cliente['id_cliente'])) {
                $updateCliente = $db->prepare("UPDATE clientes SET razon_social = :nombre, nit_ci = :nit, telefono = :tel, email = :email, direccion = :dir WHERE id_cliente = :id");
                $updateCliente->bindValue(':nombre', $nombre);
                $updateCliente->bindValue(':nit', $nit_ci !== '' ? $nit_ci : null);
                $updateCliente->bindValue(':tel', $telefono);
                $updateCliente->bindValue(':email', $email);
                $updateCliente->bindValue(':dir', $direccion);
                $updateCliente->bindValue(':id', $cliente['id_cliente']);
                $updateCliente->execute();
            } else {
                $insertCliente = $db->prepare("INSERT INTO clientes (razon_social, nit_ci, telefono, email, direccion, estado) VALUES (:nombre, :nit, :tel, :email, :dir, 'Activo')");
                $insertCliente->bindValue(':nombre', $nombre);
                $insertCliente->bindValue(':nit', $nit_ci !== '' ? $nit_ci : null);
                $insertCliente->bindValue(':tel', $telefono);
                $insertCliente->bindValue(':email', $email);
                $insertCliente->bindValue(':dir', $direccion);
                $insertCliente->execute();
            }

            $_SESSION['email'] = $email;
            $_SESSION['nombre'] = $nombre;
            $_SESSION['telefono'] = $telefono;
            $_SESSION['direccion'] = $direccion;
            $_SESSION['nit_ci'] = $nit_ci;

            $db->commit();

            echo json_encode([
                "exito" => true,
                "mensaje" => "Perfil actualizado correctamente",
                "datos" => [
                    "nombre" => $nombre,
                    "email" => $email,
                    "telefono" => $telefono,
                    "direccion" => $direccion,
                    "nit_ci" => $nit_ci,
                ]
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            if (isset($db) && $db->inTransaction()) $db->rollBack();
            echo json_encode(["error" => $e->getMessage()]);
        }
        break;

    // ==================== CAMBIAR CONTRASEÑA ====================
    case 'cambiar_password':
        verificarAutenticacion();
        $id_usuario = $_SESSION['id_usuario'];
        $password_actual = $data['password_actual'] ?? '';
        $password_nueva = $data['password_nueva'] ?? '';

        if (empty($password_actual) || empty($password_nueva)) {
            echo json_encode(["error" => "Proporciona la contraseña actual y la nueva"]); exit();
        }
        if (strlen($password_nueva) < 8 || !preg_match('/[A-Z]/', $password_nueva) || !preg_match('/[0-9]/', $password_nueva)) {
            echo json_encode(["error" => "La nueva contraseña debe tener mín. 8 caracteres, 1 mayúscula y 1 número"]); exit();
        }

        try {
            $db = (new Database())->getConnection();

            // Obtener hash actual
            $stmt = $db->prepare("SELECT password_hash FROM usuarios WHERE id_usuario = :id");
            $stmt->bindValue(':id', $id_usuario);
            $stmt->execute();
            if ($stmt->rowCount() === 0) {
                echo json_encode(["error" => "Usuario no encontrado"]); exit();
            }

            $user = $stmt->fetch();
            if (!password_verify($password_actual, $user['password_hash'])) {
                echo json_encode(["error" => "La contraseña actual es incorrecta"]); exit();
            }

            // Actualizar contraseña
            $hash = password_hash($password_nueva, PASSWORD_BCRYPT);
            $update = $db->prepare("UPDATE usuarios SET password_hash = :hash WHERE id_usuario = :id");
            $update->bindValue(':hash', $hash);
            $update->bindValue(':id', $id_usuario);
            $update->execute();

            echo json_encode(["exito" => true, "mensaje" => "Contraseña actualizada correctamente"], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            echo json_encode(["error" => $e->getMessage()]);
        }
        break;

    // ==================== LOGIN ====================
    case 'login':
        $username = sanitizar_input($data['username'] ?? '');
        $password = $data['password'] ?? '';

        if (empty($username) || empty($password)) {
            echo json_encode(["error" => "Por favor, ingrese sus credenciales."]); exit();
        }

        try {
            $db = (new Database())->getConnection();

            $stmt = $db->prepare(
                "SELECT u.*, r.nombre as rol, c.telefono as cliente_telefono, c.direccion as cliente_direccion, c.nit_ci as cliente_nit_ci, " .
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
                "telefono" => $user['cliente_telefono'] ?? '',
                "direccion" => $user['cliente_direccion'] ?? '',
                "nit_ci" => $user['cliente_nit_ci'] ?? '',
                "token" => $token,
            ];

            session_regenerate_id(true);
            $_SESSION['id_usuario'] = (int) $user['id_usuario'];
            $_SESSION['id_empleado'] = $datos['id_empleado'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['nombre'] = $user['nombre_completo'];
            $_SESSION['rol'] = $user['rol'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['telefono'] = $datos['telefono'];
            $_SESSION['direccion'] = $datos['direccion'];
            $_SESSION['nit_ci'] = $datos['nit_ci'];
            $_SESSION['api_token'] = $token;

            // Guardar en la bitácora de MongoDB silenciosamente
            registrarAuditoria($user['username'], $user['rol'], "Inicio de sesión en el sistema", "Módulo de Seguridad");

            echo json_encode([
                "exito" => true,
                "mensaje" => "¡Bienvenido de nuevo a Distribuidora Andina!",
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
        $codigo = sanitizar_input($data['codigo_otp'] ?? $data['codigo'] ?? '');
        $id = intval($data['id_usuario'] ?? 0);

        try {
            $db = (new Database())->getConnection();

            if ($id <= 0 && !empty($data['email'])) {
                $em = sanitizar_input($data['email']);
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
        $email = sanitizar_input($data['email'] ?? '');
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
        $email = sanitizar_input($data['email'] ?? '');

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
        $email = sanitizar_input($data['email'] ?? '');
        $codigo = sanitizar_input($data['codigo'] ?? '');
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

    // ==================== EDITAR PRODUCTO ====================
    case 'editar_producto':
    case 'update_producto':
        requireApiAuth(['Administrador', 'Gerente']);

        $id_producto = intval($data['id_producto'] ?? $data['id'] ?? 0);
        $codigo = sanitizar_input($data['codigo'] ?? '');
        $nombre = sanitizar_input($data['nombre'] ?? '');
        $descripcion = sanitizar_input($data['descripcion'] ?? '');
        $precio = floatval($data['precio_referencia'] ?? $data['precio'] ?? 0);
        $imagen = normalizarImagenProducto($data['imagen_principal'] ?? $data['imagen'] ?? $data['imagen_url'] ?? $data['url'] ?? '');
        $categoria_input = $data['id_categoria'] ?? $data['categoria'] ?? 0;
        $estado = sanitizar_input($data['estado'] ?? '');
        if ($estado === '' && array_key_exists('activo', $data)) {
            $estado = $data['activo'] ? 'Activo' : 'Inactivo';
        }

        if ($id_producto <= 0 || empty($nombre)) {
            echo json_encode(["error" => "ID y nombre son obligatorios"]); exit();
        }

        try {
            $db = (new Database())->getConnection();

            if (!empty($codigo)) {
                $check = $db->prepare("SELECT id_producto FROM productos WHERE codigo = :c AND id_producto != :id");
                $check->bindValue(':c', $codigo);
                $check->bindValue(':id', $id_producto);
                $check->execute();
                if ($check->rowCount() > 0) {
                    echo json_encode(["error" => "El codigo ya existe en otro producto"]); exit();
                }
            }

            $id_categoria = resolverCategoriaProducto($db, $categoria_input);
            $has_cat = false;
            try { $db->query("SELECT id_categoria FROM productos LIMIT 1"); $has_cat = true; } catch (Throwable $e) {}

            $sql = "UPDATE productos SET nombre = :nom, descripcion = :des" . (!empty($codigo) ? ", codigo = :cod" : "") . ($precio > 0 ? ", precio_referencia = :pre" : "") . ($imagen !== '' ? ", imagen_principal = :img" : "") . ($estado !== '' ? ", estado = :est" : "") . (($has_cat && $id_categoria > 0) ? ", id_categoria = :cat" : "") . " WHERE id_producto = :id";

            $stmt = $db->prepare($sql);
            $stmt->bindValue(':nom', $nombre);
            $stmt->bindValue(':des', $descripcion !== '' ? $descripcion : null);
            if (!empty($codigo)) $stmt->bindValue(':cod', $codigo);
            if ($precio > 0) $stmt->bindValue(':pre', $precio);
            if ($imagen !== '') $stmt->bindValue(':img', $imagen);
            if ($estado !== '') $stmt->bindValue(':est', $estado);
            if ($has_cat && $id_categoria > 0) $stmt->bindValue(':cat', $id_categoria, PDO::PARAM_INT);
            $stmt->bindValue(':id', $id_producto);
            $stmt->execute();

            if (array_key_exists('stock', $data) || array_key_exists('stock_min', $data) || array_key_exists('stock_max', $data)) {
                guardarInventarioProducto($db, $id_producto, $data);
            }

            echo json_encode(["exito" => true, "mensaje" => "Producto actualizado"]);
        } catch (Throwable $e) {
            echo json_encode(["error" => $e->getMessage()]);
        }
        break;

    // ==================== CREAR PRODUCTO ====================
    case 'crear_producto':
    case 'create_producto':
        requireApiAuth(['Administrador', 'Gerente']);

        $codigo = sanitizar_input($data['codigo'] ?? '');
        $nombre = sanitizar_input($data['nombre'] ?? '');
        $descripcion = sanitizar_input($data['descripcion'] ?? '');
        $precio = floatval($data['precio_referencia'] ?? $data['precio'] ?? 0);
        // Leer las URL de las imágenes desde distintos formatos que envíe el frontend
        $imagen = normalizarImagenProducto($data['imagen_principal'] ?? $data['imagen'] ?? $data['imagen_url'] ?? $data['url'] ?? '');
        $categoria_input = $data['id_categoria'] ?? $data['categoria'] ?? 0;

        if (empty($codigo) || empty($nombre) || $precio <= 0) {
            echo json_encode(["error" => "Codigo, nombre y precio son requeridos"]); exit();
        }

        try {
            $db = (new Database())->getConnection();
            $id_categoria = resolverCategoriaProducto($db, $categoria_input);
            if ($id_categoria <= 0) {
                echo json_encode(["error" => "Categoria requerida"]); exit();
            }

            $check = $db->prepare("SELECT id_producto FROM productos WHERE codigo = :c");
            $check->bindParam(':c', $codigo);
            $check->execute();
            if ($check->rowCount() > 0) {
                echo json_encode(["error" => "El codigo ya existe"]); exit();
            }

            $stmt = $db->prepare("INSERT INTO productos (codigo, nombre, descripcion, id_categoria, precio_referencia, imagen_principal, estado) VALUES (:cod, :nom, :des, :cat, :pre, :img, 'Activo')");
            $stmt->bindValue(':cod', $codigo);
            $stmt->bindValue(':nom', $nombre);
            $stmt->bindValue(':des', $descripcion !== '' ? $descripcion : null);
            $stmt->bindValue(':cat', $id_categoria, PDO::PARAM_INT);
            $stmt->bindValue(':pre', $precio);
            $stmt->bindValue(':img', $imagen !== '' ? $imagen : null);
            $stmt->execute();
            $id_producto = (int) $db->lastInsertId();

            guardarInventarioProducto($db, $id_producto, $data);

            echo json_encode([
                "exito" => true,
                "mensaje" => "Producto creado",
                "id_producto" => $id_producto
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            echo json_encode(["error" => $e->getMessage()]);
        }
        break;

    // ==================== OBTENER PRODUCTO ====================
    case 'obtener_producto':
        requireApiAuth(['Administrador', 'Gerente']);

        $id_producto = intval($_GET['id'] ?? $data['id_producto'] ?? $data['id'] ?? 0);
        if ($id_producto <= 0) {
            echo json_encode(["error" => "ID de producto invalido"]); exit();
        }

        try {
            $db = (new Database())->getConnection();
            $stmt = $db->prepare("SELECT p.*, p.imagen_principal as imagen, c.nombre as categoria,
                    COALESCE((SELECT SUM(cantidad_actual) FROM inventario WHERE id_producto = p.id_producto), 0) as stock_total,
                    COALESCE((SELECT stock_minimo FROM inventario WHERE id_producto = p.id_producto ORDER BY id_inventario LIMIT 1), 20) as stock_min,
                    COALESCE((SELECT stock_maximo FROM inventario WHERE id_producto = p.id_producto ORDER BY id_inventario LIMIT 1), 500) as stock_max
                FROM productos p
                LEFT JOIN categorias c ON p.id_categoria = c.id_categoria
                WHERE p.id_producto = :id
                LIMIT 1");
            $stmt->bindValue(':id', $id_producto, PDO::PARAM_INT);
            $stmt->execute();
            $producto = $stmt->fetch();

            if (!$producto) {
                echo json_encode(["error" => "Producto no encontrado"], JSON_UNESCAPED_UNICODE); exit();
            }

            echo json_encode(["exito" => true, "producto" => $producto], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            echo json_encode(["error" => $e->getMessage()]);
        }
        break;

    // ==================== DESACTIVAR PRODUCTO ====================
    case 'desactivar_producto':
        requireApiAuth(['Administrador']);

        $id_producto = intval($data['id_producto'] ?? $data['id'] ?? 0);
        if ($id_producto <= 0) {
            echo json_encode(["error" => "ID de producto invalido"]); exit();
        }

        try {
            $db = (new Database())->getConnection();
            $stmt = $db->prepare("UPDATE productos SET estado = 'Inactivo' WHERE id_producto = :id");
            $stmt->bindValue(':id', $id_producto, PDO::PARAM_INT);
            $stmt->execute();

            echo json_encode(["exito" => true, "mensaje" => "Producto desactivado"], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            echo json_encode(["error" => $e->getMessage()]);
        }
        break;

    // ==================== LISTAR PRODUCTOS ====================
    case 'listar_productos':
        try {
            $db = (new Database())->getConnection();
            $stmt = $db->prepare("SELECT p.*, p.imagen_principal as imagen, c.nombre as categoria,
                    COALESCE((SELECT SUM(cantidad_actual) FROM inventario WHERE id_producto = p.id_producto), 0) as stock_total,
                    COALESCE((SELECT stock_minimo FROM inventario WHERE id_producto = p.id_producto ORDER BY id_inventario LIMIT 1), 20) as stock_min,
                    COALESCE((SELECT stock_maximo FROM inventario WHERE id_producto = p.id_producto ORDER BY id_inventario LIMIT 1), 500) as stock_max
                FROM productos p
                LEFT JOIN categorias c ON p.id_categoria = c.id_categoria
                WHERE p.estado = 'Activo' OR p.estado IS NULL OR LOWER(p.estado) = 'activo'
                ORDER BY p.nombre");
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
        $nombre = sanitizar_input($data['nombre'] ?? '');
        $descripcion = sanitizar_input($data['descripcion'] ?? '');

        if (empty($nombre)) {
            echo json_encode(["error" => "Nombre requerido"]); exit();
        }

        try {
            $db = (new Database())->getConnection();
            $stmt = $db->prepare("INSERT INTO categorias (nombre, descripcion) VALUES (:n, :d)");
            $stmt->bindValue(':n', $nombre);
            $stmt->bindValue(':d', $descripcion !== '' ? $descripcion : null);
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
            $stmt = $db->prepare("SELECT c.*, (SELECT COUNT(*) FROM productos WHERE id_categoria = c.id_categoria AND (estado = 'Activo' OR estado IS NULL OR LOWER(estado) = 'activo')) as total_productos FROM categorias c ORDER BY c.nombre");
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
            $stmt = $db->prepare("SELECT i.*, p.codigo, p.nombre as producto, a.nombre as almacen, CASE WHEN i.cantidad_actual = 0 THEN 'AGOTADO' WHEN i.cantidad_actual <= i.stock_minimo AND i.stock_minimo > 0 THEN 'CRITICO' WHEN i.cantidad_actual <= (i.stock_minimo * 1.3) THEN 'ALERTA' ELSE 'NORMAL' END as estado FROM inventario i JOIN productos p ON i.id_producto = p.id_producto JOIN almacenes a ON i.id_almacen = a.id_almacen ORDER BY i.cantidad_actual ASC");
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
        $tipo = sanitizar_input($data['tipo'] ?? '');
        $id_almacen = intval($data['almacen'] ?? 0);
        $observaciones = sanitizar_input($data['observaciones'] ?? '');

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
    case 'crear_cliente':
    case 'create_cliente':
        $razon_social = sanitizar_input($data['razon_social'] ?? '');
        $nit_ci = sanitizar_input($data['nit_ci'] ?? '');
        $telefono = sanitizar_input($data['telefono'] ?? '');
        $email = sanitizar_input($data['email'] ?? '');

        if (empty($razon_social)) {
            echo json_encode(["error" => "Razon social requerida"]); exit();
        }

        try {
            $db = (new Database())->getConnection();
            $stmt = $db->prepare("INSERT INTO clientes (razon_social, nit_ci, telefono, email) VALUES (:r, :n, :t, :e)");
            $stmt->bindValue(':r', $razon_social);
            $stmt->bindValue(':n', $nit_ci !== '' ? $nit_ci : null);
            $stmt->bindValue(':t', $telefono !== '' ? $telefono : null);
            $stmt->bindValue(':e', $email !== '' ? $email : null);
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
            $stmt = $db->prepare("SELECT * FROM clientes WHERE estado = 'Activo' OR estado IS NULL OR LOWER(estado) = 'activo' ORDER BY razon_social");
            $stmt->execute();

            echo json_encode([
                "exito" => true,
                "clientes" => $stmt->fetchAll()
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            echo json_encode(["error" => $e->getMessage()]);
        }
        break;

    // ==================== EDITAR CLIENTE ====================
    case 'editar_cliente':
    case 'update_cliente':
        $id_cliente = intval($data['id_cliente'] ?? $data['id'] ?? 0);
        $razon_social = sanitizar_input($data['razon_social'] ?? $data['nombre'] ?? '');
        $nit_ci = sanitizar_input($data['nit_ci'] ?? $data['nit'] ?? $data['ci'] ?? '');
        $telefono = sanitizar_input($data['telefono'] ?? '');
        $email = sanitizar_input($data['email'] ?? '');
        $estado = sanitizar_input($data['estado'] ?? '');
        $contacto = sanitizar_input($data['contacto'] ?? '');
        $direccion = sanitizar_input($data['direccion'] ?? '');

        if ($id_cliente <= 0 || empty($razon_social)) {
            echo json_encode(["error" => "ID y razón social (o nombre) son obligatorios"]); exit();
        }

        try {
            $db = (new Database())->getConnection();

            // Validar que el correo no pertenezca a otro cliente
            if (!empty($email)) {
                $checkE = $db->prepare("SELECT id_cliente FROM clientes WHERE email = :e AND id_cliente != :id");
                $checkE->bindValue(':e', $email);
                $checkE->bindValue(':id', $id_cliente);
                $checkE->execute();
                if ($checkE->rowCount() > 0) {
                    echo json_encode(["error" => "Este correo ya está en uso por otro cliente."]); exit();
                }
            }

            $has_contacto = false;
            try { $db->query("SELECT contacto FROM clientes LIMIT 1"); $has_contacto = true; } catch (Throwable $e) {}

            $has_direccion = false;
            try { $db->query("SELECT direccion FROM clientes LIMIT 1"); $has_direccion = true; } catch (Throwable $e) {}

            $sql = "UPDATE clientes SET razon_social = :r, nit_ci = :n, telefono = :t, email = :e" . ($estado ? ", estado = :st" : "") . ($has_contacto ? ", contacto = :c" : "") . ($has_direccion ? ", direccion = :d" : "") . " WHERE id_cliente = :id";
            $upd = $db->prepare($sql);
            $upd->bindValue(':r', $razon_social);
            $upd->bindValue(':n', $nit_ci !== '' ? $nit_ci : null);
            $upd->bindValue(':t', $telefono !== '' ? $telefono : null);
            $upd->bindValue(':e', $email !== '' ? $email : null);
            if ($estado) $upd->bindValue(':st', $estado);
            if ($has_contacto) $upd->bindValue(':c', $contacto !== '' ? $contacto : null);
            if ($has_direccion) $upd->bindValue(':d', $direccion !== '' ? $direccion : null);
            $upd->bindValue(':id', $id_cliente);
            $upd->execute();

            echo json_encode(["exito" => true, "mensaje" => "Cliente actualizado correctamente"]);
        } catch (Throwable $e) {
            echo json_encode(["error" => "Error al actualizar: " . $e->getMessage()]);
        }
        break;

    // ==================== ELIMINAR CLIENTE ====================
    case 'eliminar_cliente':
        requireApiAuth(['Administrador', 'Gerente']);

        $id_cliente = intval($data['id_cliente'] ?? $data['id'] ?? 0);
        if ($id_cliente <= 0) {
            echo json_encode(["error" => "ID de cliente invalido"]); exit();
        }

        try {
            $db = (new Database())->getConnection();
            if (columnaExiste($db, 'clientes', 'estado')) {
                $stmt = $db->prepare("UPDATE clientes SET estado = 'Inactivo' WHERE id_cliente = :id");
            } else {
                $stmt = $db->prepare("DELETE FROM clientes WHERE id_cliente = :id");
            }
            $stmt->bindValue(':id', $id_cliente, PDO::PARAM_INT);
            $stmt->execute();
            echo json_encode(["exito" => true, "mensaje" => "Cliente eliminado"], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            echo json_encode(["error" => $e->getMessage()]);
        }
        break;

    // ==================== PROVEEDORES ====================
    case 'listar_proveedores':
        requireApiAuth(['Administrador', 'Gerente', 'Empleado']);

        try {
            $db = (new Database())->getConnection();
            $where = columnaExiste($db, 'proveedores', 'estado') ? "WHERE estado = 'Activo' OR estado IS NULL" : "";
            $stmt = $db->prepare("SELECT *, id_proveedor as id FROM proveedores {$where} ORDER BY razon_social ASC");
            $stmt->execute();
            echo json_encode(["exito" => true, "proveedores" => $stmt->fetchAll()], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            echo json_encode(["error" => $e->getMessage()]);
        }
        break;

    case 'crear_proveedor':
    case 'create_proveedor':
        requireApiAuth(['Administrador', 'Gerente']);

        $razon_social = sanitizar_input($data['razon_social'] ?? $data['nombre'] ?? '');
        $nit = sanitizar_input($data['nit'] ?? '');
        $contacto = sanitizar_input($data['contacto'] ?? '');
        $telefono = sanitizar_input($data['telefono'] ?? '');
        $email = sanitizar_input($data['email'] ?? '');
        $direccion = sanitizar_input($data['direccion'] ?? $data['ciudad'] ?? '');

        if ($razon_social === '') {
            echo json_encode(["error" => "Razon social requerida"]); exit();
        }

        try {
            $db = (new Database())->getConnection();
            if ($nit !== '') {
                $check = $db->prepare("SELECT id_proveedor FROM proveedores WHERE nit = :nit LIMIT 1");
                $check->bindValue(':nit', $nit);
                $check->execute();
                if ($check->fetch()) {
                    echo json_encode(["error" => "Ya existe un proveedor con ese NIT"]); exit();
                }
            }

            $stmt = $db->prepare("INSERT INTO proveedores (razon_social, nit, contacto, telefono, email, direccion) VALUES (:razon, :nit, :contacto, :telefono, :email, :direccion)");
            $stmt->bindValue(':razon', $razon_social);
            $stmt->bindValue(':nit', $nit !== '' ? $nit : null);
            $stmt->bindValue(':contacto', $contacto !== '' ? $contacto : null);
            $stmt->bindValue(':telefono', $telefono !== '' ? $telefono : null);
            $stmt->bindValue(':email', $email !== '' ? $email : null);
            $stmt->bindValue(':direccion', $direccion !== '' ? $direccion : null);
            $stmt->execute();

            echo json_encode(["exito" => true, "mensaje" => "Proveedor creado", "id_proveedor" => (int) $db->lastInsertId()], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            echo json_encode(["error" => $e->getMessage()]);
        }
        break;

    case 'editar_proveedor':
    case 'update_proveedor':
        requireApiAuth(['Administrador', 'Gerente']);

        $id_proveedor = intval($data['id_proveedor'] ?? $data['id'] ?? 0);
        $razon_social = sanitizar_input($data['razon_social'] ?? $data['nombre'] ?? '');
        $nit = sanitizar_input($data['nit'] ?? '');
        $contacto = sanitizar_input($data['contacto'] ?? '');
        $telefono = sanitizar_input($data['telefono'] ?? '');
        $email = sanitizar_input($data['email'] ?? '');
        $direccion = sanitizar_input($data['direccion'] ?? $data['ciudad'] ?? '');

        if ($id_proveedor <= 0 || $razon_social === '') {
            echo json_encode(["error" => "ID y razon social son requeridos"]); exit();
        }

        try {
            $db = (new Database())->getConnection();
            $stmt = $db->prepare("UPDATE proveedores SET razon_social = :razon, nit = :nit, contacto = :contacto, telefono = :telefono, email = :email, direccion = :direccion WHERE id_proveedor = :id");
            $stmt->bindValue(':razon', $razon_social);
            $stmt->bindValue(':nit', $nit !== '' ? $nit : null);
            $stmt->bindValue(':contacto', $contacto !== '' ? $contacto : null);
            $stmt->bindValue(':telefono', $telefono !== '' ? $telefono : null);
            $stmt->bindValue(':email', $email !== '' ? $email : null);
            $stmt->bindValue(':direccion', $direccion !== '' ? $direccion : null);
            $stmt->bindValue(':id', $id_proveedor, PDO::PARAM_INT);
            $stmt->execute();

            echo json_encode(["exito" => true, "mensaje" => "Proveedor actualizado"], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            echo json_encode(["error" => $e->getMessage()]);
        }
        break;

    case 'eliminar_proveedor':
        requireApiAuth(['Administrador', 'Gerente']);

        $id_proveedor = intval($data['id_proveedor'] ?? $data['id'] ?? 0);
        if ($id_proveedor <= 0) {
            echo json_encode(["error" => "ID de proveedor invalido"]); exit();
        }

        try {
            $db = (new Database())->getConnection();
            if (columnaExiste($db, 'proveedores', 'estado')) {
                $stmt = $db->prepare("UPDATE proveedores SET estado = 'Inactivo' WHERE id_proveedor = :id");
            } else {
                $stmt = $db->prepare("DELETE FROM proveedores WHERE id_proveedor = :id");
            }
            $stmt->bindValue(':id', $id_proveedor, PDO::PARAM_INT);
            $stmt->execute();
            echo json_encode(["exito" => true, "mensaje" => "Proveedor eliminado"], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            echo json_encode(["error" => $e->getMessage()]);
        }
        break;

    // ==================== EDITAR USUARIO ====================
    case 'editar_usuario':
        requireApiAuth(['Administrador']);

        $id_usuario = intval($data['id_usuario'] ?? $data['id'] ?? 0);
        $nombre_completo = sanitizar_input($data['nombre_completo'] ?? $data['nombre'] ?? '');
        $email = sanitizar_input($data['email'] ?? '');
        $username_form = sanitizar_input($data['username'] ?? '');

        if ($id_usuario <= 0 || empty($nombre_completo) || empty($email) || empty($username_form)) {
            echo json_encode(["error" => "ID, nombre, email y username son obligatorios."]); exit();
        }

        try {
            $db = (new Database())->getConnection();

            // Verificar email duplicado (que no sea del mismo usuario)
            $checkE = $db->prepare("SELECT id_usuario FROM usuarios WHERE email = :e AND id_usuario != :id");
            $checkE->bindValue(':e', $email);
            $checkE->bindValue(':id', $id_usuario);
            $checkE->execute();
            if ($checkE->rowCount() > 0) {
                echo json_encode(["error" => "Este correo ya está en uso por otro usuario."]); exit();
            }

            // Verificar username duplicado
            $checkU = $db->prepare("SELECT id_usuario FROM usuarios WHERE username = :u AND id_usuario != :id");
            $checkU->bindValue(':u', $username_form);
            $checkU->bindValue(':id', $id_usuario);
            $checkU->execute();
            if ($checkU->rowCount() > 0) {
                echo json_encode(["error" => "Este nombre de usuario ya está en uso."]); exit();
            }

            $db->beginTransaction();

            // Actualizar datos en la tabla usuarios
            $updU = $db->prepare("UPDATE usuarios SET username = :u, email = :e WHERE id_usuario = :id");
            $updU->bindValue(':u', $username_form);
            $updU->bindValue(':e', $email);
            $updU->bindValue(':id', $id_usuario);
            $updU->execute();

            // Sincronizar el nombre en la tabla de empleados
            $stmt = $db->prepare("SELECT id_empleado FROM usuarios WHERE id_usuario = :id");
            $stmt->bindValue(':id', $id_usuario);
            $stmt->execute();
            $userRow = $stmt->fetch();

            if ($userRow && $userRow['id_empleado']) {
                $nombres = explode(' ', $nombre_completo, 2);
                $updE = $db->prepare("UPDATE empleados SET nombre = :n, apellido = :a WHERE id_empleado = :id_e");
                $updE->bindValue(':n', $nombres[0]);
                $updE->bindValue(':a', $nombres[1] ?? '');
                $updE->bindValue(':id_e', $userRow['id_empleado']);
                $updE->execute();
            }

            $db->commit();
            echo json_encode(["exito" => true, "mensaje" => "Usuario actualizado correctamente."]);
        } catch (Exception $e) {
            if (isset($db)) $db->rollBack();
            echo json_encode(["error" => $e->getMessage()]);
        }
        break;

    // ==================== LISTAR USUARIOS ====================
    case 'listar_usuarios':
        requireApiAuth(['Administrador']);

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
        requireApiAuth(['Administrador']);

        $id = intval($data['id'] ?? 0);
        $rol_nombre = sanitizar_input($data['rol'] ?? '');

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
        requireApiAuth(['Administrador']);

        $id = intval($data['id'] ?? 0);
        $estado = sanitizar_input($data['estado'] ?? '');

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
        requireApiAuth(['Administrador']);

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

    // ==================== CREAR PEDIDO INTERNO ====================
    case 'crear_pedido':
    case 'create_pedido':
        requireApiAuth(['Administrador', 'Gerente', 'Empleado']);

        $id_cliente = intval($data['id_cliente'] ?? $data['cliente_id'] ?? 0);
        $items = normalizarItemsPedido($data['productos'] ?? $data['items'] ?? []);
        $metodo_pago = sanitizar_input($data['metodo_pago'] ?? 'Interno');

        if ($id_cliente <= 0) {
            echo json_encode(["error" => "Cliente requerido"]); exit();
        }
        if (empty($items)) {
            echo json_encode(["error" => "Agrega al menos un producto al pedido"]); exit();
        }

        try {
            $db = (new Database())->getConnection();
            $db->beginTransaction();

            $stmtCliente = $db->prepare("SELECT id_cliente FROM clientes WHERE id_cliente = :id LIMIT 1");
            $stmtCliente->bindValue(':id', $id_cliente, PDO::PARAM_INT);
            $stmtCliente->execute();
            if (!$stmtCliente->fetch()) {
                throw new Exception("Cliente no encontrado");
            }

            $has_total = false;
            try { $db->query("SELECT total FROM pedidos LIMIT 1"); $has_total = true; } catch (Throwable $e) {}

            $col_usuario = 'id_usuario';
            try { $db->query("SELECT id_usuario_creador FROM pedidos LIMIT 1"); $col_usuario = 'id_usuario_creador'; } catch (Throwable $e) {}

            $has_metodo = false;
            try { $db->query("SELECT metodo_pago FROM pedidos LIMIT 1"); $has_metodo = true; } catch (Throwable $e) {}

            $total = 0;
            foreach ($items as &$item) {
                $precio = $item['precio'] > 0 ? $item['precio'] : obtenerPrecioProducto($db, $item['id_producto']);
                if ($precio <= 0) throw new Exception("Producto ID {$item['id_producto']} no tiene precio valido");
                descontarStockPedido($db, $item['id_producto'], $item['cantidad']);
                $item['precio'] = $precio;
                $total += $precio * $item['cantidad'];
            }
            unset($item);

            $codigo = 'PED-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $sql = "INSERT INTO pedidos (codigo_pedido, id_cliente, $col_usuario, fecha_pedido, estado" . ($has_total ? ", total" : "") . ($has_metodo ? ", metodo_pago" : "") . ") VALUES (:cod, :idc, :idu, NOW(), 'Pendiente'" . ($has_total ? ", :tot" : "") . ($has_metodo ? ", :metodo" : "") . ")";
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':cod', $codigo);
            $stmt->bindValue(':idc', $id_cliente, PDO::PARAM_INT);
            $stmt->bindValue(':idu', $_SESSION['id_usuario'], PDO::PARAM_INT);
            if ($has_total) $stmt->bindValue(':tot', $total);
            if ($has_metodo) $stmt->bindValue(':metodo', $metodo_pago);
            $stmt->execute();
            $id_pedido = (int) $db->lastInsertId();

            $stmtDet = $db->prepare("INSERT INTO detalle_pedido (id_pedido, id_producto, cantidad, precio_unitario) VALUES (:idp, :idprod, :cant, :pre)");
            foreach ($items as $item) {
                $stmtDet->bindValue(':idp', $id_pedido, PDO::PARAM_INT);
                $stmtDet->bindValue(':idprod', $item['id_producto'], PDO::PARAM_INT);
                $stmtDet->bindValue(':cant', $item['cantidad'], PDO::PARAM_INT);
                $stmtDet->bindValue(':pre', $item['precio']);
                $stmtDet->execute();
            }

            $db->commit();

            echo json_encode([
                "exito" => true,
                "mensaje" => "Pedido creado correctamente",
                "id_pedido" => $id_pedido,
                "codigo_pedido" => $codigo,
                "total" => $total
            ], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            if (isset($db) && $db->inTransaction()) $db->rollBack();
            echo json_encode(["error" => $e->getMessage()]);
        }
        break;

    // ==================== LISTAR PEDIDOS ====================
    case 'listar_pedidos':
        requireApiAuth(['Administrador', 'Gerente', 'Empleado']);

        try {
            $db = (new Database())->getConnection();
            // Usamos LEFT JOIN para que no se oculte ningún pedido aunque falte algún dato
            $col_usuario = null;
            if (columnaExiste($db, 'pedidos', 'id_usuario_creador')) {
                $col_usuario = 'id_usuario_creador';
            } elseif (columnaExiste($db, 'pedidos', 'id_usuario')) {
                $col_usuario = 'id_usuario';
            }

            $usuarioSelect = $col_usuario ? "p.{$col_usuario} as id_usuario" : "NULL as id_usuario";
            $usuarioJoin = $col_usuario ? "LEFT JOIN usuarios us ON p.{$col_usuario} = us.id_usuario" : "LEFT JOIN usuarios us ON 1 = 0";
            $limite = max(1, min(1000, intval($_GET['limite'] ?? $data['limite'] ?? 1000)));

            $stmt = $db->prepare(
                "SELECT p.id_pedido as id, p.codigo_pedido as codigo, p.id_cliente,
                        COALESCE(c.razon_social, 'Cliente Eliminado/Desconocido') as cliente,
                        p.fecha_pedido as fecha, p.estado, {$usuarioSelect},
                        COALESCE(NULLIF(TRIM(CONCAT_WS(' ', e.nombre, e.apellido)), ''), c.razon_social, us.username, 'Sistema') as creado_por,
                        COALESCE((SELECT SUM(cantidad * precio_unitario) FROM detalle_pedido WHERE id_pedido = p.id_pedido), 0) as total
                 FROM pedidos p
                 LEFT JOIN clientes c ON p.id_cliente = c.id_cliente
                 {$usuarioJoin}
                 LEFT JOIN empleados e ON us.id_empleado = e.id_empleado
                 ORDER BY p.fecha_pedido DESC, p.id_pedido DESC LIMIT :limite"
            );
            $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
            $stmt->execute();
            $pedidos = $stmt->fetchAll();

            $detStmt = $db->prepare(
                "SELECT dp.id_producto, dp.cantidad, dp.precio_unitario,
                        p.codigo as producto_codigo, p.nombre as producto_nombre, p.imagen_principal
                 FROM detalle_pedido dp
                 LEFT JOIN productos p ON dp.id_producto = p.id_producto
                 WHERE dp.id_pedido = :id
                 ORDER BY dp.id_detalle"
            );

            foreach ($pedidos as &$pedido) {
                $detStmt->bindValue(':id', $pedido['id'], PDO::PARAM_INT);
                $detStmt->execute();
                $pedido['detalles'] = $detStmt->fetchAll();
            }
            unset($pedido);

            echo json_encode(["exito" => true, "pedidos" => $pedidos], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            echo json_encode(["error" => $e->getMessage()]);
        }
        break;

    // ==================== CAMBIAR ESTADO PEDIDO ====================
    case 'cambiar_estado_pedido':
        requireApiAuth(['Administrador', 'Gerente']);

        $id_pedido = intval($data['id_pedido'] ?? $data['id'] ?? 0);
        $estado = sanitizar_input($data['estado'] ?? '');
        $permitidos = ['Pendiente', 'Confirmado', 'Enviado', 'Entregado', 'Completado', 'Cancelado'];

        if ($id_pedido <= 0 || !in_array($estado, $permitidos)) {
            echo json_encode(["error" => "ID o estado invalido"]); exit();
        }

        try {
            $db = (new Database())->getConnection();
            $stmt = $db->prepare("UPDATE pedidos SET estado = :estado WHERE id_pedido = :id");
            $stmt->bindValue(':estado', $estado);
            $stmt->bindValue(':id', $id_pedido, PDO::PARAM_INT);
            $stmt->execute();

            echo json_encode(["exito" => true, "mensaje" => "Estado actualizado"], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            echo json_encode(["error" => $e->getMessage()]);
        }
        break;

    // ==================== CANCELAR PEDIDO ====================
    case 'cancelar_pedido':
        requireApiAuth(['Administrador', 'Gerente']);

        $id_pedido = intval($data['id_pedido'] ?? $data['id'] ?? 0);
        if ($id_pedido <= 0) {
            echo json_encode(["error" => "ID de pedido invalido"]); exit();
        }

        try {
            $db = (new Database())->getConnection();
            $stmt = $db->prepare("UPDATE pedidos SET estado = 'Cancelado' WHERE id_pedido = :id");
            $stmt->bindValue(':id', $id_pedido, PDO::PARAM_INT);
            $stmt->execute();

            echo json_encode(["exito" => true, "mensaje" => "Pedido cancelado"], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            echo json_encode(["error" => $e->getMessage()]);
        }
        break;

    // ==================== COMPRAS ====================
    case 'crear_orden_compra':
        requireApiAuth(['Administrador', 'Gerente']);

        $id_proveedor = intval($data['id_proveedor'] ?? $data['proveedor_id'] ?? 0);
        $productos = is_array($data['productos'] ?? null) ? $data['productos'] : [];
        $observacion = sanitizar_input($data['observacion'] ?? $data['observaciones'] ?? '');

        if ($id_proveedor <= 0) {
            echo json_encode(["error" => "Proveedor requerido"]); exit();
        }
        if (empty($productos)) {
            echo json_encode(["error" => "Debe agregar al menos un producto"]); exit();
        }

        try {
            $db = (new Database())->getConnection();
            $db->beginTransaction();

            $provStmt = $db->prepare("SELECT id_proveedor FROM proveedores WHERE id_proveedor = :id LIMIT 1");
            $provStmt->bindValue(':id', $id_proveedor, PDO::PARAM_INT);
            $provStmt->execute();
            if (!$provStmt->fetch()) {
                throw new Exception("Proveedor no encontrado");
            }

            $codigo_orden = 'OC-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
            $stmtOrden = $db->prepare("INSERT INTO ordenes_compra (codigo_orden, id_proveedor, id_usuario_creador, fecha_orden, estado, observacion) VALUES (:codigo, :proveedor, :usuario, CURDATE(), 'Pendiente', :obs)");
            $stmtOrden->bindValue(':codigo', $codigo_orden);
            $stmtOrden->bindValue(':proveedor', $id_proveedor, PDO::PARAM_INT);
            $stmtOrden->bindValue(':usuario', $_SESSION['id_usuario'], PDO::PARAM_INT);
            $stmtOrden->bindValue(':obs', $observacion !== '' ? $observacion : null);
            $stmtOrden->execute();
            $id_orden = (int) $db->lastInsertId();

            $stmtDetalle = $db->prepare("INSERT INTO detalle_orden_compra (id_orden_compra, id_producto, cantidad, precio_unitario) VALUES (:orden, :producto, :cantidad, :precio)");
            foreach ($productos as $prod) {
                $id_producto = intval($prod['id_producto'] ?? $prod['producto_id'] ?? $prod['id'] ?? 0);
                $cantidad = intval($prod['cantidad'] ?? 0);
                $precio = floatval($prod['precio_unitario'] ?? $prod['precio'] ?? 0);
                if ($id_producto <= 0 || $cantidad <= 0 || $precio <= 0) {
                    throw new Exception("Producto, cantidad y precio son requeridos en cada detalle");
                }
                $stmtDetalle->bindValue(':orden', $id_orden, PDO::PARAM_INT);
                $stmtDetalle->bindValue(':producto', $id_producto, PDO::PARAM_INT);
                $stmtDetalle->bindValue(':cantidad', $cantidad, PDO::PARAM_INT);
                $stmtDetalle->bindValue(':precio', $precio);
                $stmtDetalle->execute();
            }

            $db->commit();
            echo json_encode(["exito" => true, "mensaje" => "Orden creada", "id_orden" => $id_orden, "codigo_orden" => $codigo_orden], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            if (isset($db) && $db->inTransaction()) $db->rollBack();
            echo json_encode(["error" => $e->getMessage()]);
        }
        break;

    case 'cambiar_estado_compra':
        requireApiAuth(['Administrador', 'Gerente']);

        $id_orden = intval($data['id_orden'] ?? $data['id'] ?? 0);
        $estado = sanitizar_input($data['estado'] ?? '');
        $mapEstado = ['Recibido' => 'Recibida'];
        $estadoDb = $mapEstado[$estado] ?? $estado;
        $permitidos = ['Pendiente', 'Aprobado', 'Rechazado', 'Recibida'];

        if ($id_orden <= 0 || !in_array($estadoDb, $permitidos)) {
            echo json_encode(["error" => "ID o estado de compra invalido"]); exit();
        }

        try {
            $db = (new Database())->getConnection();
            $stmt = $db->prepare("UPDATE ordenes_compra SET estado = :estado WHERE id_orden_compra = :id");
            $stmt->bindValue(':estado', $estadoDb);
            $stmt->bindValue(':id', $id_orden, PDO::PARAM_INT);
            $stmt->execute();
            echo json_encode(["exito" => true, "mensaje" => "Estado de compra actualizado"], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            echo json_encode(["error" => $e->getMessage()]);
        }
        break;

    // ==================== DASHBOARD ====================
    case 'dashboard':
        try {
            $db = (new Database())->getConnection();

            // Función auxiliar para conteos seguros y evitar bloqueos si falta una tabla (ej. proveedores)
            $getCount = function($query) use ($db) {
                try {
                    $stmt = $db->query($query);
                    return $stmt ? (int)$stmt->fetchColumn() : 0;
                } catch (Throwable $e) {
                    return 0;
                }
            };

            // Totales
            $productos = $getCount("SELECT COUNT(*) FROM productos WHERE estado = 'Activo' OR estado IS NULL OR LOWER(estado) = 'activo'");
            $clientes = $getCount("SELECT COUNT(*) FROM clientes WHERE estado = 'Activo'");
            $proveedores = $getCount("SELECT COUNT(*) FROM proveedores WHERE estado = 'Activo'");
            $usuarios = $getCount("SELECT COUNT(*) FROM usuarios WHERE estado = 'Activo'");

            // Alertas
            $agotados = $getCount("SELECT COUNT(*) FROM inventario WHERE cantidad_actual = 0");
            $criticos = $getCount("SELECT COUNT(*) FROM inventario WHERE cantidad_actual > 0 AND cantidad_actual <= stock_minimo AND stock_minimo > 0");

            // Pedidos pendientes
            $pedidos = $getCount("SELECT COUNT(*) FROM pedidos WHERE estado IN ('Pendiente', 'Confirmado')");

            // Ventas de los últimos 6 meses (Para Gráfico de Líneas/Barras)
            $ventas_mes = [];
            try {
                $ventas_query = "
                    SELECT DATE_FORMAT(p.fecha_pedido, '%Y-%m') as mes, SUM(dp.cantidad * dp.precio_unitario) as total_ventas
                    FROM pedidos p
                    JOIN detalle_pedido dp ON p.id_pedido = dp.id_pedido
                    WHERE p.estado = 'Completado' AND p.fecha_pedido >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                    GROUP BY mes
                    ORDER BY mes ASC
                ";
                $ventas_mes = $db->query($ventas_query)->fetchAll(PDO::FETCH_ASSOC);
            } catch (Throwable $e) {}

            $meses_nombres = ['01'=>'Ene', '02'=>'Feb', '03'=>'Mar', '04'=>'Abr', '05'=>'May', '06'=>'Jun', '07'=>'Jul', '08'=>'Ago', '09'=>'Sep', '10'=>'Oct', '11'=>'Nov', '12'=>'Dic'];
            $grafico_labels = [];
            $grafico_data = [];

            foreach ($ventas_mes as $v) {
                if (!empty($v['mes'])) {
                    list($year, $month) = explode('-', $v['mes']);
                    $grafico_labels[] = $meses_nombres[$month] ?? $month;
                    $grafico_data[] = floatval($v['total_ventas']);
                }
            }

            // Top 5 Productos más vendidos (Para Gráfico Circular/Dona)
            $top_productos = [];
            try {
                $top_productos = $db->query("
                    SELECT p.id_producto, p.nombre, SUM(dp.cantidad) as total_vendido
                    FROM detalle_pedido dp
                    JOIN pedidos ped ON dp.id_pedido = ped.id_pedido
                    JOIN productos p ON dp.id_producto = p.id_producto
                    WHERE ped.estado = 'Completado'
                    GROUP BY p.id_producto, p.nombre
                    ORDER BY total_vendido DESC LIMIT 5
                ")->fetchAll(PDO::FETCH_ASSOC);
            } catch (Throwable $e) {}

            $top_labels = [];
            $top_data = [];
            foreach ($top_productos as $tp) {
                $top_labels[] = (strlen($tp['nombre']) > 15) ? substr($tp['nombre'], 0, 15) . '...' : $tp['nombre'];
                $top_data[] = intval($tp['total_vendido']);
            }

            echo json_encode([
                "exito" => true,
                "dashboard" => [
                    "productos_activos" => intval($productos),
                    "clientes_activos" => intval($clientes),
                    "proveedores_activos" => intval($proveedores),
                    "usuarios_activos" => intval($usuarios),
                    "productos_criticos" => intval($criticos),
                    "productos_agotados" => intval($agotados),
                    "pedidos_pendientes" => intval($pedidos),
                    "ventas_grafico" => [
                        "labels" => empty($grafico_labels) ? ["Sin datos"] : $grafico_labels,
                        "data" => empty($grafico_data) ? [0] : $grafico_data
                    ],
                    "top_productos" => [
                        "labels" => empty($top_labels) ? ["Sin datos"] : $top_labels,
                        "data" => empty($top_data) ? [0] : $top_data
                    ]
                ]
            ], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            echo json_encode(["error" => $e->getMessage()]);
        }
        break;

    // ==================== CREAR ALMACÉN ====================
    case 'create_almacen':
        requireApiAuth(['Administrador', 'Gerente']);

        $nombre = sanitizar_input($data['nombre'] ?? '');
        $direccion = sanitizar_input($data['direccion'] ?? '');

        if (empty($nombre)) {
            echo json_encode(["error" => "Nombre requerido"]); exit();
        }

        try {
            $db = (new Database())->getConnection();
            $stmt = $db->prepare("INSERT INTO almacenes (nombre, direccion) VALUES (:n, :d)");
            $stmt->bindValue(':n', $nombre);
            $stmt->bindValue(':d', $direccion !== '' ? $direccion : null);
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
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
        }
        session_destroy();

        echo json_encode([
            "exito" => true,
            "mensaje" => "Sesión cerrada correctamente"
        ], JSON_UNESCAPED_UNICODE);
        break;

    // ==================== OBTENER PRODUCTOS (FRONTEND CLIENTE) ====================
    case 'obtener_productos':
        try {
            $db = (new Database())->getConnection();
            // Adaptamos las columnas SQL a los nombres exactos que espera tu tienda.html
            $stmt = $db->prepare("SELECT p.id_producto as id, p.codigo, p.nombre, p.descripcion, p.precio_referencia as precio, p.imagen_principal as imagen, c.nombre as categoria, COALESCE((SELECT SUM(cantidad_actual) FROM inventario WHERE id_producto = p.id_producto), 0) as stock, 20 as stock_min, p.estado FROM productos p LEFT JOIN categorias c ON p.id_categoria = c.id_categoria WHERE p.estado = 'Activo' ORDER BY p.nombre");
            $stmt->execute();

            // Se devuelve el array directo para que el 'data.filter()' del frontend funcione sin errores
            echo json_encode($stmt->fetchAll(), JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            echo json_encode([]);
        }
        break;

    // ==================== OBTENER PEDIDOS CLIENTE ====================
    case 'obtener_pedidos_cliente':
        requireApiAuth(['Cliente']);

        $id_usuario = (int) $_SESSION['id_usuario'];
        try {
            $db = (new Database())->getConnection();

            // 1. Buscar el id_cliente usando LEFT JOIN para no perder al usuario
            $stmtC = $db->prepare("SELECT c.id_cliente, u.email, u.username FROM usuarios u LEFT JOIN clientes c ON u.email = c.email WHERE u.id_usuario = :idu");
            $stmtC->bindValue(':idu', $id_usuario);
            $stmtC->execute();
            $cliente = $stmtC->fetch();

            $id_cliente = $cliente['id_cliente'] ?? 0;

            // Auto-crear cliente si está huérfano en la base de datos
            if (!$id_cliente && $cliente) {
                $stmtIns = $db->prepare("INSERT INTO clientes (razon_social, email, estado) VALUES (:rz, :em, 'Activo')");
                $stmtIns->bindValue(':rz', $cliente['username']);
                $stmtIns->bindValue(':em', $cliente['email']);
                $stmtIns->execute();
                $id_cliente = $db->lastInsertId();
            }

            if ($id_cliente <= 0) {
                echo json_encode([]); exit();
            }

            // 2. Traer los pedidos de ese cliente
            try {
                $stmtP = $db->prepare("SELECT id_pedido as id, codigo_pedido as codigo, fecha_pedido as fecha, estado, total, '3 a 5 días hábiles' as entrega_est FROM pedidos WHERE id_cliente = :idc ORDER BY fecha_pedido DESC");
                $stmtP->bindValue(':idc', $id_cliente);
                $stmtP->execute();
            } catch (Throwable $e) {
                // Fallback por si la columna 'total' no existe en la tabla pedidos
                $stmtP = $db->prepare("SELECT p.id_pedido as id, p.codigo_pedido as codigo, p.fecha_pedido as fecha, p.estado, '3 a 5 días hábiles' as entrega_est, COALESCE((SELECT SUM(cantidad * precio_unitario) FROM detalle_pedido WHERE id_pedido = p.id_pedido), 0) as total FROM pedidos p WHERE p.id_cliente = :idc ORDER BY p.fecha_pedido DESC");
                $stmtP->bindValue(':idc', $id_cliente);
                $stmtP->execute();
            }
            $pedidos = $stmtP->fetchAll();

            // 3. Traer los items (detalle_pedido) por cada pedido
            $stmtI = $db->prepare("SELECT p.nombre, dp.cantidad, dp.precio_unitario as precio FROM detalle_pedido dp JOIN productos p ON dp.id_producto = p.id_producto WHERE dp.id_pedido = :idp");

            foreach ($pedidos as &$ped) {
                $stmtI->bindValue(':idp', $ped['id']);
                $stmtI->execute();
                $ped['items'] = $stmtI->fetchAll();
            }

            echo json_encode($pedidos, JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            echo json_encode([]);
        }
        break;

    // ==================== CREAR PEDIDO CLIENTE ====================
    case 'crear_pedido_cliente':
        requireApiAuth(['Cliente']);

        $id_usuario = (int) $_SESSION['id_usuario'];
        $items = normalizarItemsPedido($data['items'] ?? []);
        $total = floatval($data['total'] ?? 0);
        $metodo_pago = sanitizar_input($data['metodo_pago'] ?? 'Efectivo');

        if (empty($items)) {
            echo json_encode(["error" => "Agrega al menos un producto al pedido"]); exit();
        }

        try {
            $db = (new Database())->getConnection();
            $db->beginTransaction();

            // Relacionar usuario con cliente (LEFT JOIN)
            $stmtC = $db->prepare("SELECT c.id_cliente, c.email, c.razon_social, u.username, u.email as u_email FROM usuarios u LEFT JOIN clientes c ON u.email = c.email WHERE u.id_usuario = :idu");
            $stmtC->bindValue(':idu', $id_usuario);
            $stmtC->execute();
            $cliente = $stmtC->fetch();

            $id_cliente = $cliente['id_cliente'] ?? null;
            $email_cliente = $cliente['email'] ?? $cliente['u_email'] ?? '';
            $nombre_cliente = $cliente['razon_social'] ?? $cliente['username'] ?? 'Cliente';

            if (!$id_cliente && $cliente) {
                // Buscar si el cliente ya existe en la base de datos
                $stmtC2 = $db->prepare("SELECT id_cliente FROM clientes WHERE razon_social = :rz OR email = :em LIMIT 1");
                $stmtC2->bindValue(':rz', $nombre_cliente);
                $stmtC2->bindValue(':em', $email_cliente);
                $stmtC2->execute();

                if ($stmtC2->rowCount() > 0) {
                    $id_cliente = $stmtC2->fetchColumn();
                } else {
                    try {
                        $stmtIns = $db->prepare("INSERT INTO clientes (razon_social, email, estado) VALUES (:rz, :em, 'Activo')");
                        $stmtIns->bindValue(':rz', $nombre_cliente);
                        $stmtIns->bindValue(':em', $email_cliente);
                        $stmtIns->execute();
                        $id_cliente = $db->lastInsertId();
                    } catch (Throwable $e) {
                        $id_cliente = $db->query("SELECT id_cliente FROM clientes LIMIT 1")->fetchColumn();
                    }
                }
            }

            // Auto-descubrir columnas de la tabla pedidos para un INSERT perfecto
            $has_total = false;
            try { $db->query("SELECT total FROM pedidos LIMIT 1"); $has_total = true; } catch (Throwable $e) {}

            $col_usuario = 'id_usuario';
            try { $db->query("SELECT id_usuario_creador FROM pedidos LIMIT 1"); $col_usuario = 'id_usuario_creador'; } catch (Throwable $e) {}

            $has_metodo = false;
            try { $db->query("SELECT metodo_pago FROM pedidos LIMIT 1"); $has_metodo = true; } catch (Throwable $e) {}

            $total = 0;
            foreach ($items as &$item) {
                $precio = $item['precio'] > 0 ? $item['precio'] : obtenerPrecioProducto($db, $item['id_producto']);
                if ($precio <= 0) throw new Exception("Producto ID {$item['id_producto']} no tiene precio valido");
                descontarStockPedido($db, $item['id_producto'], $item['cantidad']);
                $item['precio'] = $precio;
                $total += $precio * $item['cantidad'];
            }
            unset($item);

            $sql = "INSERT INTO pedidos (codigo_pedido, id_cliente, $col_usuario, fecha_pedido, estado" . ($has_total ? ", total" : "") . ($has_metodo ? ", metodo_pago" : "") . ") VALUES (:cod, :idc, :idu, NOW(), 'Pendiente'" . ($has_total ? ", :tot" : "") . ($has_metodo ? ", :metodo" : "") . ")";

            $codigo = 'PED-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':cod', $codigo);
            $stmt->bindValue(':idc', $id_cliente);
            $stmt->bindValue(':idu', $id_usuario);
            if ($has_total) {
                $stmt->bindValue(':tot', $total);
            }
            if ($has_metodo) {
                $stmt->bindValue(':metodo', $metodo_pago);
            }
            $stmt->execute();
            $id_pedido = $db->lastInsertId();

            // Insertar los productos al detalle del pedido
            $stmtDet = $db->prepare("INSERT INTO detalle_pedido (id_pedido, id_producto, cantidad, precio_unitario) VALUES (:idp, :idprod, :cant, :pre)");
            foreach ($items as $item) {
                $stmtDet->bindValue(':idp', $id_pedido);
                $stmtDet->bindValue(':idprod', $item['id_producto']);
                $stmtDet->bindValue(':cant', $item['cantidad']);
                $stmtDet->bindValue(':pre', $item['precio']);
                $stmtDet->execute();
            }

            $db->commit();

            // Enviar factura (Con Try/Catch para que no rompa el pedido si falla el SMTP)
            try {
                if (!empty($email_cliente)) {
                    enviarCorreoPedido($email_cliente, $nombre_cliente, $codigo, $items, $total);
                }
            } catch (Throwable $e) {
                // Ignorar si el correo falla, el pedido ya se guardó con éxito
            }

            echo json_encode(["exito" => true]);
        } catch (Exception $e) {
            if (isset($db)) $db->rollBack();
            echo json_encode(["error" => "Error guardando el pedido: " . $e->getMessage()]);
        }
        break;

    // ==================== VALIDAR CUPÓN ====================
    case 'validar_cupon':
        $codigo = sanitizar_input($data['codigo'] ?? '');
        // Guardamos los cupones reales aquí, evitando ensuciar el HTML con "Mock Data"
        $cupones = ['ANDINA2026' => 10, 'DESCUENTO15' => 15, 'PROMO20' => 20, 'EXPO2026' => 25];

        if (array_key_exists($codigo, $cupones)) {
            echo json_encode(["descuento" => $cupones[$codigo]]);
        } else {
            echo json_encode(["error" => "Cupón inválido o expirado"]);
        }
        break;

    // ==================== OBTENER NOTIFICACIONES ====================
    case 'obtener_notificaciones':
        try {
            $db = (new Database())->getConnection();
            $notificaciones = [];

            try {
                $stmtC = $db->query("SELECT p.nombre, i.cantidad_actual FROM inventario i JOIN productos p ON i.id_producto = p.id_producto WHERE i.cantidad_actual <= i.stock_minimo AND i.stock_minimo > 0 LIMIT 5");
                while ($stmtC && $row = $stmtC->fetch(PDO::FETCH_ASSOC)) {
                    $notificaciones[] = [
                        "tipo" => "warning", "icono" => "bi-exclamation-triangle",
                        "mensaje" => "Stock bajo: {$row['nombre']} (Quedan {$row['cantidad_actual']})", "tiempo" => "Ahora"
                    ];
                }
            } catch (Throwable $e) {}

            try {
                $stmtP = $db->query("SELECT codigo_pedido FROM pedidos WHERE estado = 'Pendiente' LIMIT 5");
                while ($stmtP && $row = $stmtP->fetch(PDO::FETCH_ASSOC)) {
                    $notificaciones[] = [
                        "tipo" => "info", "icono" => "bi-box-seam",
                        "mensaje" => "Nuevo pedido pendiente: {$row['codigo_pedido']}", "tiempo" => "Reciente"
                    ];
                }
            } catch (Throwable $e) {}

            echo json_encode(["exito" => true, "notificaciones" => $notificaciones], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
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
                "editar_producto",
                "listar_productos",
                "create_categoria",
                "listar_categorias",
                "inventario",
                "create_cliente",
                "listar_clientes",
                "editar_cliente",
                "create_almacen",
                "listar_usuarios",
                "editar_usuario",
                "cambiar_rol",
                "cambiar_estado_usuario",
                "dashboard"
            ]
        ], JSON_UNESCAPED_UNICODE);
        break;
}
