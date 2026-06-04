<?php

require_once __DIR__ . '/env.php';


class MongoConnection
{
    private static $manager = null;

    public static function getManager()
    {
        if (self::$manager === null) {
            if (!extension_loaded('mongodb')) {
                throw new Exception("La extensión de PHP 'mongodb' no está habilitada. Actívala en php.ini y reinicia Apache/WAMP.");
            }

            $uri = $_ENV['MONGO_URI'] ?? getenv('MONGO_URI');
            // Usando la URI estándar explícita para evitar problemas de DNS SRV
            $uri = $uri ?: 'mongodb://admin_supply:H0g6MclXANIcWKHW@ac-oevws3b-shard-00-00.4grtx6y.mongodb.net:27017,ac-oevws3b-shard-00-01.4grtx6y.mongodb.net:27017,ac-oevws3b-shard-00-02.4grtx6y.mongodb.net:27017/?ssl=true&replicaSet=atlas-k1p89s-shard-0&authSource=admin&appName=Cluster0';
            self::$manager = new MongoDB\Driver\Manager($uri);
        }

        return self::$manager;
    }

    public static function getDatabaseName()
    {
        $db = $_ENV['MONGO_DB'] ?? getenv('MONGO_DB');
        return $db ?: 'SupplyChain_NoSQL';
    }
}