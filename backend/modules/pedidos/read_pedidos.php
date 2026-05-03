<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderJSON(["error" => "Método no permitido"], 405);
}

verificarAutenticacion();

$pagina = intval($_GET['pagina'] ?? 1);
$limite = intval($_GET['limite'] ?? 10);
$estado = sanitizar_input($_GET['estado'] ?? '');
$id_cliente = intval($_GET['id_cliente'] ?? 0);
$fecha_desde = sanitizar_input($_GET['fecha_desde'] ?? '');
$fecha_hasta = sanitizar_input($_GET['fecha_hasta'] ?? '');
$offset = ($pagina - 1) * $limite;

try {
    $database = new Database();
    $db = $database->getConnection();

    $where = "WHERE 1=1";
    $params = [];

    if (!empty($estado)) {
        $where .= " AND p.estado = :estado";
        $params[':estado'] = $estado;
    }

    if ($id_cliente > 0) {
        $where .= " AND p.id_cliente = :id_cliente";
        $params[':id_cliente'] = $id_cliente;
    }

    if (!empty($fecha_desde)) {
        $where .= " AND p.fecha_pedido >= :fecha_desde";
        $params[':fecha_desde'] = $fecha_desde;
    }

    if (!empty($fecha_hasta)) {
        $where .= " AND p.fecha_pedido <= :fecha_hasta";
        $params[':fecha_hasta'] = $fecha_hasta;
    }

    // Conteo
    $countQuery = "SELECT COUNT(*) as total FROM pedidos p {$where}";
    $countStmt = $db->prepare($countQuery);
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value);
    }
    $countStmt->execute();
    $total = $countStmt->fetch()['total'];

    // Pedidos con detalles
    $query = "SELECT p.*, c.razon_social as cliente_nombre, c.nit_ci as cliente_nit,
                     CONCAT(e.nombre, ' ', e.apellido) as creador_nombre
              FROM pedidos p
              JOIN clientes c ON p.id_cliente = c.id_cliente
              LEFT JOIN usuarios u ON p.id_usuario_creador = u.id_usuario
              LEFT JOIN empleados e ON u.id_empleado = e.id_empleado
              {$where}
              ORDER BY p.fecha_pedido DESC, p.id_pedido DESC
              LIMIT :limite OFFSET :offset";
    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $pedidos = $stmt->fetchAll();

    // Detalles de cada pedido
    foreach ($pedidos as &$pedido) {
        $detQuery = "SELECT dp.*, p.codigo as producto_codigo, p.nombre as producto_nombre,
                            p.imagen_principal
                     FROM detalle_pedido dp
                     JOIN productos p ON dp.id_producto = p.id_producto
                     WHERE dp.id_pedido = :id_pedido";
        $detStmt = $db->prepare($detQuery);
        $detStmt->bindParam(':id_pedido', $pedido['id_pedido']);
        $detStmt->execute();
        $pedido['detalles'] = $detStmt->fetchAll();

        // Calcular total
        $total_pedido = 0;
        foreach ($pedido['detalles'] as $detalle) {
            $total_pedido += $detalle['cantidad'] * $detalle['precio_unitario'];
        }
        $pedido['total_pedido'] = $total_pedido;
    }

    $total_paginas = ceil($total / $limite);

    responderJSON([
        "exito" => true,
        "pedidos" => $pedidos,
        "paginacion" => [
            "pagina_actual" => $pagina,
            "total_paginas" => $total_paginas,
            "total_pedidos" => $total,
            "por_pagina" => $limite
        ]
    ]);

} catch (PDOException $e) {
    error_log("Error al leer pedidos: " . $e->getMessage());
    responderJSON(["error" => "Error al obtener pedidos"], 500);
}