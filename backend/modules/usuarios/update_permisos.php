<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderJSON(["error" => "Método no permitido"], 405);
}

verificarAutenticacion();
verificarRol(['Administrador']);

$data = json_decode(file_get_contents("php://input"), true) ?: $_POST;

verificarCSRF($data['csrf_token'] ?? '');

$id_rol = intval($data['id_rol'] ?? 0);
$id_modulo = intval($data['id_modulo'] ?? 0);
$puede_ver = isset($data['puede_ver']) ? ($data['puede_ver'] ? 1 : 0) : 0;
$puede_crear = isset($data['puede_crear']) ? ($data['puede_crear'] ? 1 : 0) : 0;
$puede_editar = isset($data['puede_editar']) ? ($data['puede_editar'] ? 1 : 0) : 0;
$puede_eliminar = isset($data['puede_eliminar']) ? ($data['puede_eliminar'] ? 1 : 0) : 0;

if ($id_rol <= 0 || $id_modulo <= 0) {
    responderJSON(["error" => "ID de rol y módulo son requeridos"], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $query = "INSERT INTO rol_modulo (id_rol, id_modulo, puede_ver, puede_crear, puede_editar, puede_eliminar) 
              VALUES (:id_rol, :id_modulo, :ver, :crear, :editar, :eliminar)
              ON DUPLICATE KEY UPDATE 
              puede_ver = :ver2, puede_crear = :crear2, puede_editar = :editar2, puede_eliminar = :eliminar2";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id_rol', $id_rol);
    $stmt->bindParam(':id_modulo', $id_modulo);
    $stmt->bindParam(':ver', $puede_ver);
    $stmt->bindParam(':crear', $puede_crear);
    $stmt->bindParam(':editar', $puede_editar);
    $stmt->bindParam(':eliminar', $puede_eliminar);
    $stmt->bindParam(':ver2', $puede_ver);
    $stmt->bindParam(':crear2', $puede_crear);
    $stmt->bindParam(':editar2', $puede_editar);
    $stmt->bindParam(':eliminar2', $puede_eliminar);
    $stmt->execute();

    responderJSON([
        "exito" => true,
        "mensaje" => "Permisos actualizados exitosamente"
    ]);

} catch (PDOException $e) {
    error_log("Error al actualizar permisos: " . $e->getMessage());
    responderJSON(["error" => "Error al actualizar permisos"], 500);
}