<?php
// Script mágico para limpiar todos los conflictos de Git
$file = 'backend/test_api.php';
if(file_exists($file)){
    $c = file_get_contents($file);
    $count = 0;
    $c = preg_replace('/<<<<<<< HEAD\r?\n.*?=======\r?\n(.*?)\r?\n?>>>>>>> [a-f0-9]+/s', '$1', $c, -1, $count);
    file_put_contents($file, $c);
    echo "¡Magia completada! Se resolvieron $count conflictos automáticamente.\n";
}