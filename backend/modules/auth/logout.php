<?php
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Para máxima seguridad, se podría requerir un token CSRF aquí también.
    responderJSON(["error" => "Método no permitido"], 405);
}

session_start(); // Es necesario iniciar la sesión para poder destruirla.

session_unset();
session_destroy();

responderJSON([
    "exito" => true,
    "mensaje" => "Sesión cerrada exitosamente"
]);