<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderJSON(["error" => "Método no permitido"], 405);
}

verificarAutenticacion();

$busqueda = sanitizar_input($_GET['busqueda'] ?? '');
$pagina = intval($_GET['pagina'] ?? 1);
$limite = intval($_GET['limite'] ?? 20);
$offset = ($pagina - 1) * $limite;

try {
    $database = new Database();
    $db = $database->getConnection();

    $where = "WHERE estado = 'Activo'";
    $params = [];

    if (!empty($busqueda)) {
        $where .= " AND (razon_social LIKE :busqueda OR nit_ci LIKE :busqueda2)";
        $params[':busqueda'] = "%{$busqueda}%";
        $params[':busqueda2'] = "%{$busqueda}%";
    }

    $countQuery = "SELECT COUNT(*) as total FROM clientes {$where}";
    $countStmt = $db->prepare($countQuery);
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value);
    }
    $countStmt->execute();
    $total = $countStmt->fetch()['total'];

    $query = "SELECT * FROM clientes {$where} ORDER BY razon_social ASC LIMIT :limite OFFSET :offset";
    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    responderJSON([
        "exito" => true,
        "clientes" => $stmt->fetchAll(),
        "paginacion" => [
            "pagina_actual" => $pagina,
            "total_paginas" => ceil($total / $limite),
            "total_clientes" => $total
        ]
    ]);

} catch (PDOException $e) {
    error_log("Error al leer clientes: " . $e->getMessage());
    responderJSON(["error" => "Error al obtener clientes"], 500);
}