<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderJSON(["error" => "Método no permitido"], 405);
}

verificarAutenticacion();

$pagina = intval($_GET['pagina'] ?? 1);
$limite = intval($_GET['limite'] ?? 12);
$busqueda = sanitizar_input($_GET['busqueda'] ?? '');
$id_categoria = intval($_GET['id_categoria'] ?? 0);
$offset = ($pagina - 1) * $limite;

try {
    $database = new Database();
    $db = $database->getConnection();

    $where = "WHERE p.estado = 'Activo'";
    $params = [];

    if (!empty($busqueda)) {
        $where .= " AND (p.nombre LIKE :busqueda OR p.codigo LIKE :busqueda2)";
        $params[':busqueda'] = "%{$busqueda}%";
        $params[':busqueda2'] = "%{$busqueda}%";
    }

    if ($id_categoria > 0) {
        $where .= " AND p.id_categoria = :id_categoria";
        $params[':id_categoria'] = $id_categoria;
    }

    $countQuery = "SELECT COUNT(*) as total FROM productos p {$where}";
    $countStmt = $db->prepare($countQuery);
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value);
    }
    $countStmt->execute();
    $total = $countStmt->fetch()['total'];

    $query = "SELECT p.*, c.nombre as categoria_nombre,
              (SELECT url_imagen FROM imagenes_producto WHERE id_producto = p.id_producto ORDER BY orden LIMIT 1) as imagen_secundaria
              FROM productos p
              LEFT JOIN categorias c ON p.id_categoria = c.id_categoria
              {$where}
              ORDER BY p.created_at DESC
              LIMIT :limite OFFSET :offset";
    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $productos = $stmt->fetchAll();
    $total_paginas = ceil($total / $limite);

    responderJSON([
        "exito" => true,
        "productos" => $productos,
        "paginacion" => [
            "pagina_actual" => $pagina,
            "total_paginas" => $total_paginas,
            "total_productos" => $total,
            "por_pagina" => $limite
        ]
    ]);

} catch (PDOException $e) {
    error_log("Error al leer productos: " . $e->getMessage());
    responderJSON(["error" => "Error al obtener productos"], 500);
}