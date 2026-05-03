<?php
$hash1 = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
$hash2 = password_hash('Admin2026!', PASSWORD_BCRYPT);

echo "Hash almacenado: " . $hash1 . "<br>";
echo "Hash nuevo: " . $hash2 . "<br>";
echo "¿Coinciden?: " . (password_verify('Admin2026!', $hash1) ? 'SÍ ✅' : 'NO ❌') . "<br>";
echo "¿Coinciden con nuevo?: " . (password_verify('Admin2026!', $hash2) ? 'SÍ ✅' : 'NO ❌');
?>