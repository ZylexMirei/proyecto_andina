<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderJSON(["error" => "Método no permitido"], 405);
}

verificarAutenticacion();
verificarRol(['Administrador', 'Gerente']);

try {
    $database = new Database();
    $db = $database->getConnection();

    // Obtener todos los pedidos con sus detalles
    $query = "SELECT dp.id_pedido, dp.id_producto
              FROM detalle_pedido dp
              JOIN pedidos p ON dp.id_pedido = p.id_pedido
              WHERE p.estado != 'Cancelado'
              ORDER BY dp.id_pedido";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $detalles = $stmt->fetchAll();

    // Agrupar productos por pedido
    $pedidos = [];
    foreach ($detalles as $detalle) {
        $pedidos[$detalle['id_pedido']][] = $detalle['id_producto'];
    }

    // Calcular pares de productos comprados juntos
    $pares = [];
    $total_pedidos = count($pedidos);

    foreach ($pedidos as $productos) {
        $n = count($productos);
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $par = $productos[$i] . '-' . $productos[$j];
                if (!isset($pares[$par])) {
                    $pares[$par] = 0;
                }
                $pares[$par]++;
            }
        }
    }

    // Insertar o actualizar reglas
    $db->beginTransaction();

    $insertQuery = "INSERT INTO reglas_asociacion (id_producto_antecedente, id_producto_consecuente, confianza, veces_comprados_juntos) 
                    VALUES (:antecedente, :consecuente, :confianza, :veces)
                    ON DUPLICATE KEY UPDATE confianza = :confianza2, veces_comprados_juntos = :veces2, updated_at = NOW()";
    $insertStmt = $db->prepare($insertQuery);

    $reglas_generadas = 0;

    foreach ($pares as $par => $veces) {
        list($id1, $id2) = explode('-', $par);

        // Conteo de cada producto individual
        $count1 = 0;
        $count2 = 0;
        foreach ($pedidos as $productos) {
            if (in_array($id1, $productos)) $count1++;
            if (in_array($id2, $productos)) $count2++;
        }

        // Calcular confianza
        if ($count1 > 0) {
            $confianza = $veces / $count1;

            // Insertar regla id1 -> id2
            $insertStmt->bindParam(':antecedente', $id1);
            $insertStmt->bindParam(':consecuente', $id2);
            $insertStmt->bindParam(':confianza', $confianza);
            $insertStmt->bindParam(':veces', $veces);
            $insertStmt->bindParam(':confianza2', $confianza);
            $insertStmt->bindParam(':veces2', $veces);
            $insertStmt->execute();
            $reglas_generadas++;
        }

        if ($count2 > 0) {
            $confianza = $veces / $count2;

            // Insertar regla id2 -> id1
            $insertStmt->bindParam(':antecedente', $id2);
            $insertStmt->bindParam(':consecuente', $id1);
            $insertStmt->bindParam(':confianza', $confianza);
            $insertStmt->bindParam(':veces', $veces);
            $insertStmt->bindParam(':confianza2', $confianza);
            $insertStmt->bindParam(':veces2', $veces);
            $insertStmt->execute();
            $reglas_generadas++;
        }
    }

    $db->commit();

    responderJSON([
        "exito" => true,
        "mensaje" => "Reglas de asociación generadas exitosamente",
        "total_pedidos_analizados" => $total_pedidos,
        "reglas_generadas" => $reglas_generadas
    ]);

} catch (Exception $e) {
    if (isset($db)) $db->rollBack();
    error_log("Error al generar reglas: " . $e->getMessage());
    responderJSON(["error" => "Error al generar reglas de asociación"], 500);
}