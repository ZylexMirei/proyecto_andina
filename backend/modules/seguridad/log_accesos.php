<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderJSON(["error" => "Método no permitido"], 405);
}

verificarAutenticacion();
verificarRol(['Administrador']);

$pagina = intval($_GET['pagina'] ?? 1);
$limite = intval($_GET['limite'] ?? 20);
$id_usuario = intval($_GET['id_usuario'] ?? 0);
$exito = isset($_GET['exito']) ? intval($_GET['exito']) : null;
$offset = ($pagina - 1) * $limite;

try {
    $database = new Database();
    $db = $database->getConnection();

    $where = "WHERE 1=1";
    $params = [];

    if ($id_usuario > 0) {
        $where .= " AND la.id_usuario = :id_usuario";
        $params[':id_usuario'] = $id_usuario;
    }

    if ($exito !== null) {
        $where .= " AND la.exito = :exito";
        $params[':exito'] = $exito;
    }

    $countQuery = "SELECT COUNT(*) as total FROM log_accesos la {$where}";
    $countStmt = $db->prepare($countQuery);
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value);
    }
    $countStmt->execute();
    $total = $countStmt->fetch()['total'];

    $query = "SELECT la.*, u.username, u.email
              FROM log_accesos la
              LEFT JOIN usuarios u ON la.id_usuario = u.id_usuario
              {$where}
              ORDER BY la.fecha_hora DESC
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
        "logs" => $stmt->fetchAll(),
        "paginacion" => [
            "pagina_actual" => $pagina,
            "total_paginas" => ceil($total / $limite),
            "total_registros" => $total
        ]
    ]);

} catch (PDOException $e) {
    error_log("Error en log accesos: " . $e->getMessage());
    responderJSON(["error" => "Error al obtener logs de acceso"], 500);
}