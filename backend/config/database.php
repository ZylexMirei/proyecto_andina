<?php
// Cargar variables de entorno
require_once __DIR__ . '/env.php';

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $port;
    public $conn;

    public function __construct() {
        $this->host = getEnv('DB_HOST', '127.0.0.1');
        $this->db_name = getEnv('DB_NAME', 'distribuidora_andina');
        $this->username = getEnv('DB_USER', 'root');
        $this->password = getEnv('DB_PASSWORD', '');
        $this->port = getEnv('DB_PORT', '3306');
    }

    public function getConnection() {
        $this->conn = null;
        try {
            $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->db_name};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
        } catch (PDOException $exception) {
            // En lugar de matar la ejecución, relanzar la excepción para que el script que llama la maneje.
            throw new PDOException("Error de conexión a la base de datos: " . $exception->getMessage(), (int)$exception->getCode());
        }
        return $this->conn;
    }
}