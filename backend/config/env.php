<?php
// config/env.php
// Carga variables de entorno desde archivo .env

function loadEnv($path = __DIR__ . '/.env') {
    if (!file_exists($path)) {
        throw new Exception("Archivo .env no encontrado en: $path");
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        // Ignorar comentarios
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remover comillas si las hay
            if ((strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1) ||
                (strpos($value, "'") === 0 && strrpos($value, "'") === strlen($value) - 1)) {
                $value = substr($value, 1, -1);
            }
            
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}

function getEnv($key, $default = null) {
    return $_ENV[$key] ?? getenv($key) ?: $default;
}

// Cargar .env en la raíz del repositorio (un nivel arriba de /backend)
loadEnv(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env');
