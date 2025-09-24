<?php
// config/database.php

class Database {
    private $host = "localhost";
    private $db_name = "scooter_stok_takip";
    private $username = "root";
    private $password = "";
    public $conn;

    public function getConnection() {
        $this->conn = null;

        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4", $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            // Bağlantı hatası olursa daha iyi debug için
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
        } catch(PDOException $exception) {
            error_log("Veritabanı bağlantı hatası: " . $exception->getMessage());
            echo "<div class='alert alert-danger'>Veritabanı bağlantı hatası: " . $exception->getMessage() . "</div>";
        }

        return $this->conn;
    }
}

// Kullanım için global fonksiyon
function getDBConnection() {
    $database = new Database();
    return $database->getConnection();
}

// Temel helper fonksiyonları
function redirect($url) {
    header("Location: " . $url);
    exit();
}
?>