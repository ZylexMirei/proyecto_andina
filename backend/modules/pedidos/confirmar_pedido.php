<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderJSON(["error" => "Método no permitido"], 405);
}

verificarAutenticacion();

$data = json_decode(file_get_contents("php://input"), true) ?: $_POST;

verificarCSRF($data['csrf_token'] ?? '');

$id_pedido = intval($data['id_pedido'] ?? 0);
$accion = sanitizar_input($data['accion'] ?? 'Confirmado');

if ($id_pedido <= 0) {
    responderJSON(["error" => "ID de pedido inválido"], 400);
}

if (!in_array($accion, ['Confirmado', 'Enviado', 'Entregado', 'Cancelado'])) {
    responderJSON(["error" => "Acción inválida"], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();
    $db->beginTransaction();

    // Verificar pedido
    $checkQuery = "SELECT id_pedido, estado FROM pedidos WHERE id_pedido = :id";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bindParam(':id', $id_pedido);
    $checkStmt->execute();

    if ($checkStmt->rowCount() === 0) {
        $db->rollBack();
        responderJSON(["error" => "Pedido no encontrado"], 404);
    }

    $pedido = $checkStmt->fetch();

    // Validar transiciones de estado
    $transiciones = [
        'Pendiente' => ['Confirmado', 'Cancelado'],
        'Confirmado' => ['Enviado', 'Cancelado'],
        'Enviado' => ['Entregado'],
    ];

    if (!isset($transiciones[$pedido['estado']]) || !in_array($accion, $transiciones[$pedido['estado']])) {
        $db->rollBack();
        responderJSON([
            "error" => "No se puede cambiar de '{$pedido['estado']}' a '{$accion}'",
            "transiciones_permitidas" => $transiciones[$pedido['estado']] ?? []
        ], 400);
    }

    // Si cancela, devolver stock
    if ($accion === 'Cancelado') {
        $detQuery = "SELECT id_producto, cantidad FROM detalle_pedido WHERE id_pedido = :id";
        $detStmt = $db->prepare($detQuery);
        $detStmt->bindParam(':id', $id_pedido);
        $detStmt->execute();
        $detalles = $detStmt->fetchAll();

        $almacen_default_query = "SELECT id_almacen FROM almacenes WHERE estado = 'Activo' ORDER BY id_almacen LIMIT 1";
        $almStmt = $db->prepare($almacen_default_query);
        $almStmt->execute();
        $almacen = $almStmt->fetch();

        if ($almacen) {
            $updateStock = "UPDATE inventario SET cantidad_actual = cantidad_actual + :cantidad 
                            WHERE id_producto = :id_producto AND id_almacen = :id_almacen";
            $stmtStock = $db->prepare($updateStock);

            foreach ($detalles as $detalle) {
                $stmtStock->bindParam(':cantidad', $detalle['cantidad']);
                $stmtStock->bindParam(':id_producto', $detalle['id_producto']);
                $stmtStock->bindParam(':id_almacen', $almacen['id_almacen']);
                $stmtStock->execute();

                // Registrar devolución
                $movQuery = "INSERT INTO movimientos_stock (id_producto, tipo, cantidad, id_almacen_origen, id_usuario_responsable, observacion) 
                             VALUES (:id_producto, 'Entrada', :cantidad, :id_almacen, :id_usuario, :observacion)";
                $movStmt = $db->prepare($movQuery);
                $observacion = "Devolución por cancelación - Pedido #{$id_pedido}";
                $movStmt->bindParam(':id_producto', $detalle['id_producto']);
                $movStmt->bindParam(':cantidad', $detalle['cantidad']);
                $movStmt->bindParam(':id_almacen', $almacen['id_almacen']);
                $movStmt->bindParam(':id_usuario', $_SESSION['id_usuario']);
                $movStmt->bindParam(':observacion', $observacion);
                $movStmt->execute();
            }
        }
    }

    // Actualizar estado
    $updateQuery = "UPDATE pedidos SET estado = :estado WHERE id_pedido = :id";
    $updateStmt = $db->prepare($updateQuery);
    $updateStmt->bindParam(':estado', $accion);
    $updateStmt->bindParam(':id', $id_pedido);
    $updateStmt->execute();

    $db->commit();

    responderJSON([
        "exito" => true,
        "mensaje" => "Pedido actualizado a '{$accion}' exitosamente",
        "id_pedido" => $id_pedido
    ]);

} catch (Exception $e) {
    if (isset($db)) $db->rollBack();
    error_log("Error al confirmar pedido: " . $e->getMessage());
    responderJSON(["error" => "Error al actualizar pedido"], 500);
}