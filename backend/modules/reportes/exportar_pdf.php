<?php
// Arreglar errores sin mostrar detalle público
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["error" => "Método no permitido"]);
    exit();
}

$tipo = sanitizar_input($_GET['tipo'] ?? 'inventario');
$fecha_desde = sanitizar_input($_GET['fecha_desde'] ?? '');
$fecha_hasta = sanitizar_input($_GET['fecha_hasta'] ?? '');

try {
    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        throw new Exception('No se pudo conectar a la base de datos');
    }

    $params = [];
    $query = '';
    $titulo = '';
    $headers = [];

    switch ($tipo) {
        case 'inventario':
            $query = "SELECT p.codigo, p.nombre, a.nombre AS almacen, i.cantidad_actual, i.stock_minimo, i.stock_maximo,
                             CASE
                                 WHEN i.cantidad_actual = 0 THEN 'AGOTADO'
                                 WHEN i.cantidad_actual <= i.stock_minimo THEN 'CRÍTICO'
                                 ELSE 'NORMAL'
                             END AS estado,
                             (i.cantidad_actual * p.precio_referencia) AS valor
                      FROM inventario i
                      JOIN productos p ON i.id_producto = p.id_producto
                      LEFT JOIN almacenes a ON i.id_almacen = a.id_almacen
                      ORDER BY a.nombre, p.nombre";
            $titulo = 'REPORTE DE INVENTARIO';
            $headers = ['Código', 'Producto', 'Almacén', 'Stock', 'Mínimo', 'Máximo', 'Estado', 'Valor'];
            break;

        case 'pedidos':
            $query = "SELECT p.codigo_pedido AS codigo, COALESCE(c.razon_social, 'Consumidor Final') AS cliente,
                             p.fecha_pedido AS fecha, p.estado, COALESCE(p.total, 0) AS total
                      FROM pedidos p
                      LEFT JOIN clientes c ON p.id_cliente = c.id_cliente";

            $where = [];
            if (!empty($fecha_desde)) {
                $where[] = 'DATE(p.fecha_pedido) >= :fecha_desde';
                $params[':fecha_desde'] = $fecha_desde;
            }
            if (!empty($fecha_hasta)) {
                $where[] = 'DATE(p.fecha_pedido) <= :fecha_hasta';
                $params[':fecha_hasta'] = $fecha_hasta;
            }
            if (!empty($where)) {
                $query .= ' WHERE ' . implode(' AND ', $where);
            }
            $query .= ' ORDER BY p.fecha_pedido DESC LIMIT 500';
            $titulo = 'REPORTE DE PEDIDOS';
            $headers = ['Código', 'Cliente', 'Fecha', 'Estado', 'Total'];
            break;

        case 'ventas':
            $query = "SELECT p.codigo, p.nombre,
                             SUM(dp.cantidad) AS unidades_vendidas,
                             SUM(dp.cantidad * dp.precio_unitario) AS ingresos
                      FROM detalle_pedido dp
                      JOIN productos p ON dp.id_producto = p.id_producto
                      JOIN pedidos pe ON dp.id_pedido = pe.id_pedido
                      WHERE pe.estado != 'Cancelado'";

            if (!empty($fecha_desde)) {
                $query .= ' AND DATE(pe.fecha_pedido) >= :fecha_desde';
                $params[':fecha_desde'] = $fecha_desde;
            }
            if (!empty($fecha_hasta)) {
                $query .= ' AND DATE(pe.fecha_pedido) <= :fecha_hasta';
                $params[':fecha_hasta'] = $fecha_hasta;
            }
            $query .= ' GROUP BY p.id_producto, p.codigo, p.nombre ORDER BY ingresos DESC LIMIT 50';
            $titulo = 'REPORTE DE VENTAS POR PRODUCTO';
            $headers = ['Código', 'Producto', 'Und. Vendidas', 'Ingresos'];
            break;

        default:
            throw new Exception('Tipo de reporte inválido');
    }

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$datos) {
        $datos = [];
    }

    $fecha_generacion = date('d/m/Y H:i:s');
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: inline; filename="reporte_' . $tipo . '_' . date('Ymd') . '.html"');

    echo '<!DOCTYPE html>\n<html lang="es">\n<head>\n    <meta charset="UTF-8">\n    <title>' . htmlspecialchars($titulo) . '</title>\n    <style>\n        @media print {\n            body { margin: 0; padding: 10px; }\n            .no-print { display: none; }\n            @page { size: A4 landscape; margin: 10mm; }\n        }\n        body {\n            font-family: Arial, sans-serif;\n            margin: 20px;\n            color: #333;\n        }\n        .header {\n            text-align: center;\n            border-bottom: 3px solid #0d6efd;\n            padding-bottom: 10px;\n            margin-bottom: 20px;\n        }\n        .header h1 { color: #0d6efd; margin: 0; }\n        .header .subtitle { color: #666; font-size: 14px; }\n        .info {\n            display: flex;\n            justify-content: space-between;\n            flex-wrap: wrap;\n            gap: 10px;\n            margin-bottom: 15px;\n            font-size: 12px;\n            color: #666;\n        }\n        table {\n            width: 100%;\n            border-collapse: collapse;\n            font-size: 11px;\n        }\n        th {\n            background-color: #0d6efd;\n            color: white;\n            padding: 8px;\n            text-align: left;\n            font-size: 10px;\n        }\n        td {\n            padding: 6px 8px;\n            border-bottom: 1px solid #ddd;\n        }\n        tr:nth-child(even) { background-color: #f8f9fa; }\n        .estado-critico { color: #dc3545; font-weight: bold; }\n        .estado-agotado { color: #dc3545; font-weight: bold; background-color: #ffe6e6; }\n        .estado-normal { color: #28a745; }\n        .total-row {\n            font-weight: bold;\n            background-color: #e9ecef !important;\n        }\n        .footer {\n            text-align: center;\n            font-size: 10px;\n            color: #999;\n            margin-top: 20px;\n            border-top: 1px solid #ddd;\n            padding-top: 10px;\n        }\n        .btn-imprimir {\n            background-color: #0d6efd;\n            color: white;\n            border: none;\n            padding: 10px 20px;\n            border-radius: 5px;\n            cursor: pointer;\n            font-size: 14px;\n            margin-bottom: 20px;\n        }\n        .btn-imprimir:hover { background-color: #0b5ed7; }\n    </style>\n</head>\n<body>\n    <button class="btn-imprimir no-print" onclick="window.print()">📄 Imprimir / Guardar como PDF</button>\n    <div class="header">\n        <h1>DISTRIBUIDORA ANDINA SRL</h1>\n        <h2>' . htmlspecialchars($titulo) . '</h2>\n        <div class="subtitle">Sistema Inteligente de Gestión de Cadena de Suministro</div>\n    </div>\n    <div class="info">\n        <div><strong>Generado por:</strong> Sistema</div>\n        <div><strong>Fecha:</strong> ' . htmlspecialchars($fecha_generacion) . '</div>\n        <div><strong>Total registros:</strong> ' . count($datos) . '</div>\n    </div>';

    echo '<table>\n        <thead>\n            <tr>';
    foreach ($headers as $header) {
        echo '<th>' . htmlspecialchars($header) . '</th>';
    }
    echo '</tr>\n        </thead>\n        <tbody>';

    $total_valor = 0;
    foreach ($datos as $row) {
        echo '<tr>';
        foreach ($row as $key => $value) {
            $clase = '';
            if ($key === 'estado' && in_array(strtoupper($value), ['AGOTADO', 'CRÍTICO', 'NORMAL'])) {
                $clase = 'estado-' . strtolower($value);
            }
            $display = htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
            if (in_array($key, ['valor', 'total', 'ingresos']) && is_numeric($value)) {
                $display = 'Bs. ' . number_format($value, 2);
            }
            echo '<td class="' . $clase . '">' . $display . '</td>';
        }
        echo '</tr>';

        if (isset($row['valor'])) {
            $total_valor += floatval($row['valor']);
        }
        if (isset($row['total'])) {
            $total_valor += floatval($row['total']);
        }
        if (isset($row['ingresos'])) {
            $total_valor += floatval($row['ingresos']);
        }
    }

    if (count($datos) > 0) {
        echo '<tr class="total-row">\n                <td colspan="' . (count($headers) - 1) . '"><strong>TOTAL</strong></td>\n                <td><strong>Bs. ' . number_format($total_valor, 2) . '</strong></td>\n              </tr>';
    }

    echo '</tbody>\n    </table>\n    <div class="footer">\n        <p>Distribuidora Andina SRL - Documento generado automáticamente</p>\n        <p>Este reporte es confidencial y para uso interno de la empresa</p>\n    </div>\n    <script>\n        // Auto-imprimir al cargar (opcional, descomentar si se desea)\n        // window.onload = function() { window.print(); }\n    </script>\n</body>\n</html>';

    exit();

} catch (Throwable $e) {
    error_log('Error en exportar_pdf.php: ' . $e->getMessage());
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html>\n<html lang="es">\n<head>\n    <meta charset="UTF-8">\n    <title>Error en Reporte</title>\n    <style>\n        body { font-family: Arial, sans-serif; margin: 20px; background: #f8f9fa; }\n        .error-box { \n            background: #f8d7da; \n            color: #721c24; \n            border: 2px solid #f5c6cb; \n            padding: 20px; \n            border-radius: 8px; \n            max-width: 600px; \n            margin: 20px auto;\n        }\n        .error-box h2 { margin-top: 0; }\n    </style>\n</head>\n<body>\n    <div class="error-box">\n        <h2>⚠️ Error al Generar Reporte</h2>\n        <p>Lo sentimos, no fue posible generar el reporte en este momento.</p>\n        <p>Motivo técnico: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>\n    </div>\n</body>\n</html>';
    exit();
}
?>
