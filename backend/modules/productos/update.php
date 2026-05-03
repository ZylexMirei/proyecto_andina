<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderJSON(["error" => "Método no permitido"], 405);
}

verificarAutenticacion();

$data = json_decode(file_get_contents("php://input"), true) ?: $_POST;

verificarCSRF($data['csrf_token'] ?? '');

$id_producto = intval($data['id_producto'] ?? 0);

if ($id_producto <= 0) {
    responderJSON(["error" => "ID de producto inválido"], 400);
}

$codigo = sanitizar_input($data['codigo'] ?? '');
$nombre = sanitizar_input($data['nombre'] ?? '');
$descripcion = sanitizar_input($data['descripcion'] ?? '');
$id_categoria = intval($data['id_categoria'] ?? 0);
$precio_referencia = floatval($data['precio_referencia'] ?? 0);
$imagen_principal = sanitizar_input($data['imagen_principal'] ?? '');
$fecha_vencimiento = !empty($data['fecha_vencimiento']) ? sanitizar_input($data['fecha_vencimiento']) : null;
$estado = sanitizar_input($data['estado'] ?? 'Activo');

try {
    $database = new Database();
    $db = $database->getConnection();

    $updateQuery = "UPDATE productos SET 
                    codigo = :codigo,
                    nombre = :nombre,
                    descripcion = :descripcion,
                    id_categoria = :id_categoria,
                    precio_referencia = :precio,
                    imagen_principal = :imagen,
                    fecha_vencimiento = :fecha_vencimiento,
                    estado = :estado
                    WHERE id_producto = :id";
    $updateStmt = $db->prepare($updateQuery);
    $updateStmt->bindParam(':codigo', $codigo);
    $updateStmt->bindParam(':nombre', $nombre);
    $updateStmt->bindParam(':descripcion', $descripcion);
    $updateStmt->bindParam(':id_categoria', $id_categoria);
    $updateStmt->bindParam(':precio', $precio_referencia);
    $updateStmt->bindParam(':imagen', $imagen_principal);
    $updateStmt->bindParam(':fecha_vencimiento', $fecha_vencimiento);
    $updateStmt->bindParam(':estado', $estado);
    $updateStmt->bindParam(':id', $id_producto);
    $updateStmt->execute();

    if ($updateStmt->rowCount() === 0) {
        responderJSON(["error" => "Producto no encontrado o sin cambios"], 404);
    }

    responderJSON([
        "exito" => true,
        "mensaje" => "Producto actualizado exitosamente"
    ]);

} catch (PDOException $e) {
    error_log("Error al actualizar producto: " . $e->getMessage());
    responderJSON(["error" => "Error al actualizar producto"], 500);
}