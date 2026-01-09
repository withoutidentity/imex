<?php
// Database configuration
class Database {
    private $host = 'db';
    private $db_name = 'smart_delivery_db';
    private $username = 'root';
    private $password = 'root';
    public $conn;

    public function connect() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->exec("set names utf8");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }
        return $this->conn;
    }
}

// Global database connection
$database = new Database();
$conn = $database->connect();
