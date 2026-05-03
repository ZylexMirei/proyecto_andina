<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderJSON(["error" => "Método no permitido"], 405);
}

verificarAutenticacion();
verificarRol(['Administrador', 'Gerente']);

$anio = intval($_GET['anio'] ?? date('Y'));

try {
    $database = new Database();
    $db = $database->getConnection();

    $query = "SELECT MONTH(p.fecha_pedido) as mes,
                     SUM(dp.cantidad * dp.precio_unitario) as total_ventas,
                     COUNT(DISTINCT p.id_pedido) as cantidad_pedidos
              FROM pedidos p
              JOIN detalle_pedido dp ON p.id_pedido = dp.id_pedido
              WHERE p.estado != 'Cancelado'
              AND YEAR(p.fecha_pedido) = :anio
              GROUP BY MONTH(p.fecha_pedido)
              ORDER BY mes";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':anio', $anio);
    $stmt->execute();

    $ventas = $stmt->fetchAll();

    // Completar meses sin ventas con 0
    $meses_nombres = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
    ];

    $resultado = [];
    foreach ($meses_nombres as $num => $nombre) {
        $encontrado = false;
        foreach ($ventas as $venta) {
            if (intval($venta['mes']) === $num) {
                $resultado[] = [
                    'mes_numero' => $num,
                    'mes_nombre' => $nombre,
                    'total_ventas' => floatval($venta['total_ventas']),
                    'cantidad_pedidos' => intval($venta['cantidad_pedidos'])
                ];
                $encontrado = true;
                break;
            }
        }
        if (!$encontrado) {
            $resultado[] = [
                'mes_numero' => $num,
                'mes_nombre' => $nombre,
                'total_ventas' => 0,
                'cantidad_pedidos' => 0
            ];
        }
    }

    responderJSON([
        "exito" => true,
        "anio" => $anio,
        "ventas" => $resultado
    ]);

} catch (PDOException $e) {
    error_log("Error en ventas por mes: " . $e->getMessage());
    responderJSON(["error" => "Error al obtener ventas por mes"], 500);
}