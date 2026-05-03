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
$id_proveedor = intval($_GET['id_proveedor'] ?? 0);
$fecha_desde = sanitizar_input($_GET['fecha_desde'] ?? '');
$fecha_hasta = sanitizar_input($_GET['fecha_hasta'] ?? '');
$offset = ($pagina - 1) * $limite;

try {
    $database = new Database();
    $db = $database->getConnection();

    $where = "WHERE 1=1";
    $params = [];

    if (!empty($estado)) {
        $where .= " AND oc.estado = :estado";
        $params[':estado'] = $estado;
    }

    if ($id_proveedor > 0) {
        $where .= " AND oc.id_proveedor = :id_proveedor";
        $params[':id_proveedor'] = $id_proveedor;
    }

    if (!empty($fecha_desde)) {
        $where .= " AND oc.fecha_orden >= :fecha_desde";
        $params[':fecha_desde'] = $fecha_desde;
    }

    if (!empty($fecha_hasta)) {
        $where .= " AND oc.fecha_orden <= :fecha_hasta";
        $params[':fecha_hasta'] = $fecha_hasta;
    }

    // Conteo total
    $countQuery = "SELECT COUNT(*) as total FROM ordenes_compra oc {$where}";
    $countStmt = $db->prepare($countQuery);
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value);
    }
    $countStmt->execute();
    $total = $countStmt->fetch()['total'];

    // Obtener órdenes con proveedor y usuario creador
    $query = "SELECT oc.*, p.razon_social as proveedor_nombre, p.nit as proveedor_nit,
                     CONCAT(e.nombre, ' ', e.apellido) as creador_nombre
              FROM ordenes_compra oc
              JOIN proveedores p ON oc.id_proveedor = p.id_proveedor
              LEFT JOIN usuarios u ON oc.id_usuario_creador = u.id_usuario
              LEFT JOIN empleados e ON u.id_empleado = e.id_empleado
              {$where}
              ORDER BY oc.fecha_orden DESC, oc.id_orden_compra DESC
              LIMIT :limite OFFSET :offset";
    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $ordenes = $stmt->fetchAll();

    // Para cada orden, obtener sus detalles
    foreach ($ordenes as &$orden) {
        $detQuery = "SELECT doc.*, p.codigo as producto_codigo, p.nombre as producto_nombre
                     FROM detalle_orden_compra doc
                     JOIN productos p ON doc.id_producto = p.id_producto
                     WHERE doc.id_orden_compra = :id_orden";
        $detStmt = $db->prepare($detQuery);
        $detStmt->bindParam(':id_orden', $orden['id_orden_compra']);
        $detStmt->execute();
        $orden['detalles'] = $detStmt->fetchAll();

        // Calcular total de la orden
        $total_orden = 0;
        foreach ($orden['detalles'] as $detalle) {
            $total_orden += $detalle['cantidad'] * $detalle['precio_unitario'];
        }
        $orden['total_orden'] = $total_orden;
    }

    $total_paginas = ceil($total / $limite);

    responderJSON([
        "exito" => true,
        "ordenes" => $ordenes,
        "paginacion" => [
            "pagina_actual" => $pagina,
            "total_paginas" => $total_paginas,
            "total_ordenes" => $total,
            "por_pagina" => $limite
        ]
    ]);

} catch (PDOException $e) {
    error_log("Error al leer órdenes: " . $e->getMessage());
    responderJSON(["error" => "Error al obtener órdenes de compra"], 500);
}