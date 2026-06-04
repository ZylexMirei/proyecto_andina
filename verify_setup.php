<?php
/**
 * verify_setup.php
 * 
 * Script de verificación del entorno
 * Ejecutar en: http://localhost/proyecto_andina/verify_setup.php
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación de Configuración - Distribuidora Andina</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 30px;
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
        }
        .check-item {
            display: flex;
            align-items: center;
            padding: 12px;
            margin: 10px 0;
            border-radius: 4px;
            border-left: 4px solid #ddd;
        }
        .check-item.pass {
            background: #d4edda;
            border-left-color: #28a745;
        }
        .check-item.fail {
            background: #f8d7da;
            border-left-color: #dc3545;
        }
        .check-item.warn {
            background: #fff3cd;
            border-left-color: #ffc107;
        }
        .icon {
            font-size: 20px;
            margin-right: 12px;
            min-width: 24px;
        }
        .content {
            flex: 1;
        }
        .label {
            font-weight: 600;
            margin-bottom: 4px;
        }
        .detail {
            font-size: 12px;
            color: #666;
        }
        .section {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #eee;
        }
        .section h2 {
            color: #444;
            font-size: 18px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Verificación de Configuración</h1>
        <p class="subtitle">Distribuidora Andina - Estado del Sistema</p>

        <?php
        $issues = [];
        $warnings = [];

        // 1. Verificar .env
        $env_file = __DIR__ . '/.env';
        if (!file_exists($env_file)) {
            $issues[] = ['label' => 'Archivo .env', 'detail' => 'No encontrado. Copia .env.example a .env'];
        } else {
            echo '<div class="check-item pass"><div class="icon">✓</div><div class="content"><div class="label">Archivo .env</div><div class="detail">Encontrado</div></div></div>';
        }

        // 2. Verificar variables de entorno
        if (file_exists($env_file)) {
            require_once __DIR__ . '/backend/config/env.php';

            $required_vars = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASSWORD', 'MAIL_HOST', 'MAIL_USERNAME', 'MAIL_PASSWORD'];
            foreach ($required_vars as $var) {
                $value = getEnv($var);

                if ($var === 'DB_PASSWORD') {
                    if ($value === null) {
                        $issues[] = ['label' => "Variable $var", 'detail' => 'No configurada'];
                        continue;
                    }
                    if ($value === '') {
                        $warnings[] = ['label' => "Variable $var", 'detail' => 'No configurada. Si usas una cuenta local de MySQL sin contraseña, puede ser aceptable para desarrollo.'];
                        echo "<div class='check-item warn'><div class='icon'>⚠</div><div class='content'><div class='label'>$var</div><div class='detail'>Vacía</div></div></div>";
                        continue;
                    }
                }

                if (empty($value)) {
                    $issues[] = ['label' => "Variable $var", 'detail' => 'No configurada'];
                } else {
                    $masked = $var === 'DB_PASSWORD' || $var === 'MAIL_PASSWORD' ? '***' : $value;
                    echo "<div class='check-item pass'><div class='icon'>✓</div><div class='content'><div class='label'>$var</div><div class='detail'>Configurado</div></div></div>";
                }
            }
        }

        // 3. Verificar PHP version
        if (version_compare(PHP_VERSION, '7.4') >= 0) {
            echo '<div class="check-item pass"><div class="icon">✓</div><div class="content"><div class="label">Versión PHP</div><div class="detail">PHP ' . PHP_VERSION . '</div></div></div>';
        } else {
            $issues[] = ['label' => 'Versión PHP', 'detail' => 'Se requiere PHP 7.4+, tienes ' . PHP_VERSION];
        }

        // 4. Verificar extensiones
        $required_extensions = ['pdo', 'pdo_mysql', 'json', 'mbstring'];
        foreach ($required_extensions as $ext) {
            if (extension_loaded($ext)) {
                echo "<div class='check-item pass'><div class='icon'>✓</div><div class='content'><div class='label'>Extensión PHP: $ext</div><div class='detail'>Instalada</div></div></div>";
            } else {
                $issues[] = ['label' => "Extensión PHP: $ext", 'detail' => 'No instalada'];
            }
        }

        // 5. Verificar Database
        echo '<div class="section"><h2>Base de Datos</h2>';
        if (file_exists($env_file)) {
            require_once __DIR__ . '/backend/config/database.php';
            try {
                $db = new Database();
                $conn = $db->getConnection();
                if ($conn) {
                    echo '<div class="check-item pass"><div class="icon">✓</div><div class="content"><div class="label">Conexión a BD</div><div class="detail">Conectado correctamente</div></div></div>';
                }
            } catch (Exception $e) {
                $issues[] = ['label' => 'Conexión a BD', 'detail' => $e->getMessage()];
            }
        }
        echo '</div>';

        // 6. Verificar carpetas
        echo '<div class="section"><h2>Carpetas del Proyecto</h2>';
        $folders = ['/backend/vendor', '/backend/config', '/backend/includes', '/backend/modules', '/backend/assets'];
        foreach ($folders as $folder) {
            if (is_dir(__DIR__ . $folder)) {
                echo "<div class='check-item pass'><div class='icon'>✓</div><div class='content'><div class='label'>Carpeta: $folder</div><div class='detail'>Existe</div></div></div>";
            } else {
                $warnings[] = ['label' => "Carpeta: $folder", 'detail' => 'No encontrada'];
            }
        }
        echo '</div>';

        // 7. Mostrar problemas
        if (!empty($issues)) {
            echo '<div class="section"><h2>⚠️ Problemas Encontrados</h2>';
            foreach ($issues as $issue) {
                echo '<div class="check-item fail"><div class="icon">✗</div><div class="content"><div class="label">' . $issue['label'] . '</div><div class="detail">' . $issue['detail'] . '</div></div></div>';
            }
            echo '</div>';
        }

        // 8. Mostrar advertencias
        if (!empty($warnings)) {
            echo '<div class="section"><h2>⚠️ Advertencias</h2>';
            foreach ($warnings as $warning) {
                echo '<div class="check-item warn"><div class="icon">⚠</div><div class="content"><div class="label">' . $warning['label'] . '</div><div class="detail">' . $warning['detail'] . '</div></div></div>';
            }
            echo '</div>';
        }

        // 9. Resumen
        echo '<div class="section"><h2>Resumen</h2>';
        $status = empty($issues) ? 'pass' : 'fail';
        $message = empty($issues) ? '✓ Todo está configurado correctamente' : '✗ Hay problemas que deben resolverse';
        echo "<div class='check-item $status'><div class='icon'>" . (empty($issues) ? '✓' : '✗') . "</div><div class='content'><div class='label'>Estado General</div><div class='detail'>$message</div></div></div>";
        echo '</div>';

        // 10. Pasos siguientes
        if (empty($issues)) {
            echo '<div class="section"><h2>✓ Pasos Siguientes</h2>
            <ol style="margin-left: 20px;">
                <li>Crear base de datos: <code>mysql -u root -p < schema.sql</code></li>
                <li>Instalar dependencias frontend: <code>cd frontend/artifacts/andina-frontend && npm install</code></li>
                <li>Compilar frontend: <code>npm run build</code></li>
                <li>Iniciar servidor de desarrollo</li>
                <li>Acceder a: <code>http://localhost/proyecto_andina/</code></li>
            </ol></div>';
        }
        ?>
    </div>
</body>
</html>
