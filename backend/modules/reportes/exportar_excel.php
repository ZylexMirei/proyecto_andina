<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderJSON(["error" => "Método no permitido"], 405);
}

verificarAutenticacion();
verificarRol(['Administrador', 'Gerente']);

$tipo = sanitizar_input($_GET['tipo'] ?? 'inventario');
$id_almacen = intval($_GET['id_almacen'] ?? 0);
$fecha_desde = sanitizar_input($_GET['fecha_desde'] ?? '');
$fecha_hasta = sanitizar_input($_GET['fecha_hasta'] ?? '');

try {
    $database = new Database();
    $db = $database->getConnection();
    $params = [];

    switch ($tipo) {
        case 'inventario':
            $query = "SELECT p.codigo, p.nombre, a.nombre as almacen, 
                             i.cantidad_actual, i.stock_minimo, i.stock_maximo,
                             CASE 
                                WHEN i.cantidad_actual = 0 THEN 'AGOTADO'
                                WHEN i.cantidad_actual <= i.stock_minimo THEN 'CRÍTICO'
                                ELSE 'NORMAL'
                             END as estado,
                             (i.cantidad_actual * p.precio_referencia) as valor
                      FROM inventario i
                      JOIN productos p ON i.id_producto = p.id_producto
                      JOIN almacenes a ON i.id_almacen = a.id_almacen";
            if ($id_almacen > 0) {
                $query .= " WHERE i.id_almacen = :id_almacen";
                $params[':id_almacen'] = $id_almacen;
            }
            $query .= " ORDER BY a.nombre, p.nombre";
            $titulo = "Reporte de Inventario";
            $headers = ['Código', 'Producto', 'Almacén', 'Stock Actual', 'Stock Mínimo', 'Stock Máximo', 'Estado', 'Valor (Bs)'];
            break;

        case 'pedidos':
            $query = "SELECT p.codigo_pedido, c.razon_social, p.fecha_pedido, p.estado,
                             SUM(dp.cantidad * dp.precio_unitario) as total,
                             COUNT(dp.id_detalle) as items
                      FROM pedidos p
                      JOIN clientes c ON p.id_cliente = c.id_cliente
                      JOIN detalle_pedido dp ON p.id_pedido = dp.id_pedido";
            $where = [];
            if (!empty($fecha_desde)) { $where[] = "p.fecha_pedido >= :fecha_desde"; $params[':fecha_desde'] = $fecha_desde; }
            if (!empty($fecha_hasta)) { $where[] = "p.fecha_pedido <= :fecha_hasta"; $params[':fecha_hasta'] = $fecha_hasta; }
            if (!empty($where)) $query .= " WHERE " . implode(' AND ', $where);
            $query .= " GROUP BY p.id_pedido ORDER BY p.fecha_pedido DESC";
            $titulo = "Reporte de Pedidos";
            $headers = ['Código', 'Cliente', 'Fecha', 'Estado', 'Total (Bs)', 'Cant. Items'];
            break;

        case 'ventas':
            $query = "SELECT p.codigo, p.nombre,
                             SUM(dp.cantidad) as unidades_vendidas,
                             SUM(dp.cantidad * dp.precio_unitario) as ingresos,
                             COUNT(DISTINCT pe.id_pedido) as pedidos
                      FROM detalle_pedido dp
                      JOIN pedidos pe ON dp.id_pedido = pe.id_pedido
                      JOIN productos p ON dp.id_producto = p.id_producto
                      WHERE pe.estado != 'Cancelado'";
            if (!empty($fecha_desde)) { $query .= " AND pe.fecha_pedido >= :fecha_desde"; $params[':fecha_desde'] = $fecha_desde; }
            if (!empty($fecha_hasta)) { $query .= " AND pe.fecha_pedido <= :fecha_hasta"; $params[':fecha_hasta'] = $fecha_hasta; }
            $query .= " GROUP BY dp.id_producto ORDER BY ingresos DESC LIMIT 50";
            $titulo = "Reporte de Ventas por Producto";
            $headers = ['Código', 'Producto', 'Und. Vendidas', 'Ingresos (Bs)', 'Cant. Pedidos'];
            break;

        default:
            die("Tipo de reporte inválido.");
    }

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Encabezados HTTP para forzar la descarga en Excel
    $filename = "Reporte_" . ucfirst($tipo) . "_" . date('Ymd_His') . ".xls";
    header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    // BOM para reconocer caracteres especiales en Excel
    echo "\xEF\xBB\xBF";

    // Tabla con estilos CSS en línea que Excel interpreta para verse "ordenado"
    echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel">';
    echo '<head><meta charset="UTF-8"></head>';
    echo '<body>';
    echo '<table border="1" cellpadding="5" cellspacing="0" style="font-family: Arial, sans-serif;">';
    
    // Título principal
    echo '<tr>';
    echo '<th colspan="' . count($headers) . '" style="background-color: #0d6efd; color: white; font-size: 18px; text-align: center; height: 40px;">';
    echo 'DISTRIBUIDORA ANDINA - ' . mb_strtoupper($titulo, 'UTF-8');
    echo '</th>';
    echo '</tr>';

    // Cabeceras de columnas
    echo '<tr>';
    foreach ($headers as $header) {
        echo '<th style="background-color: #e9ecef; font-weight: bold; text-align: center; height: 30px;">' . $header . '</th>';
    }
    echo '</tr>';

    // Datos
    foreach ($datos as $row) {
        echo '<tr>';
        foreach ($headers as $index => $header) {
            $keys = array_keys($row);
            $value = $row[$keys[$index]] ?? '';
            
            // Alineación numérica y texto
            if (is_numeric($value) && strpos(strtolower($header), 'bs') !== false) {
                echo '<td style="text-align: right;">' . number_format((float)$value, 2, '.', '') . '</td>';
            } elseif (is_numeric($value)) {
                echo '<td style="text-align: center;">' . $value . '</td>';
            } else {
                echo '<td style="text-align: left;">' . htmlspecialchars($value) . '</td>';
            }
        }
        echo '</tr>';
    }

    echo '</table>';
    echo '</body></html>';
    exit();

} catch (Exception $e) {
    error_log("Error al generar Excel: " . $e->getMessage());
    die("Ocurrió un error al procesar el reporte.");
}
?>