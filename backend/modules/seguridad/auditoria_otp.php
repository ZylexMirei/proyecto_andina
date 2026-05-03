<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderJSON(["error" => "Método no permitido"], 405);
}

verificarAutenticacion();
verificarRol(['Administrador']);

$id_usuario = intval($_GET['id_usuario'] ?? 0);

try {
    $database = new Database();
    $db = $database->getConnection();

    $where = "WHERE 1=1";
    $params = [];

    if ($id_usuario > 0) {
        $where .= " AND co.id_usuario = :id_usuario";
        $params[':id_usuario'] = $id_usuario;
    }

    $query = "SELECT co.*, u.username, u.email
              FROM codigos_otp co
              JOIN usuarios u ON co.id_usuario = u.id_usuario
              {$where}
              ORDER BY co.creado_en DESC
              LIMIT 100";
    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();

    $resultados = $stmt->fetchAll();

    // Estadísticas
    $statsQuery = "SELECT 
                    COUNT(*) as total_otp,
                    SUM(CASE WHEN activo = 0 THEN 1 ELSE 0 END) as usados,
                    SUM(CASE WHEN activo = 1 AND expira_en < NOW() THEN 1 ELSE 0 END) as expirados,
                    SUM(CASE WHEN activo = 1 AND expira_en > NOW() THEN 1 ELSE 0 END) as activos
                   FROM codigos_otp co {$where}";
    $statsStmt = $db->prepare($statsQuery);
    foreach ($params as $key => $value) {
        $statsStmt->bindValue($key, $value);
    }
    $statsStmt->execute();
    $estadisticas = $statsStmt->fetch();

    responderJSON([
        "exito" => true,
        "estadisticas" => $estadisticas,
        "registros" => $resultados
    ]);

} catch (PDOException $e) {
    error_log("Error en auditoría OTP: " . $e->getMessage());
    responderJSON(["error" => "Error al obtener auditoría OTP"], 500);
}