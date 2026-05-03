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

$id_usuario = intval($data['id_usuario'] ?? 0);
$id_rol = intval($data['id_rol'] ?? 0);
$estado = sanitizar_input($data['estado'] ?? '');

if ($id_usuario <= 0 || $id_rol <= 0) {
    responderJSON(["error" => "ID de usuario y rol son requeridos"], 400);
}

$campos_actualizar = [];
$params = [':id' => $id_usuario];

if ($id_rol > 0) {
    $campos_actualizar[] = "id_rol = :id_rol";
    $params[':id_rol'] = $id_rol;
}

if (!empty($estado) && in_array($estado, ['Activo', 'Inactivo', 'Bloqueado'])) {
    $campos_actualizar[] = "estado = :estado";
    $params[':estado'] = $estado;
}

if (empty($campos_actualizar)) {
    responderJSON(["error" => "Nada que actualizar"], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // No permitir que un admin se desactive a sí mismo
    if ($id_usuario == $_SESSION['id_usuario'] && isset($params[':estado']) && $params[':estado'] != 'Activo') {
        responderJSON(["error" => "No puede desactivar su propio usuario"], 400);
    }

    $sql = "UPDATE usuarios SET " . implode(', ', $campos_actualizar) . " WHERE id_usuario = :id";
    $stmt = $db->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();

    responderJSON([
        "exito" => true,
        "mensaje" => "Usuario actualizado exitosamente"
    ]);

} catch (PDOException $e) {
    error_log("Error al actualizar usuario: " . $e->getMessage());
    responderJSON(["error" => "Error al actualizar usuario"], 500);
}