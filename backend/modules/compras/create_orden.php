<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderJSON(["error" => "Método no permitido"], 405);
}

verificarAutenticacion();

$data = json_decode(file_get_contents("php://input"), true) ?: $_POST;

verificarCSRF($data['csrf_token'] ?? '');

$id_proveedor = intval($data['id_proveedor'] ?? 0);
$productos = $data['productos'] ?? [];
$observacion = sanitizar_input($data['observacion'] ?? '');

$errores = [];
if ($id_proveedor <= 0) $errores[] = "Proveedor es requerido";
if (empty($productos) || !is_array($productos)) $errores[] = "Debe incluir al menos un producto";

foreach ($productos as $index => $prod) {
    if (empty($prod['id_producto'])) $errores[] = "Producto #{$index}: ID requerido";
    if (empty($prod['cantidad']) || $prod['cantidad'] <= 0) $errores[] = "Producto #{$index}: Cantidad inválida";
    if (empty($prod['precio_unitario']) || $prod['precio_unitario'] <= 0) $errores[] = "Producto #{$index}: Precio inválido";
}

if (!empty($errores)) {
    responderJSON(["error" => "Errores de validación", "detalles" => $errores], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();
    $db->beginTransaction();

    // Verificar que el proveedor existe y está activo
    $provQuery = "SELECT id_proveedor, razon_social FROM proveedores WHERE id_proveedor = :id AND estado = 'Activo'";
    $provStmt = $db->prepare($provQuery);
    $provStmt->bindParam(':id', $id_proveedor);
    $provStmt->execute();

    if ($provStmt->rowCount() === 0) {
        $db->rollBack();
        responderJSON(["error" => "Proveedor no encontrado o inactivo"], 404);
    }

    // Generar código de orden único
    $codigo_orden = 'OC-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));

    // Insertar cabecera de orden
    $insertOrden = "INSERT INTO ordenes_compra (codigo_orden, id_proveedor, id_usuario_creador, fecha_orden, observacion) 
                    VALUES (:codigo, :id_proveedor, :id_usuario, CURDATE(), :observacion)";
    $stmtOrden = $db->prepare($insertOrden);
    $stmtOrden->bindParam(':codigo', $codigo_orden);
    $stmtOrden->bindParam(':id_proveedor', $id_proveedor);
    $stmtOrden->bindParam(':id_usuario', $_SESSION['id_usuario']);
    $stmtOrden->bindParam(':observacion', $observacion);
    $stmtOrden->execute();
    $id_orden = $db->lastInsertId();

    // Insertar detalles de la orden
    $insertDetalle = "INSERT INTO detalle_orden_compra (id_orden_compra, id_producto, cantidad, precio_unitario) 
                      VALUES (:id_orden, :id_producto, :cantidad, :precio)";
    $stmtDetalle = $db->prepare($insertDetalle);

    foreach ($productos as $prod) {
        $id_producto = intval($prod['id_producto']);
        $cantidad = intval($prod['cantidad']);
        $precio = floatval($prod['precio_unitario']);

        $stmtDetalle->bindParam(':id_orden', $id_orden);
        $stmtDetalle->bindParam(':id_producto', $id_producto);
        $stmtDetalle->bindParam(':cantidad', $cantidad);
        $stmtDetalle->bindParam(':precio', $precio);
        $stmtDetalle->execute();
    }

    $db->commit();

    responderJSON([
        "exito" => true,
        "mensaje" => "Orden de compra creada exitosamente",
        "id_orden" => $id_orden,
        "codigo_orden" => $codigo_orden
    ], 201);

} catch (Exception $e) {
    if (isset($db)) $db->rollBack();
    error_log("Error al crear orden de compra: " . $e->getMessage());
    responderJSON(["error" => "Error al crear orden de compra"], 500);
}