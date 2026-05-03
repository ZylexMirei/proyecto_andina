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
$productos = $data['productos'] ?? [];

$errores = [];
if ($id_cliente <= 0) $errores[] = "Cliente es requerido";
if (empty($productos) || !is_array($productos)) $errores[] = "Debe incluir al menos un producto";

foreach ($productos as $index => $prod) {
    if (empty($prod['id_producto'])) $errores[] = "Producto #{$index}: ID requerido";
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
    $cliQuery = "SELECT id_cliente, razon_social FROM clientes WHERE id_cliente = :id AND estado = 'Activo'";
    $cliStmt = $db->prepare($cliQuery);
    $cliStmt->bindParam(':id', $id_cliente);
    $cliStmt->execute();

    if ($cliStmt->rowCount() === 0) {
        $db->rollBack();
        responderJSON(["error" => "Cliente no encontrado o inactivo"], 404);
    }

    // Verificar stock disponible para cada producto (usamos el primer almacén como default)
    $almacen_default_query = "SELECT id_almacen FROM almacenes WHERE estado = 'Activo' ORDER BY id_almacen LIMIT 1";
    $almStmt = $db->prepare($almacen_default_query);
    $almStmt->execute();
    $almacen_default = $almStmt->fetch();

    if (!$almacen_default) {
        $db->rollBack();
        responderJSON(["error" => "No hay almacenes activos configurados"], 500);
    }

    $id_almacen = $almacen_default['id_almacen'];

    foreach ($productos as $prod) {
        $stockQuery = "SELECT cantidad_actual FROM inventario 
                       WHERE id_producto = :id_producto AND id_almacen = :id_almacen";
        $stockStmt = $db->prepare($stockQuery);
        $stockStmt->bindParam(':id_producto', $prod['id_producto']);
        $stockStmt->bindParam(':id_almacen', $id_almacen);
        $stockStmt->execute();

        if ($stockStmt->rowCount() === 0) {
            $db->rollBack();
            responderJSON(["error" => "Producto ID {$prod['id_producto']} no tiene inventario registrado"], 400);
        }

        $stock = $stockStmt->fetch();
        if ($stock['cantidad_actual'] < $prod['cantidad']) {
            $db->rollBack();
            responderJSON([
                "error" => "Stock insuficiente para producto ID {$prod['id_producto']}",
                "stock_disponible" => $stock['cantidad_actual'],
                "cantidad_solicitada" => $prod['cantidad']
            ], 400);
        }
    }

    // Generar código de pedido
    $codigo_pedido = 'PED-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));

    // Crear pedido
    $insertPedido = "INSERT INTO pedidos (codigo_pedido, id_cliente, id_usuario_creador, fecha_pedido) 
                     VALUES (:codigo, :id_cliente, :id_usuario, CURDATE())";
    $stmtPedido = $db->prepare($insertPedido);
    $stmtPedido->bindParam(':codigo', $codigo_pedido);
    $stmtPedido->bindParam(':id_cliente', $id_cliente);
    $stmtPedido->bindParam(':id_usuario', $_SESSION['id_usuario']);
    $stmtPedido->execute();
    $id_pedido = $db->lastInsertId();

    // Insertar detalles y descontar stock
    $insertDetalle = "INSERT INTO detalle_pedido (id_pedido, id_producto, cantidad, precio_unitario) 
                      VALUES (:id_pedido, :id_producto, :cantidad, 
                             (SELECT precio_referencia FROM productos WHERE id_producto = :id_producto2))";
    $stmtDetalle = $db->prepare($insertDetalle);

    $updateStock = "UPDATE inventario SET cantidad_actual = cantidad_actual - :cantidad 
                    WHERE id_producto = :id_producto AND id_almacen = :id_almacen";
    $stmtStock = $db->prepare($updateStock);

    foreach ($productos as $prod) {
        $id_producto = intval($prod['id_producto']);
        $cantidad = intval($prod['cantidad']);

        $stmtDetalle->bindParam(':id_pedido', $id_pedido);
        $stmtDetalle->bindParam(':id_producto', $id_producto);
        $stmtDetalle->bindParam(':cantidad', $cantidad);
        $stmtDetalle->bindParam(':id_producto2', $id_producto);
        $stmtDetalle->execute();

        // Descontar stock
        $stmtStock->bindParam(':cantidad', $cantidad);
        $stmtStock->bindParam(':id_producto', $id_producto);
        $stmtStock->bindParam(':id_almacen', $id_almacen);
        $stmtStock->execute();

        // Registrar movimiento de stock
        $movQuery = "INSERT INTO movimientos_stock (id_producto, tipo, cantidad, id_almacen_origen, id_usuario_responsable, observacion) 
                     VALUES (:id_producto, 'Salida', :cantidad, :id_almacen, :id_usuario, :observacion)";
        $movStmt = $db->prepare($movQuery);
        $observacion = "Venta - Pedido #{$codigo_pedido}";
        $movStmt->bindParam(':id_producto', $id_producto);
        $movStmt->bindParam(':cantidad', $cantidad);
        $movStmt->bindParam(':id_almacen', $id_almacen);
        $movStmt->bindParam(':id_usuario', $_SESSION['id_usuario']);
        $movStmt->bindParam(':observacion', $observacion);
        $movStmt->execute();
    }

    $db->commit();

    responderJSON([
        "exito" => true,
        "mensaje" => "Pedido creado exitosamente",
        "id_pedido" => $id_pedido,
        "codigo_pedido" => $codigo_pedido
    ], 201);

} catch (Exception $e) {
    if (isset($db)) $db->rollBack();
    error_log("Error al crear pedido: " . $e->getMessage());
    responderJSON(["error" => "Error al crear pedido"], 500);
}