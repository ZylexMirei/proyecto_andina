<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderJSON(["error" => "Método no permitido"], 405);
}

verificarAutenticacion();

$termino = sanitizar_input($_GET['q'] ?? '');

if (strlen($termino) < 2) {
    responderJSON(["error" => "Ingrese al menos 2 caracteres para buscar"], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $query = "SELECT id_cliente, razon_social, nit_ci, telefono 
              FROM clientes 
              WHERE estado = 'Activo' 
              AND (razon_social LIKE :q1 OR nit_ci LIKE :q2) 
              ORDER BY razon_social 
              LIMIT 10";
    $stmt = $db->prepare($query);
    $param1 = "%{$termino}%";
    $param2 = "%{$termino}%";
    $stmt->bindParam(':q1', $param1);
    $stmt->bindParam(':q2', $param2);
    $stmt->execute();

    responderJSON([
        "exito" => true,
        "resultados" => $stmt->fetchAll()
    ]);

} catch (PDOException $e) {
    error_log("Error al buscar clientes: " . $e->getMessage());
    responderJSON(["error" => "Error al buscar clientes"], 500);
}