<?php

namespace App\Models;

use PDO;
use PDOException;

class DB
{
    public function connect()
    {
        // For Railway deployment - uses Railway's provided MySQL environment variables
        // For local development - falls back to local MySQL credentials
        $host = getenv('MYSQLHOST') ?: getenv('DB_HOST') ?: 'localhost';
        
        // Use 'railway' as the database name for Railway, 'prathriadb' for local
        $dbname = getenv('MYSQLDATABASE') ?: getenv('DB_NAME') ?: 'railway';
        $user = getenv('MYSQLUSER') ?: getenv('DB_USER') ?: 'root';
        $pass = getenv('MYSQLPASSWORD') ?: getenv('DB_PASS') ?: '';
        $port = getenv('MYSQLPORT') ?: getenv('DB_PORT') ?: '3306';

        // Connection string for MySQL
        $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";

        try {
            $conn = new PDO($dsn, $user, $pass);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $conn;
        } catch (PDOException $e) {
            throw new PDOException("Database connection failed: " . $e->getMessage());
        }
    }
}