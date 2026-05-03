<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderJSON(["error" => "Método no permitido"], 405);
}

verificarAutenticacion();
verificarRol(['Administrador']);

try {
    $database = new Database();
    $db = $database->getConnection();

    // Obtener todas las tablas
    $tablesQuery = "SHOW TABLES";
    $tablesStmt = $db->prepare($tablesQuery);
    $tablesStmt->execute();
    $tables = $tablesStmt->fetchAll(PDO::FETCH_COLUMN);

    $sql_dump = "-- Backup de Distribuidora Andina\n";
    $sql_dump .= "-- Fecha: " . date('Y-m-d H:i:s') . "\n";
    $sql_dump .= "-- Generado por: " . $_SESSION['username'] . "\n\n";
    $sql_dump .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    foreach ($tables as $table) {
        // Obtener CREATE TABLE
        $createQuery = "SHOW CREATE TABLE `{$table}`";
        $createStmt = $db->prepare($createQuery);
        $createStmt->execute();
        $createRow = $createStmt->fetch();
        $sql_dump .= $createRow['Create Table'] . ";\n\n";

        // Obtener datos
        $dataQuery = "SELECT * FROM `{$table}`";
        $dataStmt = $db->prepare($dataQuery);
        $dataStmt->execute();
        $rows = $dataStmt->fetchAll();

        if (!empty($rows)) {
            $sql_dump .= "INSERT INTO `{$table}` VALUES\n";
            $values = [];
            foreach ($rows as $row) {
                $vals = array_map(function($val) use ($db) {
                    if ($val === null) return 'NULL';
                    return $db->quote($val);
                }, $row);
                $values[] = '(' . implode(', ', $vals) . ')';
            }
            $sql_dump .= implode(",\n", $values) . ";\n\n";
        }
    }

    $sql_dump .= "SET FOREIGN_KEY_CHECKS=1;\n";

    // Guardar archivo
    $backup_dir = __DIR__ . '/../../backups/';
    if (!is_dir($backup_dir)) {
        mkdir($backup_dir, 0755, true);
    }

    $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
    $filepath = $backup_dir . $filename;

    file_put_contents($filepath, $sql_dump);

    responderJSON([
        "exito" => true,
        "mensaje" => "Backup generado exitosamente",
        "archivo" => $filename,
        "tamano" => filesize($filepath) . " bytes",
        "ruta" => $filepath
    ]);

} catch (PDOException $e) {
    error_log("Error al generar backup: " . $e->getMessage());
    responderJSON(["error" => "Error al generar backup de la base de datos"], 500);
}