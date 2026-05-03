<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderJSON(["error" => "Método no permitido"], 405);
}

verificarAutenticacion();

$id_pedido = intval($_GET['id'] ?? 0);

if ($id_pedido <= 0) {
    responderJSON(["error" => "ID de pedido inválido"], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $query = "SELECT p.*, c.razon_social as cliente_nombre, c.nit_ci as cliente_nit,
                     c.contacto as cliente_contacto, c.telefono as cliente_telefono,
                     CONCAT(e.nombre, ' ', e.apellido) as creador_nombre
              FROM pedidos p
              JOIN clientes c ON p.id_cliente = c.id_cliente
              LEFT JOIN usuarios u ON p.id_usuario_creador = u.id_usuario
              LEFT JOIN empleados e ON u.id_empleado = e.id_empleado
              WHERE p.id_pedido = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $id_pedido);
    $stmt->execute();

    if ($stmt->rowCount() === 0) {
        responderJSON(["error" => "Pedido no encontrado"], 404);
    }

    $pedido = $stmt->fetch();

    $detQuery = "SELECT dp.*, p.codigo, p.nombre, p.imagen_principal
                 FROM detalle_pedido dp
                 JOIN productos p ON dp.id_producto = p.id_producto
                 WHERE dp.id_pedido = :id";
    $detStmt = $db->prepare($detQuery);
    $detStmt->bindParam(':id', $id_pedido);
    $detStmt->execute();
    $pedido['detalles'] = $detStmt->fetchAll();

    $total = 0;
    foreach ($pedido['detalles'] as $d) {
        $total += $d['cantidad'] * $d['precio_unitario'];
    }
    $pedido['total_pedido'] = $total;

    responderJSON([
        "exito" => true,
        "pedido" => $pedido
    ]);

} catch (PDOException $e) {
    error_log("Error al leer pedido: " . $e->getMessage());
    responderJSON(["error" => "Error al obtener pedido"], 500);
}