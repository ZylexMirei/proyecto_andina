<?php
// config/env.php — variables desde .env en la raíz del repo

if (!function_exists('andina_load_env_file')) {
    function andina_load_env_file($path) {
        if (!file_exists($path)) {
            throw new Exception("Archivo .env no encontrado en: $path");
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                if ((strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1) ||
                    (strpos($value, "'") === 0 && strrpos($value, "'") === strlen($value) - 1)) {
                    $value = substr($value, 1, -1);
                }

                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }
    }
}

if (!function_exists('getEnv')) {
    function getEnv($key, $default = null) {
        return $_ENV[$key] ?? getenv($key) ?: $default;
    }
}

if (!defined('ANDINA_ENV_BOOTSTRAPPED')) {
    define('ANDINA_ENV_BOOTSTRAPPED', true);
    andina_load_env_file(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env');
}
