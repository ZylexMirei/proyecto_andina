<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderJSON(["error" => "Método no permitido"], 405);
}

verificarAutenticacion();

$id_producto = intval($_GET['id_producto'] ?? 0);
$tipo = sanitizar_input($_GET['tipo'] ?? '');
$id_almacen = intval($_GET['id_almacen'] ?? 0);
$fecha_desde = sanitizar_input($_GET['fecha_desde'] ?? '');
$fecha_hasta = sanitizar_input($_GET['fecha_hasta'] ?? '');
$pagina = intval($_GET['pagina'] ?? 1);
$limite = intval($_GET['limite'] ?? 20);
$offset = ($pagina - 1) * $limite;

try {
    $database = new Database();
    $db = $database->getConnection();

    $where = "WHERE 1=1";
    $params = [];

    if ($id_producto > 0) {
        $where .= " AND ms.id_producto = :id_producto";
        $params[':id_producto'] = $id_producto;
    }

    if (!empty($tipo) && in_array($tipo, ['Entrada', 'Salida', 'Transferencia'])) {
        $where .= " AND ms.tipo = :tipo";
        $params[':tipo'] = $tipo;
    }

    if ($id_almacen > 0) {
        $where .= " AND (ms.id_almacen_origen = :id_almacen OR ms.id_almacen_destino = :id_almacen2)";
        $params[':id_almacen'] = $id_almacen;
        $params[':id_almacen2'] = $id_almacen;
    }

    if (!empty($fecha_desde)) {
        $where .= " AND DATE(ms.fecha_hora) >= :fecha_desde";
        $params[':fecha_desde'] = $fecha_desde;
    }

    if (!empty($fecha_hasta)) {
        $where .= " AND DATE(ms.fecha_hora) <= :fecha_hasta";
        $params[':fecha_hasta'] = $fecha_hasta;
    }

    // Conteo total
    $countQuery = "SELECT COUNT(*) as total FROM movimientos_stock ms {$where}";
    $countStmt = $db->prepare($countQuery);
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value);
    }
    $countStmt->execute();
    $total = $countStmt->fetch()['total'];

    // Obtener movimientos con todos los detalles
    $query = "SELECT ms.*, 
                     p.codigo as producto_codigo, 
                     p.nombre as producto_nombre,
                     p.imagen_principal as producto_imagen,
                     ao.nombre as almacen_origen_nombre, 
                     ad.nombre as almacen_destino_nombre,
                     CONCAT(COALESCE(e.nombre, ''), ' ', COALESCE(e.apellido, '')) as responsable_nombre,
                     u.username as responsable_username
              FROM movimientos_stock ms
              JOIN productos p ON ms.id_producto = p.id_producto
              LEFT JOIN almacenes ao ON ms.id_almacen_origen = ao.id_almacen
              LEFT JOIN almacenes ad ON ms.id_almacen_destino = ad.id_almacen
              LEFT JOIN usuarios u ON ms.id_usuario_responsable = u.id_usuario
              LEFT JOIN empleados e ON u.id_empleado = e.id_empleado
              {$where}
              ORDER BY ms.fecha_hora DESC, ms.id_movimiento DESC
              LIMIT :limite OFFSET :offset";
    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $movimientos = $stmt->fetchAll();

    // Estadísticas de movimientos
    $statsQuery = "SELECT 
                    tipo,
                    COUNT(*) as cantidad,
                    SUM(cantidad) as total_unidades
                   FROM movimientos_stock ms
                   {$where}
                   GROUP BY tipo";
    $statsStmt = $db->prepare($statsQuery);
    foreach ($params as $key => $value) {
        if ($key != ':id_almacen2') {
            $statsStmt->bindValue(str_replace('2', '', $key) === $key ? $key : str_replace('2', '', $key), $value);
        }
    }
    $statsStmt->execute();
    $estadisticas = $statsStmt->fetchAll();

    $total_paginas = ceil($total / $limite);

    responderJSON([
        "exito" => true,
        "movimientos" => $movimientos,
        "estadisticas" => $estadisticas,
        "paginacion" => [
            "pagina_actual" => $pagina,
            "total_paginas" => $total_paginas,
            "total_movimientos" => intval($total),
            "por_pagina" => $limite
        ]
    ]);

} catch (PDOException $e) {
    error_log("Error al leer movimientos: " . $e->getMessage());
    responderJSON(["error" => "Error al obtener movimientos de stock"], 500);
}