<?php
if (!class_exists('Database')) {
class Database {
    private $host = "localhost";
    private $db_name = "snhs_inventory"; // Ensure this matches your MySQL DB name
    private $username = "root";
    private $password = "";
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4", $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            // Never echo in a class. Throw an exception to let the caller handle it.
            throw new PDOException("Connection error: " . $exception->getMessage());
        }
        return $this->conn;
    }
}
}
?>