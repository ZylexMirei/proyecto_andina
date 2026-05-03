<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderJSON(["error" => "Método no permitido"], 405);
}

verificarAutenticacion();
verificarRol(['Administrador']);

try {
    $database = new Database();
    $db = $database->getConnection();

    $query = "SELECT r.*, 
                     (SELECT COUNT(*) FROM usuarios WHERE id_rol = r.id_rol) as total_usuarios
              FROM roles r
              ORDER BY r.id_rol";
    $stmt = $db->prepare($query);
    $stmt->execute();

    $roles = $stmt->fetchAll();

    // Obtener módulos y permisos de cada rol
    foreach ($roles as &$rol) {
        $permQuery = "SELECT rm.*, m.nombre as modulo_nombre
                      FROM rol_modulo rm
                      JOIN modulos m ON rm.id_modulo = m.id_modulo
                      WHERE rm.id_rol = :id_rol";
        $permStmt = $db->prepare($permQuery);
        $permStmt->bindParam(':id_rol', $rol['id_rol']);
        $permStmt->execute();
        $rol['permisos'] = $permStmt->fetchAll();
    }

    responderJSON([
        "exito" => true,
        "roles" => $roles
    ]);

} catch (PDOException $e) {
    error_log("Error al leer roles: " . $e->getMessage());
    responderJSON(["error" => "Error al obtener roles"], 500);
}