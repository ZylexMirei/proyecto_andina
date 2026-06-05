<?php
require 'backend/config/database.php';
$db = (new Database())->getConnection();
$stmt = $db->query('DESCRIBE pedidos');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
