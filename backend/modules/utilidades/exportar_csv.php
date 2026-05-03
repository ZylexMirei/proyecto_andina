<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderJSON(["error" => "Método no permitido"], 405);
}

verificarAutenticacion();
verificarRol(['Administrador', 'Gerente']);

$tipo = sanitizar_input($_GET['tipo'] ?? ''); // productos, inventario, pedidos, clientes

try {
    $database = new Database();
    $db = $database->getConnection();

    switch ($tipo) {
        case 'productos':
            $query = "SELECT codigo, nombre, descripcion, precio_referencia, estado, created_at FROM productos ORDER BY nombre";
            $filename = "productos_" . date('Ymd') . ".csv";
            $headers = ['Código', 'Nombre', 'Descripción', 'Precio Referencia', 'Estado', 'Fecha Creación'];
            break;

        case 'inventario':
            $query = "SELECT p.codigo, p.nombre, a.nombre as almacen, i.cantidad_actual, i.stock_minimo, i.stock_maximo,
                             CASE 
                                WHEN i.cantidad_actual = 0 THEN 'Agotado'
                                WHEN i.cantidad_actual <= i.stock_minimo THEN 'Crítico'
                                ELSE 'Normal'
                             END as estado
                      FROM inventario i
                      JOIN productos p ON i.id_producto = p.id_producto
                      JOIN almacenes a ON i.id_almacen = a.id_almacen
                      ORDER BY p.nombre";
            $filename = "inventario_" . date('Ymd') . ".csv";
            $headers = ['Código', 'Producto', 'Almacén', 'Stock Actual', 'Stock Mínimo', 'Stock Máximo', 'Estado'];
            break;

        case 'pedidos':
            $query = "SELECT p.codigo_pedido, c.razon_social as cliente, p.fecha_pedido, p.estado,
                             SUM(dp.cantidad * dp.precio_unitario) as total
                      FROM pedidos p
                      JOIN clientes c ON p.id_cliente = c.id_cliente
                      JOIN detalle_pedido dp ON p.id_pedido = dp.id_pedido
                      GROUP BY p.id_pedido
                      ORDER BY p.fecha_pedido DESC";
            $filename = "pedidos_" . date('Ymd') . ".csv";
            $headers = ['Código', 'Cliente', 'Fecha', 'Estado', 'Total'];
            break;

        case 'clientes':
            $query = "SELECT razon_social, nit_ci, contacto, telefono, email, direccion, estado FROM clientes ORDER BY razon_social";
            $filename = "clientes_" . date('Ymd') . ".csv";
            $headers = ['Razón Social', 'NIT/CI', 'Contacto', 'Teléfono', 'Email', 'Dirección', 'Estado'];
            break;

        default:
            responderJSON(["error" => "Tipo de exportación inválido. Use: productos, inventario, pedidos, clientes"], 400);
            exit();
    }

    $stmt = $db->prepare($query);
    $stmt->execute();
    $datos = $stmt->fetchAll();

    // Configurar headers para descarga CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Agregar BOM para UTF-8 en Excel
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($output, $headers);

    foreach ($datos as $row) {
        fputcsv($output, $row);
    }

    fclose($output);
    exit();

} catch (PDOException $e) {
    error_log("Error al exportar CSV: " . $e->getMessage());
    responderJSON(["error" => "Error al exportar datos"], 500);
}