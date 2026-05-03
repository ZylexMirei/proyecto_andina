<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderJSON(["error" => "Método no permitido"], 405);
}

verificarAutenticacion();

$data = json_decode(file_get_contents("php://input"), true) ?: $_POST;

verificarCSRF($data['csrf_token'] ?? '');

$codigo = sanitizar_input($data['codigo'] ?? '');
$nombre = sanitizar_input($data['nombre'] ?? '');
$descripcion = sanitizar_input($data['descripcion'] ?? '');
$id_categoria = intval($data['id_categoria'] ?? 0);
$precio_referencia = floatval($data['precio_referencia'] ?? 0);
$imagen_principal = sanitizar_input($data['imagen_principal'] ?? '');
$fecha_vencimiento = !empty($data['fecha_vencimiento']) ? sanitizar_input($data['fecha_vencimiento']) : null;

$errores = [];
if (empty($codigo)) $errores[] = "Código es requerido";
if (empty($nombre)) $errores[] = "Nombre es requerido";
if ($precio_referencia <= 0) $errores[] = "Precio debe ser mayor a 0";

if (!empty($errores)) {
    responderJSON(["error" => "Errores de validación", "detalles" => $errores], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $checkQuery = "SELECT id_producto FROM productos WHERE codigo = :codigo";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bindParam(':codigo', $codigo);
    $checkStmt->execute();

    if ($checkStmt->rowCount() > 0) {
        responderJSON(["error" => "El código de producto ya existe"], 409);
    }

    $insertQuery = "INSERT INTO productos (codigo, nombre, descripcion, id_categoria, precio_referencia, imagen_principal, fecha_vencimiento) 
                    VALUES (:codigo, :nombre, :descripcion, :id_categoria, :precio, :imagen, :fecha_vencimiento)";
    $insertStmt = $db->prepare($insertQuery);
    $insertStmt->bindParam(':codigo', $codigo);
    $insertStmt->bindParam(':nombre', $nombre);
    $insertStmt->bindParam(':descripcion', $descripcion);
    $insertStmt->bindParam(':id_categoria', $id_categoria);
    $insertStmt->bindParam(':precio', $precio_referencia);
    $insertStmt->bindParam(':imagen', $imagen_principal);
    $insertStmt->bindParam(':fecha_vencimiento', $fecha_vencimiento);
    $insertStmt->execute();

    $id_producto = $db->lastInsertId();

    // Insertar imágenes adicionales
    if (!empty($data['imagenes_extra']) && is_array($data['imagenes_extra'])) {
        $imgQuery = "INSERT INTO imagenes_producto (id_producto, url_imagen, orden) VALUES (:id_producto, :url, :orden)";
        $imgStmt = $db->prepare($imgQuery);
        foreach ($data['imagenes_extra'] as $index => $url) {
            $orden = $index + 1;
            $imgStmt->bindParam(':id_producto', $id_producto);
            $imgStmt->bindParam(':url', $url);
            $imgStmt->bindParam(':orden', $orden);
            $imgStmt->execute();
        }
    }

    responderJSON([
        "exito" => true,
        "mensaje" => "Producto creado exitosamente",
        "id_producto" => $id_producto
    ], 201);

} catch (PDOException $e) {
    error_log("Error al crear producto: " . $e->getMessage());
    responderJSON(["error" => "Error al crear producto"], 500);
}