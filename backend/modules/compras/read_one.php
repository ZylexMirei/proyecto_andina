<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderJSON(["error" => "Método no permitido"], 405);
}

verificarAutenticacion();

$id_orden = intval($_GET['id'] ?? 0);

if ($id_orden <= 0) {
    responderJSON(["error" => "ID de orden inválido"], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $query = "SELECT oc.*, p.razon_social as proveedor_nombre, p.nit as proveedor_nit,
                     p.contacto as proveedor_contacto, p.telefono as proveedor_telefono,
                     p.email as proveedor_email,
                     CONCAT(e.nombre, ' ', e.apellido) as creador_nombre
              FROM ordenes_compra oc
              JOIN proveedores p ON oc.id_proveedor = p.id_proveedor
              LEFT JOIN usuarios u ON oc.id_usuario_creador = u.id_usuario
              LEFT JOIN empleados e ON u.id_empleado = e.id_empleado
              WHERE oc.id_orden_compra = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $id_orden);
    $stmt->execute();

    if ($stmt->rowCount() === 0) {
        responderJSON(["error" => "Orden no encontrada"], 404);
    }

    $orden = $stmt->fetch();

    $detQuery = "SELECT doc.*, p.codigo, p.nombre, p.imagen_principal
                 FROM detalle_orden_compra doc
                 JOIN productos p ON doc.id_producto = p.id_producto
                 WHERE doc.id_orden_compra = :id";
    $detStmt = $db->prepare($detQuery);
    $detStmt->bindParam(':id', $id_orden);
    $detStmt->execute();
    $orden['detalles'] = $detStmt->fetchAll();

    $total = 0;
    foreach ($orden['detalles'] as $d) {
        $total += $d['cantidad'] * $d['precio_unitario'];
    }
    $orden['total_orden'] = $total;

    responderJSON([
        "exito" => true,
        "orden" => $orden
    ]);

} catch (PDOException $e) {
    error_log("Error al leer orden: " . $e->getMessage());
    responderJSON(["error" => "Error al obtener orden de compra"], 500);
}