<?php
/**
 * api/recomendaciones.php
 * Punto de entrada unificado para el módulo de recomendaciones.
 *
 * GET  ?accion=obtener&id_producto=X[&limite=N]  → obtener_recomendaciones.php
 * POST ?accion=generar                            → generar_reglas.php
 *      (requiere rol Administrador o Gerente)
 */

session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/cors.php';

$accion = sanitizar_input($_GET['accion'] ?? '');

switch ($accion) {
    case 'obtener':
        require __DIR__ . '/../modules/recomendaciones/obtener_recomendaciones.php';
        break;

    case 'generar':
        require __DIR__ . '/../modules/recomendaciones/generar_reglas.php';
        break;

    default:
        responderJSON([
            "error"    => "Acción no reconocida. Use 'obtener' o 'generar'.",
            "acciones" => [
                "obtener" => "GET ?accion=obtener&id_producto=X[&limite=N]",
                "generar" => "POST ?accion=generar  (requiere rol Administrador o Gerente)"
            ]
        ], 400);
}