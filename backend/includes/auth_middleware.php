<?php
// includes/auth_middleware.php
// Middleware para proteger rutas y verificar roles

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../config/database.php';

/**
 * Verifica que el usuario esté autenticado
 * Si no, retorna JSON con error 401
 */
function auth_middleware() {
    if (!isset($_SESSION['id_usuario'])) {
        responderJSON([
            "error" => "No autorizado",
            "mensaje" => "Debe iniciar sesión para acceder a este recurso",
            "codigo" => 401
        ], 401);
    }
}

/**
 * Verifica que el usuario tenga uno de los roles permitidos
 * @param array $roles_permitidos Lista de roles permitidos
 */
function role_middleware($roles_permitidos = []) {
    auth_middleware();
    
    if (!empty($roles_permitidos) && !in_array($_SESSION['rol'], $roles_permitidos)) {
        responderJSON([
            "error" => "Acceso denegado",
            "mensaje" => "No tiene permisos suficientes para esta acción",
            "rol_actual" => $_SESSION['rol'],
            "roles_permitidos" => $roles_permitidos,
            "codigo" => 403
        ], 403);
    }
}

/**
 * Verifica un permiso específico por módulo y acción
 * @param string $modulo Nombre del módulo
 * @param string $accion Ver, crear, editar, eliminar
 */
function permission_middleware($modulo, $accion = 'ver') {
    auth_middleware();
    
    if (!tienePermiso($modulo, $accion)) {
        responderJSON([
            "error" => "Permiso denegado",
            "mensaje" => "No tiene permiso para {$accion} en el módulo {$modulo}",
            "codigo" => 403
        ], 403);
    }
}

/**
 * Verifica que la solicitud sea del método esperado
 * @param string|array $metodos Método(s) HTTP permitido(s)
 */
function method_middleware($metodos) {
    if (is_string($metodos)) {
        $metodos = [$metodos];
    }
    
    if (!in_array($_SERVER['REQUEST_METHOD'], $metodos)) {
        responderJSON([
            "error" => "Método no permitido",
            "mensaje" => "Método {$_SERVER['REQUEST_METHOD']} no permitido. Use: " . implode(', ', $metodos),
            "codigo" => 405
        ], 405);
    }
}

/**
 * Obtiene y cachea los datos de la solicitud para evitar leer php://input múltiples veces
 */
function get_request_data() {
    static $data = null;
    if ($data === null) {
        $data = json_decode(file_get_contents("php://input"), true) ?: $_POST;
    }
    return $data;
}

/**
 * Verifica el token CSRF en peticiones POST/PUT/DELETE
 */
function csrf_middleware() {
    if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'DELETE'])) {
        $data = get_request_data();
        $token = $data['csrf_token'] ?? '';
        
        if (empty($token)) {
            responderJSON([
                "error" => "Token CSRF requerido",
                "mensaje" => "Debe incluir un token CSRF válido",
                "codigo" => 403
            ], 403);
        }
        
        verificarCSRF($token);
    }
}

/**
 * Verifica que los campos requeridos estén presentes
 * @param array $data Datos a verificar
 * @param array $campos Lista de campos requeridos
 */
function required_fields_middleware($data, $campos) {
    $faltantes = [];
    
    foreach ($campos as $campo) {
        if (!isset($data[$campo]) || (is_string($data[$campo]) && trim($data[$campo]) === '')) {
            $faltantes[] = $campo;
        }
    }
    
    if (!empty($faltantes)) {
        responderJSON([
            "error" => "Campos requeridos faltantes",
            "campos_faltantes" => $faltantes,
            "codigo" => 400
        ], 400);
    }
}

/**
 * Middleware completo para endpoints de API
 * @param array $config Configuración del middleware
 */
function api_middleware($config = []) {
    $defaults = [
        'metodo' => 'GET',
        'roles' => [],
        'permiso_modulo' => null,
        'permiso_accion' => 'ver',
        'csrf' => true,
        'campos_requeridos' => []
    ];
    
    $config = array_merge($defaults, $config);
    
    // Verificar método HTTP
    method_middleware($config['metodo']);
    
    // Verificar autenticación y roles
    if (!empty($config['roles'])) {
        role_middleware($config['roles']);
    } else {
        auth_middleware();
    }
    
    // Verificar permiso específico
    if ($config['permiso_modulo']) {
        permission_middleware($config['permiso_modulo'], $config['permiso_accion']);
    }
    
    // Verificar CSRF
    if ($config['csrf'] && in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'DELETE'])) {
        csrf_middleware();
    }
    
    // Verificar campos requeridos
    if (!empty($config['campos_requeridos'])) {
        $data = get_request_data();
        required_fields_middleware($data, $config['campos_requeridos']);
    }
    
    return get_request_data();
}