<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderJSON(["error" => "Método no permitido"], 405);
}

verificarAutenticacion();

$data = json_decode(file_get_contents("php://input"), true) ?: $_POST;

verificarCSRF($data['csrf_token'] ?? '');

$id_cliente = intval($data['id_cliente'] ?? 0);
$id_almacen = intval($data['id_almacen'] ?? 0); // opcional: si no se envía usa el primero activo
$productos  = $data['productos'] ?? [];

$errores = [];
if ($id_cliente <= 0) $errores[] = "Cliente es requerido";
if (empty($productos) || !is_array($productos)) $errores[] = "Debe incluir al menos un producto";

foreach ($productos as $index => $prod) {
    if (empty($prod['id_producto']))                        $errores[] = "Producto #{$index}: ID requerido";
    if (empty($prod['cantidad']) || $prod['cantidad'] <= 0) $errores[] = "Producto #{$index}: Cantidad inválida";
}

if (!empty($errores)) {
    responderJSON(["error" => "Errores de validación", "detalles" => $errores], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();
    $db->beginTransaction();

    // Verificar cliente
    $cliStmt = $db->prepare(
        "SELECT id_cliente, razon_social FROM clientes WHERE id_cliente = :id AND estado = 'Activo'"
    );
    $cliStmt->bindParam(':id', $id_cliente);
    $cliStmt->execute();
    if ($cliStmt->rowCount() === 0) {
        $db->rollBack();
        responderJSON(["error" => "Cliente no encontrado o inactivo"], 404);
    }

    // Resolver almacén
    // Si el frontend envió id_almacen, validarlo. Si no, usar el primer almacén activo.
    if ($id_almacen > 0) {
        $almStmt = $db->prepare(
            "SELECT id_almacen, nombre FROM almacenes WHERE id_almacen = :id AND estado = 'Activo'"
        );
        $almStmt->bindParam(':id', $id_almacen);
        $almStmt->execute();
        if ($almStmt->rowCount() === 0) {
            $db->rollBack();
            responderJSON(["error" => "El almacén seleccionado no existe o está inactivo"], 400);
        }
    } else {
        $almStmt = $db->prepare(
            "SELECT id_almacen, nombre FROM almacenes WHERE estado = 'Activo' ORDER BY id_almacen LIMIT 1"
        );
        $almStmt->execute();
        if ($almStmt->rowCount() === 0) {
            $db->rollBack();
            responderJSON(["error" => "No hay almacenes activos configurados"], 500);
        }
        $almacen    = $almStmt->fetch();
        $id_almacen = $almacen['id_almacen'];
    }

    // Verificar stock disponible en el almacén elegido
    $stockStmt = $db->prepare(
        "SELECT cantidad_actual FROM inventario
         WHERE id_producto = :id_producto AND id_almacen = :id_almacen"
    );

    foreach ($productos as $prod) {
        $stockStmt->bindParam(':id_producto', $prod['id_producto']);
        $stockStmt->bindParam(':id_almacen',  $id_almacen);
        $stockStmt->execute();

        if ($stockStmt->rowCount() === 0) {
            $db->rollBack();
            responderJSON([
                "error" => "Producto ID {$prod['id_producto']} no tiene inventario en el almacén seleccionado"
            ], 400);
        }

        $stock = $stockStmt->fetch();
        if ($stock['cantidad_actual'] < $prod['cantidad']) {
            $db->rollBack();
            responderJSON([
                "error"               => "Stock insuficiente para producto ID {$prod['id_producto']}",
                "stock_disponible"    => $stock['cantidad_actual'],
                "cantidad_solicitada" => $prod['cantidad']
            ], 400);
        }
    }

    // Generar código de pedido
    $codigo_pedido = 'PED-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));

    // Crear cabecera del pedido
    $stmtPedido = $db->prepare(
        "INSERT INTO pedidos (codigo_pedido, id_cliente, id_usuario_creador, fecha_pedido)
         VALUES (:codigo, :id_cliente, :id_usuario, CURDATE())"
    );
    $stmtPedido->bindParam(':codigo',     $codigo_pedido);
    $stmtPedido->bindParam(':id_cliente', $id_cliente);
    $stmtPedido->bindParam(':id_usuario', $_SESSION['id_usuario']);
    $stmtPedido->execute();
    $id_pedido = $db->lastInsertId();

    // Insertar detalles, descontar stock y registrar movimiento
    $stmtDetalle = $db->prepare(
        "INSERT INTO detalle_pedido (id_pedido, id_producto, cantidad, precio_unitario)
         VALUES (:id_pedido, :id_producto, :cantidad,
                 (SELECT precio_referencia FROM productos WHERE id_producto = :id_producto2))"
    );
    $stmtStock = $db->prepare(
        "UPDATE inventario SET cantidad_actual = cantidad_actual - :cantidad
         WHERE id_producto = :id_producto AND id_almacen = :id_almacen"
    );
    $stmtMov = $db->prepare(
        "INSERT INTO movimientos_stock
             (id_producto, tipo, cantidad, id_almacen_origen, id_usuario_responsable, observacion)
         VALUES (:id_producto, 'Salida', :cantidad, :id_almacen, :id_usuario, :observacion)"
    );

    foreach ($productos as $prod) {
        $id_producto = intval($prod['id_producto']);
        $cantidad    = intval($prod['cantidad']);

        $stmtDetalle->bindParam(':id_pedido',   $id_pedido);
        $stmtDetalle->bindParam(':id_producto',  $id_producto);
        $stmtDetalle->bindParam(':cantidad',     $cantidad);
        $stmtDetalle->bindParam(':id_producto2', $id_producto);
        $stmtDetalle->execute();

        $stmtStock->bindParam(':cantidad',    $cantidad);
        $stmtStock->bindParam(':id_producto', $id_producto);
        $stmtStock->bindParam(':id_almacen',  $id_almacen);
        $stmtStock->execute();

        $observacion = "Venta - Pedido #{$codigo_pedido}";
        $stmtMov->bindParam(':id_producto', $id_producto);
        $stmtMov->bindParam(':cantidad',    $cantidad);
        $stmtMov->bindParam(':id_almacen',  $id_almacen);
        $stmtMov->bindParam(':id_usuario',  $_SESSION['id_usuario']);
        $stmtMov->bindParam(':observacion', $observacion);
        $stmtMov->execute();
    }

    $db->commit();

    responderJSON([
        "exito"         => true,
        "mensaje"       => "Pedido creado exitosamente",
        "id_pedido"     => $id_pedido,
        "codigo_pedido" => $codigo_pedido,
        "id_almacen"    => $id_almacen
    ], 201);

} catch (Exception $e) {
    if (isset($db)) $db->rollBack();
    error_log("Error al crear pedido: " . $e->getMessage());
    responderJSON(["error" => "Error al crear pedido"], 500);
}