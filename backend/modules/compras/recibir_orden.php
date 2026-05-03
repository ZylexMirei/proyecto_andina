<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderJSON(["error" => "Método no permitido"], 405);
}

verificarAutenticacion();

$data = json_decode(file_get_contents("php://input"), true) ?: $_POST;

verificarCSRF($data['csrf_token'] ?? '');

$id_orden = intval($data['id_orden'] ?? 0);
$id_almacen = intval($data['id_almacen'] ?? 0);

if ($id_orden <= 0) {
    responderJSON(["error" => "ID de orden inválido"], 400);
}

if ($id_almacen <= 0) {
    responderJSON(["error" => "Debe especificar el almacén de recepción"], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();
    $db->beginTransaction();

    // Verificar orden
    $ordenQuery = "SELECT id_orden_compra, estado FROM ordenes_compra WHERE id_orden_compra = :id";
    $ordenStmt = $db->prepare($ordenQuery);
    $ordenStmt->bindParam(':id', $id_orden);
    $ordenStmt->execute();

    if ($ordenStmt->rowCount() === 0) {
        $db->rollBack();
        responderJSON(["error" => "Orden no encontrada"], 404);
    }

    $orden = $ordenStmt->fetch();
    if ($orden['estado'] !== 'Aprobada') {
        $db->rollBack();
        responderJSON(["error" => "Solo se pueden recibir órdenes aprobadas. Estado actual: {$orden['estado']}"], 400);
    }

    // Verificar almacén
    $almQuery = "SELECT id_almacen FROM almacenes WHERE id_almacen = :id AND estado = 'Activo'";
    $almStmt = $db->prepare($almQuery);
    $almStmt->bindParam(':id', $id_almacen);
    $almStmt->execute();

    if ($almStmt->rowCount() === 0) {
        $db->rollBack();
        responderJSON(["error" => "Almacén no encontrado o inactivo"], 404);
    }

    // Obtener detalles de la orden
    $detQuery = "SELECT id_producto, cantidad FROM detalle_orden_compra WHERE id_orden_compra = :id";
    $detStmt = $db->prepare($detQuery);
    $detStmt->bindParam(':id', $id_orden);
    $detStmt->execute();
    $detalles = $detStmt->fetchAll();

    // Actualizar inventario por cada producto
    $updateInv = "INSERT INTO inventario (id_producto, id_almacen, cantidad_actual) 
                  VALUES (:id_producto, :id_almacen, :cantidad)
                  ON DUPLICATE KEY UPDATE cantidad_actual = cantidad_actual + :cantidad2";
    $stmtInv = $db->prepare($updateInv);

    // Registrar movimientos de stock
    $movQuery = "INSERT INTO movimientos_stock (id_producto, tipo, cantidad, id_almacen_origen, id_usuario_responsable, observacion) 
                 VALUES (:id_producto, 'Entrada', :cantidad, :id_almacen, :id_usuario, :observacion)";
    $stmtMov = $db->prepare($movQuery);

    foreach ($detalles as $detalle) {
        $id_producto = $detalle['id_producto'];
        $cantidad = $detalle['cantidad'];
        $observacion = "Recepción de orden #{$id_orden}";

        $stmtInv->bindParam(':id_producto', $id_producto);
        $stmtInv->bindParam(':id_almacen', $id_almacen);
        $stmtInv->bindParam(':cantidad', $cantidad);
        $stmtInv->bindParam(':cantidad2', $cantidad);
        $stmtInv->execute();

        $stmtMov->bindParam(':id_producto', $id_producto);
        $stmtMov->bindParam(':cantidad', $cantidad);
        $stmtMov->bindParam(':id_almacen', $id_almacen);
        $stmtMov->bindParam(':id_usuario', $_SESSION['id_usuario']);
        $stmtMov->bindParam(':observacion', $observacion);
        $stmtMov->execute();
    }

    // Actualizar estado de la orden
    $updateOrden = "UPDATE ordenes_compra SET estado = 'Recibida' WHERE id_orden_compra = :id";
    $stmtOrden = $db->prepare($updateOrden);
    $stmtOrden->bindParam(':id', $id_orden);
    $stmtOrden->execute();

    $db->commit();

    responderJSON([
        "exito" => true,
        "mensaje" => "Orden recibida exitosamente. Inventario actualizado.",
        "id_orden" => $id_orden,
        "id_almacen" => $id_almacen,
        "productos_actualizados" => count($detalles)
    ]);

} catch (Exception $e) {
    if (isset($db)) $db->rollBack();
    error_log("Error al recibir orden: " . $e->getMessage());
    responderJSON(["error" => "Error al recibir la orden"], 500);
}