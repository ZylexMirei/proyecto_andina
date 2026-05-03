<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderJSON(["error" => "Método no permitido"], 405);
}

verificarAutenticacion();
verificarRol(['Administrador', 'Gerente']);

$limite = intval($_GET['limite'] ?? 10);
$fecha_desde = sanitizar_input($_GET['fecha_desde'] ?? date('Y-m-01'));
$fecha_hasta = sanitizar_input($_GET['fecha_hasta'] ?? date('Y-m-d'));

try {
    $database = new Database();
    $db = $database->getConnection();

    $query = "SELECT p.id_producto, p.codigo, p.nombre, p.imagen_principal,
                     SUM(dp.cantidad) as total_unidades_vendidas,
                     SUM(dp.cantidad * dp.precio_unitario) as total_ingresos,
                     COUNT(DISTINCT dp.id_pedido) as total_pedidos
              FROM detalle_pedido dp
              JOIN pedidos pe ON dp.id_pedido = pe.id_pedido
              JOIN productos p ON dp.id_producto = p.id_producto
              WHERE pe.estado != 'Cancelado'
              AND pe.fecha_pedido BETWEEN :fecha_desde AND :fecha_hasta
              GROUP BY dp.id_producto
              ORDER BY total_unidades_vendidas DESC
              LIMIT :limite";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':fecha_desde', $fecha_desde);
    $stmt->bindParam(':fecha_hasta', $fecha_hasta);
    $stmt->bindParam(':limite', $limite, PDO::PARAM_INT);
    $stmt->execute();

    responderJSON([
        "exito" => true,
        "periodo" => [
            "desde" => $fecha_desde,
            "hasta" => $fecha_hasta
        ],
        "productos" => $stmt->fetchAll()
    ]);

} catch (PDOException $e) {
    error_log("Error en productos más vendidos: " . $e->getMessage());
    responderJSON(["error" => "Error al obtener productos más vendidos"], 500);
}