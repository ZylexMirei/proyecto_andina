<?php
// Mostrar errores directamente en pantalla si algo falla severamente
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405); echo json_encode(["error" => "Método no permitido"]); exit();
}

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
            $titulo = "REPORTE DE INVENTARIO";
            $headers = ['Código', 'Producto', 'Almacén', 'Stock', 'Mínimo', 'Máximo', 'Estado', 'Valor'];
            break;

        case 'pedidos':
            $query = "SELECT p.codigo_pedido, COALESCE(c.razon_social, 'Consumidor Final') as razon_social, p.fecha_pedido, p.estado,
                             COALESCE(SUM(dp.cantidad * dp.precio_unitario), 0) as total,
                             COUNT(dp.id_pedido) as items
                      FROM pedidos p
                      LEFT JOIN clientes c ON p.id_cliente = c.id_cliente
                      LEFT JOIN detalle_pedido dp ON p.id_pedido = dp.id_pedido";
            $where = []; // Usaremos un array para construir la cláusula WHERE
            if (!empty($fecha_desde)) {
                $where[] = "p.fecha_pedido >= :fecha_desde";
                $params[':fecha_desde'] = $fecha_desde;
            }
            if (!empty($fecha_hasta)) {
                $where[] = "p.fecha_pedido <= :fecha_hasta";
                $params[':fecha_hasta'] = $fecha_hasta;
            }
            if (!empty($where)) $query .= " WHERE " . implode(' AND ', $where);
            $query .= " GROUP BY p.id_pedido, p.codigo_pedido, c.razon_social, p.fecha_pedido, p.estado ORDER BY p.fecha_pedido DESC";
            $titulo = "REPORTE DE PEDIDOS";
            $headers = ['Código', 'Cliente', 'Fecha', 'Estado', 'Total', 'Items'];
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
            if (!empty($fecha_desde)) {
                $query .= " AND pe.fecha_pedido >= :fecha_desde";
                $params[':fecha_desde'] = $fecha_desde;
            }
            if (!empty($fecha_hasta)) {
                $query .= " AND pe.fecha_pedido <= :fecha_hasta";
                $params[':fecha_hasta'] = $fecha_hasta;
            }
            $query .= " GROUP BY p.id_producto, p.codigo, p.nombre ORDER BY ingresos DESC LIMIT 50";
            $titulo = "REPORTE DE VENTAS POR PRODUCTO";
            $headers = ['Código', 'Producto', 'Und. Vendidas', 'Ingresos', 'Pedidos'];
            break;

        default:
            http_response_code(400); echo json_encode(["error" => "Tipo de reporte inválido"]);
            exit();
    }

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $datos = $stmt->fetchAll();

    // Como no tenemos FPDF/TCPDF instalado, generamos HTML que se puede imprimir como PDF
    $fecha_generacion = date('d/m/Y H:i:s');
    
    // Configurar headers para mostrar en navegador y permitir guardar como PDF
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: inline; filename="reporte_' . $tipo . '_' . date('Ymd') . '.html"');

    // Generar HTML con estilos para impresión
    echo '<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>' . $titulo . '</title>
    <style>
        @media print {
            body { margin: 0; padding: 10px; }
            .no-print { display: none; }
            @page { size: A4 landscape; margin: 10mm; }
        }
        body { 
            font-family: Arial, sans-serif; 
            margin: 20px;
            color: #333;
        }
        .header { 
            text-align: center; 
            border-bottom: 3px solid #0d6efd; 
            padding-bottom: 10px; 
            margin-bottom: 20px; 
        }
        .header h1 { color: #0d6efd; margin: 0; }
        .header .subtitle { color: #666; font-size: 14px; }
        .info { 
            display: flex; 
            justify-content: space-between; 
            margin-bottom: 15px;
            font-size: 12px;
            color: #666;
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            font-size: 11px;
        }
        th { 
            background-color: #0d6efd; 
            color: white; 
            padding: 8px; 
            text-align: left; 
            font-size: 10px;
        }
        td { 
            padding: 6px 8px; 
            border-bottom: 1px solid #ddd; 
        }
        tr:nth-child(even) { background-color: #f8f9fa; }
        .estado-critico { color: #dc3545; font-weight: bold; }
        .estado-agotado { color: #dc3545; font-weight: bold; background-color: #ffe6e6; }
        .estado-normal { color: #28a745; }
        .total-row { 
            font-weight: bold; 
            background-color: #e9ecef !important; 
        }
        .footer { 
            text-align: center; 
            font-size: 10px; 
            color: #999; 
            margin-top: 20px;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
        .btn-imprimir {
            background-color: #0d6efd;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            margin-bottom: 20px;
        }
        .btn-imprimir:hover { background-color: #0b5ed7; }
    </style>
</head>
<body>
    <button class="btn-imprimir no-print" onclick="window.print()">
        📄 Imprimir / Guardar como PDF
    </button>
    
    <div class="header">
        <h1>DISTRIBUIDORA ANDINA SRL</h1>
        <h2>' . $titulo . '</h2>
        <div class="subtitle">Sistema Inteligente de Gestión de Cadena de Suministro</div>
    </div>
    
    <div class="info">
        <div><strong>Generado por:</strong> ' . htmlspecialchars($_GET['usuario'] ?? 'Sistema') . '</div>
        <div><strong>Fecha:</strong> ' . $fecha_generacion . '</div>
        <div><strong>Total registros:</strong> ' . count($datos) . '</div>
    </div>';

    // Mostrar tabla
    echo '<table>
        <thead>
            <tr>';
    foreach ($headers as $header) {
        echo '<th>' . $header . '</th>';
    }
    echo '</tr>
        </thead>
        <tbody>';

    $total_valor = 0;
    foreach ($datos as $row) {
        echo '<tr>';
        foreach ($row as $key => $value) {
            $clase = '';
            if ($key === 'estado' || (is_string($value) && in_array($value, ['AGOTADO', 'CRÍTICO', 'NORMAL']))) {
                $clase = 'estado-' . strtolower($value);
            }
            echo '<td class="' . $clase . '">' . htmlspecialchars($value ?? '') . '</td>';
        }
        echo '</tr>';
        
        if (isset($row['valor'])) $total_valor += floatval($row['valor']);
        if (isset($row['total'])) $total_valor += floatval($row['total']);
        if (isset($row['ingresos'])) $total_valor += floatval($row['ingresos']);
    }

    // Fila de totales
    echo '<tr class="total-row">
            <td colspan="' . (count($headers) - 1) . '"><strong>TOTAL</strong></td>
            <td><strong>Bs. ' . number_format($total_valor, 2) . '</strong></td>
          </tr>';

    echo '</tbody>
    </table>
    
    <div class="footer">
        <p>Distribuidora Andina SRL - Documento generado automáticamente</p>
        <p>Este reporte es confidencial y para uso interno de la empresa</p>
    </div>
    
    <script>
        // Auto-imprimir al cargar (opcional, descomentar si se desea)
        // window.onload = function() { window.print(); }
    </script>
</body>
</html>';

    exit();

} catch (Throwable $e) {
    error_log("Error al generar PDF: " . $e->getMessage());
    // Evitamos enviar el código 500 para que Apache no secuestre la respuesta
    echo "<div style='font-family: Arial, sans-serif; padding: 20px; border: 2px solid #dc3545; background: #f8d7da; color: #721c24; border-radius: 8px; margin: 20px;'>";
    echo "<h3>Error al Generar Reporte</h3>";
    echo "<strong>Motivo técnico reportado por la Base de Datos:</strong><br><br> <code>" . htmlspecialchars($e->getMessage()) . "</code>";
    echo "</div>";
}
?>