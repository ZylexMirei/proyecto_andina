<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderJSON(["error" => "Método no permitido"], 405);
}

verificarAutenticacion();

$id_producto = intval($_GET['id_producto'] ?? 0);
$limite = intval($_GET['limite'] ?? 4);

if ($id_producto <= 0) {
    responderJSON(["error" => "ID de producto requerido"], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Buscar en reglas de asociación
    $query = "SELECT r.*, p.codigo, p.nombre, p.precio_referencia, p.imagen_principal
              FROM reglas_asociacion r
              JOIN productos p ON r.id_producto_consecuente = p.id_producto
              WHERE r.id_producto_antecedente = :id_producto 
              AND r.activo = 1
              AND p.estado = 'Activo'
              ORDER BY r.confianza DESC
              LIMIT :limite";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id_producto', $id_producto);
    $stmt->bindParam(':limite', $limite, PDO::PARAM_INT);
    $stmt->execute();

    $recomendaciones = $stmt->fetchAll();

    // Si no hay reglas, buscar productos de la misma categoría
    if (empty($recomendaciones)) {
        $catQuery = "SELECT id_categoria FROM productos WHERE id_producto = :id_producto";
        $catStmt = $db->prepare($catQuery);
        $catStmt->bindParam(':id_producto', $id_producto);
        $catStmt->execute();
        $categoria = $catStmt->fetch();

        if ($categoria && $categoria['id_categoria']) {
            $altQuery = "SELECT id_producto, codigo, nombre, precio_referencia, imagen_principal
                        FROM productos 
                        WHERE id_categoria = :id_categoria 
                        AND id_producto != :id_producto 
                        AND estado = 'Activo'
                        LIMIT :limite";
            $altStmt = $db->prepare($altQuery);
            $altStmt->bindParam(':id_categoria', $categoria['id_categoria']);
            $altStmt->bindParam(':id_producto', $id_producto);
            $altStmt->bindParam(':limite', $limite, PDO::PARAM_INT);
            $altStmt->execute();
            $recomendaciones = $altStmt->fetchAll();

            responderJSON([
                "exito" => true,
                "recomendaciones" => $recomendaciones,
                "tipo" => "misma_categoria"
            ]);
        }
    }

    responderJSON([
        "exito" => true,
        "recomendaciones" => $recomendaciones,
        "tipo" => "reglas_asociacion"
    ]);

} catch (PDOException $e) {
    error_log("Error en recomendaciones: " . $e->getMessage());
    responderJSON(["error" => "Error al obtener recomendaciones"], 500);
}