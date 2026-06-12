<?php

require_once 'mongodb.php';
date_default_timezone_set('America/La_Paz');

function registrarAuditoria(
    $usuario,
    $rol,
    $accion,
    $modulo
)
{
    try {
        $manager = MongoConnection::getManager();
        $database = MongoConnection::getDatabaseName();

        $bulk = new MongoDB\Driver\BulkWrite();
        $bulk->insert([
            'usuario' => $usuario,
            'rol' => $rol,
            'accion' => $accion,
            'modulo' => $modulo,
            'fecha' => date('Y-m-d H:i:s')
        ]);

        $manager->executeBulkWrite($database . '.auditoria_usuarios', $bulk);
    } catch (Exception $e) {
        error_log($e->getMessage());
    }
}
