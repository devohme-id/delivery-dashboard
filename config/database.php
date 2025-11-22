<?php
class Database {
    private $host = "xxx.xxx.xxx.xxx";
    private $db_name = "your_db"; // Sesuaikan nama DB
    private $username = "your_user";
    private $password = "your_password"; // Sesuaikan password
    public $conn;

    public function getConnection() {
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
?>
