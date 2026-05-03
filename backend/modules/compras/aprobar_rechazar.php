<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderJSON(["error" => "Método no permitido"], 405);
}

verificarAutenticacion();
verificarRol(['Administrador', 'Gerente']);

$data = json_decode(file_get_contents("php://input"), true) ?: $_POST;

verificarCSRF($data['csrf_token'] ?? '');

$id_orden = intval($data['id_orden'] ?? 0);
$accion = sanitizar_input($data['accion'] ?? ''); // 'Aprobada' o 'Rechazada'
$motivo = sanitizar_input($data['motivo'] ?? '');

if ($id_orden <= 0) {
    responderJSON(["error" => "ID de orden inválido"], 400);
}

if (!in_array($accion, ['Aprobada', 'Rechazada'])) {
    responderJSON(["error" => "Acción inválida. Use 'Aprobada' o 'Rechazada'"], 400);
}

if ($accion === 'Rechazada' && empty($motivo)) {
    responderJSON(["error" => "Debe indicar el motivo de rechazo"], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Verificar que la orden existe y está pendiente
    $checkQuery = "SELECT id_orden_compra, estado FROM ordenes_compra WHERE id_orden_compra = :id";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bindParam(':id', $id_orden);
    $checkStmt->execute();

    if ($checkStmt->rowCount() === 0) {
        responderJSON(["error" => "Orden no encontrada"], 404);
    }

    $orden = $checkStmt->fetch();
    if ($orden['estado'] !== 'Pendiente') {
        responderJSON(["error" => "Solo se pueden aprobar/rechazar órdenes pendientes. Estado actual: {$orden['estado']}"], 400);
    }

    // Actualizar estado
    $motivo_texto = $accion === 'Rechazada' ? " - Motivo: {$motivo}" : '';
    $updateQuery = "UPDATE ordenes_compra SET estado = :estado, observacion = CONCAT(COALESCE(observacion, ''), :motivo) 
                    WHERE id_orden_compra = :id";
    $updateStmt = $db->prepare($updateQuery);
    $updateStmt->bindParam(':estado', $accion);
    $updateStmt->bindParam(':motivo', $motivo_texto);
    $updateStmt->bindParam(':id', $id_orden);
    $updateStmt->execute();

    responderJSON([
        "exito" => true,
        "mensaje" => "Orden {$accion} exitosamente",
        "id_orden" => $id_orden
    ]);

} catch (PDOException $e) {
    error_log("Error al aprobar/rechazar orden: " . $e->getMessage());
    responderJSON(["error" => "Error al procesar la orden"], 500);
}