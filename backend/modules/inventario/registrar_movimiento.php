<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderJSON(["error" => "Método no permitido"], 405);
}

verificarAutenticacion();

$data = json_decode(file_get_contents("php://input"), true) ?: $_POST;

verificarCSRF($data['csrf_token'] ?? '');

$id_producto = intval($data['id_producto'] ?? 0);
$tipo = sanitizar_input($data['tipo'] ?? '');
$cantidad = intval($data['cantidad'] ?? 0);
$id_almacen_origen = intval($data['id_almacen_origen'] ?? 0);
$id_almacen_destino = !empty($data['id_almacen_destino']) ? intval($data['id_almacen_destino']) : null;
$observacion = sanitizar_input($data['observacion'] ?? '');

$errores = [];
if ($id_producto <= 0) $errores[] = "Producto inválido";
if (!in_array($tipo, ['Entrada', 'Salida', 'Transferencia'])) $errores[] = "Tipo de movimiento inválido";
if ($cantidad <= 0) $errores[] = "Cantidad debe ser mayor a 0";

if (!empty($errores)) {
    responderJSON(["error" => "Errores de validación", "detalles" => $errores], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();
    $db->beginTransaction();

    // Verificar stock para salidas
    if ($tipo === 'Salida' || $tipo === 'Transferencia') {
        $stockQuery = "SELECT cantidad_actual FROM inventario WHERE id_producto = :id_producto AND id_almacen = :id_almacen";
        $stockStmt = $db->prepare($stockQuery);
        $stockStmt->bindParam(':id_producto', $id_producto);
        $stockStmt->bindParam(':id_almacen', $id_almacen_origen);
        $stockStmt->execute();

        if ($stockStmt->rowCount() === 0) {
            $db->rollBack();
            responderJSON(["error" => "No existe inventario para este producto en el almacén origen"], 400);
        }

        $stock = $stockStmt->fetch();
        if ($stock['cantidad_actual'] < $cantidad) {
            $db->rollBack();
            responderJSON(["error" => "Stock insuficiente. Disponible: {$stock['cantidad_actual']}"], 400);
        }
    }

    // Actualizar inventario según tipo
    switch ($tipo) {
        case 'Entrada':
            $updateInv = "INSERT INTO inventario (id_producto, id_almacen, cantidad_actual) 
                         VALUES (:id_producto, :id_almacen, :cantidad)
                         ON DUPLICATE KEY UPDATE cantidad_actual = cantidad_actual + :cantidad2";
            $updateStmt = $db->prepare($updateInv);
            $updateStmt->bindParam(':id_producto', $id_producto);
            $updateStmt->bindParam(':id_almacen', $id_almacen_origen);
            $updateStmt->bindParam(':cantidad', $cantidad);
            $updateStmt->bindParam(':cantidad2', $cantidad);
            $updateStmt->execute();
            break;

        case 'Salida':
            $updateInv = "UPDATE inventario SET cantidad_actual = cantidad_actual - :cantidad 
                         WHERE id_producto = :id_producto AND id_almacen = :id_almacen";
            $updateStmt = $db->prepare($updateInv);
            $updateStmt->bindParam(':cantidad', $cantidad);
            $updateStmt->bindParam(':id_producto', $id_producto);
            $updateStmt->bindParam(':id_almacen', $id_almacen_origen);
            $updateStmt->execute();
            break;

        case 'Transferencia':
            $updateOrigen = "UPDATE inventario SET cantidad_actual = cantidad_actual - :cantidad 
                            WHERE id_producto = :id_producto AND id_almacen = :id_almacen";
            $stmtOrigen = $db->prepare($updateOrigen);
            $stmtOrigen->bindParam(':cantidad', $cantidad);
            $stmtOrigen->bindParam(':id_producto', $id_producto);
            $stmtOrigen->bindParam(':id_almacen', $id_almacen_origen);
            $stmtOrigen->execute();

            $updateDestino = "INSERT INTO inventario (id_producto, id_almacen, cantidad_actual) 
                             VALUES (:id_producto, :id_almacen, :cantidad)
                             ON DUPLICATE KEY UPDATE cantidad_actual = cantidad_actual + :cantidad2";
            $stmtDestino = $db->prepare($updateDestino);
            $stmtDestino->bindParam(':id_producto', $id_producto);
            $stmtDestino->bindParam(':id_almacen', $id_almacen_destino);
            $stmtDestino->bindParam(':cantidad', $cantidad);
            $stmtDestino->bindParam(':cantidad2', $cantidad);
            $stmtDestino->execute();
            break;
    }

    // Registrar movimiento
    $movQuery = "INSERT INTO movimientos_stock (id_producto, tipo, cantidad, id_almacen_origen, id_almacen_destino, id_usuario_responsable, observacion) 
                 VALUES (:id_producto, :tipo, :cantidad, :id_almacen_origen, :id_almacen_destino, :id_usuario, :observacion)";
    $movStmt = $db->prepare($movQuery);
    $movStmt->bindParam(':id_producto', $id_producto);
    $movStmt->bindParam(':tipo', $tipo);
    $movStmt->bindParam(':cantidad', $cantidad);
    $movStmt->bindParam(':id_almacen_origen', $id_almacen_origen);
    $movStmt->bindParam(':id_almacen_destino', $id_almacen_destino);
    $movStmt->bindParam(':id_usuario', $_SESSION['id_usuario']);
    $movStmt->bindParam(':observacion', $observacion);
    $movStmt->execute();

    $db->commit();

    responderJSON([
        "exito" => true,
        "mensaje" => "Movimiento registrado exitosamente"
    ]);

} catch (Exception $e) {
    if (isset($db)) $db->rollBack();
    error_log("Error en movimiento: " . $e->getMessage());
    responderJSON(["error" => "Error al registrar movimiento"], 500);
}