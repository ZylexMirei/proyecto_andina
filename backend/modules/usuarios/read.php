<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderJSON(["error" => "Método no permitido"], 405);
}

verificarAutenticacion();
verificarRol(['Administrador']);

$pagina = intval($_GET['pagina'] ?? 1);
$limite = intval($_GET['limite'] ?? 10);
$busqueda = sanitizar_input($_GET['busqueda'] ?? '');
$estado = sanitizar_input($_GET['estado'] ?? '');
$offset = ($pagina - 1) * $limite;

try {
    $database = new Database();
    $db = $database->getConnection();

    $where = "WHERE 1=1";
    $params = [];

    if (!empty($busqueda)) {
        $where .= " AND (u.username LIKE :q1 OR u.email LIKE :q2 OR CONCAT(e.nombre, ' ', e.apellido) LIKE :q3)";
        $params[':q1'] = "%{$busqueda}%";
        $params[':q2'] = "%{$busqueda}%";
        $params[':q3'] = "%{$busqueda}%";
    }

    if (!empty($estado)) {
        $where .= " AND u.estado = :estado";
        $params[':estado'] = $estado;
    }

    $countQuery = "SELECT COUNT(*) as total FROM usuarios u {$where}";
    $countStmt = $db->prepare($countQuery);
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value);
    }
    $countStmt->execute();
    $total = $countStmt->fetch()['total'];

    $query = "SELECT u.id_usuario, u.username, u.email, u.email_verificado, u.estado, 
                     u.intentos_fallidos, u.ultimo_acceso, u.created_at,
                     r.nombre as rol, r.id_rol,
                     e.nombre as nombre_empleado, e.apellido, e.ci_nit
              FROM usuarios u
              JOIN roles r ON u.id_rol = r.id_rol
              JOIN empleados e ON u.id_empleado = e.id_empleado
              {$where}
              ORDER BY u.created_at DESC
              LIMIT :limite OFFSET :offset";
    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    responderJSON([
        "exito" => true,
        "usuarios" => $stmt->fetchAll(),
        "paginacion" => [
            "pagina_actual" => $pagina,
            "total_paginas" => ceil($total / $limite),
            "total_usuarios" => $total
        ]
    ]);

} catch (PDOException $e) {
    error_log("Error al leer usuarios: " . $e->getMessage());
    responderJSON(["error" => "Error al obtener usuarios"], 500);
}