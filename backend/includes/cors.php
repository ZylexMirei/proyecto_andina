<?php
// includes/cors.php
// Headers CORS para permitir peticiones desde el frontend
require_once __DIR__ . '/../config/env.php';

// Obtener orígenes permitidos desde .env
$allowed_origins = getEnv('ALLOWED_ORIGINS', 'http://localhost:3000,http://localhost:5173');
$allowed_origins_array = array_map('trim', explode(',', $allowed_origins));

// Verificar origen
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$is_allowed = in_array($origin, $allowed_origins_array);

if ($is_allowed) {
    header("Access-Control-Allow-Origin: $origin");
}

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Max-Age: 3600');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');

// Manejar preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}