<?php
// d:/proyecto_andina/backend/config/app.php

// Cargar variables de entorno para usarlas en la configuración
require_once __DIR__ . '/env.php';

return [
    'impuestos' => [
        // Define el porcentaje de IVA. Puede ser sobreescrito por una variable de entorno APP_IVA_PERCENTAGE.
        'iva_porcentaje' => (float) getEnv('APP_IVA_PERCENTAGE', 13.0),
    ],
    'otp' => [
        'duracion_minutos' => 10,
    ],
    // Puedes centralizar más valores aquí en el futuro
    'roles' => [
        'default_empleado' => 3,
    ]
];